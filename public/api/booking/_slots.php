<?php
/**
 * Terminbuchung — Verfügbarkeit.
 *
 * Eine einzige Quelle der Wahrheit für "ist dieser Slot frei?": Der Kalender
 * im Browser zeigt exakt das, was bkAvailability() liefert, und book.php
 * prüft vor dem Speichern noch einmal mit derselben Funktion. Ohne diese
 * zweite Prüfung könnte ein Besucher, der die Seite morgens geöffnet und
 * abends abgeschickt hat, auf einen längst vergebenen Slot buchen.
 *
 * Belegt ist ein Slot, wenn er sich mit irgendetwas überschneidet: mit einer
 * anderen Buchung aus unserem Speicher oder mit einem Eintrag aus Leandros
 * Google-Kalender.
 */

declare(strict_types=1);

require_once __DIR__ . '/_config.php';
require_once __DIR__ . '/_store.php';
require_once __DIR__ . '/_google.php';

/** Die möglichen Startzeiten eines Arbeitstages, lokal als 'H:i'. */
function bkDayTemplate(): array {
    static $times = null;
    if ($times !== null) return $times;

    $times = [];
    [$startH, $startM] = array_map('intval', explode(':', BK_DAY_START));
    [$endH, $endM]     = array_map('intval', explode(':', BK_DAY_END));
    $minute = $startH * 60 + $startM;
    $last   = $endH * 60 + $endM;

    // "< $last" statt "<=": der letzte Termin muss noch vollständig in den
    // Arbeitstag passen, nicht erst zum Feierabend beginnen.
    while ($minute + BK_SLOT_MIN <= $last) {
        $times[] = sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
        $minute += BK_SLOT_MIN;
    }
    return $times;
}

/** Überschneiden sich [$aStart,$aEnd) und [$bStart,$bEnd)? */
function bkOverlaps(string $aStart, string $aEnd, string $bStart, string $bEnd): bool {
    return $aStart < $bEnd && $bStart < $aEnd;
}

/**
 * Freie Slots für einen Zeitraum.
 *
 * @param  DateTimeImmutable $fromLocal Erster Tag (lokale Zeitzone)
 * @param  DateTimeImmutable $toLocal   Letzter Tag, einschließlich
 * @return array<string, string[]>      '2026-08-11' => ['09:00', '09:30', …]
 */
function bkAvailability(DateTimeImmutable $fromLocal, DateTimeImmutable $toLocal): array {
    $now = bkNow();
    $earliest = $now->modify('+' . BK_LEAD_HOURS . ' hours');
    $horizon  = $now->modify('+' . BK_HORIZON_DAYS . ' days');

    $rangeStart = $fromLocal->setTime(0, 0)->setTimezone(bkUtcTz());
    $rangeEnd   = $toLocal->setTime(23, 59, 59)->setTimezone(bkUtcTz());

    // Beide Quellen einmal für den ganzen Zeitraum abfragen statt pro Slot.
    $busy = array_merge(
        bkBusy(bkStamp($rangeStart), bkStamp($rangeEnd)),
        bkGoogleBusy($rangeStart, $rangeEnd)
    );

    $result = [];
    $day = $fromLocal->setTime(0, 0);
    $lastDay = $toLocal->setTime(0, 0);

    while ($day <= $lastDay) {
        $date = $day->format('Y-m-d');

        $isWorkday = in_array((int) $day->format('N'), BK_WORKDAYS, true);
        $isBlocked = in_array($date, BK_BLOCKED_DAYS, true);

        if ($isWorkday && !$isBlocked) {
            $free = [];
            foreach (bkDayTemplate() as $time) {
                $startUtc = bkUtc($date, $time);
                if ($startUtc === null) continue;
                $endUtc = $startUtc->modify('+' . BK_SLOT_MIN . ' minutes');

                if ($startUtc < $earliest || $startUtc > $horizon) continue;

                // Puffer nur für den Vergleich aufschlagen, nicht für den Termin selbst.
                $checkStart = bkStamp($startUtc->modify('-' . BK_BUFFER_MIN . ' minutes'));
                $checkEnd   = bkStamp($endUtc->modify('+' . BK_BUFFER_MIN . ' minutes'));

                $taken = false;
                foreach ($busy as $b) {
                    if (bkOverlaps($checkStart, $checkEnd, $b['start'], $b['end'])) { $taken = true; break; }
                }
                if (!$taken) $free[] = $time;
            }
            if ($free !== []) $result[$date] = $free;
        }

        $day = $day->modify('+1 day');
    }

    return $result;
}

/**
 * Prüfung unmittelbar vor dem Speichern. Bewusst dieselbe Rechnung wie
 * oben, nur auf einen einzigen Tag eingegrenzt.
 */
function bkSlotIsFree(string $date, string $time): bool {
    $startUtc = bkUtc($date, $time);
    if ($startUtc === null) return false;
    if (!in_array($time, bkDayTemplate(), true)) return false;

    $localDay = bkLocal($startUtc)->setTime(0, 0);
    $available = bkAvailability($localDay, $localDay);
    return in_array($time, $available[$date] ?? [], true);
}
