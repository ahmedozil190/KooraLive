<?php
/**
 * KooraLive Cron Sync System
 * يقوم بجلب بيانات 3 أيام وتحديث النتائج والبنك تلقائياً
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. الإعدادات والمسارات
$settingsFile = __DIR__ . '/../data/api_settings.json';
$matchesFile  = __DIR__ . '/../data/matches.json';
$bankFile     = __DIR__ . '/../data/api_fixtures.json';
$arMapFile    = __DIR__ . '/../ar_map.json';

// تأكد من وجود المجلد للعمل بشكل مستقل
if (!is_dir(dirname($settingsFile))) mkdir(dirname($settingsFile), 0777, true);

// التحقق مما إذا كان الملف يتم تشغيله مباشرة أم تم تضمينه عبر include
$isDirect = (basename($_SERVER['PHP_SELF']) == 'cron_sync.php');

function cronLog($msg) {
    global $isDirect;
    if ($isDirect) echo $msg . "\n";
}

if (!file_exists($settingsFile)) {
    if ($isDirect) die(json_encode(['error' => 'Settings file not found']));
    return;
}

$settings = json_decode(file_get_contents($settingsFile), true);
$apiKey   = $settings['api_key'] ?? '';
$arMap    = file_exists($arMapFile) ? json_decode(file_get_contents($arMapFile), true) : [];

if (empty($apiKey)) {
    if ($isDirect) die(json_encode(['error' => 'API Key is missing']));
    return;
}

// تحديث وقت المزامنة
$settings['last_cron_sync'] = time();
file_put_contents($settingsFile, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// 2. دالة الجلب من الـ API
function fetchAPI($met, $params = []) {
    global $apiKey;
    $params['met'] = $met;
    $params['APIkey'] = $apiKey;
    $url = "https://apiv2.allsportsapi.com/football/?" . http_build_query($params);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

// 3. محرك الترجمة البسيط
$translate = function($type, $id, $fallback) use (&$arMap) {
    if (isset($arMap[$type][$id])) {
        return $arMap[$type][$id];
    }
    return $fallback;
};

// 4. معالجة البيانات وتوحيدها
function formatMatch($m, $translate) {
    $hId = $m['home_team_key'];
    $aId = $m['away_team_key'];
    $lId = $m['league_key'];
    $hName = $m['event_home_team'];
    $aName = $m['event_away_team'];
    $lName = $m['league_name'];
    $statusRaw = $m['event_status'];

    // الترجمة
    $hTr = $translate('teams', $hId, $translate('countries', $hId, $translate('countries', $hName, $hName)));
    $aTr = $translate('teams', $aId, $translate('countries', $aId, $translate('countries', $aName, $aName)));
    $lTr = $translate('leagues', $lId, $lName);

    // الحالة
    $liveStatus = 'upcoming';
    if (in_array($statusRaw, ['LIVE', '1st Half', '2nd Half', 'HT'])) $liveStatus = 'live';
    elseif (in_array($statusRaw, ['FT', 'Finished', 'After ET', 'After Pen.'])) $liveStatus = 'finished';

    $evDate = $m['event_date'];
    $ts = strtotime($evDate . ' ' . $m['event_time']);
    
    $today = date('Y-m-d');
    $yest  = date('Y-m-d', strtotime('-1 day'));
    $tom   = date('Y-m-d', strtotime('+1 day'));
    $day   = 'today';
    if($evDate === $yest) $day = 'yesterday';
    elseif($evDate === $tom) $day = 'tomorrow';

    return [
        "id"              => (string)$m['event_key'],
        "event_key"       => (string)$m['event_key'],
        "timestamp"       => $ts,
        "day"             => $day,
        "homeTeam"        => $hTr,
        "homeLogo"        => $m['home_team_logo'] ?? '',
        "awayTeam"        => $aTr,
        "awayLogo"        => $m['away_team_logo'] ?? '',
        "league"          => $lTr,
        "leagueId"        => (string)$lId,
        "status"          => $liveStatus,
        "status_raw"      => $statusRaw,
        "score"           => ($m['event_final_result'] ?: ($m['event_ft_result'] ?: 'vs')),
        "round"           => $m['event_round'] ?? ''
    ];
}

// 5. التنفيذ: جلب بيانات الـ 3 أيام
cronLog("Syncing matches from API...");
$from = date('Y-m-d', strtotime('-1 day'));
$to   = date('Y-m-d', strtotime('+1 day'));

$res = fetchAPI('Fixtures', ['from' => $from, 'to' => $to]);
$allMatches = [];

if (isset($res['result']) && is_array($res['result'])) {
    foreach ($res['result'] as $m) {
        $allMatches[] = formatMatch($m, $translate);
    }
}

// 6. تحديث بنك المباريات (api_fixtures.json)
file_put_contents($bankFile, json_encode($allMatches, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
cronLog("Bank updated: " . count($allMatches) . " matches.");

// 7. تحديث المباريات المضافة للموقع (matches.json)
if (file_exists($matchesFile)) {
    $currentLive = json_decode(file_get_contents($matchesFile), true) ?: [];
    $updatedCount = 0;

    foreach ($currentLive as &$liveM) {
        // البحث عن المباراة في بيانات الـ API المجلوبة حديثاً
        foreach ($allMatches as $apiM) {
            if ($liveM['event_key'] == $apiM['id'] || (isset($liveM['id']) && $liveM['id'] == $apiM['id'])) {
                $liveM['score']      = $apiM['score'];
                $liveM['status']     = $apiM['status'];
                $liveM['status_raw'] = $apiM['status_raw'];
                $updatedCount++;
                break;
            }
        }
    }
    
    file_put_contents($matchesFile, json_encode($currentLive, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    cronLog("Site matches updated: $updatedCount matches.");
}

cronLog("Sync completed successfully at " . date('Y-m-d H:i:s'));
