<?php
header('Content-Type: application/json; charset=utf-8');

// المفتاح الخاص بك
$apiKey = 'cb73b0e4f340baf984ad40fc1894328b';

// رقم المباراة المحدد
$fixtureId = '1525172';

// رابط API-Football جلب تفاصيل مباراة واحدة كاملة
$apiUrl = "https://v3.football.api-sports.io/fixtures?id=$fixtureId";

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

if (isset($data['response'][0])) {
    echo json_encode($data['response'][0], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    echo json_encode(['info' => 'Fixture Not Found', 'raw_response' => $data], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>
