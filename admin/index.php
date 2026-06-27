<?php
session_start();
// تصحيح المسارات لتعمل من داخل مجلد admin
// منع التخزين المؤقت لضمان ظهور أحدث البيانات دائماً
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$matchesFile = '../data/matches.json';
$newsFile = '../data/news.json';

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
            $d = json_decode(@file_get_contents($matchesFile), true) ?: [];
            $d[] = array('id'=>time(),'homeTeam'=>$_POST['h'],'awayTeam'=>$_POST['a'],'homeLogo'=>$_POST['hl'],'awayLogo'=>$_POST['al'],'league'=>$_POST['l'],'time'=>$_POST['t'],'status'=>$_POST['s'],'status_text'=>$_POST['st'],'day'=>(isset($_POST['d'])?$_POST['d']:'today'),'channel'=>$_POST['c'],'streamUrl'=>$_POST['u'],'homeScore'=>"0",'awayScore'=>"0");
            file_put_contents($matchesFile, json_encode($d, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
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
                    $imgPath = '/uploads/' . $newName;
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
                            $n['image'] = '/uploads/' . $newName;
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
            $used = [];
            foreach($ns as $n) if(!empty($n['image'])) $used[] = basename($n['image']);
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
            <a href="/admin/index.php?section=main" class="nav-item <?php echo $sec=='main'?'active':''; ?>"><i class="fa-solid fa-chart-pie"></i> نظرة عامة</a>
            <a href="/admin/index.php?section=current" class="nav-item <?php echo $sec=='current'?'active':''; ?>"><i class="fa-solid fa-list-check"></i> المباريات</a>
            <a href="/admin/index.php?section=add_m" class="nav-item <?php echo $sec=='add_m'?'active':''; ?>"><i class="fa-solid fa-plus-circle"></i> إضافة مباراة</a>
            <a href="/admin/index.php?section=instant" class="nav-item <?php echo $sec=='instant'?'active':''; ?>"><i class="fa-solid fa-bolt"></i> إضافة فورية</a>
            <a href="/admin/index.php?section=news" class="nav-item <?php echo $sec=='news'?'active':''; ?>"><i class="fa-solid fa-newspaper"></i> الأخبار</a>
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
                        <?php foreach(['today','yesterday','tomorrow'] as $dayKey):
                            $dayM = array_filter($matches, function($m) use ($dayKey) { return (isset($m['day'])?$m['day']:'today') === $dayKey; });
                            $dayM = array_slice(array_reverse($dayM), 0, 5); 
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
                        <?php foreach(['today','yesterday','tomorrow'] as $dayKey):
                            $dayM = array_values(array_filter($allM, function($m) use ($dayKey) { return (isset($m['day'])?$m['day']:'today') === $dayKey; }));
                            $isVisible = $dayKey === 'today' ? '' : ' style="display:none;"';
                        ?>
                        <tr data-day="<?php echo $dayKey; ?>" data-empty="1"<?php echo (!empty($dayM) ? ' style="display:none;"' : $isVisible); ?>>
                            <td colspan="6"><div style="padding:40px; text-align:center; color:var(--text-dim);"><i class="fa-solid fa-calendar-day" style="font-size:30px; margin-bottom:10px; display:block;"></i><p>لا توجد مباريات مضافة</p></div></td>
                        </tr>
                        <?php foreach($dayM as $m):
                            $badgeClass = (isset($m['status']) && $m['status'] === 'live') ? 'badge-live' : ((isset($m['status']) && $m['status'] === 'finished') ? 'badge-finished' : 'badge-upcoming');
                            $badgeText  = (isset($m['status']) && $m['status'] === 'live') ? 'جارية' : ((isset($m['status']) && $m['status'] === 'finished') ? 'انتهت' : 'قادمة');
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
        <?php elseif($sec == 'add_m'): ?>
            <h2 style="font-weight:800; margin-bottom:25px;">إضافة مباراة</h2>
            <form method="POST" style="background:var(--bg-card); padding:30px; border-radius:15px; border:1px solid var(--border-color);">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                    <div class="form-group"><label>الفريق الأرضي</label><input type="text" name="h" class="form-input" required></div>
                    <div class="form-group"><label>لوجو الأرضي</label><input type="text" name="hl" class="form-input"></div>
                    <div class="form-group"><label>الفريق الضيف</label><input type="text" name="a" class="form-input" required></div>
                    <div class="form-group"><label>لوجو الضيف</label><input type="text" name="al" class="form-input"></div>
                    <div class="form-group"><label>البطولة</label><input type="text" name="l" class="form-input"></div>
                    <div class="form-group"><label>الوقت</label><input type="text" name="t" class="form-input" placeholder="09:00 PM"></div>
                    <div class="form-group"><label>الحالة</label><select name="s" class="form-input"><option value="upcoming">قادمة</option><option value="live">جارية الآن</option><option value="finished">انتهت</option></select></div>
                    <div class="form-group"><label>اليوم</label><select name="d" class="form-input"><option value="today">اليوم</option><option value="yesterday">الأمس</option><option value="tomorrow">الغد</option></select></div>
                </div>
                <div class="form-group" style="margin-top:10px;"><label>رابط البث</label><input type="text" name="u" class="form-input" placeholder="https://..."></div>
                <button type="submit" name="add_m" style="width:100%; padding:14px; background:#6366f1; color:#fff; border:none; border-radius:12px; margin-top:10px; font-weight:800; font-size:16px; cursor:pointer;">إضافة المباراة الآن</button>
            </form>
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
                    <form method="POST" onsubmit="return confirm('هل أنت متأكد من تنظيف الصور غير المستخدمة؟')">
                        <button type="submit" name="clean_imgs" style="padding:10px 20px; background:#6366f1; color:#fff; border:none; border-radius:10px; font-weight:800; font-size:13px; cursor:pointer; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);"><i class="fa-solid fa-broom" style="margin-left:6px;"></i> تنظيف الصور</button>
                    </form>
                </div>
                <div class="table-res" style="border-top:1px solid var(--border-color);">
                    <table class="table">
                        <thead><tr><th style="width:100px;">الصورة</th><th>العنوان</th><th style="width:120px;">التاريخ</th><th style="width:120px;">التحكم</th></tr></thead>
                        <tbody>
                        <?php foreach($displayNews as $n): ?>
                            <tr>
                                <td>
                                    <img src="<?php echo $n['image']; ?>" style="width:70px; height:45px; border-radius:8px; object-fit:cover; border:1px solid var(--border-color);">
                                </td>
                                <td style="font-weight:800; font-size:14px; color:var(--text-main);"><?php echo $n['title']; ?></td>
                                <td class="date-cell" data-time="<?php echo $n['id']; ?>" style="font-size:12px; font-weight:700; color:var(--text-sub);">--</td>
                                <td>
                                    <div style="display:flex; gap:8px;">
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
            <style>
                .p-btn { width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-main); font-weight: 700; font-size: 13px; text-decoration: none; transition: 0.3s; }
                .p-btn.active { background: var(--color-primary); color: #fff; border-color: var(--color-primary); box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); }
                .p-btn:hover:not(.active) { background: var(--bg-input); }
            </style>
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
