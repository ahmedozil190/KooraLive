<?php
/**
 * KooraLive - api.php
 * نقطة الاتصال الرئيسية للتطبيق
 * نظام Cache ذكي بدون Cron
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

// إنشاء المجلدات إذا لم تكن موجودة
if (!is_dir($cacheDir)) mkdir($cacheDir, 0777, true);

// ========== تحميل الإعدادات ==========
$defaultSettings = [
    'api_key'         => '',
    'auto_fetch'      => true,
    'cache_minutes'   => 15,
    'fetch_leagues'   => [], // قائمة البطولات المطلوبة (فارغة = كل البطولات)
];
$settings = file_exists($settingsFile)
    ? array_merge($defaultSettings, json_decode(file_get_contents($settingsFile), true) ?: [])
    : $defaultSettings;

$apiKey       = $settings['api_key'];
$autoFetch    = $settings['auto_fetch'];
$cacheMinutes = (int)($settings['cache_minutes'] ?? 15);

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
    if (!empty($data['errors'])) return null;
    return $data['response'] ?? [];
}

function mapApiMatch($f, $dayLabel) {
    $rawStatus = $f['fixture']['status']['short'] ?? 'NS';
    $statusType = 'upcoming';
    if (in_array($rawStatus, ['FT', 'AET', 'PEN'])) $statusType = 'finished';
    elseif (in_array($rawStatus, ['1H', '2H', 'HT', 'ET', 'P', 'BT', 'LIVE'])) $statusType = 'live';

    $elapsed = $f['fixture']['status']['elapsed'] ?? 0;
    $statusTextMap = [
        '1H' => 'مباشر الآن', '2H' => 'مباشر الآن', 'HT' => 'استراحة',
        'ET' => 'وقت إضافي', 'P'  => 'ركلات ترجيح', 'BT' => 'بين الأشواط',
        'FT' => 'انتهت', 'AET' => 'انتهت', 'PEN' => 'انتهت', 'LIVE' => 'مباشر الآن',
        'NS' => 'قادمة', 'TBD' => 'قادمة',
    ];
    $statusText = $statusTextMap[$rawStatus] ?? 'قادمة';
    if (in_array($rawStatus, ['1H', '2H', 'ET', 'LIVE']) && $elapsed > 0) {
        $statusText .= " {$elapsed}'";
    }

    return [
        'id'          => (string)$f['fixture']['id'],
        'source'      => 'api', // تمييز المباريات القادمة من الـ API
        'homeTeam'    => $f['teams']['home']['name']  ?? '',
        'awayTeam'    => $f['teams']['away']['name']  ?? '',
        'homeLogo'    => $f['teams']['home']['logo']  ?? '',
        'awayLogo'    => $f['teams']['away']['logo']  ?? '',
        'league'      => $f['league']['name']          ?? '',
        'leagueLogo'  => $f['league']['logo']          ?? '',
        'time'        => date('h:i A', $f['fixture']['timestamp'] ?? time()),
        'timestamp'   => $f['fixture']['timestamp'] ?? 0,
        'day'         => $dayLabel,
        'status'      => $statusType,
        'status_text' => $statusText,
        'homeScore'   => (string)($f['goals']['home'] ?? '0'),
        'awayScore'   => (string)($f['goals']['away'] ?? '0'),
        'streamUrl'   => '',
        'channel'     => 'غير معروف',
        'commentator' => 'غير معروف',
    ];
}

function smartSort(&$matches) {
    usort($matches, function($a, $b) {
        $score = ['live' => 0, 'upcoming' => 1, 'finished' => 2];
        $sA = $score[$a['status']] ?? 1;
        $sB = $score[$b['status']] ?? 1;
        if ($sA !== $sB) return $sA - $sB;
        $tA = $a['timestamp'] ?? strtotime($a['time'] ?? '00:00');
        $tB = $b['timestamp'] ?? strtotime($b['time'] ?? '00:00');
        return $tA - $tB;
    });
}

// ========== 1. الجلب اليومي (مرة واحدة كل يوم) ==========
function runDailyFetch($apiKey, $cacheDir, $dailyCacheF, $matchesFile, $settings) {
    $today = date('Y-m-d');
    $dailyCache = file_exists($dailyCacheF) ? json_decode(file_get_contents($dailyCacheF), true) : [];

    // إذا تم الجلب اليوم بالفعل، لا تجلب مرة أخرى
    if (isset($dailyCache['date']) && $dailyCache['date'] === $today) return;

    $dates = [
        'yesterday' => date('Y-m-d', strtotime('-1 day')),
        'today'     => $today,
        'tomorrow'  => date('Y-m-d', strtotime('+1 day')),
    ];

    $fetchedMatches = [];
    foreach ($dates as $dayLabel => $dateStr) {
        $result = callApi("fixtures?date=$dateStr&timezone=Asia/Riyadh", $apiKey);
        if ($result === null) continue;

        foreach ($result as $f) {
            $match = mapApiMatch($f, $dayLabel);
            // تصفية حسب البطولات المختارة إذا وجدت
            if (!empty($settings['fetch_leagues'])) {
                if (!in_array($f['league']['id'], $settings['fetch_leagues'])) continue;
            }
            $fetchedMatches[] = $match;
        }
    }

    if (empty($fetchedMatches)) return;

    // دمج مع المباريات اليدوية (المضافة من لوحة التحكم)
    $existingMatches = file_exists($matchesFile)
        ? (json_decode(file_get_contents($matchesFile), true) ?: [])
        : [];

    // احتفظ بالمباريات اليدوية والبيانات المضافة يدوياً على API matches
    $manualMatches = array_filter($existingMatches, fn($m) => ($m['source'] ?? 'manual') !== 'api');
    $existingApiMatches = array_filter($existingMatches, fn($m) => ($m['source'] ?? 'manual') === 'api');

    // احتفظ بـ streamUrl و channel و commentator المضافة يدوياً لمباريات الـ API
    $extraData = [];
    foreach ($existingApiMatches as $m) {
        $extraData[(string)$m['id']] = [
            'streamUrl'   => $m['streamUrl']   ?? '',
            'channel'     => $m['channel']     ?? 'غير معروف',
            'commentator' => $m['commentator'] ?? 'غير معروف',
        ];
    }

    // تطبيق البيانات اليدوية المحفوظة على المباريات الجديدة
    foreach ($fetchedMatches as &$m) {
        $id = (string)$m['id'];
        if (isset($extraData[$id])) {
            $m['streamUrl']   = $extraData[$id]['streamUrl'];
            $m['channel']     = $extraData[$id]['channel'];
            $m['commentator'] = $extraData[$id]['commentator'];
        }
    }

    $allMatches = array_merge(array_values($manualMatches), $fetchedMatches);
    file_put_contents($matchesFile, json_encode($allMatches, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    // حفظ تاريخ آخر جلب
    file_put_contents($dailyCacheF, json_encode(['date' => $today, 'time' => time(), 'count' => count($fetchedMatches)]));
}

// ========== 2. تحديث النتائج الحية (كل 15 دقيقة) ==========
function runLiveUpdate($apiKey, $liveCacheF, $matchesFile, $cacheMinutes) {
    $liveCache = file_exists($liveCacheF) ? json_decode(file_get_contents($liveCacheF), true) : [];
    $lastUpdate = $liveCache['time'] ?? 0;

    // تحقق هل حان وقت التحديث
    if ((time() - $lastUpdate) < ($cacheMinutes * 60)) return;

    $matches = file_exists($matchesFile)
        ? (json_decode(file_get_contents($matchesFile), true) ?: [])
        : [];

    if (empty($matches)) return;

    // نحدث فقط مباريات اليوم والأمس غير المنتهية (API فقط)
    $idsToUpdate = [];
    foreach ($matches as $m) {
        $day = $m['day'] ?? 'today';
        $status = $m['status'] ?? 'upcoming';
        $source = $m['source'] ?? 'manual';
        if ($source !== 'api') continue; // لا نحدث المباريات اليدوية تلقائياً
        if ($day === 'today' || ($day === 'yesterday' && $status !== 'finished')) {
            $idsToUpdate[] = $m['id'];
        }
    }

    if (empty($idsToUpdate)) {
        file_put_contents($liveCacheF, json_encode(['time' => time()]));
        return;
    }

    // جلب تحديثات من API (بحد أقصى 20 مباراة لكل طلب)
    $chunks = array_chunk($idsToUpdate, 20);
    $apiUpdates = [];
    foreach ($chunks as $chunk) {
        $result = callApi("fixtures?ids=" . implode('-', $chunk), $apiKey);
        if ($result) {
            foreach ($result as $f) {
                $apiUpdates[(string)$f['fixture']['id']] = $f;
            }
        }
    }

    if (empty($apiUpdates)) {
        file_put_contents($liveCacheF, json_encode(['time' => time()]));
        return;
    }

    // تطبيق التحديثات
    $changed = false;
    foreach ($matches as &$m) {
        $id = (string)$m['id'];
        if (!isset($apiUpdates[$id])) continue;
        $f = $apiUpdates[$id];

        $rawStatus = $f['fixture']['status']['short'] ?? 'NS';
        $statusType = 'upcoming';
        if (in_array($rawStatus, ['FT', 'AET', 'PEN'])) $statusType = 'finished';
        elseif (in_array($rawStatus, ['1H', '2H', 'HT', 'ET', 'P', 'BT', 'LIVE'])) $statusType = 'live';

        $elapsed = $f['fixture']['status']['elapsed'] ?? 0;
        $statusTextMap = [
            '1H' => 'مباشر الآن', '2H' => 'مباشر الآن', 'HT' => 'استراحة',
            'ET' => 'وقت إضافي', 'P'  => 'ركلات ترجيح', 'BT' => 'بين الأشواط',
            'FT' => 'انتهت', 'AET' => 'انتهت', 'PEN' => 'انتهت', 'LIVE' => 'مباشر الآن',
        ];
        $statusText = $statusTextMap[$rawStatus] ?? 'قادمة';
        if (in_array($rawStatus, ['1H', '2H', 'ET', 'LIVE']) && $elapsed > 0) {
            $statusText .= " {$elapsed}'";
        }

        $m['status']      = $statusType;
        $m['status_text'] = $statusText;
        $m['homeScore']   = (string)($f['goals']['home'] ?? '0');
        $m['awayScore']   = (string)($f['goals']['away'] ?? '0');
        $changed = true;
    }

    if ($changed) {
        file_put_contents($matchesFile, json_encode($matches, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    file_put_contents($liveCacheF, json_encode(['time' => time(), 'updated' => count($apiUpdates)]));
}

// ========== تشغيل نظام الـ Cache ==========
if (!empty($apiKey) && $autoFetch) {
    runDailyFetch($apiKey, $cacheDir, $dailyCacheF, $matchesFile, $settings);
    runLiveUpdate($apiKey, $liveCacheF, $matchesFile, $cacheMinutes);
}

// ========== معالجة طلبات التطبيق ==========
$action = $_GET['action'] ?? 'get_matches';

if ($action === 'get_matches') {
    $matches = readJson($matchesFile);
    smartSort($matches);
    echo json_encode($matches, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'get_news') {
    $news = readJson($newsFile);
    echo json_encode(array_reverse($news), JSON_UNESCAPED_UNICODE);
    exit;
}

// ========== طلبات لوحة التحكم ==========

// إحصائيات API
if ($action === 'api_status') {
    $dailyCache = file_exists($dailyCacheF) ? json_decode(file_get_contents($dailyCacheF), true) : [];
    $liveCache  = file_exists($liveCacheF)  ? json_decode(file_get_contents($liveCacheF), true)  : [];
    
    // جلب عدد الطلبات المتبقية من API-Football
    $remaining = null;
    if (!empty($apiKey)) {
        $ch = curl_init("https://v3.football.api-sports.io/status");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["x-apisports-key: $apiKey"],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        $statusData = json_decode($res, true);
        $remaining = $statusData['response']['requests']['limit_day'] ?? null;
        $used      = $statusData['response']['requests']['current']   ?? null;
    }

    echo json_encode([
        'last_daily_fetch'  => isset($dailyCache['time']) ? date('Y-m-d H:i:s', $dailyCache['time']) : 'لم يتم بعد',
        'last_daily_date'   => $dailyCache['date'] ?? 'لم يتم بعد',
        'fetched_count'     => $dailyCache['count'] ?? 0,
        'last_live_update'  => isset($liveCache['time']) ? date('Y-m-d H:i:s', $liveCache['time']) : 'لم يتم بعد',
        'next_update_in'    => isset($liveCache['time']) ? max(0, ($cacheMinutes * 60) - (time() - $liveCache['time'])) : 0,
        'requests_limit'    => $remaining,
        'requests_used'     => $used,
        'api_key_set'       => !empty($apiKey),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// حفظ إعدادات الـ API
if ($action === 'save_api_settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $newSettings = array_merge($settings, [
        'api_key'       => trim($input['api_key'] ?? ''),
        'auto_fetch'    => (bool)($input['auto_fetch'] ?? true),
        'cache_minutes' => max(5, (int)($input['cache_minutes'] ?? 15)),
    ]);
    file_put_contents($settingsFile, json_encode($newSettings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo json_encode(['success' => true]);
    exit;
}

// تحديث فوري (يدوي من لوحة التحكم)
if ($action === 'force_fetch') {
    if (empty($apiKey)) { echo json_encode(['error' => 'لا يوجد API Key']); exit; }
    // حذف الـ cache لإجبار الجلب من جديد
    if (file_exists($dailyCacheF)) unlink($dailyCacheF);
    if (file_exists($liveCacheF))  unlink($liveCacheF);
    runDailyFetch($apiKey, $cacheDir, $dailyCacheF, $matchesFile, $settings);
    runLiveUpdate($apiKey, $liveCacheF, $matchesFile, $cacheMinutes);
    $matches = readJson($matchesFile);
    echo json_encode(['success' => true, 'count' => count($matches)], JSON_UNESCAPED_UNICODE);
    exit;
}

// تفعيل/تعطيل المست خدم البث يدوياً لمباراة API
if ($action === 'update_match_stream' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input   = json_decode(file_get_contents('php://input'), true) ?: [];
    $matches = readJson($matchesFile);
    foreach ($matches as &$m) {
        if ((string)$m['id'] === (string)($input['id'] ?? '')) {
            $m['streamUrl']   = $input['streamUrl']   ?? $m['streamUrl'];
            $m['channel']     = $input['channel']     ?? $m['channel'];
            $m['commentator'] = $input['commentator'] ?? $m['commentator'];
            break;
        }
    }
    writeJson($matchesFile, $matches);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'Unknown action']);
