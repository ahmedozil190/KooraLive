<?php
header('Content-Type: text/html; charset=utf-8');
$baseDir = '../data/';
$settingsFile = $baseDir . 'api_settings.json';
$arMapFile = '../ar_map.json';

$settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
$apiKey = $settings['api_key'] ?? '';
$arMap = file_exists($arMapFile) ? json_decode(file_get_contents($arMapFile), true) : [];

if (empty($apiKey)) die("خطأ: مفتاح الـ API غير موجود!");

// استخراج قائمة الدول من القسم الجديد في ملف التعريب
$countries = $arMap['countries'] ?? [];
asort($countries); // ترتيب أبجدي للعرض

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
$selectedLeague = $_GET['league'] ?? '';
$leagues = [];
$teams = [];

// إذا تم اختيار دولة، جلب دورياتها
if (!empty($selectedCountry)) {
    $lData = callApi("leagues?country=" . urlencode($selectedCountry), $apiKey);
    $leagues = $lData['response'] ?? [];
}

// إذا تم اختيار دوري، جلب فرقه (نستخدم سنة 2023 كافتراضي)
if (!empty($selectedLeague)) {
    $tData = callApi("teams?league=" . urlencode($selectedLeague) . "&season=2023", $apiKey);
    // إذا كانت 2023 فارغة، نجرب 2024
    if (empty($tData['response'])) {
        $tData = callApi("teams?league=" . urlencode($selectedLeague) . "&season=2024", $apiKey);
    }
    $teams = $tData['response'] ?? [];
}

?>
<html>
<head>
    <title>مستعرض الأندية والبطولات</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; direction: rtl; background: #f0f2f5; padding: 20px; }
        .container { max-width: 1000px; margin: auto; background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        h1 { text-align: center; color: #1a237e; margin-bottom: 30px; }
        .selector-grid { background: #e8eaf6; padding: 20px; border-radius: 8px; margin-bottom: 30px; display: grid; grid-template-columns: 1fr 1fr auto; gap: 15px; align-items: flex-end; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        label { font-weight: bold; color: #3f51b5; font-size: 14px; }
        select { padding: 12px; border-radius: 6px; border: 1px solid #c5cae9; font-size: 15px; outline: none; background: #fff; }
        .btn { background: #3f51b5; color: #fff; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; font-weight: bold; height: 45px; }
        .btn:hover { background: #303f9f; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #eee; text-align: right; }
        th { background: #3f51b5; color: #fff; }
        tr:hover { background: #f5f5f5; }
        .team-logo { width: 30px; height: 30px; object-fit: contain; vertical-align: middle; margin-left: 10px; }
        .copy-code { background: #f9f9f9; padding: 5px 10px; border-radius: 4px; font-family: monospace; font-size: 12px; color: #d81b60; border: 1px dashed #ccc; display: inline-block; direction: ltr; }
        .info-pill { background: #e1f5fe; color: #01579b; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 مستعرض البطولات والأندية</h1>
        
        <form class="selector-grid" method="GET">
            <div class="form-group">
                <label>1. اختر الدولة:</label>
                <select name="country" onchange="this.form.submit()">
                    <option value="">-- اختر دولة --</option>
                    <?php foreach ($countries as $eng => $ar): ?>
                        <option value="<?= $eng ?>" <?= ($selectedCountry == $eng) ? 'selected' : '' ?>><?= $ar ?> (<?= $eng ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>2. اختر البطولة:</label>
                <select name="league" <?= empty($leagues) ? 'disabled' : '' ?> onchange="this.form.submit()">
                    <option value="">-- اختر البطولة --</option>
                    <?php foreach ($leagues as $l): ?>
                        <option value="<?= $l['league']['id'] ?>" <?= ($selectedLeague == $l['league']['id']) ? 'selected' : '' ?>>
                            <?= $l['league']['name'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn">تحديث</button>
        </form>

        <?php if (!empty($selectedLeague) && !empty($teams)): ?>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:2px solid #3f51b5; padding-bottom:10px;">
                <h2 style="margin:0;">قائمة الفرق (<?= count($teams) ?> فريق)</h2>
                <span class="info-pill">ID البطولة: <?= $selectedLeague ?></span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>الشعار</th>
                        <th>اسم الفريق (رسمي)</th>
                        <th>كود التعريب بالـ ID</th>
                        <th>كود التعريب بالاسم</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teams as $t): $name = $t['team']['name']; $id = $t['team']['id']; ?>
                        <tr>
                            <td><img src="<?= $t['team']['logo'] ?>" class="team-logo"></td>
                            <td><strong><?= $name ?></strong></td>
                            <td><span class="copy-code">"<?= $id ?>": "..."</span></td>
                            <td><span class="copy-code">"<?= $name ?>": "..."</span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif(!empty($selectedLeague)): ?>
            <p style="text-align: center; color: #ef5350; font-weight:bold;">لا توجد فرق متاحة لهذا الدوري في الموسم الحالي.</p>
        <?php else: ?>
            <div style="text-align: center; padding: 50px; color: #888;">
                <i class="fa-solid fa-filter" style="font-size: 50px; opacity: 0.2; margin-bottom: 20px; display: block;"></i>
                <p>يرجى اختيار الدولة أولاً ثم البطولة لعرض الفرق المشاركة.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
