<?php
header('Content-Type: application/json; charset=utf-8');

// المفتاح الخاص بك
$apiKey = 'fbcca31c5f3f9f2638659f404dc62463';

// التاريخ المطلوب
$targetDate = '2026-06-30';

// رابط API-Football جلب مباريات يوم محدد بالتفصيل
$apiUrl = "https://v3.football.api-sports.io/fixtures?date=$targetDate";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "x-apisports-key: $apiKey",
    "Accept: application/json"
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(['error' => 'Connection Error: ' . $err], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($response, true);

// إخراج كل المباريات لهذا اليوم بكامل تفاصيلها الخام
if (isset($data['response']) && !empty($data['response'])) {
    echo json_encode([
        'date' => $targetDate,
        'matches_count' => count($data['response']),
        'matches' => $data['response']
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    echo json_encode([
        'info' => 'No matches found for this date',
        'raw_response' => $data
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>
