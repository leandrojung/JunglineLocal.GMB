<?php
/**
 * /api/booking/calendar?token=…  — nur noch eine Weiterleitung.
 *
 * Diese Seite bot früher die beiden Wege in den eigenen Kalender an (Google
 * Kalender, Datei für Apple/Outlook). Beide stehen jetzt auf der Terminseite
 * /api/booking/termin, zusammen mit Uhrzeit, Videoraum, Verschieben und
 * Absagen.
 *
 * Der Grund für die Zusammenlegung: Solange Eintragen und Ändern auf zwei
 * Seiten lagen, brauchte die Bestätigungsmail zwei Links mit je einem
 * 32 Zeichen langen Zufallstoken. Damit hatte ausgerechnet die wichtigste
 * Mail des Systems das ungünstigste Zustellprofil — und sie war die einzige,
 * die beim Kunden nicht ankam. Eine Seite für alles heißt: ein Link.
 *
 * Die Adresse bleibt trotzdem bestehen und leitet weiter, denn sie steht in
 * jeder bereits verschickten Bestätigungsmail. Ein toter Link dort wäre für
 * den Kunden nicht von einem abgesagten Termin zu unterscheiden.
 */

declare(strict_types=1);

require_once __DIR__ . '/_config.php';

header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');

$token = trim((string) ($_GET['token'] ?? ''));

// Ohne Token gibt es nichts weiterzuleiten; die Terminseite selbst beantwortet
// den Fall "Termin nicht gefunden" bereits sauber, deshalb geht auch das
// dorthin — mit leerem Token.
header('Location: ' . bkManageUrl($token), true, 301);
