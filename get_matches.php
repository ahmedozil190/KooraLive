<?php
header('Content-Type: application/json; charset=utf-8');

// المفتاح الخاص بك من لوحة تحكم API-Football
$apiKey = 'fbcca31c5f3f9f2638659f404dc62463';

// رابط API-Football (v3) ليوم 29 يونيو 2026
$apiUrl = "https://v3.football.api-sports.io/fixtures?date=2026-06-29";

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
        // فك تشفير التاريخ والوقت
        $dateTime = new DateTime($f['fixture']['date']);
        $dateStr  = $dateTime->format('Y-m-d');
        $timeStr  = $dateTime->format('H:i');

        // تجهيز النتائج بتنسيق ALLSportsAPI
        $htScore = ($f['score']['halftime']['home'] !== null) ? $f['score']['halftime']['home'] . " - " . $f['score']['halftime']['away'] : "";
        $ftScore = ($f['score']['fulltime']['home'] !== null) ? $f['score']['fulltime']['home'] . " - " . $f['score']['fulltime']['away'] : "";
        $penaltyScore = ($f['score']['penalty']['home'] !== null) ? $f['score']['penalty']['home'] . " - " . $f['score']['penalty']['away'] : "";
        $finalScore = ($f['goals']['home'] !== null) ? $f['goals']['home'] . " - " . $f['goals']['away'] : "";

        $matches[] = [
            "event_key"             => $f['fixture']['id'],
            "event_date"            => $dateStr,
            "event_time"            => $timeStr,
            "event_home_team"       => $f['teams']['home']['name'],
            "home_team_key"         => $f['teams']['home']['id'],
            "event_away_team"       => $f['teams']['away']['name'],
            "away_team_key"         => $f['teams']['away']['id'],
            "event_halftime_result" => $htScore,
            "event_final_result"    => $finalScore,
            "event_ft_result"       => $ftScore,
            "event_penalty_result"  => $penaltyScore,
            "event_status"          => $f['fixture']['status']['long'],
            "country_name"          => $f['league']['country'],
            "league_name"           => $f['league']['name'],
            "league_key"            => $f['league']['id'],
            "league_round"          => $f['league']['round'] ?? "",
            "league_season"         => $f['league']['season'],
            "event_live"            => in_array($f['fixture']['status']['short'], ['1H', '2H', 'HT', 'ET', 'P', 'BT']) ? "1" : "0",
            "event_stadium"         => $f['fixture']['venue']['name'] ?? "",
            "event_referee"         => $f['fixture']['referee'] ?? "",
            "home_team_logo"        => $f['teams']['home']['logo'],
            "away_team_logo"        => $f['teams']['away']['logo'],
            "event_country_key"     => null, // غير متوفر مباشرة بتنسيق رقمي في الاستعلام
            "league_logo"           => $f['league']['logo'],
            "country_logo"          => "",
            "event_home_formation"  => "", // يحتاج لطلب lineups منفصل
            "event_away_formation"  => "", // يحتاج لطلب lineups منفصل
            "fk_stage_key"          => null,
            "stage_name"            => $f['league']['round'] ?? "",
            "league_group"          => null
        ];
    }
    echo json_encode($matches, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    echo json_encode(['info' => 'No Data Found'], JSON_UNESCAPED_UNICODE);
}
?>
