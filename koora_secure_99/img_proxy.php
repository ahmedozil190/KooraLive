<?php
/**
 * KooraLive Image Proxy
 * يقوم بتحميل الصور من AllSportsAPI وقص الأجزاء البيضاء/الشفافة تلقائياً
 * ثم يحفظها في Cache لتسريع التحميل في المرات القادمة
 */

// إعدادات الـ Cache
$cacheDir = __DIR__ . '/../data/img_cache/';
$cacheTTL = 86400 * 7; // أسبوع كامل

// التحقق من وجود رابط الصورة
if (!isset($_GET['url']) || empty($_GET['url'])) {
    http_response_code(400);
    die('Missing url parameter');
}

$imageUrl = urldecode($_GET['url']);

// التحقق من أن الرابط من AllSportsAPI فقط (أمان)
$allowedDomains = ['apiv2.allsportsapi.com', 'allsportsapi.com'];
$urlHost = parse_url($imageUrl, PHP_URL_HOST);
$isAllowed = false;
foreach ($allowedDomains as $domain) {
    if ($urlHost === $domain || strpos($urlHost, $domain) !== false) {
        $isAllowed = true;
        break;
    }
}

if (!$isAllowed) {
    http_response_code(403);
    die('Domain not allowed');
}

// إنشاء مجلد الـ Cache إذا لم يكن موجوداً
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// اسم ملف الـ Cache بناءً على رابط الصورة
$cacheKey = md5($imageUrl);
$cacheFile = $cacheDir . $cacheKey . '.png';

// إرسال الصورة من الـ Cache إذا كانت موجودة وحديثة
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=604800');
    header('X-Cache: HIT');
    readfile($cacheFile);
    exit;
}

// تحميل الصورة من AllSportsAPI
$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'user_agent' => 'Mozilla/5.0 KooraLive/1.0',
    ]
]);

$rawImage = @file_get_contents($imageUrl, false, $context);

if ($rawImage === false || empty($rawImage)) {
    http_response_code(404);
    die('Could not fetch image');
}

// التحقق من دعم مكتبة GD
if (!function_exists('imagecreatefromstring')) {
    // إذا لم تكن GD متاحة، أرسل الصورة كما هي
    header('Content-Type: image/jpeg');
    echo $rawImage;
    exit;
}

// تحويل البيانات إلى صورة GD
$srcImage = @imagecreatefromstring($rawImage);

if ($srcImage === false) {
    // إذا فشل التحويل، أرسل الصورة الأصلية
    header('Content-Type: image/jpeg');
    echo $rawImage;
    exit;
}

// ===== منطق قص الأجزاء البيضاء/الشفافة =====
$trimmed = trimWhitespace($srcImage);
imagedestroy($srcImage);

// حفظ الصورة المعالجة في الـ Cache
imagepng($trimmed, $cacheFile, 9);
imagedestroy($trimmed);

// إرسال الصورة المعالجة
header('Content-Type: image/png');
header('Cache-Control: public, max-age=604800');
header('X-Cache: MISS');
readfile($cacheFile);
exit;


/**
 * دالة قص الأجزاء البيضاء والشفافة من حواف الصورة
 */
function trimWhitespace($image) {
    $width  = imagesx($image);
    $height = imagesy($image);

    // عتبة التشابه مع اللون الأبيض (0=أبيض خالص, 255=أسود خالص)
    // نسمح بفارق حتى 30 لمعالجة الأبيض المائل للرمادي
    $threshold = 30;

    $top    = 0;
    $bottom = $height - 1;
    $left   = 0;
    $right  = $width - 1;

    // البحث عن أول صف غير أبيض من الأعلى
    for ($y = 0; $y < $height; $y++) {
        if (!isRowWhite($image, $y, $width, $threshold)) {
            $top = $y;
            break;
        }
    }

    // البحث عن أول صف غير أبيض من الأسفل
    for ($y = $height - 1; $y >= $top; $y--) {
        if (!isRowWhite($image, $y, $width, $threshold)) {
            $bottom = $y;
            break;
        }
    }

    // البحث عن أول عمود غير أبيض من اليسار
    for ($x = 0; $x < $width; $x++) {
        if (!isColWhite($image, $x, $height, $threshold)) {
            $left = $x;
            break;
        }
    }

    // البحث عن أول عمود غير أبيض من اليمين
    for ($x = $width - 1; $x >= $left; $x--) {
        if (!isColWhite($image, $x, $height, $threshold)) {
            $right = $x;
            break;
        }
    }

    // إضافة هامش صغير (padding) حتى لا يلتصق العلم بحافة الدائرة
    $padding = 4;
    $top    = max(0, $top - $padding);
    $bottom = min($height - 1, $bottom + $padding);
    $left   = max(0, $left - $padding);
    $right  = min($width - 1, $right + $padding);

    $newWidth  = $right - $left + 1;
    $newHeight = $bottom - $top + 1;

    // إذا كانت الصورة بلا أجزاء بيضاء، أرجعها كما هي
    if ($newWidth <= 0 || $newHeight <= 0 || ($newWidth === $width && $newHeight === $height)) {
        // إنشاء نسخة من الصورة الأصلية
        $copy = imagecreatetruecolor($width, $height);
        imagealphablending($copy, false);
        imagesavealpha($copy, true);
        imagecopy($copy, $image, 0, 0, 0, 0, $width, $height);
        return $copy;
    }

    // إنشاء صورة جديدة بالحجم المقصوص
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    imagealphablending($newImage, false);
    imagesavealpha($newImage, true);

    // نسخ الجزء المطلوب من الصورة الأصلية
    imagecopy($newImage, $image, 0, 0, $left, $top, $newWidth, $newHeight);

    return $newImage;
}

/**
 * التحقق إذا كان الصف كله أبيض/شفاف
 */
function isRowWhite($image, $y, $width, $threshold) {
    for ($x = 0; $x < $width; $x++) {
        $rgba  = imagecolorat($image, $x, $y);
        $alpha = ($rgba >> 24) & 0x7F;
        $r     = ($rgba >> 16) & 0xFF;
        $g     = ($rgba >> 8)  & 0xFF;
        $b     = $rgba & 0xFF;

        // إذا كان البكسل شفافاً تجاهله
        if ($alpha > 100) continue;

        // إذا لم يكن البكسل قريباً من الأبيض
        if ($r < (255 - $threshold) || $g < (255 - $threshold) || $b < (255 - $threshold)) {
            return false;
        }
    }
    return true;
}

/**
 * التحقق إذا كان العمود كله أبيض/شفاف
 */
function isColWhite($image, $x, $height, $threshold) {
    for ($y = 0; $y < $height; $y++) {
        $rgba  = imagecolorat($image, $x, $y);
        $alpha = ($rgba >> 24) & 0x7F;
        $r     = ($rgba >> 16) & 0xFF;
        $g     = ($rgba >> 8)  & 0xFF;
        $b     = $rgba & 0xFF;

        if ($alpha > 100) continue;

        if ($r < (255 - $threshold) || $g < (255 - $threshold) || $b < (255 - $threshold)) {
            return false;
        }
    }
    return true;
}
