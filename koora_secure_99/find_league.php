<?php
header('Content-Type: text/html; charset=utf-8');
$settingsFile = '../data/api_settings.json';
$settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
$apiKey = $settings['api_key'] ?? '';

if (empty($apiKey)) {
    die("خطأ: مفتاح الـ API غير موجود في الإعدادات!");
}

function callApi($endpoint, $apiKey) {
    $url = "https://v3.football.api-sports.io/$endpoint";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["x-apisports-key: $apiKey"],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// جلب كل الدوريات
$data = callApi("leagues", $apiKey);
$leagues = $data['response'] ?? [];

// ترتيب القائمة أبجدياً: حسب الدولة أولاً، ثم حسب اسم الدوري
usort($leagues, function($a, $b) {
    if ($a['country']['name'] === $b['country']['name']) {
        return strcmp($a['league']['name'], $b['league']['name']);
    }
    return strcmp($a['country']['name'], $b['country']['name']);
});

echo "<html><head><title>كل الدوريات - API</title>
<style>
    body { font-family: sans-serif; direction: ltr; padding: 20px; background: #f4f7f6; }
    h1 { text-align: center; color: #333; }
    table { width: 100%; max-width: 1000px; margin: 30px auto; border-collapse: collapse; background: #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.1); border-radius: 8px; overflow: hidden; }
    th, td { padding: 12px 15px; border-bottom: 1px solid #eee; text-align: left; }
    th { background: #6366f1; color: #fff; font-weight: bold; text-transform: uppercase; font-size: 13px; }
    tr:hover { background: #fcfcff; }
    tr:last-child td { border-bottom: none; }
    img { width: 25px; height: 25px; object-fit: contain; vertical-align: middle; margin-right: 10px; border-radius: 3px; }
    .league-name { font-weight: bold; color: #444; }
    .country-name { color: #666; font-size: 13px; font-weight: 600; }
    .type-badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; background: #eee; color: #888; }
</style>
</head><body>";

echo "<h1>All Supported Leagues & Cups</h1>";
echo "<table><thead><tr><th>ID</th><th>Country</th><th>League Name</th><th>Type</th></tr></thead><tbody>";

foreach ($leagues as $l) {
    $flag = !empty($l['country']['flag']) ? "<img src='{$l['country']['flag']}'>" : "";
    $logo = !empty($l['league']['logo']) ? "<img src='{$l['league']['logo']}'>" : "";
    $leagueId = $l['league']['id'];
    
    echo "<tr>
            <td><span class='copy-code'>\"$leagueId\": \"{$l['league']['name']}\"</span></td>
            <td>$flag <span class='country-name'>{$l['country']['name']}</span></td>
            <td>$logo <span class='league-name'>{$l['league']['name']}</span></td>
            <td><span class='type-badge'>{$l['league']['type']}</span></td>
          </tr>";
}

echo "</tbody></table></body></html>";
