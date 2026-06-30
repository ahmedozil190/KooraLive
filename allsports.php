<?php
header('Content-Type: application/json; charset=utf-8');

/**
 * ملف جلب بيانات المباريات من AllSportsAPI
 * يعرض البيانات كاملة بدون أي تعديل أو إخفاء
 */

// 1. إعدادات الاتصال
$apiKey = 'e7a82e673ef25fa08ec5198811fc2f223a7accf5fde7bc4b5a8e7d402593ebdf';

// 2. تحديد التاريخ (يمكنك تغييره من هنا يدويًا أو عبر GET)
$targetDate = isset($_GET['6/29/2026']) ? $_GET['6/29/2026'] : date('Y-m-d'); 

// 3. بناء رابط الطلب (نجلب من وإلى نفس التاريخ لجلب مباريات يوم محدد)
$apiUrl = "https://apiv2.allsportsapi.com/football/?met=Fixtures&APIkey=$apiKey&from=$targetDate&to=$targetDate";

// 4. تنفيذ الطلب باستخدام cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

// 5. معالجة الأخطاء والعرض
if ($err) {
    echo json_encode([
        'status' => 'error',
        'message' => 'تعذر الاتصال بالمزود: ' . $err
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($httpCode !== 200) {
    echo json_encode([
        'status' => 'error',
        'http_code' => $httpCode,
        'message' => 'استجابة غير صالحة من السيرفر'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 6. عرض البيانات الخام (Raw Data) كما وصلت تماماً
// قمنا بعمل decode ثم encode فقط لضمان التنسيق الجميل (Pretty Print) ودعم اللغة العربية
$data = json_decode($response, true);

if ($data === null) {
    // في حال كانت الاستجابة ليست JSON صالحة
    echo $response;
} else {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>
