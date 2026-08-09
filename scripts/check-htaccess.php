<?php
/**
 * Gegenprobe für die Sperrregeln in public/.htaccess.
 *
 *     npm run check:htaccess
 *
 * WOZU DAS GUT IST
 * Die drei Regelblöcke in der .htaccess (User-Agent-Sperre, Hotlink-Schutz,
 * Dateisperren) sind reguläre Ausdrücke. Ein zu breit geratenes Muster
 * sperrt im schlimmsten Fall Googlebot aus — und das fällt erst auf, wenn
 * die Seite Wochen später aus dem Index fällt. Dieses Skript hält die
 * Muster gegen eine Liste echter User-Agents, Referrer und Pfade und sagt
 * sofort, wenn eine Regel jemanden trifft, den sie nicht treffen darf.
 *
 * Es liest die Muster DIREKT aus public/.htaccess. Wer dort etwas ändert,
 * prüft mit diesem Aufruf, ob die Änderung sicher ist.
 *
 * GRENZE DER AUSSAGE
 * Getestet wird die Mustererkennung (PCRE), nicht der Apache selbst. Dass
 * mod_rewrite auf Hostinger aktiv ist, beweist der bereits laufende
 * www-Redirect; die Verknüpfungslogik (zwei RewriteCond = UND, "!" =
 * Verneinung) ist hier nachgebildet.
 */

declare(strict_types=1);

$htaccessPath = __DIR__ . '/../public/.htaccess';
$htaccess = @file_get_contents($htaccessPath);
if ($htaccess === false) {
    fwrite(STDERR, "public/.htaccess nicht lesbar\n");
    exit(1);
}

$failures = 0;
$checked  = 0;

function section(string $title): void {
    echo "\n" . $title . "\n" . str_repeat('-', 74) . "\n";
}

function assertResult(string $name, bool $expectedBlock, bool $actualBlock): void {
    global $failures, $checked;
    $checked++;
    if ($expectedBlock === $actualBlock) {
        printf("  ok      %-46s %s\n", $name, $actualBlock ? '403' : 'kommt durch');
    } else {
        $failures++;
        printf("  FEHLER  %-46s erwartet %s, ist %s\n", $name,
            $expectedBlock ? '403' : 'kommt durch',
            $actualBlock ? '403' : 'kommt durch');
    }
}

// =====================================================================
// 1) User-Agent-Sperre
// =====================================================================

preg_match('/RewriteCond %\{HTTP_USER_AGENT\} \((GPTBot\|[^)]+)\) \[NC\]/', $htaccess, $mBlock);
preg_match('/RewriteCond %\{HTTP_USER_AGENT\} !\((Googlebot\|[^)]+)\) \[NC\]/', $htaccess, $mAllow);

if (empty($mBlock[1]) || empty($mAllow[1])) {
    fwrite(STDERR, "Die User-Agent-Muster wurden in der .htaccess nicht gefunden.\n");
    exit(1);
}

$uaBlock = '/(' . $mBlock[1] . ')/i';
$uaAllow = '/(' . $mAllow[1] . ')/i';

/** Apache sperrt nur, wenn die Sperrliste trifft UND die Freigabeliste nicht. */
$uaBlocked = static fn(string $ua): bool =>
    preg_match($uaBlock, $ua) === 1 && preg_match($uaAllow, $ua) !== 1;

section('1) USER-AGENTS, DIE DURCHKOMMEN MÜSSEN');
$mustPass = [
    'Googlebot' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    'Googlebot Smartphone' => 'Mozilla/5.0 (Linux; Android 6.0.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    'Googlebot-Image' => 'Googlebot-Image/1.0',
    'Googlebot-News' => 'Googlebot-News',
    'Googlebot-Video' => 'Googlebot-Video/1.0',
    'AdsBot-Google' => 'AdsBot-Google (+http://www.google.com/adsbot.html)',
    'Mediapartners-Google' => 'Mediapartners-Google',
    'Google-InspectionTool' => 'Mozilla/5.0 (compatible; Google-InspectionTool/1.0;)',
    'Storebot-Google' => 'Mozilla/5.0 (compatible; Storebot-Google/1.0; +http://www.google.com/webmasters/bot.html)',
    'GoogleOther' => 'Mozilla/5.0 (compatible; GoogleOther)',
    'APIs-Google' => 'APIs-Google (+https://developers.google.com/webmasters/APIs-Google.html)',
    'Google-Site-Verification' => 'Mozilla/5.0 (compatible; Google-Site-Verification/1.0)',
    'Google-Read-Aloud' => 'Mozilla/5.0 (compatible; Google-Read-Aloud)',
    'Chrome-Lighthouse' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/136.0.0.0 Safari/537.36 Chrome-Lighthouse',
    'Bingbot' => 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
    'BingPreview' => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/534+ BingPreview/1.0b',
    'msnbot' => 'msnbot/2.0b (+http://search.msn.com/msnbot.htm)',
    'adidxbot' => 'Mozilla/5.0 (compatible; adidxbot/2.0; +http://www.bing.com/bingbot.htm)',
    'DuckDuckBot' => 'DuckDuckBot/1.1; (+http://duckduckgo.com/duckduckbot.html)',
    'DuckAssistBot' => 'Mozilla/5.0 (compatible; DuckAssistBot/1.1; +http://duckduckgo.com/duckassistbot.html)',
    'Applebot (Siri/Spotlight)' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (Applebot/0.1; +http://www.apple.com/go/applebot)',
    'Yahoo Slurp' => 'Mozilla/5.0 (compatible; Yahoo! Slurp; http://help.yahoo.com/help/us/ysearch/slurp)',
    'YandexBot' => 'Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)',
    'Baiduspider' => 'Mozilla/5.0 (compatible; Baiduspider-render/2.0; +http://www.baidu.com/search/spider.html)',
    'SeznamBot' => 'Mozilla/5.0 (compatible; SeznamBot/4.0; +http://napoveda.seznam.cz/en/seznambot-intro/)',
    'Qwantify' => 'Mozilla/5.0 (compatible; Qwantify/2.0w; +https://www.qwant.com/)',
    'OAI-SearchBot' => 'Mozilla/5.0 (compatible; OAI-SearchBot/1.0; +https://openai.com/searchbot)',
    'ChatGPT-User' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; ChatGPT-User/1.0; +https://openai.com/bot',
    'Claude-User' => 'Mozilla/5.0 (compatible; Claude-User/1.0; +Claude-User@anthropic.com)',
    'Claude-SearchBot' => 'Mozilla/5.0 (compatible; Claude-SearchBot/1.0; +Claude-SearchBot@anthropic.com)',
    'PerplexityBot' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36; compatible; PerplexityBot/1.0; +https://perplexity.ai/perplexitybot',
    'Perplexity-User' => 'Mozilla/5.0 (compatible; Perplexity-User/1.0; +https://perplexity.ai/perplexity-user)',
    'facebookexternalhit (Vorschau)' => 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
    'meta-externalfetcher' => 'meta-externalfetcher/1.1 (+https://developers.facebook.com/docs/sharing/webmasters/crawler)',
    'WhatsApp' => 'WhatsApp/2.23.20.0 A',
    'Twitterbot' => 'Twitterbot/1.0',
    'LinkedInBot' => 'LinkedInBot/1.0 (compatible; Mozilla/5.0; Apache-HttpClient +http://www.linkedin.com)',
    'Slackbot' => 'Slackbot-LinkExpanding 1.0 (+https://api.slack.com/robots)',
    'Discordbot' => 'Mozilla/5.0 (compatible; Discordbot/2.0; +https://discordapp.com)',
    'TelegramBot' => 'TelegramBot (like TwitterBot)',
    'Pinterest' => 'Pinterest/0.2 (+https://www.pinterest.com/bot.html)',
    'Chrome (Besucher)' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36',
    'Safari iPhone (Besucher)' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1',
    'Firefox (Besucher)' => 'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0',
    'curl (Deploy-Prüfung)' => 'curl/8.5.0',
    'UptimeRobot' => 'Mozilla/5.0+(compatible; UptimeRobot/2.0; http://www.uptimerobot.com/)',
];
foreach ($mustPass as $name => $ua) assertResult($name, false, $uaBlocked($ua));

section('2) USER-AGENTS, DIE GESPERRT WERDEN MÜSSEN');
$mustBlock = [
    'GPTBot' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; GPTBot/1.1; +https://openai.com/gptbot',
    'CCBot' => 'CCBot/2.0 (https://commoncrawl.org/faq/)',
    'ClaudeBot' => 'Mozilla/5.0 (compatible; ClaudeBot/1.0; +claudebot@anthropic.com)',
    'anthropic-ai' => 'anthropic-ai',
    'Bytespider' => 'Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)',
    'Amazonbot' => 'Mozilla/5.0 (Linux; like Mac OS X) AppleWebKit/537.36 (compatible; Amazonbot/0.1; +https://developer.amazon.com/support/amazonbot)',
    'Diffbot' => 'Mozilla/5.0 (compatible; Diffbot/0.1; +http://www.diffbot.com)',
    'Omgilibot' => 'omgilibot/0.3 +http://omgili.com',
    'ImagesiftBot' => 'Mozilla/5.0 (compatible; ImagesiftBot; +imagesift.com)',
    'FacebookBot (KI-Crawler)' => 'FacebookBot/1.0 (+https://developers.facebook.com/docs/sharing/bot/)',
    'meta-externalagent' => 'meta-externalagent/1.1 (+https://developers.facebook.com/docs/sharing/webmasters/crawler)',
    'Timpibot' => 'Mozilla/5.0 (compatible; Timpibot/0.8; +http://www.timpi.io)',
    'Scrapy' => 'Scrapy/2.11.2 (+https://scrapy.org)',
    'SemrushBot' => 'Mozilla/5.0 (compatible; SemrushBot/7~bl; +http://www.semrush.com/bot.html)',
    'AhrefsBot' => 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)',
    'MJ12bot' => 'Mozilla/5.0 (compatible; MJ12bot/v1.4.8; http://mj12bot.com/)',
    'DotBot' => 'Mozilla/5.0 (compatible; DotBot/1.2; +https://opensiteexplorer.org/dotbot; help@moz.com)',
    'PetalBot' => 'Mozilla/5.0 (compatible; PetalBot;+https://webmaster.petalsearch.com/site/petalbot)',
    'BLEXBot' => 'Mozilla/5.0 (compatible; BLEXBot/1.0; +http://webmeup-crawler.com/)',
    'DataForSeoBot' => 'Mozilla/5.0 (compatible; DataForSeoBot/1.0; +https://dataforseo.com/dataforseo-bot)',
];
foreach ($mustBlock as $name => $ua) assertResult($name, true, $uaBlocked($ua));

// =====================================================================
// 3) Hotlink-Schutz
// =====================================================================

preg_match_all('/RewriteCond %\{HTTP_REFERER\} !(\S+) \[NC\]/', $htaccess, $mRef);
$refPatterns  = $mRef[1] ?? [];
$emptyAllowed = str_contains($htaccess, 'RewriteCond %{HTTP_REFERER} !^$');

if (empty($refPatterns)) {
    fwrite(STDERR, "Die Hotlink-Muster wurden in der .htaccess nicht gefunden.\n");
    exit(1);
}

$refBlocked = static function (string $referer) use ($refPatterns, $emptyAllowed): bool {
    if ($emptyAllowed && $referer === '') return false;
    foreach ($refPatterns as $pattern) {
        if (preg_match('~' . $pattern . '~i', $referer)) return false;
    }
    return true;
};

section('3) HOTLINK-SCHUTZ FÜR BILDER (Referrer)');
$referers = [
    '(leer — Direktaufruf, Googlebot-Image, WhatsApp-Vorschau)' => ['', false],
    'https://jungline.de/' => ['https://jungline.de/', false],
    'https://www.jungline.de/leistungen/' => ['https://www.jungline.de/leistungen/', false],
    'aus dem eigenen Stylesheet' => ['https://jungline.de/assets/site.css', false],
    'Google-Suche (.de)' => ['https://www.google.de/', false],
    'Google-Bilder (.com)' => ['https://images.google.com/', false],
    'Google (.co.uk)' => ['https://google.co.uk/', false],
    'Bing' => ['https://www.bing.com/images', false],
    'DuckDuckGo' => ['https://duckduckgo.com/', false],
    'Ecosia' => ['https://www.ecosia.org/', false],
    'lokale Entwicklung' => ['http://localhost:5173/', false],
    'fremde Website' => ['https://fremde-seite.de/klau.html', true],
    'Scraper-Domain' => ['https://scraper.example.com/', true],
    'Ähnlich aussehende Domain' => ['https://jungline.de.boese.example/', true],
    'notjungline.de' => ['https://notjungline.de/', true],
];
foreach ($referers as $name => [$referer, $expect]) assertResult($name, $expect, $refBlocked($referer));

// =====================================================================
// 4) Datei- und Pfadsperren
// =====================================================================

preg_match('/RewriteRule \\\\\.\(([a-z0-9|]+)\)\$ - \[F,NC,L\]/', $htaccess, $mExt);
if (empty($mExt[1])) {
    fwrite(STDERR, "Das Endungs-Muster wurde in der .htaccess nicht gefunden.\n");
    exit(1);
}
$extPattern = '/\.(' . $mExt[1] . ')$/i';

$pathBlocked = static function (string $uri) use ($extPattern): bool {
    $rel = ltrim($uri, '/');    // Apache prüft in .htaccess ohne führenden Slash
    if (preg_match('~^/\.well-known/~', $uri)) return false;
    if (preg_match('~(^|/)\.[^/]+~', $rel)) return true;
    if (preg_match($extPattern, $rel)) return true;
    if (str_ends_with($rel, '~')) return true;
    return false;
};

section('4) DATEIEN, DIE ERREICHBAR BLEIBEN MÜSSEN');
foreach ([
    '/', '/index.html', '/nutzungsbedingungen/', '/assets/site.css', '/assets/site.js',
    '/robots.txt', '/ai.txt', '/sitemap.xml', '/logo.png', '/leandro-2.jpg', '/leandro-2.webp',
    '/fonts/instrument-sans-var.woff2', '/clients/energieberatung-nordbayern-logo.svg',
    '/api/gbp-check.php', '/api/booking/slots.php', '/google470510489ee8cbad.html',
    '/.well-known/ai.txt', '/.well-known/tdmrep.json', '/.well-known/acme-challenge/xyz123',
] as $uri) assertResult($uri, false, $pathBlocked($uri));

section('5) DATEIEN, DIE GESPERRT SEIN MÜSSEN');
foreach ([
    '/.git/config', '/.git/HEAD', '/.env', '/.env.local', '/.htaccess', '/.DS_Store',
    '/package.json', '/package-lock.json', '/DEPLOY.md', '/TERMINBUCHUNG.md',
    '/api/cache/abc.json', '/backup.zip', '/dump.sql', '/config.ini', '/site.conf',
    '/data.sqlite', '/index.html.bak', '/style.css.old', '/index.html~',
    '/.jungline-data/bookings.json',
] as $uri) assertResult($uri, true, $pathBlocked($uri));

echo "\n" . str_repeat('=', 74) . "\n";
printf("%d Regeln geprüft, %d Fehler\n", $checked, $failures);
if ($failures > 0) {
    echo "\nMindestens eine Regel trifft den Falschen. Bitte die Muster in\n";
    echo "public/.htaccess korrigieren, BEVOR deployt wird.\n";
}
exit($failures === 0 ? 0 : 1);
