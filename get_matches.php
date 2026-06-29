<?php
header('Content-Type: application/json; charset=utf-8');

// المفتاح الخاص بك من لوحة تحكم API-Football
$apiKey = '757f2fdd5505850e862a81f8569790bf';

// رابط API-Football (v3) لجلب جميع مباريات يوم معين مع كافة التفاصيل
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
    echo json_encode(['error' => 'خطأ في الاتصال: ' . $err], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($response, true);

if (isset($data['response']) && is_array($data['response'])) {
    $matches = [];
    foreach ($data['response'] as $f) {
        $matches[] = [
            'fixture' => [
                'id'        => $f['fixture']['id'],
                'referee'   => $f['fixture']['referee'],
                'timezone'  => $f['fixture']['timezone'],
                'date'      => $f['fixture']['date'],
                'venue'     => $f['fixture']['venue'], // يشمل اسم الملعب والمدينة
                'status'    => $f['fixture']['status'], // يشمل الحالة المختصرة والطويلة والدقائق
            ],
            'league' => $f['league'], // يشمل اسم الدوري، البلد، الموسم، وشعار الدوري
            'teams'  => $f['teams'],  // يشمل الفرق وشعاراتها والـ IDs الخاصة بها
            'goals'  => $f['goals'],  // الأهداف الإجمالية
            'score'  => [
                'halftime'  => $f['score']['halftime'],
                'fulltime'  => $f['score']['fulltime'],
                'extratime' => $f['score']['extratime'],
                'penalty'   => $f['score']['penalty'], // هنا ستجد نتيجة ضربات الترجيح 3 - 4
            ]
        ];
    }
    // عرض كل التفاصيل بدون استثناء
    echo json_encode($matches, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    echo json_encode(['info' => 'لا توجد بيانات'], JSON_UNESCAPED_UNICODE);
}
?>
