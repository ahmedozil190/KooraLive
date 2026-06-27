<?php
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

$dataFile = '../data/matches.json';
if (!is_dir('../data')) mkdir('../data', 0777, true);
if (!file_exists($dataFile)) file_put_contents($dataFile, json_encode([]));

$action = $_GET['action'] ?? '';

// جلب المباريات
if ($action === 'get_matches') {
    echo file_get_contents($dataFile);
    exit;
}

// جلب الأخبار
if ($action === 'get_news') {
    $newsFile = '../data/news.json';
    if (file_exists($newsFile)) {
        $news = json_decode(file_get_contents($newsFile), true);
        
        // ترتيب الأحدث أولاً
        usort($news, function($a, $b) { return (isset($b['id'])?$b['id']:0) - (isset($a['id'])?$a['id']:0); });
        
        // تحويل الروابط النسبية لروابط كاملة للتطبيق
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $baseUrl = "$protocol://$host/";
        
        foreach ($news as &$n) {
            if (isset($n['image']) && strpos($n['image'], '../') === 0) {
                $n['image'] = str_replace('../', $baseUrl, $n['image']);
            }
        }
        
        echo json_encode($news, JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([]);
    }
    exit;
}

// حفظ أو تعديل مباراة
if ($action === 'save_match') {
    $input = json_decode(file_get_contents('php://input'), true);
    $matches = json_decode(file_get_contents($dataFile), true);
    
    if (isset($input['id']) && $input['id'] != '') {
        // تعديل
        foreach ($matches as &$m) {
            if ($m['id'] == $input['id']) {
                $m = array_merge($m, $input);
                break;
            }
        }
    } else {
        // إضافة جديد
        $input['id'] = time();
        $matches[] = $input;
    }
    
    file_put_contents($dataFile, json_encode($matches, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo json_encode(['success' => true]);
    exit;
}

// حذف مباراة
if ($action === 'delete_match') {
    $id = $_GET['id'] ?? '';
    $matches = json_decode(file_get_contents($dataFile), true);
    $matches = array_values(array_filter($matches, function($m) use ($id) { return $m['id'] != $id; }));
    file_put_contents($dataFile, json_encode($matches, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo json_encode(['success' => true]);
    exit;
}
?>
