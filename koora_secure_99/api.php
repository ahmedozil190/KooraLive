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
    if (empty($apiKey)) return ['error' => 'No API Key'];
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
    
    if ($httpCode !== 200) return ['error' => "HTTP Error $httpCode"];
    
    $data = json_decode($response, true);
    if (!empty($data['errors'])) {
        // جمع كل الأخطاء في رسالة واحدة
        $errStr = is_array($data['errors']) ? implode(', ', array_map(function($k, $v) { return "$k: $v"; }, array_keys($data['errors']), $data['errors'])) : 'Unknown API Error';
        return ['error' => $errStr];
    }
    
    return ['response' => $data['response'] ?? []];
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
    set_time_limit(300); // 5 دقائق مهلة
    ini_set('memory_limit', '256M');

    $today = date('Y-m-d');
    $currentHour = (int)date('H');
    if ($currentHour < $fetchHour) return;

    $dailyCache = readJson($dailyCacheF);
    if (($dailyCache['date'] ?? '') === $today) return;

    $dates = [
        'yesterday' => date('Y-m-d', strtotime('-1 day')),
        'today' => $today,
        'tomorrow' => date('Y-m-d', strtotime('+1 day'))
    ];

    $fetchedMatches = [];
    foreach ($dates as $dayLabel => $dateStr) {
        $result = callApi("fixtures?date=$dateStr&timezone=Asia/Riyadh", $apiKey);
        if (isset($result['response']) && is_array($result['response'])) {
            foreach ($result['response'] as $f) {
                $fetchedMatches[] = mapApiMatch($f, $dayLabel);
            }
        }
    }

    if (!empty($fetchedMatches)) {
        writeJson($fixturesBank, $fetchedMatches);
        writeJson($dailyCacheF, ['date' => $today, 'time' => time(), 'count' => count($fetchedMatches)]);
    }
}

// ========== 2. تحديث النتائج الحية لمباريات الموقع ==========
// ========== 2. تحديث النتائج الحية لمباريات الموقع والبنك (جلب اليوم كاملاً) ==========
function runLiveUpdate($apiKey, $liveCacheF, $matchesFile, $fixturesBank, $cacheSeconds) {
    if (empty($apiKey)) return;
    $liveCache = readJson($liveCacheF);
    if ((time() - ($liveCache['time'] ?? 0)) < $cacheSeconds) return;

    // جلب كل مباريات اليوم بطلب واحد (لتوفير الطلبات ودعم الخطة المجانية)
    $todayStr = date('Y-m-d');
    $apiResult = callApi("fixtures?date=$todayStr&timezone=Asia/Riyadh", $apiKey);
    $res = $apiResult['response'] ?? [];

    if (!empty($res)) {
        $apiUpdates = [];
        foreach ($res as $f) $apiUpdates[(string)$f['fixture']['id']] = $f;

        $todayStr     = date('Y-m-d');
        $yesterdayStr = date('Y-m-d', strtotime('-1 day'));
        $tomorrowStr  = date('Y-m-d', strtotime('+1 day'));

        // 1. تحديث مباريات الموقع (matches.json)
        $matches = readJson($matchesFile);
        $twoDaysAgo = strtotime('-2 days');
        
        // تنظيف المباريات القديمة
        $matches = array_values(array_filter($matches, function($m) use ($twoDaysAgo) {
            if (empty($m['timestamp'])) return true;
            return (int)$m['timestamp'] > $twoDaysAgo;
        }));

        foreach ($matches as &$m) {
            // تحديث حقل day
            if (!empty($m['timestamp'])) {
                $matchDate = date('Y-m-d', $m['timestamp']);
                $matchStatus = $m['status'] ?? 'upcoming';
                if ($matchDate === $tomorrowStr) $m['day'] = 'tomorrow';
                elseif ($matchDate === $todayStr) $m['day'] = 'today';
                elseif ($matchDate === $yesterdayStr) {
                    $m['day'] = ($matchStatus === 'finished') ? 'yesterday' : 'today';
                }
            }

            // تحديث البيانات الحية إذا وجدت في نتائج اليوم
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

        // 2. تحديث البنك (fixturesBank) لضمان بيانات حية عند الإضافة اليدوية
        $bank = readJson($fixturesBank);
        $bankUpdated = false;
        foreach ($bank as &$bm) {
            if (isset($apiUpdates[$bm['id']])) {
                $f = $apiUpdates[$bm['id']];
                $bm['homeScore'] = (string)($f['goals']['home'] ?? '0');
                $bm['awayScore'] = (string)($f['goals']['away'] ?? '0');
                $rawStatus = $f['fixture']['status']['short'] ?? 'NS';
                if (in_array($rawStatus, ['FT', 'AET', 'PEN'])) $bm['status'] = 'finished';
                elseif (in_array($rawStatus, ['1H', '2H', 'HT', 'ET', 'P', 'LIVE'])) $bm['status'] = 'live';
                else $bm['status'] = 'upcoming';
                $bankUpdated = true;
            }
        }
        if ($bankUpdated) writeJson($fixturesBank, $bank);
    }
    writeJson($liveCacheF, ['time' => time(), 'updated' => count($res)]);
}

if ($autoFetch) {
    runDailyFetch($apiKey, $dailyCacheF, $fixturesBank, $fetchHour);
    runLiveUpdate($apiKey, $liveCacheF, $matchesFile, $fixturesBank, (int)($settings['cache_seconds'] ?? 900));
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
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ["x-apisports-key: $apiKey"], CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => 10]);
        $res = json_decode(curl_exec($ch), true); curl_close($ch);
        $rem = $res['response']['requests']['limit_day'] ?? 100;
        $usd = $res['response']['requests']['current'] ?? 0;
    }
    // عرض الوقت بنظام 12 ساعة
    $lastLiveTime = isset($l['time']) ? date('h:i A', $l['time']) : '--';
    echo json_encode([
        'last_daily_date'  => $d['date'] ?? '--',
        'last_live_update' => $lastLiveTime,
        'requests_used'    => $usd,
        'requests_limit'   => $rem,
        'api_key_set'      => !empty($apiKey)
    ], JSON_UNESCAPED_UNICODE); exit;
}

// لوحة التحكم - حفظ الإعدادات
if ($action === 'save_api_settings') {
    $inp = json_decode(file_get_contents('php://input'), true);
    // الإبقاء على المفتاح القديم إذا لم يُدخل المستخدم مفتاحاً جديداً
    $newKey = trim($inp['api_key'] ?? '');
    if (!empty($newKey)) {
        $settings['api_key'] = $newKey;
    }
    // تحديث باقي الإعدادات
    $settings['cache_seconds'] = max(5, intval($inp['cache_seconds'] ?? 900));
    $settings['fetch_hour'] = max(0, min(23, intval($inp['fetch_hour'] ?? 0)));
    $settings['auto_fetch'] = $inp['auto_fetch'] ?? true;
    writeJson($settingsFile, $settings);
    echo json_encode(['success' => true]); exit;
}

// لوحة التحكم - جلب فوري للبنك (مع تجاوز مهلة الـ Proxy)
if ($action === 'force_fetch') {
    if (empty($apiKey)) {
        echo json_encode(['success' => false, 'error' => 'مفتاح API غير موجود']); exit;
    }
    // إرسال استجابة فورية للمتصفح قبل بدء الجلب الذي قد يطول
    echo json_encode(['success' => true, 'message' => 'جاري الجلب في الخلفية...']);
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request(); // إغلاق الاتصال مع المتصفح
    } else {
        @ob_flush(); flush();
    }
    ignore_user_abort(true);
    set_time_limit(300);
    // حذف كاش اليوم لإجبار إعادة الجلب
    if (file_exists($dailyCacheF)) unlink($dailyCacheF);
    $fetchHour = (int)($settings['fetch_hour'] ?? 0);
    runDailyFetch($apiKey, $dailyCacheF, $fixturesBank, $fetchHour);
    exit;
}

// تحديث النتائج الحية يدوياً من اللوحة
if ($action === 'trigger_live_update') {
    if (empty($apiKey)) {
        echo json_encode(['success' => false, 'error' => 'مفتاح API غير موجود']); exit;
    }

    $allMatches = readJson($matchesFile);
    $totalMatches = count($allMatches);
    $idsToUpdate = [];
    foreach ($allMatches as $m) {
        $notFinished = ($m['status'] ?? '') !== 'finished';
        $hasId = !empty($m['id']) && (is_numeric($m['id']) || preg_match('/^[0-9]+$/', $m['id']));
        if ($notFinished && $hasId) $idsToUpdate[] = $m['id'];
    }

    $updatedMatchesCount = 0;
    $errorMessage = null;

    if (!empty($idsToUpdate)) {
        $chunks = array_chunk($idsToUpdate, 20);
        foreach ($chunks as $chunk) {
            $apiResult = callApi("fixtures?ids=" . implode('-', $chunk), $apiKey);
            
            if (isset($apiResult['error'])) {
                $errorMessage = $apiResult['error'];
                break;
            }

            $res = $apiResult['response'] ?? [];
            foreach ($res as $f) {
                $apiId = (string)$f['fixture']['id'];
                foreach ($allMatches as &$m) {
                    if ((string)$m['id'] === $apiId) {
                        $m['homeScore'] = (string)($f['goals']['home'] ?? '0');
                        $m['awayScore'] = (string)($f['goals']['away'] ?? '0');
                        if (isset($f['fixture']['timestamp'])) $m['timestamp'] = $f['fixture']['timestamp'];
                        $rawStatus = $f['fixture']['status']['short'] ?? 'NS';
                        if (in_array($rawStatus, ['FT', 'AET', 'PEN'])) $m['status'] = 'finished';
                        elseif (in_array($rawStatus, ['1H', '2H', 'HT', 'ET', 'P', 'LIVE'])) $m['status'] = 'live';
                        else $m['status'] = 'upcoming';
                        $updatedMatchesCount++;
                    }
                }
            }
        }
        if ($updatedMatchesCount > 0) {
            writeJson($matchesFile, array_values($allMatches));
        }
        writeJson($liveCacheF, ['time' => time(), 'updated' => $updatedMatchesCount]);
    }

    if ($errorMessage) {
        echo json_encode(['success' => false, 'error' => "خطأ من الـ API: $errorMessage"]);
    } else {
        echo json_encode([
            'success'       => true,
            'updated'       => $updatedMatchesCount,
            'ids_sent'      => count($idsToUpdate),
            'total_in_file' => $totalMatches,
            'time'          => date('h:i A', time())
        ]);
    }
    exit;
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
