<?php
/**
 * /api/booking/calendar?token=…
 *
 * Landeseite für den einen Kalender-Link in der Bestätigungsmail. Bietet dort
 * beide Wege an (Google Kalender / Datei für Apple & Outlook), die früher als
 * zwei einzelne Links direkt in der Mail standen.
 *
 * Grund für den Umweg: Die Mail-Diagnose (/mailtest, sechs Testrunden) hat
 * eine harte Schwelle beim Hoster nachgewiesen — Mails mit vier Links
 * verschwinden nach der Annahme spurlos, mit drei oder weniger kommen sie
 * durch, unabhängig davon, welche Links es sind. Zwei einzelne
 * Kalender-Buttons in der Bestätigung rissen diese Schwelle. Eine einzelne
 * Landeseite bringt beide Optionen unter, ohne dass sie in der Mail selbst
 * als zwei Links zählen.
 */

declare(strict_types=1);

require_once __DIR__ . '/_store.php';
require_once __DIR__ . '/_ics.php';
require_once __DIR__ . '/_templates.php';

header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');

$token   = trim((string) ($_GET['token'] ?? ''));
$booking = $token === '' ? null : bkFindByToken($token);

header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Termin zum Kalender hinzufügen — JunglineLocal</title>
<link rel="icon" type="image/png" href="/logo.png">
<style>
  @font-face{font-family:'Bricolage Grotesque';src:url('/fonts/bricolage-grotesque-var.woff2') format('woff2-variations');font-weight:200 800;font-display:optional}
  @font-face{font-family:'Instrument Sans';src:url('/fonts/instrument-sans-var.woff2') format('woff2-variations');font-weight:400 700;font-display:optional}
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;display:grid;place-items:center;padding:32px 18px;
       background:#F4F5FB;color:#191C21;
       font:400 16px/1.6 'Instrument Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif}
  .card{width:100%;max-width:480px;background:#fff;border:1px solid #C9D3EE;border-radius:24px;
        padding:34px 32px;box-shadow:0 2px 6px rgba(16,22,48,.06),0 14px 34px -14px rgba(16,22,48,.18)}
  .brand{font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:17px;letter-spacing:-.01em;margin-bottom:22px}
  .brand span{color:#3A4E9C}
  h1{margin:0 0 8px;font-family:'Bricolage Grotesque',sans-serif;font-weight:700;font-size:24px;line-height:1.22;letter-spacing:-.02em}
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
       text-decoration:none;cursor:pointer;border:1px solid transparent;transition:transform .16s}
  .btn:hover{transform:translateY(-1px)}
  .btn--primary{background:linear-gradient(135deg,#3A4E9C,#1A1D38);color:#fff}
  .btn--ghost{background:#fff;border-color:#C9D3EE;color:#191C21}
  @media (max-width:420px){.btn{flex:1 1 100%}}
</style>
</head>
<body>
  <main class="card">
    <div class="brand">Jungline<span>Local</span></div>

    <?php if ($booking === null || $booking['status'] !== 'confirmed'): ?>
      <h1>Termin nicht gefunden</h1>
      <p class="lead">Dieser Link gehört zu keinem aktuellen Termin.</p>
    <?php else:
      $start = new DateTimeImmutable($booking['start_utc'], bkUtcTz());
      $end   = new DateTimeImmutable($booking['end_utc'], bkUtcTz());
    ?>
      <h1>Termin zum Kalender hinzufügen</h1>
      <p class="lead">Beide Wege führen zum selben Ziel — wählen Sie, was Sie nutzen.</p>

      <dl class="facts">
        <div><dt>Termin</dt><dd><?= bkEsc(bkFormatDate($start)) ?></dd></div>
        <div><dt>Uhrzeit</dt><dd><?= bkEsc(bkFormatTime($start, $end)) ?></dd></div>
      </dl>

      <div class="row">
        <a class="btn btn--primary" href="<?= bkEsc(bkGoogleCalendarUrl($booking)) ?>">Zu Google Kalender</a>
        <a class="btn btn--ghost" href="<?= bkEsc(bkIcsUrl($booking['token'])) ?>">Apple / Outlook (Datei)</a>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>
