<?php
/**
 * KooraLive - api.php
 * نظام بنك المباريات - جلب من الـ API وتخزين منفصل للاختيار اليدوي
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');

date_default_timezone_set('Asia/Riyadh');

// ========== مسارات الملفات ==========
$baseDir      = __DIR__ . '/../data/';
$matchesFile  = $baseDir . 'matches.json';
$newsFile     = $baseDir . 'news.json';
$settingsFile = $baseDir . 'api_settings.json';
$cacheDir     = $baseDir . 'api_cache/';
$dailyCacheF  = $cacheDir . 'daily_fetch.json';
$liveCacheF   = $cacheDir . 'live_update.json';
$fixturesBank = $baseDir . 'api_fixtures.json'; // بنك المباريات الخام

// إنشاء المجلدات
if (!is_dir($cacheDir)) mkdir($cacheDir, 0777, true);
if (!is_dir($baseDir)) @mkdir($baseDir, 0777, true);

// ========== تحميل الإعدادات ==========
$defaultSettings = ['api_key' => '', 'auto_fetch' => true, 'cache_minutes' => 15];
$settings = file_exists($settingsFile) ? array_merge($defaultSettings, json_decode(file_get_contents($settingsFile), true) ?: []) : $defaultSettings;

$apiKey       = $settings['api_key'] ?? '';
$autoFetch    = $settings['auto_fetch'] ?? true;
$cacheMinutes = (int)($settings['cache_minutes'] ?? 15);
$fetchHour    = (int)($settings['fetch_hour'] ?? 0);

// ========== الدوال المساعدة ==========
function readJson($path) {
    if (!file_exists($path)) return [];
    $content = @file_get_contents($path);
    return $content ? (json_decode($content, true) ?: []) : [];
}

function writeJson($path, $data) {
    file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function callApi($endpoint, $apiKey) {
    if (empty($apiKey)) return null;
    $url = "https://v3.football.api-sports.io/$endpoint";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["x-apisports-key: $apiKey"],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) return null;
    $data = json_decode($response, true);
    return (empty($data['errors'])) ? ($data['response'] ?? []) : null;
}

function mapApiMatch($f, $dayLabel) {
    $rawStatus = $f['fixture']['status']['short'] ?? 'NS';
    $statusType = 'upcoming';
    if (in_array($rawStatus, ['FT', 'AET', 'PEN'])) $statusType = 'finished';
    elseif (in_array($rawStatus, ['1H', '2H', 'HT', 'ET', 'P', 'BT', 'LIVE'])) $statusType = 'live';

    $elapsed = $f['fixture']['status']['elapsed'] ?? 0;
    $statusTextMap = [
        '1H' => 'مباشر الآن', '2H' => 'مباشر الآن', 'HT' => 'استراحة', 'ET' => 'وقت إضافي', 'P' => 'ركلات ترجيح', 'FT' => 'انتهت', 'NS' => 'قادمة'
    ];
    $statusText = $statusTextMap[$rawStatus] ?? 'قادمة';
    if (in_array($rawStatus, ['1H', '2H', 'ET', 'LIVE']) && $elapsed > 0) $statusText .= " {$elapsed}'";

    return [
        'id' => (string)$f['fixture']['id'],
        'homeTeam' => $f['teams']['home']['name'] ?? '',
        'awayTeam' => $f['teams']['away']['name'] ?? '',
        'homeLogo' => $f['teams']['home']['logo'] ?? '',
        'awayLogo' => $f['teams']['away']['logo'] ?? '',
        'league' => $f['league']['name'] ?? '',
        'time' => date('H:i', $f['fixture']['timestamp'] ?? time()),
        'timestamp' => $f['fixture']['timestamp'] ?? 0,
        'day' => $dayLabel,
        'status' => $statusType,
        'status_text' => $statusText,
        'homeScore' => (string)($f['goals']['home'] ?? '0'),
        'awayScore' => (string)($f['goals']['away'] ?? '0'),
        'source' => 'api'
    ];
}

// ========== 1. الجلب اليومي للبنك ==========
function runDailyFetch($apiKey, $dailyCacheF, $fixturesBank, $fetchHour) {
    if (empty($apiKey)) return;
    $today = date('Y-m-d');
    $currentHour = (int)date('H');
    if ($currentHour < $fetchHour) return; // لم يحن وقت الجلب بعد

    $dailyCache = readJson($dailyCacheF);
    if (($dailyCache['date'] ?? '') === $today) return;

    $dates = ['yesterday' => date('Y-m-d', strtotime('-1 day')), 'today' => $today, 'tomorrow' => date('Y-m-d', strtotime('+1 day'))];
    $fetchedMatches = [];
    foreach ($dates as $dayLabel => $dateStr) {
        $result = callApi("fixtures?date=$dateStr&timezone=Asia/Riyadh", $apiKey);
        if ($result) foreach ($result as $f) $fetchedMatches[] = mapApiMatch($f, $dayLabel);
    }
    if ($fetchedMatches) {
        writeJson($fixturesBank, $fetchedMatches);
        writeJson($dailyCacheF, ['date' => $today, 'time' => time(), 'count' => count($fetchedMatches)]);
    }
}

// ========== 2. تحديث النتائج الحية لمباريات الموقع ==========
function runLiveUpdate($apiKey, $liveCacheF, $matchesFile, $cacheSeconds) {
    if (empty($apiKey)) return;
    $liveCache = readJson($liveCacheF);
    if ((time() - ($liveCache['time'] ?? 0)) < $cacheSeconds) return;

    $matches = readJson($matchesFile);
    $idsToUpdate = [];
    foreach ($matches as $m) {
        if (($m['source'] ?? '') === 'api' && ($m['status'] ?? '') !== 'finished') $idsToUpdate[] = $m['id'];
    }
    if (empty($idsToUpdate)) { writeJson($liveCacheF, ['time' => time()]); return; }

    $chunks = array_chunk($idsToUpdate, 20);
    $apiUpdates = [];
    foreach ($chunks as $chunk) {
        $res = callApi("fixtures?ids=" . implode('-', $chunk), $apiKey);
        if ($res) foreach ($res as $f) $apiUpdates[(string)$f['fixture']['id']] = $f;
    }
    if ($apiUpdates) {
        foreach ($matches as &$m) {
            if (isset($apiUpdates[$m['id']])) {
                $f = $apiUpdates[$m['id']];
                $m['homeScore'] = (string)($f['goals']['home'] ?? '0');
                $m['awayScore'] = (string)($f['goals']['away'] ?? '0');
                $m['time'] = date('H:i', $f['fixture']['timestamp'] ?? time());
                $m['timestamp'] = $f['fixture']['timestamp'] ?? 0;
                $rawStatus = $f['fixture']['status']['short'] ?? 'NS';
                if (in_array($rawStatus, ['FT', 'AET', 'PEN'])) $m['status'] = 'finished';
                elseif (in_array($rawStatus, ['1H', '2H', 'HT', 'ET', 'P', 'LIVE'])) $m['status'] = 'live';
                else $m['status'] = 'upcoming';
            }
        }
        writeJson($matchesFile, $matches);
    }
    writeJson($liveCacheF, ['time' => time(), 'updated' => count($apiUpdates)]);
}

if ($autoFetch) {
    runDailyFetch($apiKey, $dailyCacheF, $fixturesBank, $fetchHour);
    runLiveUpdate($apiKey, $liveCacheF, $matchesFile, (int)($settings['cache_seconds'] ?? 900));
}

// ========== معالجة الطلبات ==========
$action = $_GET['action'] ?? 'get_matches';
if ($action === 'get_matches') {
    $m = readJson($matchesFile);
    usort($m, function($a, $b) {
        $score = ['live' => 0, 'upcoming' => 1, 'finished' => 2];
        $sA = $score[$a['status']] ?? 1; $sB = $score[$b['status']] ?? 1;
        if ($sA !== $sB) return $sA - $sB;
        return ($a['timestamp'] ?? 0) - ($b['timestamp'] ?? 0);
    });
    echo json_encode($m, JSON_UNESCAPED_UNICODE); exit;
}
if ($action === 'get_news') { echo json_encode(array_reverse(readJson($newsFile)), JSON_UNESCAPED_UNICODE); exit; }

// لوحة التحكم - إحصائيات
if ($action === 'api_status') {
    $d = readJson($dailyCacheF); $l = readJson($liveCacheF);
    $rem = null; $usd = null;
    if ($apiKey) {
        $ch = curl_init("https://v3.football.api-sports.io/status");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ["x-apisports-key: $apiKey"], CURLOPT_SSL_VERIFYPEER => false]);
        $res = json_decode(curl_exec($ch), true); curl_close($ch);
        $rem = $res['response']['requests']['limit_day'] ?? 100;
        $usd = $res['response']['requests']['current'] ?? 0;
    }
    echo json_encode([
        'last_daily_date' => $d['date'] ?? '--',
        'last_live_update' => isset($l['time']) ? date('H:i', $l['time']) : '--',
        'requests_used' => $usd, 'requests_limit' => $rem, 'api_key_set' => !empty($apiKey)
    ], JSON_UNESCAPED_UNICODE); exit;
}

// لوحة التحكم - حفظ الإعدادات
if ($action === 'save_api_settings') {
    $inp = json_decode(file_get_contents('php://input'), true);
    $settings['api_key'] = trim($inp['api_key'] ?? $settings['api_key']);
    $settings['cache_seconds'] = max(5, intval($inp['cache_seconds'] ?? 900));
    $settings['fetch_hour'] = max(0, min(23, intval($inp['fetch_hour'] ?? 0)));
    $settings['auto_fetch'] = $inp['auto_fetch'] ?? true;
    writeJson($settingsFile, $settings);
    echo json_encode(['success' => true]); exit;
}

// لوحة التحكم - جلب فوري للبنك
if ($action === 'force_fetch') {
    if (file_exists($dailyCacheF)) unlink($dailyCacheF);
    runDailyFetch($apiKey, $dailyCacheF, $fixturesBank);
    echo json_encode(['success' => true, 'count' => count(readJson($fixturesBank))]); exit;
}

// لوحة التحكم - جلب قائمة البنك للاختيار
if ($action === 'get_bank') {
    $bank = readJson($fixturesBank);
    $site = readJson($matchesFile);
    $siteIds = array_column($site, 'id');
    $res = [];
    foreach ($bank as $m) if (!in_array($m['id'], $siteIds)) $res[] = $m;
    echo json_encode($res, JSON_UNESCAPED_UNICODE); exit;
}

// لوحة التحكم - إضافة مباراة من البنك للموقع
if ($action === 'add_from_bank') {
    $inp = json_decode(file_get_contents('php://input'), true);
    $id = (string)$inp['id'];
    $bank = readJson($fixturesBank);
    $site = readJson($matchesFile);
    foreach ($bank as $bm) {
        if ($bm['id'] === $id) {
            $bm['streamUrl'] = $inp['streamUrl'] ?? '';
            $bm['channel'] = $inp['channel'] ?? 'غير معروف';
            $bm['commentator'] = $inp['commentator'] ?? 'غير معروف';
            $site[] = $bm; break;
        }
    }
    writeJson($matchesFile, $site);
    echo json_encode(['success' => true]); exit;
}
