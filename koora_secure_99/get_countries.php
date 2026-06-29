<?php
/**
 * سكربت مساعد لجلب أسماء الدول من الـ API بالأسماء الرسمية
 */
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
        CURLOPT_TIMEOUT        => 20,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

$data = callApi("countries", $apiKey);
$countries = $data['response'] ?? [];

echo "<html><head><title>قائمة الدول - API</title>
<style>
    body { font-family: sans-serif; direction: ltr; padding: 20px; background: #f4f7f6; }
    table { width: 100%; max-width: 800px; margin: auto; border-collapse: collapse; background: #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
    th { background: #6366f1; color: #fff; }
    tr:nth-child(even) { background: #f9f9f9; }
    img { width: 30px; border-radius: 4px; }
</style>
</head><body>";

echo "<h1>Official API Countries List</h1>";
echo "<table>";
echo "<thead><tr><th>Flag</th><th>Country Name</th><th>Code</th></tr></thead><tbody>";

foreach ($countries as $c) {
    echo "<tr>
            <td><img src='{$c['flag']}'></td>
            <td><strong>{$c['name']}</strong></td>
            <td>{$c['code']}</td>
          </tr>";
}

echo "</tbody></table></body></html>";
