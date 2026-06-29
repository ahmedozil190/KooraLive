<?php
header('Content-Type: application/json; charset=utf-8');

// المفتاح الخاص بك
$apiKey = 'fbcca31c5f3f9f2638659f404dc62463';

// رقم المباراة المحدد (Fixture ID)
$fixtureId = '1565176';

// رابط API-Football (v3) لجلب تفاصيل مباراة واحدة بكل أحداثها وإحصائياتها وتشكيلتها
$apiUrl = "https://v3.football.api-sports.io/fixtures?id=$fixtureId";

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
    echo json_encode(['error' => 'Connection Error: ' . $err], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($response, true);

if (isset($data['response'][0])) {
    $f = $data['response'][0];
    
    // فك تشفير التاريخ والوقت
    $dateTime = new DateTime($f['fixture']['date']);
    
    // بناء النتيجة بتنسيق AllSportsAPI
    $goalscorers = [];
    $cards = [];
    $substitutions = [];
    
    if (isset($f['events'])) {
        foreach ($f['events'] as $e) {
            if ($e['type'] === 'Goal') {
                $goalscorers[] = [
                    "time" => $e['time']['elapsed'] + ($e['time']['extra'] ?? 0),
                    "home_scorer" => ($e['team']['id'] == $f['teams']['home']['id']) ? $e['player']['name'] : "",
                    "home_assist" => ($e['team']['id'] == $f['teams']['home']['id']) ? ($e['assist']['name'] ?? "") : "",
                    "away_scorer" => ($e['team']['id'] == $f['teams']['away']['id']) ? $e['player']['name'] : "",
                    "away_assist" => ($e['team']['id'] == $f['teams']['away']['id']) ? ($e['assist']['name'] ?? "") : "",
                    "score" => $e['detail'] ?? ""
                ];
            } elseif ($e['type'] === 'Card') {
                $cards[] = [
                    "time" => $e['time']['elapsed'] + ($e['time']['extra'] ?? 0),
                    "home_fault" => ($e['team']['id'] == $f['teams']['home']['id']) ? $e['player']['name'] : "",
                    "away_fault" => ($e['team']['id'] == $f['teams']['away']['id']) ? $e['player']['name'] : "",
                    "card" => $e['detail'] ?? ""
                ];
            }
        }
    }

    $result = [
        "event_key"             => $f['fixture']['id'],
        "event_date"            => $dateTime->format('Y-m-d'),
        "event_time"            => $dateTime->format('H:i'),
        "event_home_team"       => $f['teams']['home']['name'],
        "event_away_team"       => $f['teams']['away']['name'],
        "event_final_result"    => $f['goals']['home'] . " - " . $f['goals']['away'],
        "event_status"          => $f['fixture']['status']['long'],
        "event_stadium"         => $f['fixture']['venue']['name'] ?? "",
        "event_referee"         => $f['fixture']['referee'] ?? "",
        "goalscorers"           => $goalscorers,
        "cards"                 => $cards,
        "substitutions"         => $substitutions,
        "statistics"            => $f['statistics'] ?? [],
        "lineups"               => $f['lineups'] ?? [],
        "raw_response"          => "تم جلب البيانات التفصيلية بنجاح من API-Football"
    ];

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    echo json_encode(['info' => 'Fixture Not Found'], JSON_UNESCAPED_UNICODE);
}
?>
