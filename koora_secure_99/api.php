<?php
header('Content-Type: application/json; charset=utf-8');

/**
 * المحرك الرئيسي (Engine) - نسخة AllSportsAPI
 * تم التحديث للربط مع apiv2.allsportsapi.com
 */

// 1. المسارات والإعدادات
$settingsFile = __DIR__ . '/../data/api_settings.json';
$liveFile     = __DIR__ . '/../data/matches.json';
$bankFile     = __DIR__ . '/../data/api_fixtures.json';

if (!file_exists($settingsFile)) {
    echo json_encode(['error' => 'يرجى وضع مفتاح AllSportsAPI أولاً في صفحة الإعدادات']);
    exit;
}

$settings = json_decode(file_get_contents($settingsFile), true);
$apiKey   = $settings['api_key'] ?? '';
$cacheSec = $settings['cache_seconds'] ?? 60;
$fHour    = $settings['fetch_hour'] ?? 0;

$today      = date('Y-m-d');
$currentH   = (int)date('G');
$currentTime = time();

// 2. دالة جلب البيانات من AllSportsAPI
function fetchFromAllSports($met, $params = []) {
    global $apiKey;
    $params['met'] = $met;
    $params['APIkey'] = $apiKey;
    
    $url = "https://apiv2.allsportsapi.com/football/?" . http_build_query($params);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    
    $res = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($res, true);
}

// 3. إضافة مباراة من البنك إلى الموقع
if (isset($_GET['action']) && $_GET['action'] === 'add_from_bank') {
    $data = json_decode(file_get_contents('php://input'), true);
    $mid  = $data['id'] ?? '';
    
    $bank = json_decode(@file_get_contents($bankFile), true) ?: [];
    $live = json_decode(@file_get_contents($liveFile), true) ?: [];
    
    foreach ($bank as $m) {
        if ($m['id'] == $mid) {
            $m['streamUrl']   = $data['streamUrl'];
            $m['channel']     = $data['channel'];
            $m['commentator'] = $data['commentator'];
            
            $exists = false;
            foreach ($live as &$lm) {
                if ($lm['id'] == $mid) {
                    $lm = $m; 
                    $exists = true; break;
                }
            }
            if (!$exists) $live[] = $m;
            
            if(!is_dir(dirname($liveFile))) mkdir(dirname($liveFile), 0777, true);
            file_put_contents($liveFile, json_encode(array_values($live), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            echo json_encode(['success' => true]);
            exit;
        }
    }
}

// 4. دالة تنسيق البيانات (Mapping) لتناسب AllSportsAPI مع نظام الترجمة
function formatMatchData($m) {
    static $arMap = null;
    if ($arMap === null) {
        $mapFile = __DIR__ . '/../ar_map.json';
        $arMap = file_exists($mapFile) ? json_decode(file_get_contents($mapFile), true) : [];
    }

    // دالة ترجمة ذكية: تبحث بالـ ID أولاً، ثم بالاسم الإنجليزي كاحتياط
    $translate = function($type, $key, $default) use ($arMap) {
        $strKey = (string)$key;
        $strDef = (string)$default;
        // 1. البحث بالرقم
        if (isset($arMap[$type][$strKey])) return $arMap[$type][$strKey];
        // 2. البحث بالاسم (لو فشل الرقم)
        if (isset($arMap[$type][$strDef])) return $arMap[$type][$strDef];
        
        return $default;
    };

    $lNameRaw = $m['league_name'];
    $extRound = $m['event_league_round'] ?? '';
    
    // محرك استخراج الجولة من الاسم (مثال: World Cup - 1/16-finals)
    if (strpos($lNameRaw, ' - ') !== false) {
        $parts = explode(' - ', $lNameRaw, 2);
        $lNameRaw = trim($parts[0]);
        if (empty($extRound)) $extRound = trim($parts[1]);
    }

    $lName = $lNameRaw;
    $hName = $m['event_home_team'];
    $aName = $m['event_away_team'];
    $hId   = $m['home_team_key'];
    $aId   = $m['away_team_key'];
    $lId   = $m['league_key'];
    $countryId   = $m['country_key'] ?? '';
    $countryName = $m['country_name'] ?? '';

    // ترجمة الأسماء
    // 1. نحاول ترجمة الفريق بالـ ID
    // 2. إذا فشل، نحاول ترجمة اسم الفريق نفسه (hName) من قائمة الدول (للمنتخبات)
    // 3. إذا فشل، نحاول ترجمة ID الدولة (countryId)
    $translatedHomeName   = $translate('teams', $hId, $translate('countries', $hName, $translate('countries', $countryId, $hName)));
    $translatedAwayName   = $translate('teams', $aId, $translate('countries', $aName, $translate('countries', $countryId, $aName)));
    $translatedLeagueName = $translate('leagues', $lId, $translate('leagues', $lName, $lName));
    $translatedRound      = $translate('rounds', $extRound, $extRound);

    // معالجة الحالة
    $statusRaw = $m['event_status']; // AllSports يرسل مثل FT, Live, أو وقت المباراة
    $statusMapAr = [
        'Finished' => 'انتهت', 'FT' => 'انتهت', 'After ET' => 'انتهت (إضافي)', 'After Pen.' => 'انتهت (ركلات)',
        'Half Time' => 'استراحة', 'HT' => 'استراحة', 'Postponed' => 'مؤجلة', 'Cancelled' => 'ملغاة',
        'Abandoned' => 'متوقفة', 'LIVE' => 'مباشر', '1st Half' => 'الشوط الأول', '2nd Half' => 'الشوط الثاني'
    ];
    
    // تحديد الحالة العامة (live/finished/upcoming) لـ Dashboard
    $liveStatus = 'upcoming';
    if (in_array($statusRaw, ['LIVE', '1st Half', '2nd Half', 'HT', 'Half Time'])) $liveStatus = 'live';
    elseif (in_array($statusRaw, ['FT', 'Finished', 'After ET', 'After Pen.'])) $liveStatus = 'finished';

    $statusAr = $statusMapAr[$statusRaw] ?? ($arMap['status'][$statusRaw] ?? $statusRaw);

    // تحويل الوقت لـ Timestamp وتحديد اليوم
    $evDate = $m['event_date'];
    $timestamp = strtotime($evDate . ' ' . $m['event_time']);
    
    $dayTag = 'today';
    $serverToday = date('Y-m-d');
    $serverYest  = date('Y-m-d', strtotime('-1 day'));
    $serverTom   = date('Y-m-d', strtotime('+1 day'));

    if ($evDate === $serverYest) $dayTag = 'yesterday';
    elseif ($evDate === $serverTom) $dayTag = 'tomorrow';
    elseif ($evDate !== $serverToday) $dayTag = 'other'; // لو تاريخ بعيد

    return [
        "id"                  => $m['event_key'],
        "event_key"           => $m['event_key'],
        "timestamp"           => $timestamp,
        "day"                 => $dayTag,
        "homeTeam"            => $translatedHomeName,
        "event_home_team"     => $translatedHomeName,
        "homeLogo"            => $m['home_team_logo'] ?? '',
        "home_team_logo"      => $m['home_team_logo'] ?? '',
        "awayTeam"            => $translatedAwayName,
        "event_away_team"     => $translatedAwayName,
        "awayLogo"            => $m['away_team_logo'] ?? '',
        "away_team_logo"      => $m['away_team_logo'] ?? '',
        "league"              => $translatedLeagueName,
        "league_name"         => $translatedLeagueName,
        "leagueId"            => $lId,
        "league_key"          => $lId,
        "round"               => $translatedRound,
        "score"               => $m['event_final_result'] ?: "0 - 0",
        "event_final_result"  => $m['event_final_result'] ?: "0 - 0",
        "status"              => $liveStatus,
        "status_ar"           => $statusAr,
        "event_status"        => $statusRaw,
        "live"                => ($liveStatus === 'live' ? "1" : "0"),
        "channel"             => "",
        "commentator"         => "",
        "streamUrl"           => ""
    ];
}

// 5. معالج الأوامر (Action Handler)
$action = $_GET['action'] ?? '';

if ($action === 'api_status') {
    // AllSportsAPI لا يرسل حقول الـ Limit في الهيدر، لذا سنرسل معلومات تقريبية
    echo json_encode([
        'last_daily_date'  => $settings['last_daily_date'] ?? '--',
        'last_live_update' => isset($settings['last_live_update']) ? date('H:i:s', $settings['last_live_update']) : '--',
        'requests_used'    => 'متوفر',
        'requests_limit'   => 'AllSports'
    ]);
    exit;
}

if ($action === 'get_bank') {
    $current = file_exists($bankFile) ? json_decode(file_get_contents($bankFile), true) : [];
    
    if (empty($current) || (isset($_GET['force']) && $_GET['force'] === '1')) {
        $from = date('Y-m-d', strtotime('-1 day'));
        $to   = date('Y-m-d', strtotime('+1 day'));
        $res  = fetchFromAllSports('Fixtures', ['from' => $from, 'to' => $to]);
        
        if (isset($res['result']) && is_array($res['result'])) {
            $current = [];
            foreach ($res['result'] as $m) $current[] = formatMatchData($m);
            if(!is_dir(dirname($bankFile))) mkdir(dirname($bankFile), 0777, true);
            file_put_contents($bankFile, json_encode($current, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $settings['last_daily_date'] = $today;
            file_put_contents($settingsFile, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            echo json_encode(['error' => 'AllSportsAPI: لم نجد أي مباريات لليوم']);
            exit;
        }
    }
    
    echo json_encode($current);
    exit;
}

// 6. منطق التحديث الذكي (Polling Logic - اختياري لو طلبت الصفحة الرئيسية تحديث)
$needsDailyFetch = ($settings['last_daily_date'] ?? '') !== $today && $currentH >= $fHour;
$needsLiveUpdate = ($currentTime - ($settings['last_live_update'] ?? 0)) >= $cacheSec;

$responseStatus = "No update needed";

if ($needsDailyFetch) {
    $res = fetchFromAllSports('Fixtures', ['from' => $today, 'to' => $today]);
    if (isset($res['result']) && is_array($res['result'])) {
        $formatted = [];
        foreach ($res['result'] as $m) $formatted[] = formatMatchData($m);
        file_put_contents($bankFile, json_encode($formatted, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $settings['last_daily_date'] = $today;
        $settings['last_live_update'] = $currentTime;
        file_put_contents($settingsFile, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $responseStatus = "Daily fetch completed (AllSports)";
    }
} 
elseif ($needsLiveUpdate) {
    $res = fetchFromAllSports('Livescore');
    $bankMatches = json_decode(@file_get_contents($bankFile), true) ?: [];
    $liveMatches = json_decode(@file_get_contents($liveFile), true) ?: [];
    
    if (isset($res['result']) && is_array($res['result'])) {
        $updates = $res['result'];
        foreach ($updates as $ld) {
            $mid = $ld['event_key'];
            $newScore = $ld['event_final_result'] ?: "0 - 0";
            $newStat = $ld['event_status'];
            
            // تحديث البنك والمباريات المضافة
            foreach ([$bankMatches, $liveMatches] as &$list) {
                foreach ($list as &$bm) {
                    if ($bm['id'] == $mid) {
                        $bm['score'] = $newScore;
                        $bm['status_ar'] = $newStat; // سيتم ترجمتها في اللدورة القادمة أو تُترك كما هي
                        $bm['status'] = (in_array($newStat, ['FT', 'Finished']) ? 'finished' : 'live');
                        break;
                    }
                }
            }
        }
        file_put_contents($bankFile, json_encode($bankMatches, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        file_put_contents($liveFile, json_encode($liveMatches, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $responseStatus = "Live scores updated (AllSports)";
    }
    $settings['last_live_update'] = $currentTime;
    file_put_contents($settingsFile, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

echo json_encode(['success' => true, 'status' => $responseStatus]);
