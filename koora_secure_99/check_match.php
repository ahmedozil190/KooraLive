<?php
header('Content-Type: text/html; charset=utf-8');
$settingsFile = '../data/api_settings.json';
$settings = json_decode(file_get_contents($settingsFile), true);
$apiKey = $settings['api_key'] ?? '';

function callApi($endpoint, $apiKey) {
    $url = "https://v3.football.api-sports.io/$endpoint";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["x-apisports-key: $apiKey"],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// جلب مباريات يوم 4 يوليو 2026 (كأس العالم)
$targetDate = "2026-07-04";
$data = callApi("fixtures?date=$targetDate", $apiKey);
$fixtures = $data['response'] ?? [];

echo "<html><head><title>Matches on $targetDate</title>
<style>
    body { font-family: sans-serif; padding: 20px; background: #f0f2f5; }
    .match-card { background: #fff; padding: 15px; margin-bottom: 10px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 20px; }
    .team { font-weight: bold; font-size: 18px; }
    .league { color: #666; font-size: 14px; }
</style>
</head><body>";

echo "<h1>Matches for Date: $targetDate</h1>";

if (empty($fixtures)) {
    echo "<p>No matches found or API error.</p>";
    if (isset($data['errors'])) print_r($data['errors']);
}

foreach ($fixtures as $f) {
    $home = $f['teams']['home']['name'];
    $away = $f['teams']['away']['name'];
    $league = $f['league']['name'];
    
    echo "<div class='match-card'>";
    echo "<span class='league'>$league</span> | ";
    echo "<span class='team'>$home</span> vs <span class='team'>$away</span>";
    echo "</div>";
}

echo "</body></html>";
