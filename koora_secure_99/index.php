<?php
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
ob_start();
error_reporting(E_ALL);
// تصحيح المسارات لتعمل من داخل مجلد admin
// منع التخزين المؤقت لضمان ظهور أحدث البيانات دائماً
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$matchesFile = '../data/matches.json';
$newsFile = '../data/news.json';
$clubsFile = '../data/clubs.json';
$leaguesFile = '../data/leagues.json';
$settingsFile = '../data/api_settings.json';
$fixturesBank = '../data/api_fixtures.json';

// تأكد من وجود المجلد
if (!is_dir('../data')) mkdir('../data', 0777, true);
$arMapFile      = '../ar_map.json'; // خارج مجلد data

clearstatcache(true, $matchesFile);
$matches = json_decode(@file_get_contents($matchesFile), true) ?: [];

if (isset($_POST['login'])) {
    if ($_POST['user'] === 'admin' && $_POST['pass'] === '123456') { 
        $_SESSION['a'] = true; 
        header("Location: index.php"); exit; 
    }
}
if (isset($_GET['logout'])) { session_destroy(); header("Location: index.php"); exit; }
$auth = isset($_SESSION['a']);
$sec = isset($_GET['section']) ? $_GET['section'] : 'main';
$news = json_decode(@file_get_contents($newsFile), true);
if(!$news) $news = array();

if ($auth) {
        // --------------------------------------------------

    if (isset($_GET['del_m'])) {
        $ms = json_decode(@file_get_contents($matchesFile), true) ?: [];
        $ms = array_filter($ms, function($v) { return $v['id'] != $_GET['del_m']; });
        file_put_contents($matchesFile, json_encode(array_values($ms), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX);
        $backSec = isset($_GET['section']) ? $_GET['section'] : 'main';
        $backDay = isset($_GET['day']) ? $_GET['day'] : 'today';
        header("Location: index.php?section=$backSec&day=$backDay"); exit;
    }
    if (isset($_GET['del_n'])) {
        $ns = json_decode(@file_get_contents($newsFile), true) ?: [];
        $ns = array_filter($ns, function($v) { return $v['id'] != $_GET['del_n']; });
        file_put_contents($newsFile, json_encode(array_values($ns), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX);
        header("Location: index.php?section=news"); exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_n'])) {
            $imgPath = isset($_POST['i']) ? $_POST['i'] : '';
            if (isset($_FILES['img_file']) && $_FILES['img_file']['error'] === 0) {
                $dir = '../uploads/';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $ext = pathinfo($_FILES['img_file']['name'], PATHINFO_EXTENSION);
                $newName = time() . '_' . rand(100, 999) . '.' . $ext;
                if (move_uploaded_file($_FILES['img_file']['tmp_name'], $dir . $newName)) {
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'];
                    $imgPath = "$protocol://$host/uploads/" . $newName;
                }
            }
            $d = json_decode(@file_get_contents($newsFile), true);
            if(!$d) $d = array();
            $d[] = array('id'=>time(),'title'=>$_POST['t'],'image'=>$imgPath,'content'=>$_POST['c'],'date'=>date('Y-m-d'));
            file_put_contents($newsFile, json_encode($d, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX);
            header("Location: index.php?section=news&success=1"); exit;
        }
        if (isset($_POST['save_edit'])) {
            $mid = $_POST['edit_match_id'];
            $ms = json_decode(@file_get_contents($matchesFile), true) ?: [];
            foreach ($ms as &$m) {
                if ($m['id'] == $mid) {
                    $m['status']      = isset($_POST['edit_status']) ? $_POST['edit_status'] : $m['status'];
                    $m['channel']     = isset($_POST['edit_channel']) ? $_POST['edit_channel'] : (isset($m['channel']) ? $m['channel'] : '');
                    $m['commentator'] = isset($_POST['edit_commentator']) ? $_POST['edit_commentator'] : (isset($m['commentator']) ? $m['commentator'] : '');
                    $m['score']       = isset($_POST['edit_score']) ? $_POST['edit_score'] : (isset($m['score']) ? $m['score'] : 'vs');
                    $m['streamUrl']  = isset($_POST['edit_stream']) ? $_POST['edit_stream'] : (isset($m['streamUrl']) ? $m['streamUrl'] : '');
                    
                    $statusMap = array('live'=>'مباشر','upcoming'=>'قادمة','finished'=>'انتهت');
                    $m['status_text'] = isset($statusMap[$m['status']]) ? $statusMap[$m['status']] : $m['status'];
                    break;
                }
            }
            file_put_contents($matchesFile, json_encode(array_values($ms), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), LOCK_EX);
            header("Location: index.php?section=current&success=1"); exit;
        }
        if (isset($_POST['save_news_edit'])) {
            $nid = $_POST['edit_news_id'];
            $ns = json_decode(@file_get_contents($newsFile), true) ?: [];
            foreach ($ns as &$n) {
                if ($n['id'] == $nid) {
                    $n['title']   = $_POST['n_t'];
                    $n['content'] = $_POST['n_c'];
                    if(!empty($_POST['n_i'])) $n['image'] = $_POST['n_i'];
                    if (isset($_FILES['n_img_file']) && $_FILES['n_img_file']['error'] === 0) {
                        $dir = '../uploads/';
                        $ext = pathinfo($_FILES['n_img_file']['name'], PATHINFO_EXTENSION);
                        $newName = time() . '_' . rand(100, 999) . '.' . $ext;
                        if (move_uploaded_file($_FILES['n_img_file']['tmp_name'], $dir . $newName)) {
                            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                            $host = $_SERVER['HTTP_HOST'];
                            $n['image'] = "$protocol://$host/uploads/" . $newName;
                        }
                    }
                    break;
                }
            }
            file_put_contents($newsFile, json_encode(array_values($ns), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
            header("Location: index.php?section=news"); exit;
        }
        if (isset($_POST['clean_imgs'])) {
            $files = glob('../uploads/*');
            $ns = json_decode(@file_get_contents($newsFile), true) ?: [];
            $ms = json_decode(@file_get_contents($matchesFile), true) ?: [];
            $cs = json_decode(@file_get_contents($clubsFile), true) ?: [];
            $used = [];
            foreach($ns as $n) if(!empty($n['image'])) $used[] = basename($n['image']);
            foreach($cs as $c) if(!empty($c['logo'])) $used[] = basename($c['logo']);
            foreach($ms as $m) {
                if(!empty($m['homeLogo'])) $used[] = basename($m['homeLogo']);
                if(!empty($m['awayLogo'])) $used[] = basename($m['awayLogo']);
            }
            $count = 0;
            foreach($files as $f) {
                if(!in_array(basename($f), $used)) { unlink($f); $count++; }
            }
            header("Location: index.php?section=news&cleaned=$count"); exit;
        }
        if (isset($_POST['save_api_mgr'])) {
            $s = json_decode(@file_get_contents($settingsFile), true) ?: []; 
            $s['api_key'] = trim($_POST['api_key']);
            file_put_contents($settingsFile, json_encode($s, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
            // تشغيل المزامنة بشكل آمن عبر رابط خارجي لعدم إبطاء الصفحة أو التسبب في أخطاء
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $syncUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/cron_sync.php";
            @file_get_contents($syncUrl); 
            
            header("Location: index.php?section=api_mgr&success=1"); exit;
        }
        if (isset($_POST['save_fav_leagues'])) {
            $s = json_decode(@file_get_contents($settingsFile), true) ?: [];
            $s['fav_leagues'] = isset($_POST['favs']) ? implode(',', $_POST['favs']) : '';
            file_put_contents($settingsFile, json_encode($s, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
            header("Location: index.php?section=fav_leagues&success=1"); exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <script>const t = localStorage.getItem('theme') || 'dark'; document.documentElement.setAttribute('data-theme', t);</script>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - كورة لايف</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=2.1">
    <style>
        .status-badge.status-live { background: rgba(16, 185, 129, 0.15) !important; color: #10b981 !important; border: 1px solid rgba(16, 185, 129, 0.2) !important; position:relative; }
        .status-badge.status-live::before { content:''; width:6px; height:6px; background:#10b981; border-radius:50%; margin-left:6px; animation: pulse 1.5s infinite; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); } 70% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); } 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); } }
        .status-badge.status-final { background: rgba(107, 114, 128, 0.1) !important; color: #6b7280 !important; border: 1px solid rgba(107, 114, 128, 0.2) !important; }
        .status-badge.status-up { background: rgba(99, 102, 241, 0.1) !important; color: #6366f1 !important; border: 1px solid rgba(99, 102, 241, 0.2) !important; }
    </style>
    <script>
        function formatLocalTime(ts) {
            if(!ts) return '--:--';
            try {
                const d = new Date(ts * 1000);
                return d.toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit', hour12: true});
            } catch(e) { return '--:--'; }
        }
    </script>
</head>
<body>
<?php if (!$auth): ?>
    <div style="flex:1; display:flex; align-items:center; justify-content:center;">
        <form method="POST" style="background:var(--card); padding:40px; border-radius:20px; border:1px solid var(--border); width:380px;">
            <div style="text-align:center; margin-bottom: 20px;">
                <i class="fa-solid fa-gauge-high" style="font-size: 50px; color: #6366f1;"></i>
            </div>
            <h2 style="text-align:center; font-weight:800; margin-bottom:30px;">دخول النظام</h2>
            <input type="text" name="user" style="width:100%; padding:14px; background:var(--bg-main); border:1px solid var(--border-color); color:var(--text-main); border-radius:10px; margin-bottom:15px; box-sizing:border-box;" placeholder="اسم المستخدم" required>
            <input type="password" name="pass" style="width:100%; padding:14px; background:var(--bg-main); border:1px solid var(--border-color); color:var(--text-main); border-radius:10px; margin-bottom:25px; box-sizing:border-box;" placeholder="كلمة المرور" required>
            <button type="submit" name="login" style="width:100%; padding:14px; background:#6366f1; color:#fff; border:none; border-radius:10px; font-weight:800; cursor:pointer;">دخول</button>
        </form>
    </div>
<?php else: ?>
    <aside class="side">
        <div style="padding:30px; font-size:24px; font-weight:800; color:#6366f1; text-align:center; border-bottom:1px solid var(--border);">كورة لايف</div>
        <div style="padding-top:20px;">
            <a href="index.php?section=main"    class="nav-item <?php echo $sec=='main'   ?'active':''; ?>"><i class="fa-solid fa-chart-pie"></i> نظرة عامة</a>
            <a href="index.php?section=current"  class="nav-item <?php echo $sec=='current'?'active':''; ?>"><i class="fa-solid fa-list-check"></i> المباريات الحالية</a>
            <a href="index.php?section=api_add"  class="nav-item <?php echo $sec=='api_add'?'active':''; ?>"><i class="fa-solid fa-cloud-arrow-down"></i> إضافة مباراة</a>
            <a href="index.php?section=news"     class="nav-item <?php echo $sec=='news'   ?'active':''; ?>"><i class="fa-solid fa-newspaper"></i> أخر الأخبار</a>
            <a href="index.php?section=fav_leagues" class="nav-item <?php echo $sec=='fav_leagues'?'active':''; ?>"><i class="fa-solid fa-star"></i> الدوريات المفضلة</a>
            <a href="index.php?section=api_mgr"  class="nav-item <?php echo $sec=='api_mgr'?'active':''; ?>"><i class="fa-solid fa-plug-circle-bolt"></i> إدارة API</a>
        </div>
        <div class="sidebar-footer">
            <div id="adm-theme" class="f-icon"><i class="fa-solid fa-moon"></i></div>
            <a href="index.php?logout=1" class="f-icon" style="color:#ef4444;"><i class="fa-solid fa-power-off"></i></a>
        </div>
    </aside>
    <main class="main">
        <div class="toast-container" id="toast-container"></div>
        <?php if($sec == 'main'): ?>
            <h2 style="font-weight:800; margin-bottom:25px;">نظرة عامة</h2>
            <?php 
                clearstatcache(true, $matchesFile);
                $matches = json_decode(@file_get_contents($matchesFile), true) ?: [];
                $total = count($matches); $live = 0; $wait = 0; $done = 0;
                foreach($matches as $m) {
                    $s = isset($m['status']) ? $m['status'] : '';
                    if($s == 'live') $live++; elseif($s == 'finished') $done++; else $wait++;
                }
            ?>
            <div class="stats-grid">
                <div class="stat-card total"><i class="fa-solid fa-futbol"></i><h3><?php echo $total; ?></h3><p>إجمالي المباريات</p></div>
                <div class="stat-card live"><i class="fa-solid fa-tower-broadcast"></i><h3><?php echo $live; ?></h3><p>مباريات جارية</p></div>
                <div class="stat-card waiting"><i class="fa-solid fa-clock"></i><h3><?php echo $wait; ?></h3><p>بانتظار البداية</p></div>
                <div class="stat-card finished"><i class="fa-solid fa-check-double"></i><h3><?php echo $done; ?></h3><p>مباريات منتهية</p></div>
            </div>
            <div class="recent-card">
                <div class="recent-header" style="justify-content:space-between; flex-wrap:wrap; gap:10px;">
                    <div style="display:flex; align-items:center; gap:12px;"><i class="fa-solid fa-futbol"></i><h3>آخر المباريات المضافة</h3></div>
                    <div class="day-tabs" style="margin-bottom:0;">
                        <div class="day-tab" data-day="yesterday" onclick="switchDay(this)">مباريات الأمس</div>
                        <div class="day-tab active" data-day="today" onclick="switchDay(this)">مباريات اليوم</div>
                        <div class="day-tab" data-day="tomorrow" onclick="switchDay(this)">مباريات الغد</div>
                    </div>
                </div>
                <div style="overflow-x:auto;">
                    <table class="table">
                        <thead><tr><th>المباراة</th><th>البطولة</th><th>الوقت</th><th>الحالة</th><th>البث</th><th>التحكم</th></tr></thead>
                        <tbody id="ov-tbody">
                        <?php 
                            function sortMatches($list) {
                                usort($list, function($a, $b) {
                                    $score = ['live' => 0, 'upcoming' => 1, 'finished' => 2];
                                    $sA = isset($a['status']) ? ($score[$a['status']] ?? 1) : 1;
                                    $sB = isset($b['status']) ? ($score[$b['status']] ?? 1) : 1;
                                    if ($sA != $sB) return $sA - $sB;
                                    return ($a['timestamp'] ?? 0) - ($b['timestamp'] ?? 0);
                                });
                                return $list;
                            }
                            $dayM = sortMatches($matches);
                        ?>
                        <?php foreach($dayM as $m): 
                            $statusType = isset($m['status']) ? $m['status'] : 'upcoming';
                            $badgeClass = ($statusType === 'live') ? 'status-live' : (($statusType === 'finished') ? 'status-final' : 'status-up');
                             $statusMap = array('live'=>'مباشر الآن','upcoming'=>'لم تبدأ بعد','finished'=>'انتهت المباراة');
                             $stTxt = !empty($m['status_ar']) ? $m['status_ar'] : (isset($statusMap[$statusType]) ? $statusMap[$statusType] : 'لم تبدأ بعد');
                             if ($statusType === 'live' && !empty($m['status_raw'])) {
                                 if (is_numeric($m['status_raw'])) $stTxt = 'مباشر ' . $m['status_raw'] . '\'';
                                 elseif ($m['status_raw'] === 'Half Time') $stTxt = 'بين الشوطين';
                             }
                        ?>
                         <tr class="match-row" data-ts="<?php echo $m['timestamp'] ?? 0; ?>" data-status="<?php echo $statusType; ?>">
                             <td style="padding:12px 25px;">
                                <div style="display:grid; grid-template-columns:1fr auto 1fr; align-items:center; gap:12px;">
                                    <div style="display:flex; align-items:center; gap:8px; justify-content:flex-end;">
                                        <span style="font-weight:700; font-size:13px; color:var(--text-main); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:145px;"><?php echo $m['homeTeam']; ?></span>
                                        <img src="<?php echo $m['homeLogo']; ?>" style="width:26px; height:26px; object-fit:contain; flex-shrink:0;">
                                    </div>
                                    <span style="background:var(--bg-main); padding:4px 10px; border-radius:8px; color:var(--text-main); font-size:13px; font-weight:800; width:60px; min-width:60px; max-width:60px; text-align:center; border:1px solid var(--border-color); white-space:nowrap; display:inline-block; box-sizing:border-box;">
                                        <?php $sc=trim($m['score']??''); echo(empty($sc)||$sc==='-'||strtolower($sc)==='vs')?'VS':$sc; ?>
                                    </span>
                                    <div style="display:flex; align-items:center; gap:8px; justify-content:flex-start;">
                                        <img src="<?php echo $m['awayLogo']; ?>" style="width:26px; height:26px; object-fit:contain; flex-shrink:0;">
                                        <span style="font-weight:700; font-size:13px; color:var(--text-main); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:145px;"><?php echo $m['awayTeam']; ?></span>
                                    </div>
                                </div>
                             </td>
                             <td><?php echo htmlspecialchars(isset($m['league'])?$m['league']:'--'); ?></td>
                             <td style="font-weight:800; color:var(--text-main);">
                                 <script>document.write(formatLocalTime(<?php echo isset($m['timestamp'])?$m['timestamp']:'null'; ?>));</script>
                             </td>
                             <td><span class="status-badge <?php echo $badgeClass; ?>"><?php echo $stTxt; ?></span></td>
                             <td style="font-size:16px; text-align:center;"><?php echo !empty($m['streamUrl']) && $m['streamUrl'] !== '#' ? '✅' : '❌'; ?></td>
                             <td>
                                 <div style="display:flex; gap:8px;">
                                     <button class="btn-edit" onclick="openEditModal(this)" data-match='<?php echo htmlspecialchars(json_encode($m), ENT_QUOTES); ?>'><i class="fa-solid fa-pen"></i></button>
                                     <a href="index.php?del_m=<?php echo $m['id']; ?>&section=main" class="btn-del" onclick="return confirm('حذف؟')"><i class="fa-solid fa-trash"></i></a>
                                 </div>
                             </td>
                         </tr>
                        <?php endforeach; ?>
                        <tr data-day="today" data-empty="1" style="display:none;"><td colspan="6" style="text-align:center; padding:50px 0;"><div style="font-size:40px; color:var(--text-sub); opacity:0.3; margin-bottom:10px;"><i class="fa-solid fa-calendar-day"></i></div><div style="font-weight:700; color:var(--text-sub);">لا توجد مباريات مضافة لليوم</div></td></tr>
                        <tr data-day="yesterday" data-empty="1" style="display:none;"><td colspan="6" style="text-align:center; padding:50px 0;"><div style="font-size:40px; color:var(--text-sub); opacity:0.3; margin-bottom:10px;"><i class="fa-solid fa-clock-rotate-left"></i></div><div style="font-weight:700; color:var(--text-sub);">لا توجد مباريات مضافة بالأمس</div></td></tr>
                        <tr data-day="tomorrow" data-empty="1" style="display:none;"><td colspan="6" style="text-align:center; padding:50px 0;"><div style="font-size:40px; color:var(--text-sub); opacity:0.3; margin-bottom:10px;"><i class="fa-solid fa-calendar-plus"></i></div><div style="font-weight:700; color:var(--text-sub);">لا توجد مباريات مضافة لغداً</div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif($sec == 'current'):
            $allM = json_decode(@file_get_contents($matchesFile), true) ?: [];
            $cur_total = count($allM); $cur_live = count(array_filter($allM, function($m) { return (isset($m['status'])?$m['status']:'') === 'live'; }));
            $cur_wait = count(array_filter($allM, function($m) { return (isset($m['status'])?$m['status']:'') === 'upcoming'; }));
            $cur_done = count(array_filter($allM, function($m) { return (isset($m['status'])?$m['status']:'') === 'finished'; }));
        ?>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
                <h2 style="font-weight:800;">إدارة المباريات</h2>
            </div>
            <div class="stats-grid">
                <div class="stat-card total"><i class="fa-solid fa-futbol"></i><h3><?php echo $cur_total; ?></h3><p>إجمالي المباريات</p></div>
                <div class="stat-card live"><i class="fa-solid fa-tower-broadcast"></i><h3><?php echo $cur_live; ?></h3><p>مباريات جارية</p></div>
                <div class="stat-card waiting"><i class="fa-solid fa-clock"></i><h3><?php echo $cur_wait; ?></h3><p>بانتظار البداية</p></div>
                <div class="stat-card finished"><i class="fa-solid fa-check-double"></i><h3><?php echo $cur_done; ?></h3><p>مباريات منتهية</p></div>
            </div>
            <div class="recent-card">
                <div class="recent-header" style="justify-content:space-between; flex-wrap:wrap; gap:10px;">
                    <div style="display:flex; align-items:center; gap:12px;"><i class="fa-solid fa-list-check"></i><h3>المباريات الحالية</h3></div>
                    <div class="day-tabs" style="margin-bottom:0;">
                        <div class="day-tab" data-day="yesterday" onclick="switchTab(this)">مباريات الأمس</div>
                        <div class="day-tab active" data-day="today" onclick="switchTab(this)">مباريات اليوم</div>
                        <div class="day-tab" data-day="tomorrow" onclick="switchTab(this)">مباريات الغد</div>
                    </div>
                </div>
                <div style="overflow-x:auto;">
                    <table>
                        <thead><tr><th>المباراة</th><th>البطولة</th><th>الوقت</th><th>الحالة</th><th>البث</th><th>التحكم</th></tr></thead>
                        <tbody id="cur-tbody">
                        <?php 
                        if (!function_exists('sortMatches')) {
                            function sortMatches($list) {
                                usort($list, function($a, $b) {
                                    $score = ['live' => 0, 'upcoming' => 1, 'finished' => 2];
                                    $sA = isset($a['status']) ? ($score[$a['status']] ?? 1) : 1;
                                    $sB = isset($b['status']) ? ($score[$b['status']] ?? 1) : 1;
                                    if ($sA != $sB) return $sA - $sB;
                                    return ($a['timestamp'] ?? 0) - ($b['timestamp'] ?? 0);
                                });
                                return $list;
                            }
                        }
                        $dayM = sortMatches($allM);
                        ?>
                        <?php foreach($dayM as $m):
                            $statusType = isset($m['status']) ? $m['status'] : 'upcoming';
                            $badgeClass = ($statusType === 'live') ? 'status-live' : (($statusType === 'finished') ? 'status-final' : 'status-up');
                            $statusMap = array('live'=>'مباشر الآن','upcoming'=>'لم تبدأ بعد','finished'=>'انتهت المباراة');
                            $badgeText = !empty($m['status_ar']) ? $m['status_ar'] : (isset($statusMap[$statusType]) ? $statusMap[$statusType] : 'لم تبدأ بعد');
                            if ($statusType === 'live' && !empty($m['status_raw'])) {
                                if (is_numeric($m['status_raw'])) $badgeText = 'مباشر ' . $m['status_raw'] . '\'';
                                elseif ($m['status_raw'] === 'Half Time') $badgeText = 'بين الشوطين';
                            }
                        ?>
                         <tr class="match-row" data-ts="<?php echo $m['timestamp'] ?? 0; ?>" data-status="<?php echo $statusType; ?>">
                             <td style="padding:12px 25px;">
                                <div style="display:grid; grid-template-columns:1fr auto 1fr; align-items:center; gap:12px;">
                                    <div style="display:flex; align-items:center; gap:8px; justify-content:flex-end;">
                                        <span style="font-weight:700; font-size:13px; color:var(--text-main); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:145px;"><?php echo $m['homeTeam']; ?></span>
                                        <img src="<?php echo $m['homeLogo']; ?>" style="width:26px; height:26px; object-fit:contain; flex-shrink:0;">
                                    </div>
                                    <span style="background:var(--bg-main); padding:4px 10px; border-radius:8px; color:var(--text-main); font-size:13px; font-weight:800; width:60px; min-width:60px; max-width:60px; text-align:center; border:1px solid var(--border-color); white-space:nowrap; display:inline-block; box-sizing:border-box;">
                                        <?php $sc=trim($m['score']??''); echo(empty($sc)||$sc==='-'||strtolower($sc)==='vs')?'VS':$sc; ?>
                                    </span>
                                    <div style="display:flex; align-items:center; gap:8px; justify-content:flex-start;">
                                        <img src="<?php echo $m['awayLogo']; ?>" style="width:26px; height:26px; object-fit:contain; flex-shrink:0;">
                                        <span style="font-weight:700; font-size:13px; color:var(--text-main); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:145px;"><?php echo $m['awayTeam']; ?></span>
                                    </div>
                                </div>
                             </td>
                              <td><?php echo htmlspecialchars(isset($m['league'])?$m['league']:'--'); ?></td>
                             <td style="font-weight:800; color:var(--text-main);">
                                 <script>document.write(formatLocalTime(<?php echo isset($m['timestamp'])?$m['timestamp']:'null'; ?>));</script>
                             </td>
                            <td><span class="status-badge <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span></td>
                            <td style="font-size:16px; text-align:center;"><?php echo !empty($m['streamUrl']) && $m['streamUrl'] !== '#' ? '✅' : '❌'; ?></td>
                            <td>
                                <div style="display:flex; gap:8px;">
                                    <button class="btn-edit" onclick="openEditModal(this)" data-match='<?php echo htmlspecialchars(json_encode($m), ENT_QUOTES); ?>'><i class="fa-solid fa-pen"></i></button>
                                    <a href="index.php?del_m=<?php echo $m['id']; ?>&section=current" class="btn-del" onclick="return confirm('حذف؟')"><i class="fa-solid fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr data-day="today" data-empty="1" style="display:none;"><td colspan="6" style="text-align:center; padding:50px 0;"><div style="font-size:40px; color:var(--text-sub); opacity:0.3; margin-bottom:10px;"><i class="fa-solid fa-calendar-day"></i></div><div style="font-weight:700; color:var(--text-sub);">لا توجد مباريات مضافة لليوم</div></td></tr>
                        <tr data-day="yesterday" data-empty="1" style="display:none;"><td colspan="6" style="text-align:center; padding:50px 0;"><div style="font-size:40px; color:var(--text-sub); opacity:0.3; margin-bottom:10px;"><i class="fa-solid fa-clock-rotate-left"></i></div><div style="font-weight:700; color:var(--text-sub);">لا توجد مباريات مضافة بالأمس</div></td></tr>
                        <tr data-day="tomorrow" data-empty="1" style="display:none;"><td colspan="6" style="text-align:center; padding:50px 0;"><div style="font-size:40px; color:var(--text-sub); opacity:0.3; margin-bottom:10px;"><i class="fa-solid fa-calendar-plus"></i></div><div style="font-weight:700; color:var(--text-sub);">لا توجد مباريات مضافة لغداً</div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="searchModal" class="modal-overlay">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 id="modalTitle">اختيار</h3>
                        <div class="modal-close" onclick="closeModals()"><i class="fa-solid fa-times"></i></div>
                    </div>
                    <div style="padding:15px;">
                        <div class="search-box">
                            <i class="fa-solid fa-search"></i>
                            <input type="text" id="modalSearch" placeholder="ابحث هنا..." oninput="filterModalItems()">
                        </div>
                        <div id="modalItemsList" class="modal-list"></div>
                    </div>
                </div>
            </div>

            <style>
                .custom-select-trigger { background:var(--bg-card); border:1px solid var(--border-color); padding:12px 15px; border-radius:12px; display:flex; justify-content:space-between; align-items:center; cursor:pointer; transition:0.3s; font-weight:700; color:var(--text-main); }
                .custom-select-trigger:hover { border-color:#6366f1; background:rgba(99,102,241,0.05); }
                
                .modal-overlay { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); backdrop-filter:blur(5px); z-index:9999; display:none; align-items:center; justify-content:center; }
                .modal-content { background:var(--bg-card); width:90%; max-width:450px; border-radius:20px; overflow:hidden; border:1px solid var(--border-color); box-shadow:0 20px 50px rgba(0,0,0,0.3); animation:fadeInScale 0.3s ease; position:relative; }
                .modal-body { padding:25px; }
                @keyframes fadeInScale { from{opacity:0; transform:scale(0.95);} to{opacity:1; transform:scale(1);} }
                
                .modal-header { padding:20px; background:var(--bg-body); border-bottom:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center; }
                .modal-close { width:32px; height:32px; border-radius:50%; background:rgba(255,0,0,0.1); color:#ff4757; display:flex; align-items:center; justify-content:center; cursor:pointer; }
                
                .search-box { display:flex; align-items:center; gap:10px; background:var(--bg-body); padding:10px 15px; border-radius:12px; border:1px solid var(--border-color); margin-bottom:15px; }
                .search-box input { background:transparent; border:none; outline:none; color:var(--text-main); font-weight:700; width:100%; }
                
                .modal-list { max-height:350px; overflow-y:auto; padding-right:5px; }
                .modal-item { display:flex; align-items:center; gap:15px; padding:12px; border-radius:10px; cursor:pointer; transition:0.2s; margin-bottom:5px; }
                .modal-item:hover { background:rgba(99,102,241,0.1); color:#6366f1; }
                .modal-item img { width:30px; height:30px; object-fit:contain; }
                .modal-item span { font-weight:700; }
            </style>

            <script>
                let currentModalTarget = '';
                let clubsData = <?php echo json_encode($cs); ?>;
                let leaguesData = <?php echo json_encode($ls); ?>;
                const statusOptions = [{id:'upcoming', name:'قادمة'}, {id:'live', name:'جارية الآن'}, {id:'finished', name:'انتهت'}];
                const dayOptions = [{id:'today', name:'اليوم'}, {id:'yesterday', name:'الأمس'}, {id:'tomorrow', name:'الغد'}];

                function openSearchModal(target) {
                    currentModalTarget = target;
                    const modal = document.getElementById('searchModal');
                    const title = document.getElementById('modalTitle');
                    const list = document.getElementById('modalItemsList');
                    const search = document.getElementById('modalSearch');
                    
                    search.value = '';
                    search.parentElement.style.display = 'flex';
                    
                    if(target == 'h' || target == 'a') {
                        title.innerText = 'اختيار الفريق';
                        renderList(clubsData);
                    } else if(target == 'l') {
                        title.innerText = 'اختيار البطولة';
                        renderList(leaguesData);
                    }
                    modal.style.display = 'flex';
                }

                function openSimpleModal(target) {
                    currentModalTarget = target;
                    const modal = document.getElementById('searchModal');
                    const title = document.getElementById('modalTitle');
                    const search = document.getElementById('modalSearch');
                    
                    search.parentElement.style.display = 'none';
                    
                    if(target == 's') {
                        title.innerText = 'اختيار الحالة';
                        renderList(statusOptions);
                    } else if(target == 'd') {
                        title.innerText = 'اختيار اليوم';
                        renderList(dayOptions);
                    }
                    modal.style.display = 'flex';
                }

                function renderList(data) {
                    const list = document.getElementById('modalItemsList');
                    list.innerHTML = '';
                    data.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'modal-item';
                        let imgHtml = item.logo ? `<img src="${item.logo}">` : '';
                        div.innerHTML = `${imgHtml}<span>${item.name}</span>`;
                        div.onclick = () => selectItem(item);
                        list.appendChild(div);
                    });
                }

                function filterModalItems() {
                    const query = document.getElementById('modalSearch').value.toLowerCase();
                    const data = (currentModalTarget == 'h' || currentModalTarget == 'a') ? clubsData : leaguesData;
                    const filtered = data.filter(i => i.name.toLowerCase().includes(query));
                    renderList(filtered);
                }

                function selectItem(item) {
                    document.getElementById(currentModalTarget + '-input').value = (item.id && (currentModalTarget == 's' || currentModalTarget == 'd')) ? item.id : item.name;
                    document.getElementById(currentModalTarget + '-display').innerText = item.name;
                    closeModals();
                }

                function closeModals() {
                    document.getElementById('searchModal').style.display = 'none';
                }
                
                window.onclick = function(event) {
                    if (event.target == document.getElementById('searchModal')) closeModals();
                }
            </script>
        <?php elseif($sec == 'api_add'): 
            $apiS = json_decode(@file_get_contents($settingsFile), true) ?: [];
            $hasKey = !empty($apiS['api_key']);
            $bank = json_decode(@file_get_contents($fixturesBank), true) ?: [];
            $arMap = file_exists($arMapFile) ? (json_decode(file_get_contents($arMapFile), true) ?: []) : []; 
            $c_total = count($bank);
            $c_today = count(array_filter($bank, fn($m) => ($m['day']??'') == 'today'));
            $c_yest  = count(array_filter($bank, fn($m) => ($m['day']??'') == 'yesterday'));
            $c_tom   = count(array_filter($bank, fn($m) => ($m['day']??'') == 'tomorrow'));
        ?>
            <h2 style="font-weight:800; margin-bottom:25px;">إضافة مباريات من الـ API</h2>
            
            <?php if(!$hasKey): ?>
            <div style="background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.2); padding:20px; border-radius:15px; margin-bottom:30px; display:flex; align-items:center; gap:15px;">
                <i class="fa-solid fa-triangle-exclamation" style="font-size:24px; color:#ef4444;"></i>
                <div>
                    <h4 style="margin:0; color:#ef4444; font-weight:800;">مفتاح الـ API غير موجود!</h4>
                    <p style="margin:5px 0 0; font-size:13px; color:var(--text-sub);">يرجى الانتقال لصفحة <strong>"إدارة API"</strong> وإضافة المفتاح الخاص بك لتتمكن من جلب المباريات.</p>
                </div>
                <a href="index.php?section=api_mgr" style="margin-right:auto; background:#ef4444; color:#fff; padding:8px 18px; border-radius:10px; font-weight:700; text-decoration:none; font-size:13px;">انتقل للإعدادات</a>
            </div>
            <?php endif; ?>
            
            <div class="stats-grid" style="margin-bottom:30px;">
                <div class="stat-card total"><i class="fa-solid fa-database"></i><h3><?php echo $c_total; ?></h3><p>إجمالي المباريات</p></div>
                <div class="stat-card live"><i class="fa-solid fa-calendar-check"></i><h3><?php echo $c_today; ?></h3><p>مباريات اليوم</p></div>
                <div class="stat-card waiting"><i class="fa-solid fa-calendar-minus"></i><h3><?php echo $c_yest; ?></h3><p>مباريات الأمس</p></div>
                <div class="stat-card finished"><i class="fa-solid fa-calendar-plus"></i><h3><?php echo $c_tom; ?></h3><p>مباريات الغد</p></div>
            </div>

            <div class="recent-card">
                <div class="recent-header" style="justify-content:space-between; flex-wrap:wrap; gap:10px;">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <i class="fa-solid fa-cloud-bolt"></i> 
                        <h3 style="margin:0;">إضافة مباراة</h3>
                    </div>
                    <div class="day-tabs" style="margin-bottom:0;">
                        <div class="day-tab" data-day="yesterday" onclick="switchApiTab(this)">مباريات الأمس</div>
                        <div class="day-tab active" data-day="today" onclick="switchApiTab(this)">مباريات اليوم</div>
                        <div class="day-tab" data-day="tomorrow" onclick="switchApiTab(this)">مباريات الغد</div>
                    </div>
                </div>
                
                <div style="overflow-x:auto;">
                    <table>
                        <thead><tr><th>المباراة</th><th>البطولة</th><th>الوقت</th><th>الحالة</th><th>التحكم</th></tr></thead>
                        <tbody id="api-bank-body">
                            <?php if(empty($bank)): ?>
                                <tr><td colspan="5" style="text-align:center; padding:40px 0; color:var(--text-dim);">جاري جلب البيانات من الـ API...</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="addApiModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:10000; align-items:center; justify-content:center; backdrop-filter:blur(6px);">
                <div style="background:var(--bg-card); width:90%; max-width:450px; border-radius:20px; overflow:hidden; border:1px solid var(--border-color); box-shadow:0 30px 60px rgba(0,0,0,0.5); animation:fadeInScale 0.3s ease;">
                    <div style="background:var(--bg-body); padding:20px 25px; border-bottom:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                        <h3 style="margin:0; font-size:18px; font-weight:800; color:var(--text-main);">
                            <i class="fa-solid fa-plus-circle" style="color:#6366f1; margin-left:8px;"></i> إضافة بيانات البث
                        </h3>
                        <div onclick="closeApiModal()" style="width:32px; height:32px; border-radius:50%; background:rgba(255,0,0,0.1); color:#ff4757; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:16px;">
                            <i class="fa-solid fa-xmark"></i>
                        </div>
                    </div>
                    <div style="padding:25px; background:var(--bg-card);">
                        <input type="hidden" id="add-api-id">
                        
                        <div style="margin-bottom:15px;">
                            <label style="display:block; margin-bottom:8px; font-weight:700; font-size:13px; color:var(--text-main);">رابط البث</label>
                            <input type="text" id="add-api-url" class="form-input" placeholder="أدخل رابط البث" style="width:100%; box-sizing:border-box;">
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:20px;">
                            <div>
                                <label style="display:block; margin-bottom:8px; font-weight:700; font-size:13px; color:var(--text-main);">القناة</label>
                                <input type="text" id="add-api-channel" class="form-input" placeholder="beIN Sports 1" style="width:100%; box-sizing:border-box;">
                            </div>
                            <div>
                                <label style="display:block; margin-bottom:8px; font-weight:700; font-size:13px; color:var(--text-main);">المعلق</label>
                                <input type="text" id="add-api-comm" class="form-input" placeholder="اسم المعلق" style="width:100%; box-sizing:border-box;">
                            </div>
                        </div>

                        <button onclick="confirmAddFromBank()" style="width:100%; height:55px; background:linear-gradient(135deg,#6366f1,#4f46e5); color:#fff; border:none; border-radius:12px; font-weight:800; font-size:16px; cursor:pointer; box-shadow:0 10px 20px rgba(99,102,241,0.3); display:flex; align-items:center; justify-content:center; gap:8px;">
                            <i class="fa-solid fa-check-circle"></i> تأكيد الإضافة للموقع
                        </button>
                    </div>
                    </div>
                </div>
            </div>

            <style>
                .r-tab { padding:8px 20px; border-radius:8px; font-size:13px; font-weight:700; color:var(--text-sub); cursor:pointer; transition:0.3s; }
                .r-tab:hover { color:var(--text-main); }
                .r-tab.active { background:var(--card); color:#6366f1; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); }
                .api-add-btn { padding:7px 14px; border-radius:8px; border:1px solid #6366f1; background:rgba(99,102,241,0.05); color:#6366f1; cursor:pointer; font-weight:700; transition:0.2s; font-size:12px; }
                .api-add-btn:hover { background:#6366f1; color:#fff; transform: translateY(-2px); }
            </style>

            <script>
                const favLeaguesIds = <?php echo json_encode(array_filter(explode(',', $apiS['fav_leagues'] ?? ''))); ?>;
                let apiBank = <?php echo json_encode($bank); ?>;
                let addedMatchIds = <?php 
                    clearstatcache(true, $matchesFile);
                    $addedIds = [];
                    foreach($matches as $mm) {
                        if(!empty($mm['event_key'])) $addedIds[] = (string)$mm['event_key'];
                        if(!empty($mm['id'])) $addedIds[] = (string)$mm['id'];
                    }
                    echo json_encode(array_values(array_unique($addedIds))); 
                ?>;
                
                window.addEventListener('DOMContentLoaded', () => {
                    if (apiBank && apiBank.length > 0) {
                        renderBank('today');
                    }
                    loadBank(); 
                });

                async function loadBank() {
                    try {
                        const r = await fetch('api.php?action=get_bank&t=' + Date.now());
                        const data = await r.json();
                        if (data.error) {
                            document.getElementById('api-bank-body').innerHTML = `<tr><td colspan="5" style="text-align:center; padding:40px 0;">
                                <div style="font-size:45px; color:var(--text-sub); opacity:0.3; margin-bottom:15px;"><i class="fa-solid fa-triangle-exclamation"></i></div>
                                <div style="font-weight:700; color:var(--text-sub);">${data.error}</div>
                            </td></tr>`;
                            return;
                        }
                        apiBank = data;
                        const activeTab = document.querySelector('.day-tab.active').dataset.day;
                        renderBank(activeTab);
                    } catch(e) { 
                        console.error(e); 
                        document.getElementById('api-bank-body').innerHTML = `<tr><td colspan="5" style="text-align:center; padding:40px 0; color:#ef4444;">حدث خطأ في الاتصال بالـ API</td></tr>`;
                    }
                }

                function renderBank(day) {
                    const tbody = document.getElementById('api-bank-body');
                    if(!apiBank) return;

                    const toYMD = (d) => d.getFullYear() + '-' + (d.getMonth() + 1) + '-' + d.getDate();
                    const now = new Date();
                    const todayStr = toYMD(now);
                    const yesterday = new Date(); yesterday.setDate(now.getDate() - 1);
                    const yestStr = toYMD(yesterday);
                    const tomorrow = new Date(); tomorrow.setDate(now.getDate() + 1);
                    const tomStr = toYMD(tomorrow);

                    let filtered = apiBank.filter(m => {
                        let mId = String(m.id || m.event_key);
                        if (addedMatchIds.includes(mId)) return false;
                        
                        const mDate = new Date(m.timestamp * 1000);
                        const mStr = toYMD(mDate);

                        if (day === 'today') return mStr === todayStr;
                        if (day === 'yesterday') return mStr === yestStr;
                        if (day === 'tomorrow') return mStr === tomStr;
                        return false;
                    });

                    if (favLeaguesIds.length > 0) {
                        filtered = filtered.filter(m => favLeaguesIds.includes(String(m.leagueId)));
                    }
                    
                    if(filtered.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:40px 0;">
                            <div style="font-size:45px; color:var(--text-sub); opacity:0.3; margin-bottom:15px;"><i class="fa-solid fa-folder-open"></i></div>
                            <div style="font-weight:700; color:var(--text-sub);">لا توجد مباريات جديدة متاحة حالياً</div>
                        </td></tr>`;
                        return;
                    }

                    const grouped = filtered.reduce((acc, match) => {
                        const league = match.league || 'بطولات أخرى';
                        if (!acc[league]) acc[league] = [];
                        acc[league].push(match);
                        return acc;
                    }, {});

                    let html = '';
                    for (const league in grouped) {
                        const leagueId = grouped[league][0].leagueId || '-';
                        html += `
                        <tr class="league-group-header">
                            <td colspan="5" style="background:var(--bg-body); padding:12px 25px; border-bottom:1px solid var(--border-color);">
                                <div style="display:flex; align-items:center; gap:12px;">
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <i class="fa-solid fa-trophy" style="color:#f59e0b; font-size:14px;"></i>
                                        <span style="font-weight:800; font-size:15px; color:var(--text-main);">${league}</span>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:10px; margin-right:auto; direction:ltr;">
                                        <span style="background:rgba(99,102,241,0.1); color:#6366f1; padding:4px 12px; border-radius:30px; font-size:11px; font-weight:800;">${grouped[league].length} Matches</span>
                                        <span style="background:var(--bg-main); color:var(--text-sub); border:1px solid var(--border-color); padding:3px 10px; border-radius:8px; font-size:11px; font-weight:700;">ID: ${leagueId}</span>
                                    </div>
                                </div>
                            </td>
                        </tr>`;

                        html += grouped[league].map(m => {
                            let stClass = 'status-up';
                            if(m.status === 'live') stClass = 'status-live';
                            else if(m.status === 'finished') stClass = 'status-final';
                            
                            let stTxt = m.status_ar || 'لم تبدأ بعد';
                            if (m.status === 'live' && m.status_raw) {
                                if (!isNaN(m.status_raw)) stTxt = 'مباشر ' + m.status_raw + '\'';
                                else if (m.status_raw === 'Half Time') stTxt = 'بين الشوطين';
                            }

                            return `
                            <tr style="transition: 0.2s;">
                                <td style="padding:12px 20px;">
                                    <div style="display:grid; grid-template-columns:1fr auto 1fr; align-items:center; gap:12px;">
                                        <div style="display:flex; align-items:center; gap:8px; justify-content:flex-end;">
                                            <span style="font-weight:700; font-size:13px; color:var(--text-main); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:140px;">${m.homeTeam}</span>
                                            <img src="${m.homeLogo}" style="width:25px; height:25px; object-fit:contain; flex-shrink:0;">
                                        </div>
                                        <span style="background:var(--bg-main); padding:4px 8px; border-radius:8px; color:var(--text-main); font-size:12px; font-weight:800; width:60px; min-width:60px; max-width:60px; text-align:center; border:1px solid var(--border-color); white-space:nowrap; display:inline-block; box-sizing:border-box;">
                                            ${(!m.score || m.score.trim()==='-' || m.score.toLowerCase()==='vs') ? 'VS' : m.score}
                                        </span>
                                        <div style="display:flex; align-items:center; gap:8px; justify-content:flex-start;">
                                            <img src="${m.awayLogo}" style="width:25px; height:25px; object-fit:contain; flex-shrink:0;">
                                            <span style="font-weight:700; font-size:13px; color:var(--text-main); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:140px;">${m.awayTeam}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>${m.league}</td>
                                <td style="font-weight:800; color:var(--text-main);">${formatLocalTime(m.timestamp)}</td>
                                <td>
                                    <span class="status-badge ${stClass}">${stTxt}</span>
                                </td>
                                <td>
                                    <button class="api-add-btn" onclick="openApiModal('${m.id}')">
                                        <i class="fa-solid fa-plus" style="margin-left:5px;"></i> إضافة للموقع
                                    </button>
                                </td>
                            </tr>`;
                        }).join('');
                    }
                    tbody.innerHTML = html;
                }

                function switchApiTab(el) {
                    document.querySelectorAll('.day-tabs .day-tab').forEach(t => t.classList.remove('active'));
                    el.classList.add('active');
                    renderBank(el.dataset.day);
                }

                function openApiModal(id) {
                    document.getElementById('add-api-id').value = id;
                    const modal = document.getElementById('addApiModal');
                    modal.style.display = 'flex';
                }

                function closeApiModal() {
                    document.getElementById('addApiModal').style.display = 'none';
                    document.getElementById('add-api-url').value = '';
                    document.getElementById('add-api-channel').value = '';
                    document.getElementById('add-api-comm').value = '';
                }

                async function confirmAddFromBank() {
                    const id = document.getElementById('add-api-id').value;
                    const url = document.getElementById('add-api-url').value;
                    const ch  = document.getElementById('add-api-channel').value;
                    const comm = document.getElementById('add-api-comm').value;

                    const matchData = apiBank.find(m => String(m.id) === String(id));
                    if(!matchData) { alert('لم يتم العثور على بيانات المباراة في البنك'); return; }

                    const btn = document.querySelector('#addApiModal button');
                    const originalText = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> جاري الإضافة...';

                    try {
                        const payload = {
                            ...matchData,
                            event_key: matchData.id,
                            streamUrl: url,
                            channel: ch,
                            commentator: comm
                        };
                        const r = await fetch('api.php?action=add_from_bank', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify(payload)
                        });
                        const d = await r.json();
                        if(d.success) {
                            showToast('تم بنجاح! المباراة الآن حية في الموقع ✅', 'success');
                            addedMatchIds.push(String(id));
                            closeApiModal();
                            renderBank(document.querySelector('.day-tab.active').dataset.day);
                        }
                    } catch(e) { showToast('خطأ في الاتصال', 'error'); }
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }

                loadBank();
            </script>
        </div>

        <?php elseif($sec == 'fav_leagues'):
            $apiS = json_decode(@file_get_contents($settingsFile), true) ?: [];
            $favs = !empty($apiS['fav_leagues']) ? explode(',', $apiS['fav_leagues']) : [];
            $map = json_decode(@file_get_contents($arMapFile), true) ?: [];
            $allLeagues = $map['leagues'] ?? [];
        ?>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
                <h2 style="font-weight:800; margin:0;"><i class="fa-solid fa-star" style="color:#f59e0b; margin-left:10px;"></i>الدوريات المفضلة</h2>
                <div class="search-box-api" style="width:300px; position:relative;">
                    <i class="fa-solid fa-magnifying-glass" style="position:absolute; right:15px; top:50%; transform:translateY(-50%); color:var(--text-dim);"></i>
                    <input type="text" id="league-search" placeholder="ابحث عن دوري..." oninput="filterLeagues()" style="width:100%; padding:12px 40px 12px 15px; background:var(--bg-card); border:1px solid var(--border-color); border-radius:12px; color:var(--text-main); font-weight:700; outline:none;">
                </div>
            </div>

            <div style="background:var(--bg-card); padding:25px; border-radius:20px; border:1px solid var(--border-color); margin-bottom:30px;">
                <p style="color:var(--text-sub); margin-bottom:20px; font-weight:700;">اختر الدوريات التي تريد جلب مبارياتها فقط من الـ API.</p>
                <form method="POST">
                    <div id="leagues-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(250px, 1fr)); gap:15px; max-height:550px; overflow-y:auto; padding:10px;">
                        <?php if(empty($allLeagues)): ?>
                            <div style="grid-column:1/-1; text-align:center; padding:50px; color:var(--text-dim);">لا توجد بطولات مترجمة في ar_map.json</div>
                        <?php else: ?>
                            <?php foreach($allLeagues as $lid => $lname): $isChecked = in_array($lid, $favs); ?>
                                <label class="league-card-item <?php echo $isChecked ? 'active' : ''; ?>" style="display:flex; align-items:center; gap:12px; padding:15px; background:var(--bg-body); border:1px solid var(--border-color); border-radius:12px; cursor:pointer; transition:0.2s;">
                                    <input type="checkbox" name="favs[]" value="<?php echo $lid; ?>" <?php echo $isChecked ? 'checked' : ''; ?> onchange="this.parentElement.classList.toggle('active', this.checked)" style="display:none;">
                                    <div class="custom-chk" style="width:20px; height:20px; border:2px solid var(--border-color); border-radius:5px; display:flex; align-items:center; justify-content:center; color:transparent; font-size:10px; transition:0.2s; flex-shrink:0;">
                                        <i class="fa-solid fa-check"></i>
                                    </div>
                                    <div style="display:flex; align-items:center; justify-content:space-between; width:100%;">
                                        <span style="font-weight:700; font-size:14px; color:var(--text-main);"><?php echo $lname; ?></span>
                                        <span style="font-size:11px; opacity:0.6; font-weight:800; background:var(--bg-card); padding:3px 8px; border-radius:6px;">ID: <?php echo $lid; ?></span>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div style="margin-top:25px; border-top:1px solid var(--border-color); padding-top:20px; display:flex; justify-content:flex-end;">
                        <button type="submit" name="save_fav_leagues" style="padding:15px 45px; background:linear-gradient(135deg,#6366f1,#4f46e5); color:#fff; border:none; border-radius:12px; font-weight:800; cursor:pointer; box-shadow:0 10px 20px rgba(99,102,241,0.2); display:flex; align-items:center; gap:10px;">
                            <i class="fa-solid fa-save"></i> حفظ الإعدادات
                        </button>
                    </div>
                </form>
            </div>
            
            <style>
                .league-card-item.active { border-color:#6366f1 !important; background:rgba(99,102,241,0.05) !important; }
                .league-card-item.active .custom-chk { background:#6366f1 !important; border-color:#6366f1 !important; color:#fff !important; }
                #leagues-grid::-webkit-scrollbar { width: 6px; }
                #leagues-grid::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 10px; }
            </style>

            <script>
                function filterLeagues() {
                    const q = document.getElementById('league-search').value.toLowerCase();
                    document.querySelectorAll('.league-card-item').forEach(item => {
                        const txt = item.innerText.toLowerCase();
                        item.style.display = txt.includes(q) ? 'flex' : 'none';
                    });
                }
            </script>

        <?php elseif($sec == 'api_mgr'):
            $apiSettingsFile = __DIR__ . '/../data/api_settings.json';
            $apiSettings = file_exists($apiSettingsFile) ? json_decode(file_get_contents($apiSettingsFile), true) : [];
            $rawTs = $apiSettings['last_cron_sync'] ?? 0;
            $syncStatus = (isset($apiSettings['last_cron_sync']) && (time() - $apiSettings['last_cron_sync']) < 300) ? 'نشط الآن' : 'بانتظار المزامنة';
            $statusColor = ($syncStatus == 'نشط الآن') ? '#10b981' : '#f59e0b';
        ?>
            <h2 style="font-weight:800; margin-bottom:8px;"><i class="fa-solid fa-plug-circle-bolt" style="color:#10b981;"></i>إدارة نظام API</h2>
            
            <div class="stats-grid" style="margin-bottom:25px;">
                <div class="stat-card live" style="border-right: 4px solid <?php echo $statusColor; ?>;">
                    <i class="fa-solid fa-circle-check" style="color:<?php echo $statusColor; ?>;"></i>
                    <h3 style="font-size:16px;"><?php echo $syncStatus; ?></h3>
                    <p>حالة التحديث التلقائي</p>
                </div>
                <div class="stat-card total">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    <h3 id="st-last-sync" style="font-size:16px;" data-ts="<?php echo $rawTs; ?>">لم يتم المزامنة بعد</h3>
                    <p>آخر مزامنة ناجحة</p>
                </div>
            </div>

            <div class="recent-card">
                <div class="recent-header">
                    <i class="fa-solid fa-gears" style="color:#6366f1;"></i>
                    <h3 style="margin-right:10px;">إعدادات الاتصال بالـ API</h3>
                </div>
                <form method="POST" style="padding:25px;">
                    <div class="form-group">
                        <label>مفتاح API-Football (AllSportsAPI)</label>
                        <div style="position:relative;">
                            <input type="password" name="api_key" id="api-key-input" class="form-input"
                                value="<?php echo $apiSettings['api_key'] ?? ''; ?>"
                                placeholder="أدخل المفتاح هنا..."
                                style="padding-left:45px;">
                            <i class="fa-solid fa-eye" onclick="toggleApiKey()" style="position:absolute; left:15px; top:50%; transform:translateY(-50%); cursor:pointer; color:var(--text-sub);"></i>
                        </div>
                        <p style="font-size:12px; color:var(--text-dim); margin-top:10px;">
                            <i class="fa-solid fa-circle-info" style="margin-left:5px;"></i>
                            يتم تحديث جميع البيانات (أمس، اليوم، غداً) بشكل آلي تماماً عبر نظام الـ Cron Job.
                        </p>
                    </div>

                    <button type="submit" name="save_api_mgr" class="p-btn" style="width:100%; height:55px; background:#6366f1; color:#fff; border-radius:12px; font-weight:800; font-size:16px; border:none; cursor:pointer;">
                        <i class="fa-solid fa-floppy-disk" style="margin-left:8px;"></i> حفظ المفتاح وتفعيل المزامنة
                    </button>
                </form>
            </div>

            <script>
            function formatLocalSyncTime() {
                const el = document.getElementById('st-last-sync');
                const ts = parseInt(el.getAttribute('data-ts'));
                if (ts > 0) {
                    const date = new Date(ts * 1000);
                    // تنسيق التاريخ والوقت بصيغة YYYY-MM-DD HH:MM:SS AM/PM
                    const y = date.getFullYear();
                    const m = String(date.getMonth() + 1).padStart(2, '0');
                    const d = String(date.getDate()).padStart(2, '0');
                    
                    let hours = date.getHours();
                    const minutes = String(date.getMinutes()).padStart(2, '0');
                    const seconds = String(date.getSeconds()).padStart(2, '0');
                    const ampm = hours >= 12 ? 'PM' : 'AM';
                    hours = hours % 12;
                    hours = hours ? hours : 12; // الساعة 0 تصبح 12
                    const h = String(hours).padStart(2, '0');

                    el.textContent = `${y}-${m}-${d} ${h}:${minutes}:${seconds} ${ampm}`;
                }
            }
            window.addEventListener('DOMContentLoaded', formatLocalSyncTime);
            </script>

            <script>
            (async function loadApiStatus() {
                try {
                    const r = await fetch('api.php?action=api_status');
                    const d = await r.json();
                    document.getElementById('st-fetch-date').textContent  = d.last_daily_date  || '--';
                    document.getElementById('st-live-update').textContent = d.last_live_update || '--';
                    document.getElementById('st-requests').textContent    = d.requests_used != null ? d.requests_used + ' / ' + d.requests_limit : 'غير متاح';
                } catch(e) { console.error('API Status Error:', e); }
            })();

            function toggleApiKey() {
                const inp = document.getElementById('api-key-input');
                const icon = inp.nextElementSibling;
                if (inp.type === 'password') {
                    inp.type = 'text';
                    icon.className = 'fa-solid fa-eye-slash';
                } else {
                    inp.type = 'password';
                    icon.className = 'fa-solid fa-eye';
                }
            }


            // تم حذف وظائف forceFetch و triggerLiveUpdate لعدم الحاجة إليها بعد تنفيذ الأتمتة
            </script>

        <?php elseif($sec == 'news'):
            $allNOriginal = json_decode(@file_get_contents($newsFile), true) ?: [];
            usort($allNOriginal, function($a, $b) { return (isset($b['id'])?$b['id']:0) - (isset($a['id'])?$a['id']:0); });
            $limit = 10;
            $totalNews = count($allNOriginal);
            $totalPages = ceil($totalNews / $limit);
            $page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
            $start = ($page - 1) * $limit;
            $displayNews = array_slice($allNOriginal, $start, $limit);
        ?>
            <h2 style="font-weight:800; margin-bottom:25px;">إدارة الأخبار</h2>
            <!-- استمارة إضافة خبر (في الأعلى كما كانت) -->
            <div class="recent-card" style="margin-bottom:30px;">
                <div style="padding:20px 25px; border-bottom:1px solid var(--border-color); font-size:17px; font-weight:800; display:flex; align-items:center; gap:10px;">
                    <i class="fa-solid fa-plus-circle" style="color:#6366f1;"></i> إضافة خبر جديد
                </div>
                <form method="POST" enctype="multipart/form-data" style="padding:25px;">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">
                        <div class="form-group"><label>العنوان</label><input type="text" name="t" class="form-input" placeholder="عنوان الخبر..." required></div>
                        <div class="form-group"><label>الصورة</label>
                            <div class="image-input-group">
                                <div id="mini-preview" class="mini-preview"><button type="button" class="mini-remove" onclick="removeImg(event)"><i class="fa-solid fa-xmark"></i></button></div>
                                <input type="text" name="i" id="img-url-backup" class="form-input" style="flex:1;" placeholder="رابط خارجي...">
                                <div class="upload-btn-icon" onclick="document.getElementById('news-img').click()"><i class="fa-solid fa-camera"></i></div>
                                <input type="file" name="img_file" id="news-img" accept="image/*" hidden onchange="previewImg(this)">
                            </div>
                        </div>
                    </div>
                    <div class="form-group"><label>المحتوى (يدعم [H2] و [H3])</label><textarea name="c" class="form-input" rows="5" required style="resize:vertical;"></textarea></div>
                    <button type="submit" name="add_n" style="width:100%; padding:14px; background:#6366f1; color:#fff; border:none; border-radius:12px; font-weight:800; font-size:16px; cursor:pointer;">نشر الخبر الآن</button>
                </form>
            </div>

            <div class="recent-card">
                <div class="recent-header" style="justify-content:space-between; flex-wrap:wrap; gap:10px;">
                    <div style="display:flex; align-items:center; gap:12px;"><i class="fa-solid fa-newspaper" style="color:#6366f1;"></i><h3>قائمة الأخبار</h3></div>
                    <form method="POST" onsubmit="return confirm('هل أنت متأكد من تنظيف كافة الصور غير المستخدمة في الموقع؟')">
                        <button type="submit" name="clean_imgs" style="padding:10px 20px; background:#6366f1; color:#fff; border:none; border-radius:10px; font-weight:800; font-size:13px; cursor:pointer; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);"><i class="fa-solid fa-broom" style="margin-left:6px;"></i> تنظيف كافة الصور</button>
                    </form>
                </div>
                <div class="table-res" style="border-top:1px solid var(--border-color);">
                    <table class="table">
                        <thead><tr><th style="width:120px; text-align:center;">الصورة</th><th style="text-align:right;">العنوان</th><th style="width:180px; text-align:center;">التاريخ</th><th style="width:180px; text-align:center;">التحكم</th></tr></thead>
                        <tbody>
                        <?php if(count($displayNews) > 0): ?>
                        <?php foreach($displayNews as $n): ?>
                            <tr>
                                <td style="text-align:center;">
                                    <img src="<?php echo $n['image']; ?>" style="width:80px; height:50px; border-radius:8px; object-fit:cover; border:1px solid var(--border-color);">
                                </td>
                                <td style="font-weight:800; font-size:14px; color:var(--text-main); text-align:right;"><?php echo $n['title']; ?></td>
                                <td class="date-cell" data-time="<?php echo $n['id']; ?>" style="font-size:13px; font-weight:700; color:var(--text-sub); text-align:center;">--</td>
                                <td>
                                    <div style="display:flex; gap:10px; justify-content:center;">
                                        <button class="btn-edit" onclick="openNewsEdit(this)" data-news='<?php echo htmlspecialchars(json_encode($n), ENT_QUOTES); ?>'><i class="fa-solid fa-pen"></i></button>
                                        <a href="index.php?del_n=<?php echo $n['id']; ?>&section=news&p=<?php echo $page; ?>" class="btn-del" onclick="return confirm('حذف؟')"><i class="fa-solid fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align:center; padding:50px 0;">
                                    <div style="font-size:45px; color:var(--text-sub); opacity:0.3; margin-bottom:15px;"><i class="fa-solid fa-folder-open"></i></div>
                                    <div style="font-weight:700; color:var(--text-sub);">لا توجد أخبار مضافة حالياً</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if($totalPages > 1): ?>
            <div style="display:flex; justify-content:center; gap:8px; margin-top:25px;">
                <?php if($page > 1): ?><a href="index.php?section=news&p=<?php echo $page-1; ?>" class="p-btn"><i class="fa-solid fa-chevron-right"></i></a><?php endif; ?>
                <?php for($i=1; $i<=$totalPages; $i++): ?>
                    <a href="index.php?section=news&p=<?php echo $i; ?>" class="p-btn <?php echo $i==$page?'active':''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if($page < $totalPages): ?><a href="index.php?section=news&p=<?php echo $page+1; ?>" class="p-btn"><i class="fa-solid fa-chevron-left"></i></a><?php endif; ?>
            </div>
            <?php endif; ?>

            <div id="news-edit-modal" class="modal-overlay"><div class="modal-box"><div class="modal-head">تعديل الخبر</div>
                <form method="POST" enctype="multipart/form-data"><input type="hidden" name="edit_news_id" id="en-id"><div class="modal-body">
                    <div class="full"><label>العنوان</label><input type="text" name="n_t" id="en-t" class="form-input" required></div>
                    <div class="full"><label>الصورة</label><div class="image-input-group">
                        <div id="mini-preview-edit" class="mini-preview"></div>
                        <input type="text" name="n_i" id="en-url" class="form-input" style="flex:1;">
                        <input type="file" name="n_img_file" id="n-img-file" accept="image/*" hidden onchange="previewImg(this, true)">
                        <div class="upload-btn-icon" onclick="document.getElementById('n-img-file').click()"><i class="fa-solid fa-camera"></i></div>
                    </div></div>
                    <div class="full"><label>المحتوى</label><textarea name="n_c" id="en-c" rows="8" class="form-input"></textarea></div>
                </div><div class="modal-foot"><button type="button" class="btn-cancel-sm" onclick="document.getElementById('news-edit-modal').classList.remove('open')">إلغاء</button><button type="submit" name="save_news_edit" class="btn-primary-sm">حفظ</button></div></form>
            </div></div>
        <?php endif; ?>
        
        <div id="edit-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:10000; align-items:center; justify-content:center; backdrop-filter:blur(6px);">
            <div style="background:var(--bg-card); width:90%; max-width:450px; border-radius:20px; overflow:hidden; border:1px solid var(--border-color); box-shadow:0 30px 60px rgba(0,0,0,0.5); animation:fadeInScale 0.3s ease;">
                <div style="background:var(--bg-body); padding:20px 25px; border-bottom:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin:0; font-size:18px; font-weight:800; color:var(--text-main);">
                        <i class="fa-solid fa-pen" style="color:#6366f1; margin-left:8px;"></i> تعديل بيانات البث
                    </h3>
                    <div onclick="closeEditModal()" style="width:32px; height:32px; border-radius:50%; background:rgba(255,0,0,0.1); color:#ff4757; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:16px;">
                        <i class="fa-solid fa-xmark"></i>
                    </div>
                </div>
                <form method="POST" action="index.php?section=<?php echo $sec; ?>">
                    <input type="hidden" name="edit_match_id" id="edit-id">
                    <div style="padding:25px; background:var(--bg-card);">
                        <div style="margin-bottom:15px;">
                            <label style="display:block; margin-bottom:8px; font-weight:700; font-size:13px; color:var(--text-main);">رابط البث</label>
                            <input type="text" name="edit_stream" id="edit-stream" class="form-input" placeholder="أدخل رابط البث" style="width:100%; box-sizing:border-box;">
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:20px;">
                            <div>
                                <label style="display:block; margin-bottom:8px; font-weight:700; font-size:13px; color:var(--text-main);">القناة</label>
                                <input type="text" name="edit_channel" id="edit-channel" class="form-input" placeholder="beIN Sports 1" style="width:100%; box-sizing:border-box;">
                            </div>
                            <div>
                                <label style="display:block; margin-bottom:8px; font-weight:700; font-size:13px; color:var(--text-main);">المعلق</label>
                                <input type="text" name="edit_commentator" id="edit-commentator" class="form-input" placeholder="اسم المعلق" style="width:100%; box-sizing:border-box;">
                            </div>
                        </div>
                        <button type="submit" name="save_edit" style="width:100%; height:55px; background:linear-gradient(135deg,#6366f1,#4f46e5); color:#fff; border:none; border-radius:12px; font-weight:800; font-size:16px; cursor:pointer; box-shadow:0 10px 20px rgba(99,102,241,0.3); display:flex; align-items:center; justify-content:center; gap:8px;">
                            <i class="fa-solid fa-check-circle"></i> حفظ التعديلات
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    <script>
        const themeBtn = document.getElementById('adm-theme');
        themeBtn.onclick = () => {
            const current = document.documentElement.getAttribute('data-theme');
            const target = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', target);
            localStorage.setItem('theme', target);
            themeBtn.querySelector('i').className = target === 'dark' ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
        };
        let activeDay = 'today';
        function switchDay(el) {
            document.querySelectorAll('.day-tab').forEach(t => t.classList.remove('active'));
            el.classList.add('active'); 
            activeDay = el.dataset.day; 
            
            // تحديث روابط الحذف فورياً عند التبديل
            if(typeof updateCurDeleteLinks === 'function') updateCurDeleteLinks(activeDay);
            if(typeof updateOvDeleteLinks === 'function') updateOvDeleteLinks(activeDay);
            
            filterMatches();
        }
        function filterMatches() {
            const search = (document.getElementById('cur-search')?.value || '').toLowerCase();
            const rows = document.querySelectorAll('tbody tr[data-day]');
            let counts = {today:0, yesterday:0, tomorrow:0};
            rows.forEach(r => {
                r.style.display = 'none'; if(r.dataset.empty) return;
                if(r.dataset.day === activeDay){
                    const txt = r.innerText.toLowerCase();
                    if(!search || txt.includes(search)){ r.style.display = ''; counts[activeDay]++; }
                }
            });
            document.querySelectorAll('tr[data-empty]').forEach(r => {
                if(r.dataset.day === activeDay) r.style.display = counts[activeDay] === 0 ? '' : 'none';
                else r.style.display = 'none';
            });
        }
        function openEditModal(btn) {
            const m = JSON.parse(btn.getAttribute('data-match'));
            document.getElementById('edit-id').value = m.id;
            document.getElementById('edit-channel').value = m.channel || '';
            document.getElementById('edit-commentator').value = m.commentator || '';
            document.getElementById('edit-stream').value = m.streamUrl || '';
            document.getElementById('edit-modal').style.display = 'flex';
        }
        function closeEditModal() {
            document.getElementById('edit-modal').style.display = 'none';
        }
        function openNewsEdit(btn) {
            const n = JSON.parse(btn.getAttribute('data-news'));
            document.getElementById('en-id').value = n.id;
            document.getElementById('en-t').value = n.title;
            document.getElementById('en-c').value = n.content;
            document.getElementById('en-url').value = n.image;
            document.getElementById('news-edit-modal').classList.add('open');
        }
        function previewImg(input, isEdit = false) {
            const preview = document.getElementById(isEdit ? 'mini-preview-edit' : 'mini-preview');
            const urlInput = document.getElementById(isEdit ? 'en-url' : 'img-url-backup');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    preview.style.backgroundImage = `url('${e.target.result}')`;
                    preview.style.display = 'block';
                    if(urlInput) urlInput.value = input.files[0].name;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        function removeImg(e) {
            e.stopPropagation(); document.getElementById('news-img').value = "";
            document.getElementById('mini-preview').style.display = 'none';
            document.getElementById('img-url-backup').value = "";
        }
        window.onclick = (e) => { if(e.target.classList.contains('modal-overlay')) document.querySelectorAll('.modal-overlay').forEach(m=>m.classList.remove('open')); };
        function showToast(msg, type = 'info') {
            const container = document.getElementById('toast-container');
            if(!container) return;
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `<i class="fa-solid ${type==='success'?'fa-check-circle':'fa-info-circle'}"></i> <span>${msg}</span>`;
            container.appendChild(toast);
            setTimeout(() => toast.classList.add('show'), 100);
            setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 500); }, 3000);
        }
        function formatLocalDates() {
            document.querySelectorAll('.date-cell').forEach(cell => {
                const ts = parseInt(cell.getAttribute('data-time'));
                if(ts) {
                    const d = new Date(ts * 1000);
                    cell.innerText = d.toLocaleDateString('ar-EG', {day:'2-digit', month:'2-digit', year:'numeric'});
                }
            });
        }
        window.onload = () => {
            formatLocalDates();
            const url = new URL(window.location.href);
            
            // استعادة اليوم المختار من الرابط
            const urlDay = url.searchParams.get('day');
            if(urlDay) {
                const tab = document.querySelector(`.day-tab[data-day="${urlDay}"]`);
                if(tab) switchDay(tab);
            }

            if(url.searchParams.has('success')) showToast('تمت العملية بنجاح', 'success');
            if(url.searchParams.has('error') && url.searchParams.get('error') == 'exists') showToast('هذا الأسم موجود بالفعل!', 'error');
            if(url.searchParams.has('error') && url.searchParams.get('error') == 'empty') showToast('لا يمكن إضافة بيانات فارغة!', 'error');
            if(url.searchParams.has('cleaned')) showToast(`تم تنظيف ${url.searchParams.get('cleaned')} صورة`, 'success');
            
            // تنظيف الرابط لمنع تكرار الرسائل عند التحديث
            if (url.searchParams.has('success') || url.searchParams.has('cleaned')) {
                const cleanUrl = url.protocol + "//" + url.host + url.pathname + (url.searchParams.has('section') ? '?section=' + url.searchParams.get('section') : '');
                window.history.replaceState({path: cleanUrl}, '', cleanUrl);
            }
        };
    </script>
<?php endif; ?>
    <script>
        function refreshDashboardTabs() {
            // استخدام توقيت المتصفح بدقة
            const now = new Date();
            const getStr = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            
            const todayStr = getStr(now);
            const yest = new Date(); yest.setDate(now.getDate() - 1);
            const yestStr = getStr(yest);
            const tom = new Date(); tom.setDate(now.getDate() + 1);
            const tomStr = getStr(tom);

            let counts = { today: 0, yesterday: 0, tomorrow: 0 };
            
            // البحث عن التبويب النشط في القسم الحالي فقط
            const activeTbody = document.querySelector('#ov-tbody') || document.querySelector('#cur-tbody');
            if (!activeTbody) return;
            
            const tabContainer = activeTbody.closest('.recent-card').querySelector('.day-tabs');
            const activeTab = tabContainer.querySelector('.day-tab.active').dataset.day;

            activeTbody.querySelectorAll('.match-row').forEach(row => {
                const ts = parseInt(row.dataset.ts);
                const status = row.dataset.status;
                if (!ts) { row.style.display = 'none'; return; }

                const mDate = new Date(ts * 1000);
                const mStr = getStr(mDate);
                
                let targetDay = '';
                if (mStr === todayStr) targetDay = 'today';
                else if (mStr === yestStr) {
                    targetDay = (status === 'live') ? 'today' : 'yesterday';
                }
                else if (mStr === tomStr) targetDay = 'tomorrow';

                row.dataset.day = targetDay;
                if (targetDay) {
                    if (targetDay === activeTab) counts[activeTab]++;
                    
                    // إظهار الصف إذا كان يطابق التبويب المختار
                    row.style.display = (targetDay === activeTab) ? '' : 'none';
                } else {
                    row.style.display = 'none';
                }
            });
            
            // تحديث رسائل الفراغ
            activeTbody.querySelectorAll('tr[data-empty="1"]').forEach(emptyRow => {
                const d = emptyRow.dataset.day;
                emptyRow.style.display = (counts[d] === 0 && d === activeTab) ? '' : 'none';
            });
        }
        function switchTab(el) {
            document.querySelectorAll('.day-tab').forEach(t => t.classList.remove('active'));
            el.classList.add('active');
            refreshDashboardTabs();
        }
        window.addEventListener('DOMContentLoaded', refreshDashboardTabs);
    </script>
</body>
</html>
