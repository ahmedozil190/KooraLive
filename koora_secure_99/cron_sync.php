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
    $params['timezone'] = 'UTC'; // إجبار الـ API على إرسال توقيت جرينتش الموحد
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
    // نظام ترجمة متطور للبطولات
    $lTr = $translate('leagues', $lId, null);
    if (!$lTr) {
        if (strpos($lName, ' - ') !== false) {
            $parts = explode(' - ', $lName, 2);
            $base = trim($parts[0]);
            $round = trim($parts[1]);
            
            $trBase = $translate('leagues', $base, $base);
            $trRound = $translate('rounds', $round, $round);
            $lTr = ($trBase === $trRound) ? $trBase : $trBase . ' - ' . $trRound;
        } else {
            $lTr = $translate('leagues', $lName, $lName);
        }
    }

    // ترجمة الدور الإضافي (إذا وجد في حقل منفصل)
    $roundRaw = $m['league_round'] ?? ($m['event_round'] ?? '');
    $roundTr = !empty($roundRaw) ? $translate('rounds', $roundRaw, $roundRaw) : '';
    if (!empty($roundTr) && strpos($lTr, $roundTr) === false) {
        $lTr .= ' - ' . $roundTr;
    }

    // الحالة
    $statusMapAr = [
        'Finished' => 'انتهت', 'FT' => 'انتهت', 'After ET' => 'انتهت (إضافي)', 'After Pen.' => 'انتهت (ركلات)',
        'Half Time' => 'استراحة', 'HT' => 'استراحة', 'Postponed' => 'مؤجلة', 'Cancelled' => 'تم الإلغاء',
        'Abandoned' => 'متوقفة', 'LIVE' => 'مباشر', '1st Half' => 'الشوط الأول', '2nd Half' => 'الشوط الثاني',
        'Not Started' => 'لم تبدأ بعد'
    ];
    
    $liveStatus = 'upcoming';
    if (in_array($statusRaw, ['LIVE', '1st Half', '2nd Half', 'HT', 'Half Time', 'Extra Time', 'Penalty Shootout']) || is_numeric($statusRaw) || strpos($statusRaw, '+') !== false) {
        $liveStatus = 'live';
    } elseif (in_array($statusRaw, ['FT', 'Finished', 'After ET', 'After Pen.'])) {
        $liveStatus = 'finished';
    }

    $statusAr = $statusMapAr[$statusRaw] ?? $statusRaw;

    $evDate = $m['event_date'];
    $ts = strtotime($evDate . ' ' . $m['event_time'] . ' UTC');
    
    $today = date('Y-m-d');
    $yest  = date('Y-m-d', strtotime('-1 day'));
    $tom   = date('Y-m-d', strtotime('+1 day'));
    $day   = 'today';
    if($evDate === $yest) $day = 'yesterday';
    elseif($evDate === $tom) $day = 'tomorrow';

    // استخراج النتيجة والأهداف للتطبيق (مع معالجة ذكية لركلات الترجيح)
    $score  = ($m['event_final_result'] ?: ($m['event_ft_result'] ?: 'vs'));
    
    // تصحيح النتيجة في حالة ركلات الترجيح لتجنب أخطاء الـ API
    if ($statusRaw === 'After Pen.' && !empty($m['event_penalty_result'])) {
        $baseScore = $m['event_ft_result'] ?: '0 - 0'; // النتيجة الأصلية قبل الركلات
        $penScore  = $m['event_penalty_result'];      // نتيجة الركلات فقط
        
        // استخراج أرقام الركلات (نتوقع صيغة "2 - 4")
        $pHome = "0"; $pAway = "0";
        if (strpos($penScore, '-') !== false) {
            $pParts = explode('-', $penScore);
            $pHome = trim($pParts[0]);
            $pAway = trim($pParts[1]);
        }
        
        // بناء النتيجة بالشكل المطلوب: (ركلات الفريق الأول) نتيجة المباراة (ركلات الفريق الثاني)
        $score = "($pHome) $baseScore ($pAway)";
    }

    $hScore = "0"; $aScore = "0";
    if (strpos($score, '-') !== false) {
        // نستخدم النتيجة الأصلية لاستخراج الأهداف (HomeScore - AwayScore)
        // إذا كانت ركلات ترجيح، النتيجة الأصلية موجودة في event_ft_result
        $scoreForCalc = ($statusRaw === 'After Pen.') ? ($m['event_ft_result'] ?: '0 - 0') : $score;
        $parts  = explode('-', $scoreForCalc);
        $hScore = trim($parts[0] ?? '0');
        $aScore = trim($parts[1] ?? '0');
    }

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
        "status_ar"       => $statusAr,
        "status_raw"      => $statusRaw,
        "score"           => $score,
        "homeScore"       => $hScore,
        "awayScore"       => $aScore,
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
        // إذا كنت قد أنهيت المباراة يدوياً في لوحة التحكم، فلن نسمح للـ Cron بتعديلها مرة أخرى
        if (isset($liveM['status']) && $liveM['status'] === 'finished') {
            continue;
        }

        // البحث عن المباراة في بيانات الـ API المجلوبة حديثاً
        foreach ($allMatches as $apiM) {
            if ($liveM['event_key'] == $apiM['id'] || (isset($liveM['id']) && $liveM['id'] == $apiM['id'])) {
                $liveM['score']       = $apiM['score'];
                $liveM['homeScore']   = $apiM['homeScore'];
                $liveM['awayScore']   = $apiM['awayScore'];
                $liveM['status']      = $apiM['status'];
                $liveM['status_raw']  = $apiM['status_raw'];
                $liveM['status_ar']   = $apiM['status_ar'];
                $liveM['status_text'] = $apiM['status_ar'];
                
                // تحديث الترجمة ديناميكياً في حال تم تغييرها في الملف
                $liveM['league']      = $apiM['league'];
                $liveM['homeTeam']    = $apiM['homeTeam'];
                $liveM['awayTeam']    = $apiM['awayTeam'];
                
                if ($apiM['status'] === 'live') {
                    $liveM['status'] = 'live';
                }
                
                $updatedCount++;
                break;
            }
        }
    }
    
    file_put_contents($matchesFile, json_encode($currentLive, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    cronLog("Site matches updated: $updatedCount matches.");
}

cronLog("Sync completed successfully at " . date('Y-m-d H:i:s'));
