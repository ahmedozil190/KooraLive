<?php
header('Content-Type: application/json; charset=utf-8');

// المفتاح الجديد الخاص بك
$apiKey = 'cb73b0e4f340baf984ad40fc1894328b';

// التاريخ المطلوب
$targetDate = '2026-06-30';

// رابط API-Football جلب مباريات يوم محدد بالتفصيل
$apiUrl = "https://v3.football.api-sports.io/fixtures?date=$targetDate";

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

if (isset($data['response']) && is_array($data['response'])) {
    $matches = [];
    foreach ($data['response'] as $f) {
        $dateTime = new DateTime($f['fixture']['date']);
        $dateStr  = $dateTime->format('Y-m-d');
        $timeStr  = $dateTime->format('H:i');

        // معالجة الأحداث (الأهداف والبطاقات والتبديلات)
        $goalscorers = [];
        $cards = [];
        $substitutions = [];
        
        if (isset($f['events']) && is_array($f['events'])) {
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
                        "card" => $e['detail'] ?? "Yellow Card"
                    ];
                } elseif ($e['type'] === 'subst') {
                    $substitutions[] = [
                        "time" => $e['time']['elapsed'] + ($e['time']['extra'] ?? 0),
                        "home_substitution" => ($e['team']['id'] == $f['teams']['home']['id']) ? $e['player']['id'] . " | " . $e['assist']['name'] : "",
                        "away_substitution" => ($e['team']['id'] == $f['teams']['away']['id']) ? $e['player']['id'] . " | " . $e['assist']['name'] : ""
                    ];
                }
            }
        }

        $htScore = ($f['score']['halftime']['home'] !== null) ? $f['score']['halftime']['home'] . " - " . $f['score']['halftime']['away'] : "";
        $ftScore = ($f['score']['fulltime']['home'] !== null) ? $f['score']['fulltime']['home'] . " - " . $f['score']['fulltime']['away'] : "";
        $penaltyScore = ($f['score']['penalty']['home'] !== null) ? $f['score']['penalty']['home'] . " - " . $f['score']['penalty']['away'] : "";
        $finalScore = ($f['goals']['home'] !== null) ? $f['goals']['home'] . " - " . $f['goals']['away'] : "";

        $matches[] = [
            "id"             => $f['fixture']['id'],
            "date"           => $dateStr,
            "time"           => $timeStr,
            "homeName"       => $f['teams']['home']['name'],
            "homeId"         => $f['teams']['home']['id'],
            "homeLogo"       => $f['teams']['home']['logo'],
            "awayName"       => $f['teams']['away']['name'],
            "awayId"         => $f['teams']['away']['id'],
            "awayLogo"       => $f['teams']['away']['logo'],
            "score"          => $finalScore,
            "halftimeScore"  => $htScore,
            "fulltimeScore"  => $ftScore,
            "penaltyScore"   => $penaltyScore,
            "status"         => $f['fixture']['status']['long'],
            "statusShort"    => $f['fixture']['status']['short'],
            "country"        => $f['league']['country'],
            "leagueName"     => $f['league']['name'],
            "leagueId"       => $f['league']['id'],
            "leagueLogo"     => $f['league']['logo'],
            "round"          => $f['league']['round'] ?? "",
            "season"         => $f['league']['season'],
            "live"           => in_array($f['fixture']['status']['short'], ['1H', '2H', 'HT', 'ET', 'P', 'BT']) ? "1" : "0",
            "stadium"        => $f['fixture']['venue']['name'] ?? "",
            "referee"        => $f['fixture']['referee'] ?? "",
            "goalscorers"    => $goalscorers,
            "cards"          => $cards,
            "substitutions"  => $substitutions
        ];
    }
    echo json_encode($matches, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    echo json_encode(['info' => 'No Data Found'], JSON_UNESCAPED_UNICODE);
}
?>
