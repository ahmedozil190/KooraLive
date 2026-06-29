<?php
header('Content-Type: application/json; charset=utf-8');

// المفتاح الخاص بك من لوحة تحكم API-Football
$apiKey = '757f2fdd5505850e862a81f8569790bf';

// رابط API-Football (v3) لجلب جميع المباريات المباشرة
$apiUrl = "https://v3.football.api-sports.io/fixtures?live=all";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "x-apisports-key: $apiKey"
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(['error' => 'خطأ في الاتصال بـ API-Football: ' . $err], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($response, true);

if (isset($data['response']) && is_array($data['response'])) {
    $matches = [];
    foreach ($data['response'] as $f) {
        $matches[] = [
            'id'           => $f['fixture']['id'],
            'homeTeam'     => $f['teams']['home']['name'],
            'awayTeam'     => $f['teams']['away']['name'],
            'homeScore'    => $f['goals']['home'],
            'awayScore'    => $f['goals']['away'],
            'status'       => $f['fixture']['status']['short'], // مثل 1H, 2H, HT
            'minute'       => $f['fixture']['status']['elapsed'], // الدقيقة الحالية
            'league'       => $f['league']['name'],
            'country'      => $f['league']['country'],
            'homeLogo'     => $f['teams']['home']['logo'],
            'awayLogo'     => $f['teams']['away']['logo']
        ];
    }
    echo json_encode($matches, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    $errorMsg = $data['errors']['token'] ?? ($data['errors']['requests'] ?? 'لا توجد مباريات مباشرة حالياً');
    echo json_encode(['info' => $errorMsg], JSON_UNESCAPED_UNICODE);
}
?>
