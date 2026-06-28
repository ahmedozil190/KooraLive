<?php
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Africa/Cairo');

// مفتاحك
$apiKey = 'e39e7a6fe1141aeddaf7a66b42e6cd9a';

// الحصول على التاريخ (اليوم كافتراضي)
$date = $_GET['date'] ?? date('Y-m-d');

$url = "https://v3.football.api-sports.io/fixtures?date=$date&timezone=Africa/Cairo";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "x-apisports-key: $apiKey", // جربنا x-apisports-key بدلاً من x-rapidapi-key للتأكد
    "x-rapidapi-host: v3.football.api-sports.io"
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response  = curl_exec($ch);
$headerSz  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header    = substr($response, 0, $headerSz);
$body      = substr($response, $headerSz);
curl_close($ch);

// قراءة الرصيد
$remaining = '?';
if (preg_match('/x-ratelimit-requests-remaining: (\d+)/i', $header, $m)) {
    $remaining = $m[1];
}
$used = 100 - (int)$remaining;

$data     = json_decode($body, true);
$fixtures = $data['response'] ?? [];

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>مستكشف مباريات كورة لايف</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; direction:rtl; background:#0f172a; color:#f1f5f9; padding:20px; }
        .container { max-width:1200px; margin:0 auto; }
        
        /* Dashboard Stats */
        .dashboard-stats { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:15px; margin-bottom:25px; }
        .stat-card { background:#1e293b; padding:20px; border-radius:12px; border:1px solid #334155; text-align:center; }
        .stat-val { display:block; font-size:28px; font-weight:800; color:#38bdf8; }
        .stat-label { font-size:12px; color:#94a3b8; text-transform:uppercase; }

        /* Controls */
        .controls { background:#1e293b; padding:15px 25px; border-radius:12px; border:1px solid #334155; margin-bottom:25px; display:flex; align-items:center; gap:15px; }
        input[type='date'] { padding:10px 15px; background:#0f172a; border:1px solid #334155; border-radius:8px; color:#fff; font-size:15px; outline:none; }
        button { padding:10px 30px; background:#38bdf8; color:#fff; border:none; border-radius:8px; cursor:pointer; font-weight:700; }
        button:hover { background:#0ea5e9; }

        /* Table */
        .card { background:#1e293b; border-radius:12px; border:1px solid #334155; overflow:hidden; }
        table { width:100%; border-collapse:collapse; }
        th { padding:15px; background:#0f172a; color:#64748b; font-size:13px; text-align:center; }
        td { padding:15px; border-bottom:1px solid #334155; text-align:center; vertical-align:middle; }
        tr:hover { background:rgba(56,189,248,0.03); }

        .id-badge { background:#0f172a; color:#38bdf8; font-family:monospace; padding:6px 10px; border-radius:6px; border:1px solid #38bdf8; font-weight:800; }
        .team-home { color:#60a5fa; font-weight:700; text-align:right; flex:1; }
        .team-away { color:#fbbf24; font-weight:700; text-align:left; flex:1; }
        .match-wrap { display:flex; align-items:center; justify-content:center; gap:15px; }
        .score-wrap { display:flex; align-items:center; justify-content:center; gap:8px; min-width:80px; }
        .score-num { font-size:24px; font-weight:900; }
        .status-badge { padding:4px 12px; border-radius:6px; font-size:11px; font-weight:700; background:#334155; color:#94a3b8; }
        .status-live { background:rgba(239,68,68,0.1); color:#f87171; border:1px solid #ef4444; }
        .minute { color:#f87171; font-weight:800; font-size:18px; }
        .empty { padding:60px; text-align:center; color:#475569; }
    </style>
</head>
<body>
<div class="container">
    <div class="dashboard-stats">
        <div class="stat-card"><span class="stat-val">100</span><span class="stat-label">إجمالي الطلبات</span></div>
        <div class="stat-card"><span class="stat-val" style="color:#22c55e"><?= $remaining ?></span><span class="stat-label">المتبقي اليوم</span></div>
        <div class="stat-card"><span class="stat-val" style="color:#ef4444"><?= $used ?></span><span class="stat-label">المستهلك</span></div>
        <div class="stat-card"><span class="stat-val" style="color:#a78bfa"><?= count($fixtures) ?></span><span class="stat-label">مباريات هذا اليوم</span></div>
    </div>

    <form class="controls" method="GET">
        <label>اختر اليوم:</label>
        <input type="date" name="date" value="<?= $date ?>">
        <button type="submit">تحديث القائمة</button>
        <span style="color:#64748b; font-size:14px; margin-right:auto">📅 بحث عن مباريات يوم: <strong><?= $date ?></strong></span>
    </form>

    <div class="card">
        <?php if (empty($fixtures)): ?>
            <div class="empty">لا توجد مباريات متاحة للعرض في هذا اليوم. جرب اختيار تاريخ آخر.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID المباراة</th>
                    <th>اللقاء</th>
                    <th>النتيجة</th>
                    <th>الدقيقة</th>
                    <th>الوقت</th>
                    <th>الحالة</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($fixtures as $item): 
                $fix = $item['fixture'];
                $isLive = in_array($fix['status']['short'], ['1H', 'HT', '2H', 'ET', 'P']);
                $hG = $item['goals']['home'] ?? '-';
                $aG = $item['goals']['away'] ?? '-';
            ?>
                <tr>
                    <td><span class="id-badge"><?= $fix['id'] ?></span></td>
                    <td>
                        <div class="match-wrap">
                            <span class="team-home"><?= $item['teams']['home']['name'] ?></span>
                            <span style="color:#334155">vs</span>
                            <span class="team-away"><?= $item['teams']['away']['name'] ?></span>
                        </div>
                    </td>
                    <td>
                        <div class="score-wrap">
                            <span class="score-num" style="color:#60a5fa"><?= $hG ?></span>
                            <span style="color:#475569">:</span>
                            <span class="score-num" style="color:#fbbf24"><?= $aG ?></span>
                        </div>
                    </td>
                    <td class="minute"><?= ($isLive && $fix['status']['elapsed']) ? $fix['status']['elapsed'] . "'" : "—" ?></td>
                    <td style="color:#38bdf8; font-weight:700"><?= date('h:i A', strtotime($fix['date'])) ?></td>
                    <td><span class="status-badge <?= $isLive ? 'status-live' : '' ?>"><?= $fix['status']['long'] ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
