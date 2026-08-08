<?php
/**
 * /api/booking/cancel?token=…
 *
 * Die Seite hinter dem Absage-/Verschiebelink aus jeder Mail. Der Token ist
 * der Schlüssel zum Termin — wer ihn hat, darf ihn absagen. Genau so
 * arbeitet auch Calendly; ein Login wäre für ein 30-Minuten-Erstgespräch
 * eine unzumutbare Hürde.
 *
 * Abgesagt wird ausschließlich per POST. Ein GET darf nichts verändern,
 * sonst genügt der Linkscanner eines Mailprogramms, um dem Kunden im
 * Hintergrund den Termin zu stornieren — ein Fehler, den man erst bemerkt,
 * wenn niemand zum Gespräch erscheint.
 *
 * Mit &format=json liefert dieselbe Route die Termindaten für die
 * Verschieben-Ansicht des Kalenders zurück.
 */

declare(strict_types=1);

require_once __DIR__ . '/_store.php';
require_once __DIR__ . '/_google.php';
require_once __DIR__ . '/_mail.php';
require_once __DIR__ . '/_ics.php';
require_once __DIR__ . '/_templates.php';

header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$asJson = ($_GET['format'] ?? '') === 'json';

$booking = $token === '' ? null : bkFindByToken($token);

// ---------------------------------------------------------------------
// JSON-Variante für die Verschieben-Ansicht im Kalender
// ---------------------------------------------------------------------
if ($asJson) {
    if ($booking === null) {
        respond(404, ['success' => false, 'error' => 'not_found']);
    }
    $start = new DateTimeImmutable($booking['start_utc'], bkUtcTz());
    $end   = new DateTimeImmutable($booking['end_utc'], bkUtcTz());
    respond(200, [
        'success' => true,
        'status' => $booking['status'],
        'date_label' => bkFormatDate($start),
        'time_label' => bkFormatTime($start, $end),
        'prefill' => [
            'name' => $booking['name'],
            'email' => $booking['email'],
            'phone' => $booking['phone'],
            'company' => $booking['company'],
            'message' => $booking['message'],
        ],
    ]);
}

// ---------------------------------------------------------------------
// Absage ausführen
// ---------------------------------------------------------------------
$done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $booking !== null && $booking['status'] === 'confirmed') {
    try {
        $cancelled = bkCancel($token) ?? $booking;

        if (($booking['gcal_event_id'] ?? '') !== '') {
            bkGoogleDelete((string) $booking['gcal_event_id']);
        }

        $ics = ['content' => bkIcs($cancelled, 'CANCEL'), 'method' => 'CANCEL'];

        $toCustomer = bkMailCancelled($cancelled, false);
        bkMail($cancelled['email'], $cancelled['name'], $toCustomer['subject'],
               $toCustomer['html'], $toCustomer['text'], $ics, bkOwnerEmail());

        $toOwner = bkMailCancelled($cancelled, true);
        bkMail(bkOwnerEmail(), bkOwnerName(), $toOwner['subject'], $toOwner['html'], $toOwner['text']);

        $booking = $cancelled;
        $done = true;
    } catch (Throwable $e) {
        error_log('booking/cancel: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------
// Seite ausgeben
// ---------------------------------------------------------------------
$site = bkSiteUrl();
$bookingUrl = $site . '/kontakt/#termin';

if ($booking === null) {
    $heading = 'Termin nicht gefunden';
    $lead = 'Dieser Link gehört zu keinem Termin. Vielleicht wurde er bereits abgesagt oder der Link ist unvollständig kopiert.';
    $state = 'unknown';
} elseif ($booking['status'] !== 'confirmed') {
    $heading = $done ? 'Termin abgesagt' : 'Dieser Termin ist bereits abgesagt';
    $lead = $done
        ? 'Erledigt. Sie bekommen gleich eine Bestätigung per E-Mail — der Termin verschwindet damit auch aus Ihrem Kalender.'
        : 'Der Termin wurde bereits storniert. Sie können sich jederzeit einen neuen aussuchen.';
    $state = 'cancelled';
} else {
    $heading = 'Termin absagen oder verschieben';
    $lead = 'Kein Problem — wählen Sie einfach, wie es weitergehen soll.';
    $state = 'open';
}

$start = $booking !== null ? new DateTimeImmutable($booking['start_utc'], bkUtcTz()) : null;
$end   = $booking !== null ? new DateTimeImmutable($booking['end_utc'], bkUtcTz()) : null;

header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= bkEsc($heading) ?> — JunglineLocal</title>
<link rel="icon" type="image/png" href="/logo.png">
<style>
  /* Dieselben lokal gehosteten Schriften wie die Website. font-display:optional
     heißt: sind sie nicht sofort da, wird die Systemschrift genommen statt
     den Text zu verstecken — die Seite kommt aus einer E-Mail und muss auch
     bei wackliger Verbindung sofort lesbar sein. */
  @font-face{font-family:'Bricolage Grotesque';src:url('/fonts/bricolage-grotesque-var.woff2') format('woff2-variations');font-weight:200 800;font-display:optional}
  @font-face{font-family:'Instrument Sans';src:url('/fonts/instrument-sans-var.woff2') format('woff2-variations');font-weight:400 700;font-display:optional}
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;display:grid;place-items:center;padding:32px 18px;
       background:#F4F5FB;color:#191C21;
       font:400 16px/1.6 'Instrument Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif}
  .card{width:100%;max-width:520px;background:#fff;border:1px solid #C9D3EE;border-radius:24px;
        padding:34px 32px;box-shadow:0 2px 6px rgba(16,22,48,.06),0 14px 34px -14px rgba(16,22,48,.18)}
  .brand{font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:17px;letter-spacing:-.01em;margin-bottom:22px}
  .brand span{color:#3A4E9C}
  h1{margin:0 0 8px;font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:26px;line-height:1.22;letter-spacing:-.02em}
  p.lead{margin:0;color:#59647F}
  .facts{margin:22px 0;border:1px solid #C9D3EE;border-radius:14px;background:#F4F5FB;overflow:hidden}
  .facts div{display:flex;justify-content:space-between;gap:14px;padding:12px 16px;font-size:15px}
  .facts div+div{border-top:1px solid #C9D3EE}
  .facts dt{color:#59647F;font-size:13px}
  .facts dd{margin:0;font-weight:600;text-align:right}
  .row{display:flex;flex-wrap:wrap;gap:10px;margin-top:24px}
  .btn{flex:1 1 auto;display:inline-flex;align-items:center;justify-content:center;gap:8px;
       padding:13px 22px;border-radius:999px;font-family:'Bricolage Grotesque',sans-serif;
       font-weight:700;font-size:15px;
       text-decoration:none;cursor:pointer;border:1px solid transparent;transition:transform .16s,box-shadow .16s}
  .btn:hover{transform:translateY(-1px)}
  .btn--primary{background:linear-gradient(135deg,#3A4E9C,#1A1D38);color:#fff}
  .btn--ghost{background:#fff;border-color:#C9D3EE;color:#191C21}
  .btn--danger{background:#fff;border-color:#E3C4C4;color:#94373A}
  .btn--danger:hover{background:#FDF5F5}
  .note{margin:22px 0 0;font-size:13px;color:#8A93AC}
  .note a{color:#59647F}
  form{margin:0;display:contents}
  .ok{display:inline-flex;align-items:center;gap:9px;margin-bottom:14px;padding:6px 14px 6px 8px;
      border-radius:999px;background:#E8F3EC;color:#2A6B45;font-size:13px;font-weight:600}
  .ok svg{flex:none}
  @media (max-width:420px){.btn{flex:1 1 100%}}
</style>
</head>
<body>
  <main class="card">
    <div class="brand">Jungline<span>Local</span></div>

    <?php if ($done): ?>
      <div class="ok">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
        Abgesagt
      </div>
    <?php endif; ?>

    <h1><?= bkEsc($heading) ?></h1>
    <p class="lead"><?= bkEsc($lead) ?></p>

    <?php if ($booking !== null && $start !== null && $end !== null): ?>
      <dl class="facts">
        <div><dt>Termin</dt><dd><?= bkEsc(bkFormatDate($start)) ?></dd></div>
        <div><dt>Uhrzeit</dt><dd><?= bkEsc(bkFormatTime($start, $end)) ?></dd></div>
        <div><dt>Für</dt><dd><?= bkEsc($booking['name']) ?></dd></div>
      </dl>
    <?php endif; ?>

    <div class="row">
      <?php if ($state === 'open'): ?>
        <a class="btn btn--primary" href="<?= bkEsc($bookingUrl . '?verschieben=' . rawurlencode($token)) ?>">Anderen Termin wählen</a>
        <form method="post" action="/api/booking/cancel">
          <input type="hidden" name="token" value="<?= bkEsc($token) ?>">
          <button type="submit" class="btn btn--danger">Termin absagen</button>
        </form>
      <?php else: ?>
        <a class="btn btn--primary" href="<?= bkEsc($bookingUrl) ?>">Neuen Termin wählen</a>
        <a class="btn btn--ghost" href="<?= bkEsc($site) ?>/">Zur Startseite</a>
      <?php endif; ?>
    </div>

    <p class="note">
      Fragen? Schreiben Sie an <a href="mailto:<?= bkEsc(bkOwnerEmail()) ?>"><?= bkEsc(bkOwnerEmail()) ?></a>
      oder rufen Sie an: <a href="tel:+4917655769680">+49 176 55769680</a>.
    </p>
  </main>
</body>
</html>
