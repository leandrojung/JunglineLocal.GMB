<?php
/**
 * Terminbuchung — Speicher.
 *
 * Bevorzugt SQLite (PDO), weil dort ein UNIQUE-Index die Doppelbuchung auf
 * Datenbankebene unmöglich macht: Zwei Besucher, die im selben Moment auf
 * denselben Slot klicken, können nicht beide gewinnen — der zweite INSERT
 * scheitert am Index, und genau daran erkennt book.php den Konflikt.
 *
 * Fehlt pdo_sqlite auf dem Server, greift ein JSON-Speicher mit exklusiver
 * Dateisperre (flock). Der ist langsamer, aber ebenso wenig anfällig für
 * Doppelbuchungen, weil Lesen und Schreiben in derselben Sperre passieren.
 *
 * Ein Termin ist eindeutig über seinen UTC-Startzeitpunkt. Abgesagte
 * Termine bleiben als Zeile erhalten (Nachvollziehbarkeit), zählen aber
 * nicht mehr als belegt.
 */

declare(strict_types=1);

require_once __DIR__ . '/_config.php';

function bkUseSqlite(): bool {
    static $ok = null;
    return $ok ??= class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true);
}

// =====================================================================
// SQLite
// =====================================================================

function bkDb(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $pdo = new PDO('sqlite:' . bkDataDir() . '/bookings.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    // WAL: Leser (Slot-Abfragen) blockieren den Schreiber (Buchung) nicht.
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('CREATE TABLE IF NOT EXISTS bookings (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        token         TEXT    NOT NULL UNIQUE,
        start_utc     TEXT    NOT NULL,
        end_utc       TEXT    NOT NULL,
        name          TEXT    NOT NULL,
        email         TEXT    NOT NULL,
        phone         TEXT    NOT NULL DEFAULT "",
        company       TEXT    NOT NULL DEFAULT "",
        message       TEXT    NOT NULL DEFAULT "",
        status        TEXT    NOT NULL DEFAULT "confirmed",
        gcal_event_id TEXT    NOT NULL DEFAULT "",
        created_at    TEXT    NOT NULL,
        reminded_at   TEXT    NOT NULL DEFAULT "",
        ip            TEXT    NOT NULL DEFAULT ""
    )');
    // Der eigentliche Doppelbuchungs-Schutz: pro Startzeit höchstens ein
    // bestätigter Termin. Abgesagte sind ausgenommen, damit ein
    // freigewordener Slot wieder buchbar ist.
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS bookings_slot
                ON bookings(start_utc) WHERE status = "confirmed"');
    $pdo->exec('CREATE INDEX IF NOT EXISTS bookings_range ON bookings(start_utc, status)');
    return $pdo;
}

// =====================================================================
// JSON-Fallback
// =====================================================================

function bkJsonFile(): string {
    return bkDataDir() . '/bookings.json';
}

/**
 * Liest die Buchungen, übergibt sie an $fn und schreibt zurück, was $fn
 * zurückgibt — alles innerhalb einer exklusiven Sperre. Gibt $fn null
 * zurück, bleibt die Datei unverändert. Der Rückgabewert von bkJsonTx ist
 * das, was $fn über $out meldet.
 */
function bkJsonTx(callable $fn, mixed &$out = null): void {
    $file = bkJsonFile();
    $fp = fopen($file, 'c+');
    if ($fp === false) throw new RuntimeException('Buchungsdatei nicht beschreibbar.');
    try {
        if (!flock($fp, LOCK_EX)) throw new RuntimeException('Buchungsdatei gesperrt.');
        $raw = stream_get_contents($fp);
        $rows = $raw === '' || $raw === false ? [] : (json_decode($raw, true) ?: []);
        $next = $fn($rows, $out);
        if (is_array($next)) {
            $encoded = json_encode($next, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string) $encoded);
            fflush($fp);
        }
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

// =====================================================================
// Öffentliche Schnittstelle — für beide Speicher identisch
// =====================================================================

/**
 * Belegte Zeitfenster im Bereich [$fromUtc, $toUtc) als Liste von
 * ['start' => 'Y-m-d H:i:s', 'end' => 'Y-m-d H:i:s'].
 */
function bkBusy(string $fromUtc, string $toUtc): array {
    if (bkUseSqlite()) {
        $stmt = bkDb()->prepare('SELECT start_utc, end_utc FROM bookings
                                 WHERE status = "confirmed" AND start_utc >= ? AND start_utc < ?');
        $stmt->execute([$fromUtc, $toUtc]);
        return array_map(
            static fn(array $r): array => ['start' => $r['start_utc'], 'end' => $r['end_utc']],
            $stmt->fetchAll()
        );
    }

    $busy = [];
    bkJsonTx(static function (array $rows) use ($fromUtc, $toUtc, &$busy): null {
        foreach ($rows as $r) {
            if (($r['status'] ?? '') !== 'confirmed') continue;
            if ($r['start_utc'] < $fromUtc || $r['start_utc'] >= $toUtc) continue;
            $busy[] = ['start' => $r['start_utc'], 'end' => $r['end_utc']];
        }
        return null;
    });
    return $busy;
}

/**
 * Legt eine Buchung an. Gibt false zurück, wenn der Slot in der Zwischenzeit
 * vergeben wurde — der Aufrufer meldet dem Besucher dann "gerade weg".
 */
function bkInsert(array $rec): bool {
    if (bkUseSqlite()) {
        try {
            $stmt = bkDb()->prepare('INSERT INTO bookings
                (token, start_utc, end_utc, name, email, phone, company, message, status, gcal_event_id, created_at, ip)
                VALUES (:token,:start,:end,:name,:email,:phone,:company,:message,"confirmed","",:created,:ip)');
            $stmt->execute([
                ':token' => $rec['token'], ':start' => $rec['start_utc'], ':end' => $rec['end_utc'],
                ':name' => $rec['name'], ':email' => $rec['email'], ':phone' => $rec['phone'],
                ':company' => $rec['company'], ':message' => $rec['message'],
                ':created' => $rec['created_at'], ':ip' => $rec['ip'],
            ]);
            return true;
        } catch (PDOException $e) {
            // 23000 = Integritätsverletzung, hier immer der Slot-Index.
            if ($e->getCode() === '23000') return false;
            throw $e;
        }
    }

    $ok = false;
    bkJsonTx(static function (array $rows) use ($rec, &$ok): ?array {
        foreach ($rows as $r) {
            if (($r['status'] ?? '') === 'confirmed' && $r['start_utc'] === $rec['start_utc']) {
                return null;   // belegt — nichts schreiben
            }
        }
        $rec['status'] = 'confirmed';
        $rec['gcal_event_id'] = '';
        $rec['reminded_at'] = '';
        $rec['id'] = count($rows) + 1;
        $rows[] = $rec;
        $ok = true;
        return $rows;
    });
    return $ok;
}

function bkFindByToken(string $token): ?array {
    if (bkUseSqlite()) {
        $stmt = bkDb()->prepare('SELECT * FROM bookings WHERE token = ?');
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    $found = null;
    bkJsonTx(static function (array $rows) use ($token, &$found): null {
        foreach ($rows as $r) {
            if (($r['token'] ?? '') === $token) { $found = $r; break; }
        }
        return null;
    });
    return $found;
}

/** Hinterlegt die Google-Kalender-Event-ID zu einer Buchung. */
function bkSetEventId(string $token, string $eventId): void {
    if (bkUseSqlite()) {
        $stmt = bkDb()->prepare('UPDATE bookings SET gcal_event_id = ? WHERE token = ?');
        $stmt->execute([$eventId, $token]);
        return;
    }
    bkJsonTx(static function (array $rows) use ($token, $eventId): array {
        foreach ($rows as &$r) {
            if (($r['token'] ?? '') === $token) $r['gcal_event_id'] = $eventId;
        }
        return $rows;
    });
}

/** Sagt einen Termin ab. Rückgabe: die Buchung, oder null wenn unbekannt. */
function bkCancel(string $token): ?array {
    $row = bkFindByToken($token);
    if ($row === null) return null;

    if (bkUseSqlite()) {
        $stmt = bkDb()->prepare('UPDATE bookings SET status = "cancelled" WHERE token = ?');
        $stmt->execute([$token]);
    } else {
        bkJsonTx(static function (array $rows) use ($token): array {
            foreach ($rows as &$r) {
                if (($r['token'] ?? '') === $token) $r['status'] = 'cancelled';
            }
            return $rows;
        });
    }
    $row['status'] = 'cancelled';
    return $row;
}

/** Wie viele Buchungen kamen heute schon von dieser IP? */
function bkCountForIpToday(string $ip): int {
    if ($ip === '') return 0;
    $since = bkNow()->modify('-24 hours')->format('Y-m-d H:i:s');

    if (bkUseSqlite()) {
        $stmt = bkDb()->prepare('SELECT COUNT(*) c FROM bookings WHERE ip = ? AND created_at >= ?');
        $stmt->execute([$ip, $since]);
        return (int) ($stmt->fetch()['c'] ?? 0);
    }

    $n = 0;
    bkJsonTx(static function (array $rows) use ($ip, $since, &$n): null {
        foreach ($rows as $r) {
            if (($r['ip'] ?? '') === $ip && ($r['created_at'] ?? '') >= $since) $n++;
        }
        return null;
    });
    return $n;
}

/**
 * Bestätigte Termine, die in den nächsten $withinHours Stunden starten und
 * für die noch keine Erinnerung raus ist (siehe remind.php).
 */
function bkDueReminders(int $withinHours = 24): array {
    $now = bkNow();
    $until = $now->modify('+' . $withinHours . ' hours');

    if (bkUseSqlite()) {
        $stmt = bkDb()->prepare('SELECT * FROM bookings
                                 WHERE status = "confirmed" AND reminded_at = ""
                                   AND start_utc > ? AND start_utc <= ?');
        $stmt->execute([bkStamp($now), bkStamp($until)]);
        return $stmt->fetchAll();
    }

    $due = [];
    bkJsonTx(static function (array $rows) use ($now, $until, &$due): null {
        foreach ($rows as $r) {
            if (($r['status'] ?? '') !== 'confirmed') continue;
            if (($r['reminded_at'] ?? '') !== '') continue;
            if ($r['start_utc'] <= bkStamp($now) || $r['start_utc'] > bkStamp($until)) continue;
            $due[] = $r;
        }
        return null;
    });
    return $due;
}

/**
 * Löscht Buchungen, deren Termin länger als $days zurückliegt. Die
 * Datenschutzerklärung nennt sechs Monate — ohne diese Funktion wäre das
 * eine Behauptung, die der Server nicht einhält. Wird vom Cron zusammen mit
 * den Erinnerungen aufgerufen (siehe remind.php).
 */
function bkPurgeOlderThan(int $days = 180): int {
    $cutoff = bkStamp(bkNow()->modify('-' . $days . ' days'));

    if (bkUseSqlite()) {
        $stmt = bkDb()->prepare('DELETE FROM bookings WHERE start_utc < ?');
        $stmt->execute([$cutoff]);
        return $stmt->rowCount();
    }

    $removed = 0;
    bkJsonTx(static function (array $rows) use ($cutoff, &$removed): array {
        $kept = array_values(array_filter($rows, static fn(array $r): bool => ($r['start_utc'] ?? '') >= $cutoff));
        $removed = count($rows) - count($kept);
        return $kept;
    });
    return $removed;
}

function bkMarkReminded(string $token): void {
    $stamp = bkStamp(bkNow());
    if (bkUseSqlite()) {
        $stmt = bkDb()->prepare('UPDATE bookings SET reminded_at = ? WHERE token = ?');
        $stmt->execute([$stamp, $token]);
        return;
    }
    bkJsonTx(static function (array $rows) use ($token, $stamp): array {
        foreach ($rows as &$r) {
            if (($r['token'] ?? '') === $token) $r['reminded_at'] = $stamp;
        }
        return $rows;
    });
}
