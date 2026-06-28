<?php
session_start();
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

if (isset($_POST['login'])) {
    if ($_POST['user'] === 'admin' && $_POST['pass'] === '123456') { 
        $_SESSION['a'] = true; 
        header("Location: /admin/index.php"); 
        exit; 
    }
}
if (isset($_GET['logout'])) { session_destroy(); header("Location: /admin/index.php"); exit; }
$auth = isset($_SESSION['a']);
$sec = isset($_GET['section']) ? $_GET['section'] : 'main';
$news = json_decode(@file_get_contents($newsFile), true);
if(!$news) $news = array();

if ($auth) {
        // --- كود حذف الأندية والبطولات (نقل للأعلى للإصلاح) ---
        if (isset($_GET['del_club'])) {
            $cData = json_decode(@file_get_contents($clubsFile), true) ?: [];
            $targetId = strval($_GET['del_club']);
            $newC = [];
            foreach($cData as $item) {
                if (strval($item['id']) !== $targetId) { $newC[] = $item; }
            }
            file_put_contents($clubsFile, json_encode(array_values($newC), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
            header("Location: /admin/index.php?section=clubs&tab=clubs&success=deleted"); exit;
        }
        if (isset($_GET['del_league'])) {
            $lData = json_decode(@file_get_contents($leaguesFile), true) ?: [];
            $targetId = strval($_GET['del_league']);
            $newL = [];
            foreach($lData as $item) {
                if (strval($item['id']) !== $targetId) { $newL[] = $item; }
            }
            file_put_contents($leaguesFile, json_encode(array_values($newL), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
            header("Location: /admin/index.php?section=clubs&tab=leagues&success=deleted"); exit;
        }
        // --------------------------------------------------

    if (isset($_GET['del_m'])) {
        $ms = json_decode(@file_get_contents($matchesFile), true) ?: [];
        $ms = array_filter($ms, function($v) { return $v['id'] != $_GET['del_m']; });
        file_put_contents($matchesFile, json_encode(array_values($ms), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
        $backSec = isset($_GET['section']) ? $_GET['section'] : 'main';
        $backDay = isset($_GET['day']) ? $_GET['day'] : 'today';
        header("Location: /admin/index.php?section=$backSec&day=$backDay"); exit;
    }
    if (isset($_GET['del_n'])) {
        $ns = json_decode(@file_get_contents($newsFile), true) ?: [];
        $ns = array_filter($ns, function($v) { return $v['id'] != $_GET['del_n']; });
        file_put_contents($newsFile, json_encode(array_values($ns), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
        header("Location: /admin/index.php?section=news"); exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_m'])) {
            $h = trim($_POST['h']); $a = trim($_POST['a']); $l = trim($_POST['l']);
            $t = trim($_POST['t']); $mc = trim($_POST['m_c']); $ch = trim($_POST['c']); $u = trim($_POST['u']);
            
            if (empty($h) || empty($a) || empty($l) || empty($t) || empty($mc) || empty($ch) || empty($u)) {
                header("Location: /admin/index.php?section=add_m&error=empty"); exit;
            }

            $ms = json_decode(@file_get_contents($matchesFile), true) ?: [];
            $cs = json_decode(@file_get_contents($clubsFile), true) ?: [];
            
            $hLogo = ""; $aLogo = "";
            foreach($cs as $c) {
                if($c['name'] == $h) $hLogo = $c['logo'];
                if($c['name'] == $a) $aLogo = $c['logo'];
            }
            
            $ms[] = array(
                'id' => time(),
                'homeTeam' => $h,
                'awayTeam' => $a,
                'homeLogo' => $hLogo,
                'awayLogo' => $aLogo,
                'league' => $l,
                'time' => $_POST['t'],
                'commentator' => $_POST['m_c'], // المعلق
                'status' => $_POST['s'],
                'status_text' => ($_POST['s'] == 'live' ? 'جارية الآن' : ($_POST['s'] == 'finished' ? 'انتهت' : 'قادمة')),
                'day' => (isset($_POST['d']) ? $_POST['d'] : 'today'),
                'channel' => $_POST['c'],
                'streamUrl' => $_POST['u'],
                'homeScore' => "0",
                'awayScore' => "0"
            );
            file_put_contents($matchesFile, json_encode($ms, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
            header("Location: /admin/index.php?section=add_m&success=1"); exit;
        }
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
            file_put_contents($newsFile, json_encode($d, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
            header("Location: /admin/index.php?section=news&success=1"); exit;
        }
        if (isset($_POST['save_edit'])) {
            $mid = $_POST['edit_match_id'];
            $ms = json_decode(@file_get_contents($matchesFile), true) ?: [];
            foreach ($ms as &$m) {
                if ($m['id'] == $mid) {
                    if(empty($_POST['edit_h']) || empty($_POST['edit_a']) || empty($_POST['edit_l'])) continue;
                    $m['homeTeam']    = $_POST['edit_h'];
                    $m['awayTeam']    = $_POST['edit_a'];
                    $m['league']      = $_POST['edit_l'];
                    $m['status']      = isset($_POST['edit_status']) ? $_POST['edit_status'] : $m['status'];
                    $m['channel']     = isset($_POST['edit_channel']) ? $_POST['edit_channel'] : (isset($m['channel']) ? $m['channel'] : '');
                    $m['commentator'] = isset($_POST['edit_commentator']) ? $_POST['edit_commentator'] : (isset($m['commentator']) ? $m['commentator'] : '');
                    $m['score']       = isset($_POST['edit_score']) ? $_POST['edit_score'] : (isset($m['score']) ? $m['score'] : 'vs');
                    $m['streamUrl']  = isset($_POST['edit_stream']) ? $_POST['edit_stream'] : (isset($m['streamUrl']) ? $m['streamUrl'] : '');
                    $statusMap = array('live'=>'جارية الآن','upcoming'=>'قادمة','finished'=>'انتهت');
                    $m['status_text'] = isset($statusMap[$m['status']]) ? $statusMap[$m['status']] : $m['status'];
                    break;
                }
            }
            file_put_contents($matchesFile, json_encode(array_values($ms), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
            header("Location: /admin/index.php?section=current"); exit;
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
            header("Location: /admin/index.php?section=news"); exit;
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
            header("Location: /admin/index.php?section=news&cleaned=$count"); exit;
        }
        if (isset($_POST['add_club'])) {
            $c = json_decode(@file_get_contents($clubsFile), true) ?: [];
            $newNameClean = trim($_POST['c_name']);
            $exists = false;
            foreach($c as $ex) { if(trim($ex['name']) == $newNameClean) { $exists = true; break; } }
            
            if (!$exists) {
                $logo = $_POST['c_logo_url'];
                if (isset($_FILES['c_logo_file']) && $_FILES['c_logo_file']['error'] === 0) {
                    $dir = '../uploads/';
                    if (!is_dir($dir)) mkdir($dir, 0777, true);
                    $ext = pathinfo($_FILES['c_logo_file']['name'], PATHINFO_EXTENSION);
                    $newName = 'club_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['c_logo_file']['tmp_name'], $dir . $newName)) {
                        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                        $logo = "$protocol://{$_SERVER['HTTP_HOST']}/uploads/$newName";
                    }
                }
                $c[] = ['id'=>time(), 'name'=>$newNameClean, 'logo'=>$logo];
                file_put_contents($clubsFile, json_encode($c, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
                header("Location: /admin/index.php?section=clubs&tab=clubs&success=1"); exit;
            } else {
                header("Location: /admin/index.php?section=clubs&tab=clubs&error=exists"); exit;
            }
        }
        if (isset($_POST['add_league'])) {
            $l = json_decode(@file_get_contents($leaguesFile), true) ?: [];
            $newNameClean = trim($_POST['l_name']);
            $exists = false;
            foreach($l as $ex) { if(trim($ex['name']) == $newNameClean) { $exists = true; break; } }

            if (!$exists) {
                $l[] = ['id'=>time(), 'name'=>$newNameClean, 'desc'=>$_POST['l_desc']];
                file_put_contents($leaguesFile, json_encode($l, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
                header("Location: /admin/index.php?section=clubs&tab=leagues&success=1"); exit;
            } else {
                header("Location: /admin/index.php?section=clubs&tab=leagues&error=exists"); exit;
            }
        }
        // (تم نقل كود الحذف للأعلى لضمان التنفيذ)
        if (isset($_POST['instant_add'])) {
            $code = $_POST['html_code'];
            $targetDay = $_POST['target_day'];
            $matches = json_decode(@file_get_contents($matchesFile), true) ?: [];
            
            // تقسيم الكود حسب بلوكات المباريات
            $blocks = explode('<div class="EventBox">', $code);
            $addedCount = 0;
            
            foreach ($blocks as $block) {
                if (trim($block) == '' || !strpos($block, 'EventLink')) continue;
                
                // استخراج الرابط
                preg_match('/href="(.*?)"/i', $block, $u);
                // استخراج الفريق الأول (Right)
                preg_match('/EventTeam Right.*?alt="(.*?)"/s', $block, $hName);
                preg_match('/EventTeam Right.*?data-src="(.*?)"/s', $block, $hLogo);
                // استخراج الفريق الثاني (Left)
                preg_match('/EventTeam Left.*?alt="(.*?)"/s', $block, $aName);
                preg_match('/EventTeam Left.*?data-src="(.*?)"/s', $block, $aLogo);
                // استخراج الوقت
                preg_match('/id="EventHour">(.*?)<\/div>/s', $block, $evTime);
                // استخراج القناة، المعلق، والبطولة من التذييل
                preg_match_all('/<li>(.*?)<\/li>/s', $block, $footerItems);
                
                $m = [
                    'id' => time() . "_" . rand(100,999),
                    'homeTeam' => isset($hName[1]) ? $hName[1] : 'غير معروف',
                    'homeLogo' => isset($hLogo[1]) ? $hLogo[1] : '',
                    'awayTeam' => isset($aName[1]) ? $aName[1] : 'غير معروف',
                    'awayLogo' => isset($aLogo[1]) ? $aLogo[1] : '',
                    'league' => isset($footerItems[1][2]) ? $footerItems[1][2] : '--',
                    'time' => isset($evTime[1]) ? $evTime[1] : '00:00 AM',
                    'status' => ($targetDay == 'yesterday' ? 'finished' : 'upcoming'),
                    'status_text' => ($targetDay == 'yesterday' ? 'انتهت' : 'قادمة'),
                    'day' => $targetDay,
                    'channel' => isset($footerItems[1][0]) ? $footerItems[1][0] : '--',
                    'commentator' => isset($footerItems[1][1]) ? $footerItems[1][1] : '--',
                    'streamUrl' => isset($u[1]) ? $u[1] : '#',
                    'homeScore' => "0",
                    'awayScore' => "0"
                ];

                // التحقق من وجود المباراة مسبقاً لمنع التكرار
                $exists = false;
                foreach ($matches as $ex) {
                    if ($ex['homeTeam'] == $m['homeTeam'] && $ex['awayTeam'] == $m['awayTeam'] && $ex['day'] == $m['day']) {
                        $exists = true;
                        break;
                    }
                }
                
                if (!$exists) {
                    $matches[] = $m;
                    $addedCount++;
                }
                usleep(1000); 
            }
            
            file_put_contents($matchesFile, json_encode($matches, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
            header("Location: /admin/index.php?section=instant&success=1&count=$addedCount");
            exit;
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
    <link rel="stylesheet" href="style.css">
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
            <a href="/admin/index.php?section=main"    class="nav-item <?php echo $sec=='main'   ?'active':''; ?>"><i class="fa-solid fa-chart-pie"></i> نظرة عامة</a>
            <a href="/admin/index.php?section=current"  class="nav-item <?php echo $sec=='current'?'active':''; ?>"><i class="fa-solid fa-list-check"></i> المباريات الحالية</a>
            <a href="/admin/index.php?section=api_add"  class="nav-item <?php echo $sec=='api_add'?'active':''; ?>"><i class="fa-solid fa-cloud-arrow-down"></i> إضافة مباراة API</a>
            <a href="/admin/index.php?section=clubs"    class="nav-item <?php echo $sec=='clubs'  ?'active':''; ?>"><i class="fa-solid fa-shield-halved"></i> الأندية والبطولات</a>
            <a href="/admin/index.php?section=add_m"   class="nav-item <?php echo $sec=='add_m'  ?'active':''; ?>"><i class="fa-solid fa-plus-circle"></i> إضافة مباراة</a>
            <a href="/admin/index.php?section=instant"  class="nav-item <?php echo $sec=='instant'?'active':''; ?>"><i class="fa-solid fa-bolt"></i> إضافة فورية</a>
            <a href="/admin/index.php?section=news"     class="nav-item <?php echo $sec=='news'   ?'active':''; ?>"><i class="fa-solid fa-newspaper"></i> أخر الأخبار</a>
            <a href="/admin/index.php?section=api_mgr"  class="nav-item <?php echo $sec=='api_mgr'?'active':''; ?>"><i class="fa-solid fa-plug-circle-bolt"></i> إدارة API</a>
        </div>
        <div class="sidebar-footer">
            <div id="adm-theme" class="f-icon"><i class="fa-solid fa-moon"></i></div>
            <a href="/admin/index.php?logout=1" class="f-icon" style="color:#ef4444;"><i class="fa-solid fa-power-off"></i></a>
        </div>
    </aside>
    <main class="main">
        <div class="toast-container" id="toast-container"></div>
        <?php if($sec == 'main'): ?>
            <h2 style="font-weight:800; margin-bottom:25px;">نظرة عامة</h2>
            <?php 
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
                    <table>
                        <thead><tr><th>المباراة</th><th>البطولة</th><th>الوقت</th><th>الحالة</th><th>البث</th><th>التحكم</th></tr></thead>
                        <tbody id="ov-tbody">
                        <?php 
                        // منطق التصفية الذكي والترتيب (مثل التطبيق)
                        function sortMatches($list) {
                            usort($list, function($a, $b) {
                                $score = ['live' => 0, 'upcoming' => 1, 'finished' => 2];
                                $sA = isset($a['status']) ? $score[$a['status']] : 1;
                                $sB = isset($b['status']) ? $score[$b['status']] : 1;
                                if ($sA != $sB) return $sA - $sB;
                                return strtotime(isset($a['time'])?$a['time']:'00:00') - strtotime(isset($b['time'])?$b['time']:'00:00');
                            });
                            return $list;
                        }

                        foreach(['today','yesterday','tomorrow'] as $dayKey):
                            $dayM = array_filter($matches, function($m) use ($dayKey) {
                                $mDay = isset($m['day']) ? $m['day'] : 'today';
                                $mSt = isset($m['status']) ? $m['status'] : '';
                                if ($dayKey == 'today') {
                                    return ($mDay == 'today') || ($mDay == 'yesterday' && $mSt != 'finished');
                                } else if ($dayKey == 'yesterday') {
                                    return ($mDay == 'yesterday' && $mSt == 'finished');
                                }
                                return $mDay == $dayKey;
                            });
                            $dayM = sortMatches($dayM);
                            $isVisible = $dayKey === 'today' ? '' : ' style="display:none;"';
                        ?>
                        <tr data-day="<?php echo $dayKey; ?>" data-empty="1"<?php echo (!empty($dayM) ? ' style="display:none;"' : $isVisible); ?>>
                            <td colspan="6"><div style="padding:40px; text-align:center; color:var(--text-dim);"><i class="fa-solid fa-futbol" style="font-size:30px; margin-bottom:10px; display:block;"></i><p>لا توجد مباريات مضافة لهذا اليوم</p></div></td>
                        </tr>
                        <?php foreach($dayM as $m): $statusClass = (isset($m['status']) && $m['status'] === 'live') ? 'status-live' : ''; ?>
                         <tr data-day="<?php echo $dayKey; ?>"<?php echo $isVisible; ?>>
                             <td><?php echo htmlspecialchars($m['homeTeam'] . " vs " . $m['awayTeam']); ?></td>
                             <td><?php echo htmlspecialchars(isset($m['league'])?$m['league']:'--'); ?></td>
                             <td><?php echo date("h:i A", strtotime($m['time'])); ?></td>
                             <td class="<?php echo $statusClass; ?>"><?php echo htmlspecialchars(isset($m['status_text'])?$m['status_text']:'--'); ?></td>
                             <td style="font-size:16px;"><?php echo !empty($m['streamUrl']) && $m['streamUrl'] !== '#' ? '✅' : '❌'; ?></td>
                             <td>
                                 <div style="display:flex; gap:8px;">
                                     <button class="btn-edit" onclick="openEditModal(this)" data-match='<?php echo htmlspecialchars(json_encode($m), ENT_QUOTES); ?>'><i class="fa-solid fa-pen"></i></button>
                                     <a href="/admin/index.php?del_m=<?php echo $m['id']; ?>&section=main&day=<?php echo $dayKey; ?>" class="btn-del" onclick="return confirm('حذف؟')"><i class="fa-solid fa-trash"></i></a>
                                 </div>
                             </td>
                         </tr>
                        <?php endforeach; endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <script>
                // تحديث روابط الحذف في الرئيسي لتشمل اليوم المختار
                function updateOvDeleteLinks(day) {
                    document.querySelectorAll('#ov-tbody .btn-del').forEach(a => {
                        let url = new URL(a.href);
                        url.searchParams.set('day', day);
                        a.href = url.toString();
                    });
                }
            </script>
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
                <div class="stat-card total"><i class="fa-solid fa-futbol"></i><h3><?php echo $cur_total; ?></h3><p>إجمالي</p></div>
                <div class="stat-card live"><i class="fa-solid fa-tower-broadcast"></i><h3><?php echo $cur_live; ?></h3><p>جارية</p></div>
                <div class="stat-card waiting"><i class="fa-solid fa-clock"></i><h3><?php echo $cur_wait; ?></h3><p>قادمة</p></div>
                <div class="stat-card finished"><i class="fa-solid fa-check-double"></i><h3><?php echo $cur_done; ?></h3><p>منتهية</p></div>
            </div>
            <div class="recent-card">
                <div class="recent-header" style="justify-content:space-between; flex-wrap:wrap; gap:10px;">
                    <div style="display:flex; align-items:center; gap:12px;"><i class="fa-solid fa-list-check"></i><h3>المباريات الحالية</h3></div>
                    <div class="day-tabs" style="margin-bottom:0;">
                        <div class="day-tab" data-day="yesterday" onclick="switchDay(this)">مباريات الأمس</div>
                        <div class="day-tab active" data-day="today" onclick="switchDay(this)">مباريات اليوم</div>
                        <div class="day-tab" data-day="tomorrow" onclick="switchDay(this)">مباريات الغد</div>
                    </div>
                </div>
                <div style="overflow-x:auto;">
                    <table>
                        <thead><tr><th>المباراة</th><th>البطولة</th><th>الوقت</th><th>الحالة</th><th>البث</th><th>التحكم</th></tr></thead>
                        <tbody id="cur-tbody">
                        <?php 
                        // نفس منطق التصفية الذكي كما في التطبيق
                        if (!function_exists('sortMatches')) {
                            function sortMatches($list) {
                                usort($list, function($a, $b) {
                                    $score = ['live' => 0, 'upcoming' => 1, 'finished' => 2];
                                    $sA = isset($a['status']) ? ($score[$a['status']] ?? 1) : 1;
                                    $sB = isset($b['status']) ? ($score[$b['status']] ?? 1) : 1;
                                    if ($sA != $sB) return $sA - $sB;
                                    return strtotime(isset($a['time'])?$a['time']:'00:00') - strtotime(isset($b['time'])?$b['time']:'00:00');
                                });
                                return $list;
                            }
                        }
                        foreach(['today','yesterday','tomorrow'] as $dayKey):
                            $dayM = array_values(array_filter($allM, function($m) use ($dayKey) {
                                $mDay = isset($m['day']) ? $m['day'] : 'today';
                                $mSt  = isset($m['status']) ? $m['status'] : '';
                                if ($dayKey == 'today') {
                                    return ($mDay == 'today') || ($mDay == 'yesterday' && $mSt != 'finished');
                                } else if ($dayKey == 'yesterday') {
                                    return ($mDay == 'yesterday' && $mSt == 'finished');
                                }
                                return $mDay == $dayKey;
                            }));
                            $dayM = sortMatches($dayM);
                            $isVisible = $dayKey === 'today' ? '' : ' style="display:none;"';
                        ?>
                        <tr data-day="<?php echo $dayKey; ?>" data-empty="1"<?php echo (!empty($dayM) ? ' style="display:none;"' : $isVisible); ?>>
                            <td colspan="6"><div style="padding:40px; text-align:center; color:var(--text-dim);"><i class="fa-solid fa-calendar-day" style="font-size:30px; margin-bottom:10px; display:block;"></i><p>لا توجد مباريات مضافة</p></div></td>
                        </tr>
                        <?php foreach($dayM as $m):
                            $badgeClass = (isset($m['status']) && $m['status'] === 'live') ? 'badge-live' : ((isset($m['status']) && $m['status'] === 'finished') ? 'badge-finished' : 'badge-upcoming');
                            $badgeText  = 'لم تبدأ بعد';
                            if(isset($m['status'])) {
                                if($m['status'] === 'live') $badgeText = 'مباشر الآن';
                                elseif($m['status'] === 'finished') $badgeText = 'انتهت المباراة';
                            }
                        ?>
                        <tr data-day="<?php echo $dayKey; ?>"<?php echo $isVisible; ?>>
                            <td><?php echo htmlspecialchars($m['homeTeam'] . " vs " . $m['awayTeam']); ?></td>
                            <td><?php echo htmlspecialchars(isset($m['league'])?$m['league']:'--'); ?></td>
                            <td><?php echo date("h:i A", strtotime($m['time'])); ?></td>
                            <td><span class="<?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span></td>
                            <td style="font-size:16px;"><?php echo !empty($m['streamUrl']) && $m['streamUrl'] !== '#' ? '✅' : '❌'; ?></td>
                            <td>
                                <div style="display:flex; gap:8px;">
                                    <button class="btn-edit" onclick="openEditModal(this)" data-match='<?php echo htmlspecialchars(json_encode($m), ENT_QUOTES); ?>'><i class="fa-solid fa-pen"></i></button>
                                    <a href="/admin/index.php?del_m=<?php echo $m['id']; ?>&section=current&day=<?php echo $dayKey; ?>" class="btn-del" onclick="return confirm('حذف؟')"><i class="fa-solid fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <script>
                function updateCurDeleteLinks(day) {
                    document.querySelectorAll('#cur-tbody .btn-del').forEach(a => {
                        let url = new URL(a.href);
                        url.searchParams.set('day', day);
                        a.href = url.toString();
                    });
                }
            </script>
        <?php elseif($sec == 'instant'): ?>
            <h2 style="font-weight:800; margin-bottom:25px;">الإضافة الفورية</h2>
            <div class="recent-card">
                <form method="POST">
                    <div class="recent-header" style="justify-content:space-between; flex-wrap:wrap; gap:10px;">
                        <div style="display:flex; align-items:center; gap:12px;"><i class="fa-solid fa-bolt" style="color:#6366f1;"></i><h3>إضافة كود المباريات</h3></div>
                        <div class="day-tabs">
                            <input type="radio" name="target_day" value="yesterday" id="d-y" hidden>
                            <label for="d-y" class="day-tab-label">مباريات الأمس</label>
                            
                            <input type="radio" name="target_day" value="today" id="d-td" hidden checked>
                            <label for="d-td" class="day-tab-label">مباريات اليوم</label>
                            
                            <input type="radio" name="target_day" value="tomorrow" id="d-tr" hidden>
                            <label for="d-tr" class="day-tab-label">مباريات الغد</label>
                        </div>
                    </div>
                    <div style="padding:25px;">
                        <div class="form-group">
                            <textarea name="html_code" class="form-input" rows="15" placeholder="ألصق الأكواد البرمجية للمباريات هنا..." required style="font-family:monospace; font-size:12px; height:350px; resize:vertical;"></textarea>
                        </div>
                        <button type="submit" name="instant_add" style="width:100%; padding:16px; background:linear-gradient(90deg, #6366f1, #4f46e5); color:#fff; border:none; border-radius:15px; font-weight:800; font-size:17px; cursor:pointer; box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);"><i class="fa-solid fa-bolt" style="margin-left:8px;"></i> إضافة كافة المباريات الآن</button>
                    </div>
                </form>
            </div>
            <style>
                .day-tab-label { padding: 8px 18px; background: transparent; border-radius: 10px; cursor: pointer; font-weight: 700; font-size: 13px; color: var(--text-sub); transition: 0.3s; border: 1px solid transparent; }
                .day-tab-label:hover { color: var(--text-main); }
                input[type="radio"]:checked + .day-tab-label { background: var(--color-primary); color: #fff; box-shadow: 0 4px 15px rgba(99, 102, 241, 0.2); }
                .day-tabs { gap: 5px; }
            </style>
        <?php elseif($sec == 'clubs'): 
            $allC = json_decode(@file_get_contents($clubsFile), true) ?: [];
            $allL = json_decode(@file_get_contents($leaguesFile), true) ?: [];
            
            $tab = isset($_GET['tab']) ? $_GET['tab'] : 'clubs';
            $page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
            $limit = 10;
            
            $activeList = ($tab == 'clubs') ? $allC : $allL;
            usort($activeList, function($a, $b) { return $b['id'] - $a['id']; }); // الأحدث أولاً
            
            $totalItems = count($activeList);
            $totalPages = ceil($totalItems / $limit);
            $start = ($page - 1) * $limit;
            $displayItems = array_slice($activeList, $start, $limit);
        ?>
            <h2 style="font-weight:800; margin-bottom:25px;">إدارة الأندية والبطولات</h2>
            
            <!-- قسم الإضافة (منفصل في الأعلى) -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:25px; margin-bottom:30px;">
                <div class="recent-card">
                    <div class="recent-header"><div style="display:flex; align-items:center; gap:12px;"><i class="fa-solid fa-shield-halved" style="color:#6366f1;"></i><h3>إضافة نادٍ جديد</h3></div></div>
                    <form method="POST" enctype="multipart/form-data" style="padding:20px;">
                        <input type="text" name="c_name" class="form-input" placeholder="اسم النادي..." required style="margin-bottom:15px;">
                        <div class="image-input-group" style="margin-bottom:15px;">
                            <input type="text" name="c_logo_url" id="club-url" class="form-input" style="flex:1;" placeholder="رابط الشعار...">
                            <div class="upload-btn-icon" onclick="document.getElementById('club-img').click()"><i class="fa-solid fa-camera"></i></div>
                            <input type="file" name="c_logo_file" id="club-img" accept="image/*" hidden onchange="document.getElementById('club-url').value=this.files[0].name">
                        </div>
                        <button type="submit" name="add_club" class="btn-primary" style="width:100%; padding:14px; background:#6366f1; border-radius:12px; font-weight:800; border:none; color:#fff; cursor:pointer;"><i class="fa-solid fa-plus-circle" style="margin-left:8px;"></i> إضافة النادي</button>
                    </form>
                </div>
                <div class="recent-card">
                    <div class="recent-header"><div style="display:flex; align-items:center; gap:12px;"><i class="fa-solid fa-trophy" style="color:#6366f1;"></i><h3>إضافة بطولة جديدة</h3></div></div>
                    <form method="POST" style="padding:20px;">
                        <input type="text" name="l_name" class="form-input" placeholder="اسم البطولة..." required style="margin-bottom:15px;">
                        <input type="text" name="l_desc" class="form-input" placeholder="وصف البطولة (اختياري)..." style="margin-bottom:15px;">
                        <button type="submit" name="add_league" class="btn-primary" style="width:100%; padding:14px; background:#6366f1; border-radius:12px; font-weight:800; border:none; color:#fff; cursor:pointer;"><i class="fa-solid fa-plus-circle" style="margin-left:8px;"></i> إضافة البطولة</button>
                    </form>
                </div>
            </div>

            <!-- سجل الأندية والبطولات -->
            <div class="recent-card">
                <div class="recent-header" style="justify-content:space-between; flex-wrap:wrap; gap:10px;">
                    <div style="display:flex; align-items:center; gap:12px;"><i class="fa-solid fa-database" style="color:#6366f1;"></i><h3>سجل الأندية والبطولات</h3></div>
                    <div class="day-tabs" style="display:flex; background:var(--bg-body); padding:5px; border-radius:12px; border:1px solid var(--border-color); width:230px; margin-bottom:0;">
                        <a href="/admin/index.php?section=clubs&tab=clubs" class="day-tab-link <?php echo $tab=='clubs'?'active':''; ?>">الأندية</a>
                        <a href="/admin/index.php?section=clubs&tab=leagues" class="day-tab-link <?php echo $tab=='leagues'?'active':''; ?>">البطولات</a>
                    </div>
                </div>
                <style>
                    .day-tab-link { flex:1; text-align:center; padding:8px 15px; border-radius:10px; cursor:pointer; color:var(--text-sub); font-weight:700; transition:0.3s; text-decoration:none; font-size:13px; }
                    .day-tab-link.active { background:var(--color-primary); color:#fff; box-shadow:0 4px 15px rgba(99, 102, 241, 0.2); }
                    .day-tab-link:not(.active):hover { background:var(--border-color); color:var(--text-main); }
                </style>
                <div class="table-res" style="border-top:1px solid var(--border-color);">
                    <table class="table">
                        <?php if($totalItems > 0): ?>
                        <thead>
                            <tr>
                                <?php if($tab == 'clubs'): ?>
                                    <th style="width:120px; text-align:center;">الشعار</th>
                                    <th style="text-align:right;">اسم النادي</th>
                                <?php else: ?>
                                    <th style="text-align:right;">اسم البطولة</th>
                                <?php endif; ?>
                                <th style="width:120px; text-align:center;">التحكم</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($displayItems as $item): ?>
                            <tr>
                                <?php if($tab == 'clubs'): ?>
                                    <td style="text-align:center;"><img src="<?php echo $item['logo']; ?>" style="width:35px; height:35px; object-fit:contain;"></td>
                                    <td style="font-weight:800; font-size:14px; text-align:right;"><?php echo $item['name']; ?></td>
                                    <td style="text-align:center;">
                                        <a href="/admin/index.php?section=clubs&del_club=<?php echo $item['id']; ?>" class="btn-del" onclick="return confirm('حذف هذا النادي؟')"><i class="fa-solid fa-trash"></i></a>
                                    </td>
                                <?php else: ?>
                                    <td style="font-weight:800; font-size:14px; text-align:right;"><?php echo $item['name']; ?></td>
                                    <td style="text-align:center;">
                                        <a href="/admin/index.php?section=clubs&del_league=<?php echo $item['id']; ?>" class="btn-del" onclick="return confirm('حذف هذه البطولة؟')"><i class="fa-solid fa-trash"></i></a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php else: ?>
                            <tbody>
                                <tr>
                                    <td colspan="3" style="text-align:center; padding:50px 0;">
                                        <div style="font-size:45px; color:var(--text-sub); opacity:0.3; margin-bottom:15px;"><i class="fa-solid fa-folder-open"></i></div>
                                        <div style="font-weight:700; color:var(--text-sub);">لا توجد <?php echo $tab=='clubs'?'أندية':'بطولات'; ?> مضافة حالياً</div>
                                    </td>
                                </tr>
                            </tbody>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <!-- أرقام الصفحات للسجل -->
            <?php if($totalPages > 1): ?>
            <div style="display:flex; justify-content:center; gap:8px; margin-top:25px;">
                <?php if($page > 1): ?><a href="/admin/index.php?section=clubs&tab=<?php echo $tab; ?>&p=<?php echo $page-1; ?>" class="p-btn"><i class="fa-solid fa-chevron-right"></i></a><?php endif; ?>
                <?php for($i=1; $i<=$totalPages; $i++): ?>
                    <a href="/admin/index.php?section=clubs&tab=<?php echo $tab; ?>&p=<?php echo $i; ?>" class="p-btn <?php echo $i==$page?'active':''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if($page < $totalPages): ?><a href="/admin/index.php?section=clubs&tab=<?php echo $tab; ?>&p=<?php echo $page+1; ?>" class="p-btn"><i class="fa-solid fa-chevron-left"></i></a><?php endif; ?>
            </div>
            <?php endif; ?>
        <?php elseif($sec == 'add_m'): ?>
            <h2 style="font-weight:800; margin-bottom:25px;">إضافة مباراة (يدوياً)</h2>
            <form method="POST" id="matchForm" style="background:var(--bg-card); padding:30px; border-radius:15px; border:1px solid var(--border-color);">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                    <?php 
                        $cs = json_decode(@file_get_contents($clubsFile), true) ?: [];
                        $ls = json_decode(@file_get_contents($leaguesFile), true) ?: [];
                    ?>
                    
                    <!-- الفريق الأرضي -->
                    <div class="form-group">
                        <label>الفريق الأول</label>
                        <div class="custom-select-trigger" onclick="openSearchModal('h')">
                            <span id="h-display">اختر فريق...</span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </div>
                        <input type="hidden" name="h" id="h-input" required>
                    </div>

                    <!-- الفريق الضيف -->
                    <div class="form-group">
                        <label>الفريق الضيف (الثاني)</label>
                        <div class="custom-select-trigger" onclick="openSearchModal('a')">
                            <span id="a-display">اختر الفريق...</span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </div>
                        <input type="hidden" name="a" id="a-input" required>
                    </div>

                    <!-- البطولة -->
                    <div class="form-group">
                        <label>البطولة</label>
                        <div class="custom-select-trigger" onclick="openSearchModal('l')">
                            <span id="l-display">اختر البطولة...</span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </div>
                        <input type="hidden" name="l" id="l-input">
                    </div>

                    <div class="form-group"><label>الوقت</label><input type="text" name="t" class="form-input" placeholder="09:00 PM"></div>
                    <div class="form-group"><label>اسم المعلق</label><input type="text" name="m_c" class="form-input" placeholder="عصام الشوالي"></div>
                    
                    <!-- الحالة -->
                    <div class="form-group">
                        <label>الحالة</label>
                        <div class="custom-select-trigger" onclick="openSimpleModal('s')">
                            <span id="s-display">قادمة</span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </div>
                        <input type="hidden" name="s" id="s-input" value="upcoming">
                    </div>

                    <!-- اليوم -->
                    <div class="form-group">
                        <label>اليوم</label>
                        <div class="custom-select-trigger" onclick="openSimpleModal('d')">
                            <span id="d-display">اليوم</span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </div>
                        <input type="hidden" name="d" id="d-input" value="today">
                    </div>

                    <div class="form-group"><label>القناة</label><input type="text" name="c" class="form-input" placeholder="beIN Sports 1"></div>
                    <div class="form-group" style="grid-column: span 2;"><label>رابط البث</label><input type="text" name="u" class="form-input" placeholder="https://..."></div>
                </div>
                <button type="submit" name="add_m" style="width:100%; padding:14px; background:#6366f1; color:#fff; border:none; border-radius:12px; margin-top:20px; font-weight:800; font-size:16px; cursor:pointer; box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);">إضافة المباراة الآن</button>
            </form>
            <script>
                document.getElementById('matchForm').onsubmit = function(e) {
                    const h = document.getElementById('h-input').value;
                    const a = document.getElementById('a-input').value;
                    const l = document.getElementById('l-input').value;
                    const t = this.t.value.trim();
                    const mc = this.m_c.value.trim();
                    const ch = this.c.value.trim();
                    const u = this.u.value.trim();
                    
                    if(!h || !a || !l || !t || !mc || !ch || !u) {
                        e.preventDefault();
                        showToast('الرجاء إكمال كافة الخانات المطلوبة', 'error');
                        return false;
                    }
                };
            </script>

            <!-- Modal المتقدم للبحث -->
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
                .modal-content { background:var(--bg-card); width:90%; max-width:450px; border-radius:20px; overflow:hidden; border:1px solid var(--border-color); box-shadow:0 20px 50px rgba(0,0,0,0.3); animation:fadeInScale 0.3s ease; }
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
                <a href="/admin/index.php?section=api_mgr" style="margin-right:auto; background:#ef4444; color:#fff; padding:8px 18px; border-radius:10px; font-weight:700; text-decoration:none; font-size:13px;">انتقل للإعدادات</a>
            </div>
            <?php endif; ?>
            
            <!-- بطاقات الإحصائيات الجمالية -->
            <div class="stats-grid" style="margin-bottom:30px;">
                <div class="stat-card total"><i class="fa-solid fa-database"></i><h3><?php echo $c_total; ?></h3><p>إجمالي المباريات</p></div>
                <div class="stat-card live"><i class="fa-solid fa-calendar-check"></i><h3><?php echo $c_today; ?></h3><p>مباريات اليوم</p></div>
                <div class="stat-card waiting"><i class="fa-solid fa-calendar-minus"></i><h3><?php echo $c_yest; ?></h3><p>مباريات الأمس</p></div>
                <div class="stat-card finished"><i class="fa-solid fa-calendar-plus"></i><h3><?php echo $c_tom; ?></h3><p>مباريات الغد</p></div>
            </div>

            <!-- حاوية الجدول بتصميم "نظرة عامة" -->
            <div class="recent-card">
                <div class="recent-header" style="justify-content:space-between; flex-wrap:wrap; gap:10px;">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <i class="fa-solid fa-cloud-arrow-down" style="color:#6366f1;"></i> 
                        <h3 style="margin:0;">المباريات</h3>
                    </div>
                    <!-- تبويبات الأيام بتصميم "نظرة عامة" -->
                    <div class="day-tabs" style="margin-bottom:0;">
                        <div class="day-tab" data-day="yesterday" onclick="switchApiTab(this)">مباريات الأمس</div>
                        <div class="day-tab active" data-day="today" onclick="switchApiTab(this)">مباريات اليوم</div>
                        <div class="day-tab" data-day="tomorrow" onclick="switchApiTab(this)">مباريات الغد</div>
                    </div>
                </div>
                
                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="text-align:right; border-bottom:1px solid var(--border-color); color:var(--text-sub); font-size:13px;">
                                <th style="padding:15px 25px;">المباراة</th>
                                <th style="padding:15px;">البطولة</th>
                                <th style="padding:15px;">الوقت</th>
                                <th style="padding:15px;">الحالة</th>
                                <th style="padding:15px 25px; text-align:left;">التحكم</th>
                            </tr>
                        </thead>
                        <tbody id="api-bank-body">
                            <tr><td colspan="5" style="text-align:center; padding:50px; color:var(--text-dim);">جاري تحميل البيانات...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Modal إضافة بيانات البث -->
            <div id="addApiModal" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:1000; align-items:center; justify-content:center;">
                <div class="modal" style="background:var(--card); width:450px; border-radius:15px; overflow:hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);">
                    <div class="modal-header" style="padding:20px; border-bottom:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                        <h3 style="margin:0; font-size:18px;">إضافة بيانات البث</h3>
                        <button onclick="closeApiModal()" style="background:none; border:none; color:var(--text-main); cursor:pointer; font-size:20px;"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <div style="padding:25px;">
                        <input type="hidden" id="add-api-id">
                        <div class="form-group" style="margin-bottom:18px;">
                            <label style="display:block; margin-bottom:8px; font-weight:700;">رابط البث</label>
                            <input type="text" id="add-api-url" class="form-input" placeholder="https://..." style="width:100%; box-sizing:border-box; padding:12px;">
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                            <div class="form-group">
                                <label style="display:block; margin-bottom:8px; font-weight:700;">القناة</label>
                                <input type="text" id="add-api-channel" class="form-input" placeholder="beIN 1" style="width:100%; box-sizing:border-box; padding:12px;">
                            </div>
                            <div class="form-group">
                                <label style="display:block; margin-bottom:8px; font-weight:700;">المعلق</label>
                                <input type="text" id="add-api-comm" class="form-input" placeholder="المعلق" style="width:100%; box-sizing:border-box; padding:12px;">
                            </div>
                        </div>
                        <button onclick="confirmAddFromBank()" style="width:100%; padding:15px; background:linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color:#fff; border:none; border-radius:12px; margin-top:25px; font-weight:800; cursor:pointer; box-shadow: 0 10px 15px -3px rgba(99,102,241,0.3);">
                            <i class="fa-solid fa-check-circle" style="margin-left:8px;"></i> تأكيد الإضافة للموقع
                        </button>
                    </div>
                </div>
            </div>

            <style>
                .r-tab { padding:8px 20px; border-radius:8px; font-size:13px; font-weight:700; color:var(--text-sub); cursor:pointer; transition:0.3s; }
                .r-tab:hover { color:var(--text-main); }
                .r-tab.active { background:var(--card); color:#6366f1; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); }
                .api-add-btn { padding:7px 14px; border-radius:8px; border:1px solid #6366f1; background:rgba(99,102,241,0.05); color:#6366f1; cursor:pointer; font-weight:700; transition:0.2s; font-size:12px; }
                .api-add-btn:hover { background:#6366f1; color:#fff; transform: translateY(-2px); }
                .status-badge { padding:4px 10px; border-radius:6px; font-size:11px; font-weight:800; display:inline-block; }
                .status-live { background:rgba(239,68,68,0.15); color:#ef4444; border:1px solid rgba(239,68,68,0.2); }
                .status-final { background:rgba(16,185,129,0.15); color:#10b981; border:1px solid rgba(16,185,129,0.2); }
                .status-up { background:var(--bg-main); color:var(--text-dim); border:1px solid var(--border-color); }
            </style>

            <script>
                let apiBank = [];
                async function loadBank() {
                    try {
                        const r = await fetch('/admin/api.php?action=get_bank');
                        apiBank = await r.json();
                        const activeTab = document.querySelector('.day-tab.active').dataset.day;
                        renderBank(activeTab);
                    } catch(e) { console.error(e); }
                }

                function renderBank(day) {
                    const tbody = document.getElementById('api-bank-body');
                    const filtered = apiBank.filter(m => m.day === day);
                    if(filtered.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:60px; color:var(--text-dim);"><i class="fa-solid fa-check-double" style="font-size:30px; margin-bottom:15px; display:block; color:#10b981;"></i> لا توجد مباريات جديدة متاحة حالياً</td></tr>`;
                        return;
                    }
                    tbody.innerHTML = filtered.map(m => {
                        let stClass = 'status-up', stTxt = 'لم تبدأ بعد';
                        if(m.status === 'live') { stClass = 'status-live'; stTxt = 'مباشر الآن'; }
                        else if(m.status === 'finished') { stClass = 'status-final'; stTxt = 'انتهت المباراة'; }

                        return `
                        <tr style="border-bottom:1px solid var(--border-color); transition: 0.2s;">
                            <td style="padding:18px 25px;">
                                <div style="display:flex; align-items:center; gap:12px;">
                                    <div style="display:flex; align-items:center; gap:8px; min-width:120px; justify-content:flex-end;">
                                        <span style="font-weight:700; font-size:14px;">${m.homeTeam}</span>
                                        <img src="${m.homeLogo}" style="width:26px; height:26px; object-fit:contain;">
                                    </div>
                                    <span style="background:var(--bg-main); padding:2px 8px; border-radius:6px; color:var(--text-dim); font-size:11px; font-weight:800;">VS</span>
                                    <div style="display:flex; align-items:center; gap:8px; min-width:120px;">
                                        <img src="${m.awayLogo}" style="width:26px; height:26px; object-fit:contain;">
                                        <span style="font-weight:700; font-size:14px;">${m.awayTeam}</span>
                                    </div>
                                </div>
                            </td>
                            <td style="padding:15px; color:var(--text-sub); font-size:13px; font-weight:600;">${m.league}</td>
                            <td style="padding:15px; font-weight:800; color:#6366f1; font-size:14px;">${m.time}</td>
                            <td style="padding:15px;">
                                <span class="status-badge ${stClass}">${stTxt}</span>
                            </td>
                            <td style="padding:15px 25px; text-align:left;">
                                <button class="api-add-btn" onclick="openApiModal('${m.id}')">
                                    <i class="fa-solid fa-plus" style="margin-left:5px;"></i> إضافة للموقع
                                </button>
                            </td>
                        </tr>`;
                    }).join('');
                }

                function switchApiTab(tab) {
                    document.querySelectorAll('.day-tab').forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    renderBank(tab.dataset.day);
                }

                function openApiModal(id) {
                    document.getElementById('add-api-id').value = id;
                    document.getElementById('addApiModal').style.display = 'flex';
                }

                function closeApiModal() {
                    document.getElementById('addApiModal').style.display = 'none';
                    document.getElementById('add-api-url').value = '';
                }

                async function confirmAddFromBank() {
                    const id = document.getElementById('add-api-id').value;
                    const url = document.getElementById('add-api-url').value;
                    const ch  = document.getElementById('add-api-channel').value;
                    const comm = document.getElementById('add-api-comm').value;

                    const btn = event.currentTarget;
                    const originalText = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> جاري الإضافة...';

                    try {
                        const r = await fetch('/admin/api.php?action=add_from_bank', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({id, streamUrl: url, channel: ch, commentator: comm})
                        });
                        const d = await r.json();
                        if(d.success) {
                            showToast('تم بنجاح! المباراة الآن حية في الموقع ✅', 'success');
                            closeApiModal();
                            loadBank();
                        }
                    } catch(e) { showToast('خطأ في الاتصال', 'error'); }
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }

                loadBank();
            </script>
        <?php elseif($sec == 'api_mgr'):
            $apiSettingsFile = __DIR__ . '/../data/api_settings.json';
            $apiSettings = file_exists($apiSettingsFile) ? json_decode(file_get_contents($apiSettingsFile), true) : [];
            $savedKey = isset($apiSettings['api_key']) && !empty($apiSettings['api_key']);
            $cacheMin = $apiSettings['cache_minutes'] ?? 15;
            $fetchHour = $apiSettings['fetch_hour'] ?? 0;
            $autoF    = isset($apiSettings['auto_fetch']) ? $apiSettings['auto_fetch'] : true;
        ?>
            <h2 style="font-weight:800; margin-bottom:8px;"><i class="fa-solid fa-plug-circle-bolt" style="color:#10b981;"></i> إدارة مزود البيانات (API)</h2>
            <p style="color:var(--text-sub); margin-bottom:25px; font-size:14px;">نظام الكاش الذكي: يتم أرشفة مباريات الأيام الثلاثة (أمس، اليوم، غد) مرة واحدة يومياً لتوفير الطلبات.</p>

            <!-- بطاقات الحالة المحدثة -->
            <div class="stats-grid" style="margin-bottom:25px;">
                <div class="stat-card total"><i class="fa-solid fa-calendar-check"></i><h3 id="st-fetch-date" style="font-size:16px;">...</h3><p>آخر جلب يومي</p></div>
                <div class="stat-card waiting"><i class="fa-solid fa-moon"></i><h3 style="font-size:16px;"><?php echo sprintf("%02d:00", $fetchHour); ?> <?php echo $fetchHour >= 12 ? 'PM' : 'AM'; ?></h3><p>تحديث البنك القادم</p></div>
                <div class="stat-card live"><i class="fa-solid fa-rotate"></i><h3 id="st-live-update" style="font-size:16px;">...</h3><p>آخر تحديث حي</p></div>
                <div class="stat-card finished"><i class="fa-solid fa-gauge-high"></i><h3 id="st-requests" style="font-size:16px;">...</h3><p>الطلبـات المستخدمة</p></div>
            </div>

            <div style="max-width:800px; margin:0 auto;">
                <div class="recent-card">
                    <div class="recent-header">
                        <i class="fa-solid fa-gears" style="color:#6366f1;"></i>
                        <h3 style="margin-right:10px;">إعدادات المزامنة والاتصال</h3>
                    </div>
                    <div style="padding:25px;">
                        <div class="form-group">
                            <label>مفتاح API-Football</label>
                            <div style="position:relative;">
                                <input type="password" id="api-key-input" class="form-input"
                                    placeholder="<?php echo $savedKey ? '•••••••••• (محفوظ)' : 'أدخل المفتاح هنا...'; ?>"
                                    style="padding-left:45px;">
                                <i class="fa-solid fa-eye" onclick="toggleApiKey()" style="position:absolute; left:15px; top:50%; transform:translateY(-50%); cursor:pointer; color:var(--text-sub);"></i>
                            </div>
                        </div>

                        <div class="form-group" style="display:flex; gap:15px; margin-bottom:15px; flex-wrap:wrap;">
                            <div style="flex:1; min-width:150px;">
                                <label>تحديث النتائج (بالدقائق)</label>
                                <input type="number" id="cache-minutes" class="form-input" value="<?php echo $cacheMin; ?>" min="1" style="text-align:right;">
                            </div>
                            <div style="flex:1; min-width:200px;">
                                <label>وقت الجلب اليومي</label>
                                <div style="display:flex; gap:10px; align-items:center; width:100%;">
                                    <input type="number" id="fetch-h-12" class="form-input" style="flex:1; text-align:right;" 
                                        value="<?php echo ($fetchHour == 0) ? 12 : ($fetchHour > 12 ? $fetchHour-12 : $fetchHour); ?>" min="1" max="12">
                                    <div class="time-toggle" id="ampm-toggle" style="flex:1; display:flex;">
                                        <div class="t-opt <?php echo $fetchHour < 12 ? 'active' : ''; ?>" data-val="AM" style="flex:1; text-align:center;">AM</div>
                                        <div class="t-opt <?php echo $fetchHour >= 12 ? 'active' : ''; ?>" data-val="PM" style="flex:1; text-align:center;">PM</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <style>
                            .time-toggle { display:flex; background:var(--bg-main); padding:4px; border-radius:10px; border:1px solid var(--border-color); }
                            .t-opt { padding:8px 20px; border-radius:8px; cursor:pointer; font-weight:800; font-size:13px; color:var(--text-dim); transition:0.3s; }
                            .t-opt.active { background:#6366f1; color:#fff; box-shadow:0 4px 10px rgba(99,102,241,0.3); }
                        </style>
                        <script>
                            document.querySelectorAll('.t-opt').forEach(opt => {
                                opt.onclick = function() {
                                    this.parentElement.querySelectorAll('.t-opt').forEach(o => o.classList.remove('active'));
                                    this.classList.add('active');
                                }
                            });
                        </script>

                        <div class="form-group" style="display:flex; align-items:center; gap:12px; margin-bottom:25px;">
                            <input type="checkbox" id="auto-fetch" style="width:18px; height:18px; cursor:pointer;" <?php echo $autoF?'checked':''; ?>>
                            <label for="auto-fetch" style="margin:0; cursor:pointer; font-weight:700;">تفعيل الجلب التلقائي (الوضع الذكي)</label>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                            <button onclick="saveApiSettings()" class="p-btn" style="height:50px; background:#6366f1; color:#fff; border-radius:12px; font-weight:800;">
                                <i class="fa-solid fa-floppy-disk" style="margin-left:8px;"></i> حفظ الإعدادات
                            </button>
                            <button onclick="forceFetch()" class="p-btn" style="height:50px; background:rgba(16,185,129,0.1); color:#10b981; border:1px solid #10b981; border-radius:12px; font-weight:800;">
                                <i class="fa-solid fa-cloud-arrow-down" style="margin-left:8px;"></i> جلب بنك جديد (Snapshot)
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            (async function loadApiStatus() {
                try {
                    const r = await fetch('/admin/api.php?action=api_status');
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

            async function saveApiSettings() {
                const keyInput = document.getElementById('api-key-input').value.trim();
                const min = document.getElementById('cache-minutes').value;
                const h12 = parseInt(document.getElementById('fetch-h-12').value);
                const ampm = document.querySelector('.t-opt.active').dataset.val;
                const auto = document.getElementById('auto-fetch').checked;

                // تحويل الوقت من 12h إلى 24h
                let hour24 = h12;
                if (ampm === 'PM' && h12 < 12) hour24 += 12;
                if (ampm === 'AM' && h12 === 12) hour24 = 0;

                const r = await fetch('/admin/api.php?action=save_api_settings', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        api_key: keyInput, // سيتم تجاهله في السيرفر إذا كان فارغاً
                        cache_minutes: parseInt(min), 
                        fetch_hour: hour24, 
                        auto_fetch: auto
                    })
                });
                const d = await r.json();
                if (d.success) { 
                    showToast('تم حفظ الإعدادات بنجاح ✅', 'success'); 
                    setTimeout(()=>location.reload(), 1000); 
                } else {
                    showToast('خطأ في الحفظ', 'error');
                }
            }

            async function forceFetch() {
                showToast('جاري جلب المباريات... قد يستغرق بضع ثوانٍ', 'success');
                try {
                    const r = await fetch('/admin/api.php?action=force_fetch');
                    const d = await r.json();
                    if (d.success) {
                        showToast('تم جلب ' + d.count + ' مباراة بنجاح ✅', 'success');
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        showToast(d.error || 'حدث خطأ أثناء الجلب', 'error');
                    }
                } catch(e) { showToast('تعذر الاتصال بالسيرفر', 'error'); }
            }
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
                                        <a href="/admin/index.php?del_n=<?php echo $n['id']; ?>&section=news&p=<?php echo $page; ?>" class="btn-del" onclick="return confirm('حذف؟')"><i class="fa-solid fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if($totalPages > 1): ?>
            <div style="display:flex; justify-content:center; gap:8px; margin-top:25px;">
                <?php if($page > 1): ?><a href="/admin/index.php?section=news&p=<?php echo $page-1; ?>" class="p-btn"><i class="fa-solid fa-chevron-right"></i></a><?php endif; ?>
                <?php for($i=1; $i<=$totalPages; $i++): ?>
                    <a href="/admin/index.php?section=news&p=<?php echo $i; ?>" class="p-btn <?php echo $i==$page?'active':''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if($page < $totalPages): ?><a href="/admin/index.php?section=news&p=<?php echo $page+1; ?>" class="p-btn"><i class="fa-solid fa-chevron-left"></i></a><?php endif; ?>
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
        
        <!-- نافذة تعديل المباراة (عامة) -->
        <div id="edit-modal" class="modal-overlay"><div class="modal-box"><div class="modal-head"><i class="fa-solid fa-pen" style="color:#6366f1;"></i> تعديل المباراة</div>
            <form method="POST" action="/admin/index.php?section=<?php echo $sec; ?>"><input type="hidden" name="edit_match_id" id="edit-id"><div class="modal-body">
                <div><label>القناة</label><input type="text" name="edit_channel" id="edit-channel" class="form-input"></div>
                <div><label>المعلق</label><input type="text" name="edit_commentator" id="edit-commentator" class="form-input"></div>
                <div><label>الحالة</label><select name="edit_status" id="edit-status" class="form-input"><option value="upcoming">قادمة</option><option value="live">جارية الآن</option><option value="finished">انتهت</option></select></div>
                <div><label>النتيجة</label><input type="text" name="edit_score" id="edit-score" class="form-input"></div>
                <div class="full"><label>رابط البث</label><input type="text" name="edit_stream" id="edit-stream" class="form-input"></div>
            </div><div class="modal-foot"><button type="button" class="btn-cancel-sm" onclick="document.getElementById('edit-modal').classList.remove('open')">إلغاء</button><button type="submit" name="save_edit" class="btn-primary-sm">حفظ</button></div></form>
        </div></div>
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
            document.getElementById('edit-status').value = m.status || 'upcoming';
            document.getElementById('edit-score').value = m.score || '';
            document.getElementById('edit-stream').value = m.streamUrl || '';
            document.getElementById('edit-modal').classList.add('open');
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
</body>
</html>
