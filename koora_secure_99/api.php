<?php
header('Content-Type: application/json; charset=utf-8');

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

    // تنظيف البيانات المضافة
    $newMatch = [
        "id"           => (string)$data['event_key'],
        "event_key"    => (string)$data['event_key'],
        "timestamp"    => $data['timestamp'] ?? time(),
        "homeTeam"     => $data['homeTeam'] ?? '',
        "homeLogo"     => $data['homeLogo'] ?? '',
        "awayTeam"     => $data['awayTeam'] ?? '',
        "awayLogo"     => $data['awayLogo'] ?? '',
        "league"       => $data['league'] ?? '',
        "leagueId"     => $data['leagueId'] ?? '',
        "status"       => $data['status'] ?? 'upcoming',
        "score"        => $data['score'] ?? 'vs',
        "round"        => $data['round'] ?? '',
        "streamUrl"    => $data['streamUrl'] ?? '#',
        "channel"      => $data['channel'] ?? '',
        "commentator"  => $data['commentator'] ?? ''
    ];

    $ms[] = $newMatch;
    file_put_contents($liveFile, json_encode(array_values($ms), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
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
