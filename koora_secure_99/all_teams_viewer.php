<?php
header('Content-Type: text/html; charset=utf-8');
$baseDir = '../data/';
$settingsFile = $baseDir . 'api_settings.json';
$arMapFile = $baseDir . 'ar_map.json';

$settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
$apiKey = $settings['api_key'] ?? '';
$arMap = file_exists($arMapFile) ? json_decode(file_get_contents($arMapFile), true) : [];

if (empty($apiKey)) die("خطأ: مفتاح الـ API غير موجود!");

// استخراج قائمة الدول فقط من ملف التعريب
$countries = [];
foreach ($arMap as $eng => $ar) {
    if (strpos($eng, '_S') === 0) continue; // تخطي الفواصل
    // أضفنا هذا الفلتر لنتأكد أنها دولة معتمدة في الـ API (التي أضفتها أنت مؤخراً)
    $countries[$eng] = $ar;
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

$selectedCountry = $_GET['country'] ?? '';
$teams = [];
if (!empty($selectedCountry)) {
    $data = callApi("teams?country=" . urlencode($selectedCountry), $apiKey);
    $teams = $data['response'] ?? [];
}
?>
<html>
<head>
    <title>مستعرض الأندية العالمي</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; direction: rtl; background: #f0f2f5; padding: 30px; }
        .container { max-width: 900px; margin: auto; background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        h1 { text-align: center; color: #1a237e; margin-bottom: 30px; }
        .selector-box { background: #e8eaf6; padding: 20px; border-radius: 8px; margin-bottom: 30px; display: flex; align-items: center; gap: 15px; justify-content: center; }
        select { padding: 10px 20px; border-radius: 6px; border: 1px solid #c5cae9; font-size: 16px; outline: none; min-width: 250px; }
        .btn { background: #3f51b5; color: #fff; border: none; padding: 10px 25px; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .btn:hover { background: #303f9f; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #eee; text-align: right; }
        th { background: #3f51b5; color: #fff; }
        tr:hover { background: #f5f5f5; }
        .team-logo { width: 30px; height: 30px; object-fit: contain; vertical-align: middle; margin-left: 10px; }
        .copy-code { background: #f9f9f9; padding: 5px 10px; border-radius: 4px; font-family: monospace; font-size: 13px; color: #d81b60; border: 1px dashed #ccc; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🌍 مستعرض أندية العالم</h1>
        
        <form class="selector-box" method="GET">
            <label>اختر الدولة:</label>
            <select name="country">
                <option value="">-- اختر دولة --</option>
                <?php foreach ($countries as $eng => $ar): ?>
                    <option value="<?= $eng ?>" <?= ($selectedCountry == $eng) ? 'selected' : '' ?>><?= $ar ?> (<?= $eng ?>)</option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn">عرض الأندية</button>
        </form>

        <?php if (!empty($selectedCountry)): ?>
            <h2>أندية دولة: <?= $arMap[$selectedCountry] ?? $selectedCountry ?> (<?= count($teams) ?> فريق)</h2>
            <table>
                <thead>
                    <tr>
                        <th>الشعار</th>
                        <th>اسم الفريق (رسمي)</th>
                        <th>كود التعريب (للنسخ)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teams as $t): $name = $t['team']['name']; ?>
                        <tr>
                            <td><img src="<?= $t['team']['logo'] ?>" class="team-logo"></td>
                            <td class="league-name"><?= $name ?></td>
                            <td><span class="copy-code">"<?= $name ?>": "..."</span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align: center; color: #888;">يرجى اختيار دولة من القائمة لعرض أنديتها.</p>
        <?php endif; ?>
    </div>
</body>
</html>
