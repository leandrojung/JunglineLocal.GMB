<?php
/**
 * Wortvergleich für den Chat-Assistenten: ordnet eine freie Frage einer
 * festen Antwort aus _wissen.php zu, ganz ohne KI.
 *
 * WARUM ES DAS GIBT
 * Eine feste Antwort kann nicht falsch sein, kostet nichts und kommt sofort.
 * Das Sprachmodell wird deshalb nur gefragt, wenn kein Eintrag eindeutig
 * passt. Und es darf selbst auch nur eine feste Antwort auswählen, statt
 * etwas Neues zu formulieren, wenn ein Eintrag die Frage abdeckt.
 *
 * WIE GEZÄHLT WIRD
 * Jeder Suchbegriff eines Eintrags, der am Wortanfang in der Frage vorkommt,
 * bringt Punkte: 1 je Wort, 2 je Wort bei starken Begriffen ("!" davor).
 * Ein "$" am Ende verlangt das ganze Wort ("kosten$" trifft nicht "kostenlos").
 * Ein Ausdruck aus mehreren Wörtern zählt je Wort, spezifische Formulierungen
 * ("wie lange dauert ein relaunch") schlagen also allgemeine ("relaunch").
 *
 * "EINDEUTIG" heißt: mindestens 2 Punkte und entweder doppelt so viele wie
 * der Zweitplatzierte oder mindestens 3 Punkte Vorsprung. Bei Gleichstand oder Unsicherheit wird NICHT geraten,
 * dann entscheidet das Sprachmodell oder der Besucher wird weitergeleitet.
 */

declare(strict_types=1);

require_once __DIR__ . '/_wissen.php';

/**
 * Kleinbuchstaben, Umlaute aufgelöst, alles außer Buchstaben und Ziffern
 * wird zu einem Leerzeichen. Beide Seiten des Vergleichs (Frage und
 * Suchbegriffe) laufen durch dieselbe Funktion.
 */
function chatNormalisiere(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    $s = strtr($s, [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'á' => 'a', 'à' => 'a', 'ó' => 'o', 'ú' => 'u', 'í' => 'i',
    ]);
    $s = (string) preg_replace('/[^a-z0-9]+/u', ' ', $s);
    return trim($s);
}

/**
 * Punkte je Eintrag für eine Frage.
 *
 * @return array<string,float> Eintrags-ID => Punkte, absteigend sortiert,
 *                             nur Einträge mit mindestens einem Treffer.
 */
function chatBewerte(string $frage): array {
    $q = ' ' . chatNormalisiere($frage) . ' ';
    $punkte = [];

    foreach (chatWissen() as $id => $eintrag) {
        $summe = 0.0;
        foreach ($eintrag['kw'] as $kw) {
            $stark = str_starts_with($kw, '!');
            if ($stark) $kw = substr($kw, 1);
            // "$" am Ende: nur das ganze Wort, nicht jede Wortform davor.
            $ganz = str_ends_with($kw, '$');
            if ($ganz) $kw = substr($kw, 0, -1);
            $norm = chatNormalisiere($kw);
            if ($norm === '') continue;
            // Sonst genügt der Wortanfang: "preis" trifft "preise".
            if (!str_contains($q, ' ' . $norm . ($ganz ? ' ' : ''))) continue;
            $woerter = substr_count($norm, ' ') + 1;
            $summe += ($stark ? 2 : 1) * $woerter;
        }
        if ($summe > 0) $punkte[$id] = $summe;
    }

    arsort($punkte);
    return $punkte;
}

/**
 * Liefert die ID des eindeutig passenden Eintrags oder null.
 */
function chatFindeFaq(string $frage): ?string {
    $punkte = chatBewerte($frage);
    if ($punkte === []) return null;

    $ids = array_keys($punkte);
    $best = $punkte[$ids[0]];
    $zweit = isset($ids[1]) ? $punkte[$ids[1]] : 0.0;

    if ($best >= 2.0 && ($best >= 2.0 * $zweit || $best - $zweit >= 3.0)) return $ids[0];
    return null;
}

/**
 * Schwächere Zuordnung für den Notfall (Sprachmodell nicht erreichbar oder
 * Tagesbudget aufgebraucht): bester Treffer ab einem Punkt, auch ohne
 * deutlichen Abstand. Besser eine passende feste Antwort als gar keine.
 */
function chatFindeFaqNotfall(string $frage): ?string {
    $punkte = chatBewerte($frage);
    if ($punkte === []) return null;
    $id = array_key_first($punkte);
    return $punkte[$id] >= 2.0 ? $id : null;
}

/** Begrüßung ohne Anliegen ("Hallo", "Guten Tag") */
function chatIstBegruessung(string $frage): bool {
    $n = chatNormalisiere($frage);
    if ($n === '' || mb_strlen($n) > 30) return false;
    return (bool) preg_match('/^(hallo|hi|hey|moin|servus|huhu|guten (tag|morgen|abend)|gruess (gott|dich)|gruezi)( .{0,18})?$/', $n);
}
