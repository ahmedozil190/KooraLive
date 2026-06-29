<?php
header('Content-Type: application/json; charset=utf-8');

// المفتاح الجديد الخاص بك
$apiKey = 'fbcca31c5f3f9f2638659f404dc62463';

// رابط API-Football (v3) مع جلب الأحداث (الأهداف، البطاقات، إلخ)
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
            "league_logo"           => $f['league']['logo'],
            "goalscorers"           => $goalscorers,
            "cards"                 => $cards,
            "substitutions"         => $substitutions,
            "statistics"            => [], // تحتاج لطلب منفصل لكل مباراة
            "lineups"               => [], // تحتاج لطلب lineups منفصل
        ];
    }
    echo json_encode($matches, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    echo json_encode(['info' => 'No Data Found'], JSON_UNESCAPED_UNICODE);
}
?>
