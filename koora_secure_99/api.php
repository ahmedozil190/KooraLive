<?php
/**
 * KooraLive - api.php
 * نظام بنك المباريات - جلب من الـ API وتخزين منفصل للاختيار اليدوي
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');

date_default_timezone_set('Asia/Riyadh');

// رفع حدود السيرفر لمعالجة البيانات الضخمة من AllSportsAPI
ini_set('memory_limit', '512M');
set_time_limit(120);

// ========== مسارات الملفات ==========
$baseDir      = __DIR__ . '/../data/';
$matchesFile  = $baseDir . 'matches.json';
$newsFile     = $baseDir . 'news.json';
$settingsFile = $baseDir . 'api_settings.json';
$cacheDir     = $baseDir . 'api_cache/';
$dailyCacheF  = $cacheDir . 'daily_fetch.json';
$liveCacheF   = $cacheDir . 'live_update.json';
$fixturesBank = $baseDir . 'api_fixtures.json';
$arMapFile    = __DIR__ . '/../ar_map.json'; // خارج مجلد data

// ========== دوال التعريب ==========
function getArName($engName, $id = '', $type = 'league') {
    global $arMapFile;
    static $map = null;
    if ($map === null) {
        $map = file_exists($arMapFile) ? json_decode(file_get_contents($arMapFile), true) : [];
    }

    $engName = trim($engName);
    $id = (string)$id;

    // 1. إذا كان النوع "دوري"
    if ($type === 'league') {
        if (!empty($id) && isset($map['leagues'][$id])) return $map['leagues'][$id];
        if (isset($map['leagues'][$engName])) return $map['leagues'][$engName];
    }

    // 2. إذا كان النوع "فريق" (نبحث في الفرق ثم الدول لأن المنتخبات تعتبر فرقاً)
    if ($type === 'team') {
        if (!empty($id) && isset($map['teams'][$id])) return $map['teams'][$id];
        if (isset($map['teams'][$engName])) return $map['teams'][$engName];
        if (isset($map['countries'][$engName])) return $map['countries'][$engName];
    }

    // 3. إذا كان النوع "دولة" فقط
    if ($type === 'country') {
        if (isset($map['countries'][$engName])) return $map['countries'][$engName];
    }
    
    return $engName;
}

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

// ========== دوار جلب البيانات من AllSportsAPI ==========
function callApi($endpoint, $apiKey) {
    if (empty($apiKey)) return ['error' => 'No API Key'];
    
    // تعديل الرابط ليكون أكثر استقراراً مع إضافة بارامترات لضمان جودة الرد
    $url = "https://apiv2.allsportsapi.com/football/?APIkey=$apiKey&$endpoint";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) KooraLive/1.0'
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) return ['error' => "CURL Error: $curlError"];
    if ($httpCode !== 200) {
        $msg = "HTTP Error $httpCode";
        // إذا كان هناك رد من السيرفر حتى مع الخطأ، نحاول قراءته
        $temp = json_decode($response, true);
        if (isset($temp['error'])) $msg .= " - " . (is_array($temp['error']) ? json_encode($temp['error']) : $temp['error']);
        return ['error' => $msg];
    }
    
    $data = json_decode($response, true);
    if (!isset($data['result']) && isset($data['error'])) {
        return ['error' => $data['error']];
    }
    
    return ['response' => $data['result'] ?? []];
}

function mapApiMatch($f, $dayLabel) {
    // AllSportsAPI format mapping
    $status = trim($f['event_status'] ?? '');
    $isLive = ($f['event_live'] == '1');
    
    $statusType = 'upcoming';
    if ($isLive) $statusType = 'live';
    elseif (strpos($status, 'Finished') !== false || strpos($status, 'FT') !== false) $statusType = 'finished';

    $statusText = $status;
    if (empty($status) && $statusType === 'upcoming') $statusText = 'قادمة';
    if ($statusType === 'finished') $statusText = 'انتهت';
    if ($status === 'Half Time') $statusText = 'استراحة';

    // استخراج الأهداف
    $score = $f['event_final_result'] ?? '0 - 0';
    $parts = explode('-', $score);
    $hScore = trim($parts[0] ?? '0');
    $aScore = trim($parts[1] ?? '0');

    $ts = !empty($f['event_date']) ? strtotime($f['event_date'] . ' ' . ($f['event_time'] ?? '00:00')) : time();

    return [
        'id' => (string)$f['event_key'],
        'homeTeam' => getArName($f['event_home_team'] ?? '', $f['home_team_key'] ?? '', 'team'),
        'awayTeam' => getArName($f['event_away_team'] ?? '', $f['away_team_key'] ?? '', 'team'),
        'homeTeamEng' => $f['event_home_team'] ?? '',
        'awayTeamEng' => $f['event_away_team'] ?? '',
        'homeLogo' => $f['home_team_logo'] ?? '',
        'awayLogo' => $f['away_team_logo'] ?? '',
        'league' => getArName($f['league_name'] ?? '', $f['league_key'] ?? '', 'league') . ' - ' . getArName($f['country_name'] ?? '', '', 'country'),
        'leagueEng' => $f['league_name'] ?? '',
        'countryEng' => $f['country_name'] ?? '',
        'league_id' => (string)($f['league_key'] ?? ''),
        'time' => $f['event_time'] ?? '00:00',
        'timestamp' => $ts,
        'day' => $dayLabel,
        'status' => $statusType,
        'status_text' => $statusText,
        'homeScore' => $hScore,
        'awayScore' => $aScore,
        'source' => 'api'
    ];
}

// ========== 1. الجلب اليومي للبنك (مع دعم الفلترة) ==========
function runDailyFetch($apiKey, $dailyCacheF, $fixturesBank, $fetchHour) {
    if (empty($apiKey)) return;
    global $settingsFile;
    set_time_limit(300);

    $today = date('Y-m-d');
    $currentHour = (int)date('H');
    if ($currentHour < $fetchHour) return;

    $dailyCache = readJson($dailyCacheF);
    if (($dailyCache['date'] ?? '') === $today) return;

    $settings = readJson($settingsFile);
    $favLeagues = !empty($settings['fav_leagues']) ? array_map('trim', explode(',', $settings['fav_leagues'])) : [];

    $dates = [
        'yesterday' => date('Y-m-d', strtotime('-1 day')),
        'today' => $today,
        'tomorrow' => date('Y-m-d', strtotime('+1 day'))
    ];

    $fetchedMatches = [];
    foreach ($dates as $dayLabel => $dateStr) {
        // AllSportsAPI uses met=Fixtures & from=X & to=X
        $result = callApi("met=Fixtures&from=$dateStr&to=$dateStr", $apiKey);
        if (isset($result['response']) && is_array($result['response'])) {
            foreach ($result['response'] as $f) {
                if (!empty($favLeagues)) {
                    if (isset($f['league_key']) && !in_array((string)$f['league_key'], $favLeagues)) {
                        continue;
                    }
                }
                $fetchedMatches[] = mapApiMatch($f, $dayLabel);
            }
        }
    }

    if (!empty($fetchedMatches)) {
        writeJson($fixturesBank, $fetchedMatches);
        writeJson($dailyCacheF, ['date' => $today, 'time' => time(), 'count' => count($fetchedMatches)]);
    }
}

// ========== 2. تحديث النتائج الحية لمباريات الموقع والبنك ==========
function runLiveUpdate($apiKey, $liveCacheF, $matchesFile, $fixturesBank, $cacheSeconds) {
    if (empty($apiKey)) return;
    $liveCache = readJson($liveCacheF);
    if ((time() - ($liveCache['time'] ?? 0)) < $cacheSeconds) return;

    // 1. جلب المباريات المضافة في الموقع أولاً لنعرف ماذا نحتاج
    $matches = readJson($matchesFile);
    if (empty($matches)) return; 

    // 2. طلب النتائج الحية
    $apiResult = callApi("met=Livescore", $apiKey);
    $res = $apiResult['response'] ?? [];
    if (empty($res)) return;

    // 3. فلترة البيانات: نأخذ فقط ما نحتاجه (IDs والأهداف) لتوفير الذاكرة
    $apiUpdates = [];
    foreach ($res as $f) {
        $id = (string)$f['event_key'];
        $apiUpdates[$id] = [
            'score' => $f['event_final_result'] ?? '0 - 0',
            'status' => trim($f['event_status'] ?? ''),
            'live' => ($f['event_live'] == '1')
        ];
    }
    unset($res); // مسح المصفوفة الضخمة الأصلية فوراً لتحرير الذاكرة

    // 4. تحديث مباريات الموقع
    $twoDaysAgo = strtotime('-2 days');
    $matchesUpdated = false;

    foreach ($matches as &$m) {
        if (($m['timestamp'] ?? 0) < $twoDaysAgo) continue;

        if (isset($apiUpdates[$m['id']])) {
            $update = $apiUpdates[$m['id']];
            $scoreParts = explode('-', $update['score']);
            
            $newH = trim($scoreParts[0] ?? '0');
            $newA = trim($scoreParts[1] ?? '0');
            $newStatus = $update['status'];
            
            if ($m['homeScore'] != $newH || $m['awayScore'] != $newA || $m['status_text'] != $newStatus) {
                $m['homeScore'] = $newH;
                $m['awayScore'] = $newA;
                $m['status_text'] = $newStatus ?: 'مباشر';
                if ($update['live']) $m['status'] = 'live';
                elseif (strpos($newStatus, 'Finished') !== false) $m['status'] = 'finished';
                $matchesUpdated = true;
            }
        }
    }
    
    if ($matchesUpdated) writeJson($matchesFile, $matches);
    writeJson($liveCacheF, ['time' => time(), 'updated' => count($apiUpdates)]);
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

// لوحة التحكم - حفظ الإعدادات (تحديث ذكي)
if ($action === 'save_api_settings') {
    $inp = json_decode(file_get_contents('php://input'), true);
    if (isset($inp['api_key']))    $settings['api_key'] = trim($inp['api_key']);
    if (isset($inp['auto_fetch'])) $settings['auto_fetch'] = (bool)$inp['auto_fetch'];
    if (isset($inp['cache_seconds'])) $settings['cache_seconds'] = max(5, intval($inp['cache_seconds']));
    if (isset($inp['fetch_hour']))    $settings['fetch_hour'] = max(0, min(23, intval($inp['fetch_hour'])));
    if (isset($inp['fav_leagues']))   $settings['fav_leagues'] = $inp['fav_leagues'];
    writeJson($settingsFile, $settings);
    echo json_encode(['success' => true]); exit;
}

// إضافة بطولة جديدة لملف التعريب
if ($action === 'add_league') {
    $inp = json_decode(file_get_contents('php://input'), true);
    $id = trim($inp['id'] ?? '');
    $name = trim($inp['name'] ?? '');
    if (empty($id) || empty($name)) {
        echo json_encode(['success' => false, 'error' => 'البيانات ناقصة']); exit;
    }
    $map = readJson($arMapFile);
    if (!isset($map['leagues'])) $map['leagues'] = [];
    $map['leagues'][$id] = $name;
    writeJson($arMapFile, $map);
    echo json_encode(['success' => true]); exit;
}

// حذف بطولة من ملف التعريب
if ($action === 'delete_league') {
    $inp = json_decode(file_get_contents('php://input'), true);
    $id = trim($inp['id'] ?? '');
    if (empty($id)) {
        echo json_encode(['success' => false, 'error' => 'الرقم غير موجود']); exit;
    }
    $map = readJson($arMapFile);
    if (isset($map['leagues'][$id])) {
        unset($map['leagues'][$id]);
        writeJson($arMapFile, $map);
        echo json_encode(['success' => true]); exit;
    }
    echo json_encode(['success' => false, 'error' => 'البطولة غير موجودة']); exit;
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

// لوحة التحكم - جلب قائمة البنك للاختيار (مع دعم الفلترة والترجمة اللحظية)
if ($action === 'get_bank') {
    $bank = readJson($fixturesBank);
    $site = readJson($matchesFile);
    $settings = readJson($settingsFile);
    $favLeagues = !empty($settings['fav_leagues']) ? array_map('trim', explode(',', $settings['fav_leagues'])) : [];
    
    $siteIds = array_column($site, 'id');
    $res = [];
    foreach ($bank as $m) {
        if (in_array($m['id'], $siteIds)) continue;
        
        // إعادة الترجمة لحظياً بناءً على آخر التعديلات في ar_map.json
        if (isset($m['leagueEng'])) {
            $m['league'] = getArName($m['leagueEng'], $m['league_id'] ?? '', 'league') . ' - ' . getArName($m['countryEng'] ?? '', '', 'country');
        }
        if (isset($m['homeTeamEng'])) $m['homeTeam'] = getArName($m['homeTeamEng'], $m['homeID'] ?? '', 'team');
        if (isset($m['awayTeamEng'])) $m['awayTeam'] = getArName($m['awayTeamEng'], $m['awayID'] ?? '', 'team');

        // تطبيق الفلترة
        if (!empty($favLeagues)) {
            $mLeagueId = $m['league_id'] ?? '';
            if (!empty($mLeagueId) && !in_array($mLeagueId, $favLeagues)) {
                continue;
            }
        }
        $res[] = $m;
    }
    echo json_encode($res, JSON_UNESCAPED_UNICODE); exit;
}

// لوحة التحكم - إضافة مباراة من البنك للموقع
if ($action === 'add_from_bank') {
    $inp = json_decode(file_get_contents('php://input'), true);
    $id = (string)$inp['id'];
    $bank = readJson($fixturesBank);
    $site = readJson($matchesFile);
    $arMap = readJson($arMapFile);
    $mapUpdated = false;

    // معالجة الترجمات المرسلة وحفظها في ar_map
    if (!empty($inp['translations'])) {
        foreach ($inp['translations'] as $type => $data) {
            if (!empty($data['ar'])) {
                $section = ($type === 'league') ? 'leagues' : 'teams';
                // بالنسبة للفرق نستخدم الاسم الإنجليزي كمفتاح، وبالنسبة للبطولات نستخدم الـ ID
                $key = ($section === 'leagues') ? (string)$data['id'] : (string)$data['eng'];
                if (!empty($key)) {
                    $arMap[$section][$key] = trim($data['ar']);
                    $mapUpdated = true;
                }
            }
        }
    }
    if ($mapUpdated) writeJson($arMapFile, $arMap);

    foreach ($bank as $bm) {
        if ($bm['id'] === $id) {
            // تحديث الأسماء العربية فوراً للمباراة المضافة
            if (!empty($inp['translations']['home']['ar']))   $bm['homeTeam'] = trim($inp['translations']['home']['ar']);
            if (!empty($inp['translations']['away']['ar']))   $bm['awayTeam'] = trim($inp['translations']['away']['ar']);
            if (!empty($inp['translations']['league']['ar'])) $bm['league']   = trim($inp['translations']['league']['ar']);

            $bm['streamUrl'] = $inp['streamUrl'] ?? '';
            $bm['channel'] = $inp['channel'] ?? 'غير معروف';
            $bm['commentator'] = $inp['commentator'] ?? 'غير معروف';
            $site[] = $bm;
            break;
        }
    }
    writeJson($matchesFile, $site);
    echo json_encode(['success' => true]); exit;
}
