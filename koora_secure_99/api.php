<?php
header('Content-Type: application/json; charset=utf-8');

/**
 * المحرك الرئيسي (Engine) - نسخة API-Football v3
 * تم التحديث للربط مع dashboard.api-football.com
 */

// 1. المسارات والإعدادات
$settingsFile = __DIR__ . '/../data/api_settings.json';
$liveFile     = __DIR__ . '/../data/matches.json';
$bankFile     = __DIR__ . '/../data/api_fixtures.json';

if (!file_exists($settingsFile)) {
    echo json_encode(['error' => 'Settings file not found']);
    exit;
}

$settings = json_decode(file_get_contents($settingsFile), true);
$apiKey   = $settings['api_key'] ?? '';
$cacheSec = $settings['cache_seconds'] ?? 60;
$fHour    = $settings['fetch_hour'] ?? 0;

$today      = date('Y-m-d');
$currentH   = (int)date('G');
$currentTime = time();


// 2. دالة جلب البيانات من API-Football v3
function fetchFromApiFootball($endpoint, $params = []) {
    global $apiKey;
    $url = "https://v3.football.api-sports.io/" . $endpoint . "?" . http_build_query($params);
    
    $ch = curl_init();
    curl_setopt($ch, $url, $url); // This line had a typo in target, corrected below
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "x-apisports-key: $apiKey",
        "Content-Type: application/json"
    ]);
    
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


// 4. دالة تنسيق البيانات (Mapping) لتناسب API-Football
function formatMatchData($match) {
    $f = $match['fixture'];
    $l = $match['league'];
    $t = $match['teams'];
    $g = $match['goals'];
    
    $homeScore = ($g['home'] !== null) ? $g['home'] : 0;
    $awayScore = ($g['away'] !== null) ? $g['away'] : 0;
    
    $statusShort = $f['status']['short'];
    // تحويل الحالات لتناسب لوحة التحكم
    $liveStatus = in_array($statusShort, ['1H', 'HT', '2H', 'ET', 'P', 'LIVE']) ? "1" : "0";
    
    return [
        "id"                  => $f['id'],
        "event_key"           => $f['id'],
        "timestamp"           => $f['timestamp'],
        "day"                 => "today",
        "homeTeam"            => $t['home']['name'],
        "event_home_team"     => $t['home']['name'],
        "homeLogo"            => $t['home']['logo'],
        "home_team_logo"      => $t['home']['logo'],
        "awayTeam"            => $t['away']['name'],
        "event_away_team"     => $t['away']['name'],
        "awayLogo"            => $t['away']['logo'],
        "away_team_logo"      => $t['away']['logo'],
        "league"              => $l['name'],
        "league_name"         => $l['name'],
        "leagueId"            => $l['id'],
        "league_key"          => $l['id'],
        "score"               => "$homeScore - $awayScore",
        "event_final_result"  => "$homeScore - $awayScore",
        "status"              => $statusShort,
        "event_status"        => $statusShort,
        "live"                => $liveStatus,
        "event_live"          => $liveStatus,
        "channel"             => "",
        "commentator"         => "",
        "streamUrl"           => ""
    ];
}


// 5. معالج الأوامر (Action Handler)
$action = $_GET['action'] ?? '';

if ($action === 'api_status') {
    echo json_encode([
        'last_daily_date'  => $settings['last_daily_date'] ?? '--',
        'last_live_update' => isset($settings['last_live_update']) ? date('H:i:s', $settings['last_live_update']) : '--',
        'requests_used'    => null,
        'requests_limit'   => 'Unlimited'
    ]);
    exit;
}

if ($action === 'get_bank') {
    $current = file_exists($bankFile) ? json_decode(file_get_contents($bankFile), true) : [];
    
    if (empty($current)) {
        $res = fetchFromApiFootball('fixtures', ['date' => $today]);
        if (isset($res['response']) && is_array($res['response'])) {
            foreach ($res['response'] as $m) $current[] = formatMatchData($m);
            if(!is_dir(dirname($bankFile))) mkdir(dirname($bankFile), 0777, true);
            file_put_contents($bankFile, json_encode($current, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $settings['last_daily_date'] = $today;
            file_put_contents($settingsFile, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
    }
    
    echo json_encode($current);
    exit;
}

// 6. منطق التحديث الذكي (Polling Logic)
$needsDailyFetch = ($settings['last_daily_date'] ?? '') !== $today && $currentH >= $fHour;
$needsLiveUpdate = ($currentTime - ($settings['last_live_update'] ?? 0)) >= $cacheSec;

$responseStatus = "No update needed";

if ($needsDailyFetch) {
    $res = fetchFromApiFootball('fixtures', ['date' => $today]);
    if (isset($res['response']) && is_array($res['response'])) {
        $formatted = [];
        foreach ($res['response'] as $m) $formatted[] = formatMatchData($m);
        if(!is_dir(dirname($bankFile))) mkdir(dirname($bankFile), 0777, true);
        file_put_contents($bankFile, json_encode($formatted, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $settings['last_daily_date'] = $today;
        $settings['last_live_update'] = $currentTime;
        file_put_contents($settingsFile, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $responseStatus = "Daily fetch completed";
    }
} 
elseif ($needsLiveUpdate) {
    $res = fetchFromApiFootball('fixtures', ['live' => 'all']);
    $bankMatches = json_decode(@file_get_contents($bankFile), true) ?: [];
    $liveMatches = json_decode(@file_get_contents($liveFile), true) ?: [];
    
    if (isset($res['response']) && is_array($res['response'])) {
        $updates = $res['response'];
        
        // تحديث البنك والمباريات الحية
        foreach ($updates as $ld) {
            $mid = $ld['fixture']['id'];
            $newScore = $ld['goals']['home'] . " - " . $ld['goals']['away'];
            $newStat = $ld['fixture']['status']['short'];
            
            foreach ($bankMatches as &$bm) {
                if ($bm['id'] == $mid) {
                    $bm['score'] = $newScore;
                    $bm['status'] = $newStat;
                    $bm['live'] = "1";
                    break;
                }
            }
            foreach ($liveMatches as &$lm) {
                if ($lm['id'] == $mid) {
                    $lm['score'] = $newScore;
                    $lm['status'] = $newStat;
                    $lm['live'] = "1";
                    break;
                }
            }
        }
        
        file_put_contents($bankFile, json_encode($bankMatches, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        file_put_contents($liveFile, json_encode($liveMatches, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $responseStatus = "Live scores updated";
    }
    $settings['last_live_update'] = $currentTime;
    file_put_contents($settingsFile, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

echo json_encode(['success' => true, 'status' => $responseStatus]);
