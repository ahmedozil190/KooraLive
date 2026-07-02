<?php
/**
 * KooraLive - Security API with Auto-Sync
 * هذا الملف هو الواجهة العامة المؤمنة لتطبيق الموبايل
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');

date_default_timezone_set('Asia/Riyadh');

// ========== مسارات الملفات (المجلد الرئيسي) ==========
$baseDir      = __DIR__ . '/data/';
$matchesFile  = $baseDir . 'matches.json';
$newsFile     = $baseDir . 'news.json';
$settingsFile = $baseDir . 'api_settings.json';
$cacheDir     = $baseDir . 'api_cache/';
$dailyCacheF  = $cacheDir . 'daily_fetch.json';
$liveCacheF   = $cacheDir . 'live_update.json';
$fixturesBank = $baseDir . 'api_fixtures.json';

// ========== تحميل الإعدادات للمزامنة ==========
$settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
$apiKey    = $settings['api_key'] ?? '';
$autoFetch = $settings['auto_fetch'] ?? true;

// ========== الدوال الأساسية ==========
function readJson($path) {
    if (!file_exists($path)) return [];
    $content = @file_get_contents($path);
    return $content ? (json_decode($content, true) ?: []) : [];
}

function writeJson($path, $data) {
    file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function callApi($endpoint, $apiKey) {
    if (empty($apiKey)) return ['error' => 'No API Key'];
    $url = "https://v3.football.api-sports.io/$endpoint";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["x-apisports-key: $apiKey"],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($httpCode === 200) ? json_decode($response, true) : ['error' => 'HTTP Error'];
}

// ========== محرك التحديث التلقائي (تم النقل لنظام الكرون لزيادة السرعة والدقة) ==========
// تم تعطيل التحديث المباشر هنا لأن الكرون يقوم بالمهمة في الخلفية كل دقيقة
// لضمان أفضل أداء وحفاظاً على عدد طلبات الـ API

// ========== عرض البيانات للموبايل ==========
$action = $_GET['action'] ?? 'get_matches';

if ($action === 'get_matches') {
    $m = readJson($matchesFile);
    usort($m, function($a, $b) {
        $score = ['live' => 0, 'upcoming' => 1, 'finished' => 2];
        $sA = $score[$a['status']] ?? 1; $sB = $score[$b['status']] ?? 1;
        if ($sA !== $sB) return $sA - $sB;
        return ($a['timestamp'] ?? 0) - ($b['timestamp'] ?? 0);
    });
    echo json_encode($m, JSON_UNESCAPED_UNICODE);
} elseif ($action === 'get_news') {
    $n = readJson($newsFile);
    echo json_encode(array_reverse($n), JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['error' => 'Invalid action']);
}
