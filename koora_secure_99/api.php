<?php
header('Content-Type: application/json; charset=utf-8');

/**
 * المحرك الرئيسي (Engine) - النسخة النظيفة والمستقرة
 * المزود: AllSportsAPI
 */

// 1. المسارات والإعدادات
$settingsFile = __DIR__ . '/../data/api_settings.json';
$matchesFile  = __DIR__ . '/../data/matches.json';

if (!file_exists($settingsFile)) {
    echo json_encode(['error' => 'Settings file not found']);
    exit;
}

$settings = json_decode(file_get_contents($settingsFile), true);
$apiKey   = $settings['api_key'];
$cacheSec = $settings['cache_seconds'] ?? 60;
$fHour    = $settings['fetch_hour'] ?? 0;

$today      = date('Y-m-d');
$currentH   = (int)date('G');
$currentTime = time();


// 2. دالة جلب البيانات من AllSportsAPI
function fetchFromAllSports($params) {
    global $apiKey;
    $url = "https://apiv2.allsportsapi.com/football/?APIkey=$apiKey&" . http_build_query($params);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $res = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($res, true);
}


// 3. دالة معالجة وتنسيق البيانات (Mapping)
function formatMatchData($match) {
    return [
        "id"                  => $match['event_key'],
        "event_key"           => $match['event_key'],
        "timestamp"           => strtotime($match['event_date'] . ' ' . $match['event_time']),
        "day"                 => "today",
        "homeTeam"            => $match['event_home_team'],
        "event_home_team"     => $match['event_home_team'],
        "homeLogo"            => $match['home_team_logo'],
        "home_team_logo"      => $match['home_team_logo'],
        "awayTeam"            => $match['event_away_team'],
        "event_away_team"     => $match['event_away_team'],
        "awayLogo"            => $match['away_team_logo'],
        "away_team_logo"      => $match['away_team_logo'],
        "league"              => $match['league_name'],
        "league_name"         => $match['league_name'],
        "leagueId"            => $match['league_key'],
        "league_key"          => $match['league_key'],
        "score"               => !empty($match['event_final_result']) ? $match['event_final_result'] : "0 - 0",
        "event_final_result"  => !empty($match['event_final_result']) ? $match['event_final_result'] : "0 - 0",
        "status"              => $match['event_status'],
        "event_status"        => $match['event_status'],
        "live"                => (strpos($match['event_status'], ':') === false && !empty($match['event_status']) && $match['event_status'] != 'Finished') ? "1" : "0",
        "event_live"          => (strpos($match['event_status'], ':') === false && !empty($match['event_status']) && $match['event_status'] != 'Finished') ? "1" : "0",
        "channel"             => "",
        "commentator"         => "",
        "streamUrl"           => ""
    ];
}


// 5. معالج الأوامر (Action Handler) للوحة التحكم
$action = $_GET['action'] ?? '';

if ($action === 'api_status') {
    echo json_encode([
        'last_daily_date'  => $settings['last_daily_date'] ?? '--',
        'last_live_update' => isset($settings['last_live_update']) ? date('H:i:s', $settings['last_live_update']) : '--',
        'requests_used'    => null, // AllSportsAPI لا توفرها برمجياً
        'requests_limit'   => '1,000,000'
    ]);
    exit;
}

if ($action === 'get_bank') {
    // إرجاع محتوى matches.json للوحة التحكم
    $current = file_exists($matchesFile) ? json_decode(file_get_contents($matchesFile), true) : [];
    echo json_encode($current);
    exit;
}

// 4. منطق التحديث الذكي (Polling Logic)
$needsDailyFetch = ($settings['last_daily_date'] ?? '') !== $today && $currentH >= $fHour;
$needsLiveUpdate = ($currentTime - ($settings['last_live_update'] ?? 0)) >= $cacheSec;

$responseStatus = "No update needed";

if ($needsDailyFetch) {
    // جلب جدول مباريات اليوم بالكامل
    $data = fetchFromAllSports(['met' => 'Fixtures', 'from' => $today, 'to' => $today]);
    
    if (isset($data['result']) && is_array($data['result'])) {
        $formatted = [];
        foreach ($data['result'] as $m) {
            $formatted[] = formatMatchData($m);
        }
        file_put_contents($matchesFile, json_encode($formatted, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        
        // تحديث الإعدادات
        $settings['last_daily_date'] = $today;
        $settings['last_live_update'] = $currentTime;
        file_put_contents($settingsFile, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        
        $responseStatus = "Daily fetch completed: " . count($formatted) . " matches.";
    }
} 
elseif ($needsLiveUpdate) {
    // تحديث النتائج المباشرة فقط
    // ملاحظة: في AllSportsAPI نستخدم Live لمعرفة المباريات الجارية حالياً
    $data = fetchFromAllSports(['met' => 'Livescore']);
    
    if (isset($data['result']) && is_array($data['result'])) {
        $currentMatches = json_decode(file_get_contents($matchesFile), true) ?: [];
        $liveData = $data['result'];
        
        // تحديث البيانات الحية داخل المصفوفة الرئيسية
        foreach ($currentMatches as &$m) {
            foreach ($liveData as $ld) {
                if ($m['id'] == $ld['event_key']) {
                    $m['score'] = $ld['event_final_result'];
                    $m['status'] = $ld['event_status'];
                    $m['live'] = "1";
                    break;
                }
            }
        }
        
        file_put_contents($matchesFile, json_encode($currentMatches, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        
        $settings['last_live_update'] = $currentTime;
        file_put_contents($settingsFile, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        
        $responseStatus = "Live status updated.";
    } else {
        // إذا لم توجد مباريات مباشرة، نقوم بتحديث وقت آخر تحديث فقط لتجنب كثرة الطلبات الفارغة
        $settings['last_live_update'] = $currentTime;
        file_put_contents($settingsFile, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $responseStatus = "No live matches currently.";
    }
}

echo json_encode([
    'success' => true,
    'status'  => $responseStatus,
    'time'    => date('Y-m-d H:i:s')
]);
