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
        "id"            => $match['event_key'],
        "timestamp"     => strtotime($match['event_date'] . ' ' . $match['event_time']),
        "date"          => $match['event_date'],
        "time"          => $match['event_time'],
        "homeName"      => $match['event_home_team'],
        "homeId"        => $match['home_team_key'],
        "homeLogo"      => $match['home_team_logo'],
        "awayName"      => $match['event_away_team'],
        "awayId"        => $match['away_team_key'],
        "awayLogo"      => $match['away_team_logo'],
        "score"         => !empty($match['event_final_result']) ? $match['event_final_result'] : "0 - 0",
        "halftimeScore" => $match['event_halftime_result'],
        "penaltyScore"  => $match['event_penalty_result'],
        "status"        => $match['event_status'],
        "statusShort"   => ($match['event_status'] == 'Finished') ? 'FT' : (($match['event_status'] == '') ? 'NS' : $match['event_status']),
        "leagueName"    => $match['league_name'],
        "leagueId"      => $match['league_key'],
        "leagueLogo"    => $match['league_logo'],
        "country"       => $match['country_name'],
        "live"          => (strpos($match['event_status'], ':') === false && !empty($match['event_status']) && $match['event_status'] != 'Finished') ? "1" : "0",
        // حقول إضافية للموقع
        "channel"       => "",
        "commentator"   => "",
        "streamUrl"     => ""
    ];
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
