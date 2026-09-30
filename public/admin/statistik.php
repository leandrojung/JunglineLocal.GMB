<?php
/**
 * /admin/statistik.php — Auswertung der cookielosen Besucherstatistik.
 * Liegt im per Passwort (.htaccess, Basic Auth) geschützten Admin-Ordner.
 * Datenquelle und Datenschutz: siehe api/_stats.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../api/_stats.php';

date_default_timezone_set('Europe/Berlin');
header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');

$tage = (int) ($_GET['tage'] ?? 30);
if (!in_array($tage, [7, 30, 90], true)) $tage = 30;
$daten = statsRange($tage);

$summe = static function (array $daten, string $art): array {
    $s = [];
    foreach ($daten as $t) foreach (($t[$art] ?? []) as $k => $v) $s[$k] = ($s[$k] ?? 0) + (int) $v;
    arsort($s);
    return $s;
};
$seiten = $summe($daten, 'pv');
$quellen = $summe($daten, 'ref');
$geraete = $summe($daten, 'dev');
$ereignisse = $summe($daten, 'ev');
$gesamt = array_sum($seiten);
$proTag = array_map(static fn ($t) => array_sum($t['pv'] ?? []), $daten);
$maxTag = max(1, ...array_values($proTag));
$ev = static fn (string $k): int => (int) ($ereignisse[$k] ?? 0);
$quote = static fn (int $a, int $b): string => $b > 0 ? round($a / $b * 100) . ' %' : '–';
$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$liste = static function (array $werte, int $max = 12) use ($h): string {
    if ($werte === []) return '<p class="leer">Noch keine Daten.</p>';
    $top = max(1, ...array_values($werte));
    $out = '';
    foreach (array_slice($werte, 0, $max, true) as $k => $v) {
        $out .= '<div class="zeile"><span class="k">' . $h((string) $k) . '</span><span class="v">' . $v . '</span>'
              . '<i style="width:' . round($v / $top * 100) . '%"></i></div>';
    }
    return $out;
};
?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Statistik · Jungline Local</title>
<style>
  :root{--bg:#F5F5F7;--card:#fff;--ink:#1D1D1F;--dim:#6E6E73;--line:#E3E3E8;--brand:#3D50C8;--bar:rgba(61,80,200,.12)}
  @media (prefers-color-scheme:dark){:root{--bg:#0A0D1F;--card:#151A36;--ink:#F4F5FB;--dim:rgba(232,235,248,.6);--line:rgba(255,255,255,.1);--brand:#AEBBFF;--bar:rgba(174,187,255,.16)}}
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:var(--bg);color:var(--ink);font:15px/1.5 -apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;padding:28px 16px 60px}
  .wrap{max-width:980px;margin:0 auto}
  header{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:14px;margin-bottom:22px}
  h1{font-size:1.7rem;letter-spacing:-.02em}
  .sub{color:var(--dim);font-size:.9rem;margin-top:4px}
  nav a{display:inline-block;padding:7px 14px;border-radius:999px;color:var(--dim);text-decoration:none;font-weight:600;font-size:.9rem}
  nav a.on{background:var(--card);color:var(--ink);box-shadow:0 1px 3px rgba(0,0,0,.08)}
  .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px}
  .card{background:var(--card);border-radius:18px;padding:20px 20px 16px}
  .card h2{font-size:.95rem;margin-bottom:12px}
  .kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:14px}
  .kpi b{display:block;font-size:2rem;letter-spacing:-.02em;line-height:1.1}
  .kpi span{color:var(--dim);font-size:.85rem}
  .chart{display:flex;align-items:flex-end;gap:2px;height:120px;margin-top:6px}
  .chart i{flex:1;background:var(--brand);border-radius:3px 3px 0 0;min-height:2px;opacity:.85}
  .zeile{position:relative;display:flex;justify-content:space-between;gap:10px;padding:7px 10px;border-radius:8px;font-size:.9rem;overflow:hidden}
  .zeile i{position:absolute;left:0;top:0;bottom:0;background:var(--bar);z-index:0}
  .zeile span{position:relative;z-index:1}
  .zeile .k{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .zeile .v{font-variant-numeric:tabular-nums;font-weight:600}
  .trichter .zeile{padding:9px 10px}
  .leer{color:var(--dim);font-size:.9rem}
  .fuss{margin-top:22px;color:var(--dim);font-size:.82rem;max-width:70ch}
</style>
</head>
<body>
<div class="wrap">
  <header>
    <div>
      <h1>Besucherstatistik</h1>
      <p class="sub">Letzte <?= $tage ?> Tage · ohne Cookies, ohne IP-Adressen</p>
    </div>
    <nav>
      <?php foreach ([7, 30, 90] as $n): ?>
        <a href="?tage=<?= $n ?>" class="<?= $n === $tage ? 'on' : '' ?>"><?= $n ?> Tage</a>
      <?php endforeach; ?>
    </nav>
  </header>

  <div class="kpis">
    <div class="card kpi"><b><?= $gesamt ?></b><span>Seitenaufrufe</span></div>
    <div class="card kpi"><b><?= $ev('check_start') ?></b><span>Profil-Checks gestartet</span></div>
    <div class="card kpi"><b><?= $ev('booking_done') ?></b><span>Termine gebucht</span></div>
    <div class="card kpi"><b><?= $ev('contact_sent') + $ev('report_request') ?></b><span>Anfragen (Formular + Auswertung)</span></div>
    <div class="card kpi"><b><?= $ev('tel_click') ?></b><span>Anruf angetippt</span></div>
  </div>

  <div class="card" style="margin-bottom:14px">
    <h2>Seitenaufrufe pro Tag</h2>
    <div class="chart">
      <?php foreach ($proTag as $tag => $n): ?><i title="<?= $h(date('d.m.', strtotime($tag))) ?>: <?= $n ?>" style="height:<?= round($n / $maxTag * 100) ?>%"></i><?php endforeach; ?>
    </div>
  </div>

  <div class="grid">
    <div class="card trichter">
      <h2>Wege zur Anfrage</h2>
      <div class="zeile"><span class="k">Profil-Check → Ergebnis</span><span class="v"><?= $ev('check_result') ?> / <?= $ev('check_start') ?></span></div>
      <div class="zeile"><span class="k">Ergebnis → Auswertung angefordert</span><span class="v"><?= $quote($ev('report_request'), $ev('check_result')) ?></span></div>
      <div class="zeile"><span class="k">Kalender: Uhrzeit gewählt → gebucht</span><span class="v"><?= $ev('booking_done') ?> / <?= $ev('booking_day') ?></span></div>
      <div class="zeile"><span class="k">Startscreen gezeigt</span><span class="v"><?= $ev('chooser_shown') ?></span></div>
      <div class="zeile"><span class="k">… davon eine Wahl getroffen</span><span class="v"><?= $quote($ev('chooser_seo') + $ev('chooser_webdesign') + $ev('chooser_skip'), $ev('chooser_shown')) ?></span></div>
    </div>
    <div class="card"><h2>Seiten</h2><?= $liste($seiten) ?></div>
    <div class="card"><h2>Herkunft</h2><?= $liste($quellen) ?></div>
    <div class="card"><h2>Geräte</h2><?= $liste($geraete) ?></div>
    <div class="card">
      <h2>Alle Ereignisse</h2>
      <?php
        $benannt = [];
        foreach ($ereignisse as $k => $v) $benannt[STATS_EVENTS[$k] ?? $k] = $v;
        echo $liste($benannt, 20);
      ?>
    </div>
  </div>

  <p class="fuss">Gezählt werden nur Tagessummen. Wer „Do Not Track“ oder „Global Privacy Control“ im Browser aktiviert hat, wird nicht gezählt; Suchmaschinen-Crawler ebenfalls nicht. Deshalb liegen die Zahlen etwas unter denen, die ein Tracking-Dienst zeigen würde — dafür ohne Einwilligungsbanner.</p>
</div>
</body>
</html>
