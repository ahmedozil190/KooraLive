<?php
header('Content-Type: application/json; charset=utf-8');

/**
 * المحرك الرئيسي (Engine) - النسخة النظيفة والمستقرة
 * المزود: AllSportsAPI
 */

// 1. المسارات والإعدادات
$settingsFile = __DIR__ . '/../data/api_settings.json';
$liveFile     = __DIR__ . '/../data/matches.json';       // المباريات التي تظهر في الموقع
$bankFile     = __DIR__ . '/../data/api_fixtures.json';  // بنك المباريات القادم من الـ API

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


// 3. إضافة مباراة من البنك إلى الموقع
if (isset($_GET['action']) && $_GET['action'] === 'add_from_bank') {
    $data = json_decode(file_get_contents('php://input'), true);
    $mid  = $data['id'] ?? '';
    
    $bank = json_decode(file_get_contents($bankFile), true) ?: [];
    $live = json_decode(file_get_contents($liveFile), true) ?: [];
    
    foreach ($bank as $m) {
        if ($m['id'] == $mid) {
            $m['streamUrl']   = $data['streamUrl'];
            $m['channel']     = $data['channel'];
            $m['commentator'] = $data['commentator'];
            
            // تحقق إذا كانت موجودة مسبقاً لمنع التكرار
            $exists = false;
            foreach ($live as &$lm) {
                if ($lm['id'] == $mid) {
                    $lm = $m; // تحديث
                    $exists = true; break;
                }
            }
            if (!$exists) $live[] = $m; // إضافة جديدة
            
            file_put_contents($liveFile, json_encode(array_values($live), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            echo json_encode(['success' => true]);
            exit;
        }
    }
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
        'requests_used'    => null,
        'requests_limit'   => '1,000,000'
    ]);
    exit;
}

if ($action === 'get_bank') {
    $current = file_exists($bankFile) ? json_decode(file_get_contents($bankFile), true) : [];
    
    if (empty($current)) {
        $data = fetchFromAllSports(['met' => 'Fixtures', 'from' => $today, 'to' => $today]);
        if (isset($data['result']) && is_array($data['result'])) {
            foreach ($data['result'] as $m) $current[] = formatMatchData($m);
            file_put_contents($bankFile, json_encode($current, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $settings['last_daily_date'] = $today;
            file_put_contents($settingsFile, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
    }
    
    echo json_encode($current);
    exit;
}

// 4. منطق التحديث الذكي (Polling Logic)
$needsDailyFetch = ($settings['last_daily_date'] ?? '') !== $today && $currentH >= $fHour;
$needsLiveUpdate = ($currentTime - ($settings['last_live_update'] ?? 0)) >= $cacheSec;

$responseStatus = "No update needed";

if ($needsDailyFetch) {
    $data = fetchFromAllSports(['met' => 'Fixtures', 'from' => $today, 'to' => $today]);
    if (isset($data['result']) && is_array($data['result'])) {
        $formatted = [];
        foreach ($data['result'] as $m) $formatted[] = formatMatchData($m);
        file_put_contents($bankFile, json_encode($formatted, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $settings['last_daily_date'] = $today;
        $settings['last_live_update'] = $currentTime;
        file_put_contents($settingsFile, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $responseStatus = "Daily fetch completed";
    }
} 
elseif ($needsLiveUpdate) {
    $data = fetchFromAllSports(['met' => 'Livescore']);
    $bankMatches = json_decode(@file_get_contents($bankFile), true) ?: [];
    $liveMatches = json_decode(@file_get_contents($liveFile), true) ?: [];
    
    if (isset($data['result']) && is_array($data['result'])) {
        $results = $data['result'];
        
        // تحديث البنك
        foreach ($bankMatches as &$m) {
            foreach ($results as $ld) {
                if ($m['id'] == $ld['event_key']) {
                    $m['score'] = $ld['event_final_result'];
                    $m['status'] = $ld['event_status'];
                    $m['live'] = "1";
                    break;
                }
            }
        }
        
        // تحديث المباريات الحية بالموقع
        foreach ($liveMatches as &$m) {
            foreach ($results as $ld) {
                if ($m['id'] == $ld['event_key']) {
                    $m['score'] = $ld['event_final_result'];
                    $m['status'] = $ld['event_status'];
                    $m['live'] = "1";
                    break;
                }
            }
        }
        
        file_put_contents($bankFile, json_encode($bankMatches, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        file_put_contents($liveFile, json_encode($liveMatches, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $responseStatus = "All files updated with live scores";
    }
    $settings['last_live_update'] = $currentTime;
    file_put_contents($settingsFile, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

echo json_encode(['success' => true, 'status' => $responseStatus]);
