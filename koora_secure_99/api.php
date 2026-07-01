<?php
header('Content-Type: application/json; charset=utf-8');

/**
 * المحرك الرئيسي (Engine) - نسخة الحماية القصوى
 * المزود: AllSportsAPI
 */

// 1. المسارات والإعدادات
$settingsFile = __DIR__ . '/../data/api_settings.json';
$liveFile     = __DIR__ . '/../data/matches.json';
$bankFile     = __DIR__ . '/../data/api_fixtures.json';
$arMapFile    = __DIR__ . '/../data/ar_map.json';

// تحميل الإعدادات
$settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
$apiKey   = $settings['api_key'] ?? '';
$cacheSec = $settings['cache_seconds'] ?? 60;
$fHour    = $settings['fetch_hour'] ?? 0;

// تحميل القاموس العربي
$arMap = file_exists($arMapFile) ? json_decode(file_get_contents($arMapFile), true) : [];

date_default_timezone_set('UTC');
$today = date('Y-m-d');
$currentTime = time();

// 2. دالة جلب البيانات من AllSportsAPI
function fetchFromAllSports($params) {
    global $apiKey;
    if (empty($apiKey)) return ['error' => 'API Key Missing'];
    
    $params['timezone'] = 'UTC';
    $params['APIkey']   = $apiKey;
    $url = "https://apiv2.allsportsapi.com/football/?" . http_build_query($params);
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false, // مهم جداً للاستضافات
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_USERAGENT      => 'Mozilla/5.0'
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($res, true) ?: ['error' => 'Invalid JSON'];
}

// 3. دالة الترجمة والمعالجة
function formatMatchData($m) {
    global $arMap;
    
    $tr = function($txt, $cat) use ($arMap) {
        $txt = trim($txt);
        if (isset($arMap[$cat][$txt])) return $arMap[$cat][$txt];
        // بحث احتياطي في كافة الأقسام إذا لم يجد في القسم المحدد
        foreach ($arMap as $section) {
            if (isset($section[$txt])) return $section[$txt];
        }
        return $txt;
    };

    $status = $m['event_status'] ?? 'upcoming';
    $isLive = (strpos($status, ':') === false && !empty($status) && !in_array($status, ['Finished', 'FT', 'After Extra Time']));

    // معالجة النتيجة لفصلها
    $fullScore = !empty($m['event_final_result']) ? $m['event_final_result'] : "0 - 0";
    $scoreParts = explode('-', $fullScore);
    $hScore = trim($scoreParts[0] ?? '0');
    $aScore = trim($scoreParts[1] ?? '0');

    return [
        "id"                  => (string)($m['event_key'] ?? ''),
        "event_key"           => (string)($m['event_key'] ?? ''),
        "timestamp"           => strtotime(($m['event_date'] ?? 'today') . ' ' . ($m['event_time'] ?? '00:00')),
        "day"                 => "today",
        "homeTeam"            => $tr($m['event_home_team'] ?? '', 'teams'),
        "homeLogo"            => $m['home_team_logo'] ?? '',
        "awayTeam"            => $tr($m['event_away_team'] ?? '', 'teams'),
        "awayLogo"            => $m['away_team_logo'] ?? '',
        "league"              => $tr($m['league_name'] ?? '', 'leagues'),
        "country"             => $tr($m['country_name'] ?? '', 'countries'),
        "score"               => $fullScore,
        "homeScore"           => $hScore,
        "awayScore"           => $aScore,
        "status"              => $status,
        "isLive"              => $isLive ? "1" : "0",
        "channel"             => "غير معروف",
        "commentator"         => "غير معروف",
        "streamUrl"           => ""
    ];
}

// 4. معالجة الأوامر
$action = $_GET['action'] ?? '';

// أ. جلب البنك من الملف أو من الـ API إذا كان فارغاً
if ($action === 'get_bank') {
    $bankData = file_exists($bankFile) ? json_decode(file_get_contents($bankFile), true) : [];
    
    if (empty($bankData)) {
        $days = [
            'yesterday' => date('Y-m-d', strtotime('-1 day')),
            'today'     => date('Y-m-d'),
            'tomorrow'  => date('Y-m-d', strtotime('+1 day'))
        ];
        
        $newBank = [];
        foreach ($days as $label => $dateVal) {
            $apiRes = fetchFromAllSports(['met' => 'Fixtures', 'from' => $dateVal, 'to' => $dateVal]);
            if (isset($apiRes['result']) && is_array($apiRes['result'])) {
                foreach ($apiRes['result'] as $m) {
                    $f = formatMatchData($m);
                    $f['day'] = $label;
                    $newBank[] = $f;
                }
            }
        }
        
        if (!empty($newBank)) {
            file_put_contents($bankFile, json_encode($newBank, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $bankData = $newBank;
        }
    }
    echo json_encode($bankData);
    exit;
}

// ب. إضافة مباراة للجدول المباشر
if ($action === 'add_from_bank') {
    $input = json_decode(file_get_contents('php://input'), true);
    $mid   = $input['id'] ?? '';
    
    $bank = json_decode(@file_get_contents($bankFile), true) ?: [];
    $live = json_decode(@file_get_contents($liveFile), true) ?: [];
    
    foreach ($bank as $m) {
        if ($m['id'] == $mid) {
            $m['streamUrl']   = $input['streamUrl'] ?? '';
            $m['channel']     = $input['channel'] ?? '';
            $m['commentator'] = $input['commentator'] ?? '';
            
            $found = false;
            foreach ($live as &$lm) {
                if ($lm['id'] == $mid) { $lm = $m; $found = true; break; }
            }
            if (!$found) $live[] = $m;
            
            file_put_contents($liveFile, json_encode(array_values($live), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            echo json_encode(['success' => true]);
            exit;
        }
    }
    exit;
}

// ج. حالة الـ API
if ($action === 'api_status') {
    echo json_encode([
        'last_daily_date'  => $settings['last_daily_date'] ?? '--',
        'last_live_update' => isset($settings['last_live_update']) ? date('H:i:s', $settings['last_live_update']) : '--'
    ]);
    exit;
}

// 5. التحديث التلقائي (Smart Polling)
$needsDaily = ($settings['last_daily_date'] ?? '') !== $today && (int)date('G') >= $fHour;
$needsLive  = ($currentTime - ($settings['last_live_update'] ?? 0)) >= $cacheSec;

if ($needsDaily) {
    // جلب البنك مجدداً لتحديث اليوم
    $days = ['yesterday','today','tomorrow'];
    $all = [];
    foreach($days as $d) {
        $dv = date('Y-m-d', strtotime(($d=='today'?'':$d))); // تقريب للتبسيط
        // (يمكن تحسين جلب التواريخ هنا)
    }
    // لتبسيط العملية وتجنب المسح، سنعتمد على jfet_bank عند الحاجة
    $settings['last_daily_date'] = $today;
    file_put_contents($settingsFile, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

if ($needsLive) {
    $apiRes = fetchFromAllSports(['met' => 'Livescore']);
    if (isset($apiRes['result']) && is_array($apiRes['result'])) {
        $results = $apiRes['result'];
        
        // تحديث البنك (فقط إذا كان موجوداً)
        if (file_exists($bankFile)) {
            $bank = json_decode(file_get_contents($bankFile), true) ?: [];
            foreach($bank as &$m) {
                foreach($results as $r) {
                    if ($m['id'] == $r['event_key']) {
                        $m['score'] = $r['event_final_result'];
                        $m['status'] = $r['event_status'];
                        break;
                    }
                }
            }
            file_put_contents($bankFile, json_encode($bank, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
        
        // تحديث الموقع
        if (file_exists($liveFile)) {
            $live = json_decode(file_get_contents($liveFile), true) ?: [];
            foreach($live as &$m) {
                foreach($results as $r) {
                    if ($m['id'] == $r['event_key']) {
                        $m['score'] = $r['event_final_result'];
                        $m['status'] = $r['event_status'];
                        break;
                    }
                }
            }
            file_put_contents($liveFile, json_encode($live, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
    }
    $settings['last_live_update'] = $currentTime;
    file_put_contents($settingsFile, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

echo json_encode(['success' => true]);
