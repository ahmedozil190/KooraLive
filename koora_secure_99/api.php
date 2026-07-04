<?php
header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

/**
 * KooraLive API Engine - Optimized for Cron Sync
 */

$settingsFile = __DIR__ . '/../data/api_settings.json';
$liveFile     = __DIR__ . '/../data/matches.json';
$bankFile     = __DIR__ . '/../data/api_fixtures.json';

// 1. إضافة مباراة من البنك إلى الموقع
if (isset($_GET['action']) && $_GET['action'] === 'add_from_bank') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['event_key'])) {
        echo json_encode(['error' => 'بيانات غير مكتملة']);
        exit;
    }

    $ms = json_decode(@file_get_contents($liveFile), true) ?: [];
    
    // منع التكرار
    foreach ($ms as $existing) {
        if ($existing['event_key'] == $data['event_key']) {
            echo json_encode(['error' => 'المباراة مضافة بالفعل في جدول المباريات الحالية']);
            exit;
        }
    }

    // معالجة النتيجة فوراً لتظهر بشكل صحيح (مع دعم ركلات الترجيح)
    $score = $data['score'] ?? 'vs';
    $statusRaw = $data['status_raw'] ?? '';
    
    if ($statusRaw === 'After Pen.' && !empty($data['event_penalty_result'])) {
        $baseScore = $data['event_ft_result'] ?: '0 - 0';
        $penScore  = $data['event_penalty_result'];
        $pHome = "0"; $pAway = "0";
        if (strpos($penScore, '-') !== false) {
            $pParts = explode('-', $penScore);
            $pHome = trim($pParts[0]);
            $pAway = trim($pParts[1]);
        }
        $score = "($pHome) $baseScore ($pAway)";
    } else {
        if (strpos($score, '-') !== false) {
            $parts = explode('-', $score);
            $hScore = trim($parts[0] ?? '0');
            $aScore = trim($parts[1] ?? '0');
            $score = $hScore . " - " . $aScore;
        }
    }

    $hScore = "0"; $aScore = "0";
    if (preg_match('/\(?\d+\)?\s*(\d+)\s*-\s*(\d+)\s*\(?\d+\)?/', $score, $matches)) {
        $hScore = $matches[1];
        $aScore = $matches[2];
    } elseif (strpos($score, '-') !== false) {
        $parts = explode('-', $score);
        $hScore = trim($parts[0]);
        $aScore = trim($parts[1]);
    }

    // تنظيف البيانات المضافة
    $newMatch = [
        "id"           => (string)$data['id'],
        "event_key"    => (string)$data['id'],
        "timestamp"    => $data['timestamp'] ?? time(),
        "homeTeam"     => $data['homeTeam'] ?? '',
        "homeLogo"     => $data['homeLogo'] ?? '',
        "awayTeam"     => $data['awayTeam'] ?? '',
        "awayLogo"     => $data['awayLogo'] ?? '',
        "league"       => $data['league'] ?? '',
        "leagueId"     => $data['leagueId'] ?? '',
        "status"       => $data['status'] ?? 'upcoming',
        "status_raw"   => $data['status_raw'] ?? '',
        "status_ar"    => $data['status_ar'] ?? '',
        "score"        => $score,
        "homeScore"    => $hScore,
        "awayScore"    => $aScore,
        "round"        => $data['round'] ?? '',
        "streamUrl"    => $data['streamUrl'] ?? '#',
        "channel"      => $data['channel'] ?? '',
        "commentator"  => $data['commentator'] ?? ''
    ];

    $ms[] = $newMatch;
    file_put_contents($liveFile, json_encode(array_values($ms), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    clearstatcache();
    echo json_encode(['success' => true]);
    exit;
}

// 2. جلب بنك المباريات (الذي يحدثه الكرون كل 10 ثواني)
if (isset($_GET['action']) && $_GET['action'] === 'get_bank') {
    if (file_exists($bankFile)) {
        echo file_get_contents($bankFile);
    } else {
        echo json_encode([]);
    }
    exit;
}

// 3. الحالة الافتراضية
echo json_encode([
    'success' => true,
    'message' => 'API Engine is active',
    'sync_mode' => 'Cron Job Managed'
]);
