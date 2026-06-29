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

// ابحث في كل البطولات
$data = callApi("leagues", $apiKey);
$leagues = $data['response'] ?? [];

echo "<h1>Search Results for 'Verde' in Leagues:</h1><ul>";
foreach ($leagues as $l) {
    if (stripos($l['country']['name'], 'Verde') !== false || stripos($l['league']['name'], 'Verde') !== false) {
        echo "<li>Country: <strong>{$l['country']['name']}</strong> | League: {$l['league']['name']}</li>";
    }
}
echo "</ul>";
