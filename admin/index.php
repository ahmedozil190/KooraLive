<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم كورة لايف</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #0f172a; --card: #1e293b; --primary: #3b82f6; --danger: #ef4444; --text: #f8fafc; }
        body { font-family: 'Cairo', sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .btn { padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-weight: bold; transition: 0.3s; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        
        .match-card { background: var(--card); padding: 20px; border-radius: 15px; margin-bottom: 15px; display: grid; grid-template-columns: 1fr 2fr 1fr; align-items: center; text-align: center; border: 1px solid #334155; }
        .team-info img { width: 50px; height: 50px; object-fit: contain; }
        .match-actions { grid-column: span 3; margin-top: 15px; padding-top: 15px; border-top: 1px solid #334155; display: flex; gap: 10px; justify-content: center; }

        /* Modal Styles */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); align-items: center; justify-content: center; z-index: 1000; }
        .modal-content { background: var(--card); padding: 30px; border-radius: 20px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto; }
        input, select { width: 100%; padding: 12px; margin: 10px 0; border-radius: 8px; border: 1px solid #334155; background: #0f172a; color: white; box-sizing: border-box; }
        label { font-size: 14px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>لوحة تحكم المباريات</h1>
            <button class="btn btn-primary" onclick="openModal()">إضافة مباراة جديدة +</button>
        </header>

        <div id="matches-list">
            <!-- سيتم تحميل المباريات هنا -->
        </div>
    </div>

    <!-- Modal لإضافة/تعديل مباراة -->
    <div id="matchModal" class="modal">
        <div class="modal-content">
            <h2 id="modalTitle">إضافة مباراة</h2>
            <form id="matchForm">
                <input type="hidden" id="matchId">
                <label>الفريق الأول</label>
                <input type="text" id="homeTeam" required>
                <label>رابط شعار الفريق الأول</label>
                <input type="text" id="homeLogo">
                
                <label>الفريق الثاني</label>
                <input type="text" id="awayTeam" required>
                <label>رابط شعار الفريق الثاني</label>
                <input type="text" id="awayLogo">

                <label>وقت المباراة (مثال: 09:00 PM)</label>
                <input type="text" id="time" required>
                
                <label>رابط البث المباشر (Stream URL)</label>
                <input type="text" id="streamUrl" required>
                
                <label>اسم القناة</label>
                <input type="text" id="channel">
                
                <label>اسم المعلق</label>
                <input type="text" id="commentator">

                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button type="submit" class="btn btn-primary" style="flex:1">حفظ</button>
                    <button type="button" class="btn btn-danger" onclick="closeModal()">إلغاء</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        async function loadMatches() {
            const res = await fetch('api.php?action=get_matches');
            const matches = await res.json();
            const list = document.getElementById('matches-list');
            list.innerHTML = matches.length ? '' : '<p style="text-align:center">لا توجد مباريات حالياً</p>';
            
            matches.forEach(m => {
                list.innerHTML += `
                    <div class="match-card">
                        <div class="team-info">
                            <img src="${m.homeLogo || 'https://via.placeholder.com/50'}" alt="">
                            <p>${m.homeTeam}</p>
                        </div>
                        <div>
                            <p style="font-size:1.2rem; font-weight:bold">${m.time}</p>
                            <p style="color:#94a3b8">${m.channel}</p>
                        </div>
                        <div class="team-info">
                            <img src="${m.awayLogo || 'https://via.placeholder.com/50'}" alt="">
                            <p>${m.awayTeam}</p>
                        </div>
                        <div class="match-actions">
                            <button class="btn btn-primary" onclick='editMatch(${JSON.stringify(m)})'>تعديل</button>
                            <button class="btn btn-danger" onclick="deleteMatch(${m.id})">حذف</button>
                        </div>
                    </div>
                `;
            });
        }

        function openModal() {
            document.getElementById('matchForm').reset();
            document.getElementById('matchId').value = '';
            document.getElementById('modalTitle').innerText = 'إضافة مباراة';
            document.getElementById('matchModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('matchModal').style.display = 'none';
        }

        function editMatch(m) {
            document.getElementById('matchId').value = m.id;
            document.getElementById('homeTeam').value = m.homeTeam;
            document.getElementById('homeLogo').value = m.homeLogo;
            document.getElementById('awayTeam').value = m.awayTeam;
            document.getElementById('awayLogo').value = m.awayLogo;
            document.getElementById('time').value = m.time;
            document.getElementById('streamUrl').value = m.streamUrl;
            document.getElementById('channel').value = m.channel;
            document.getElementById('commentator').value = m.commentator;
            document.getElementById('modalTitle').innerText = 'تعديل المباراة';
            document.getElementById('matchModal').style.display = 'flex';
        }

        async function deleteMatch(id) {
            if (confirm('هل أنت متأكد من حذف هذه المباراة؟')) {
                await fetch(`api.php?action=delete_match&id=${id}`);
                loadMatches();
            }
        }

        document.getElementById('matchForm').onsubmit = async (e) => {
            e.preventDefault();
            const data = {
                id: document.getElementById('matchId').value,
                homeTeam: document.getElementById('homeTeam').value,
                homeLogo: document.getElementById('homeLogo').value,
                awayTeam: document.getElementById('awayTeam').value,
                awayLogo: document.getElementById('awayLogo').value,
                time: document.getElementById('time').value,
                streamUrl: document.getElementById('streamUrl').value,
                channel: document.getElementById('channel').value,
                commentator: document.getElementById('commentator').value,
            };

            await fetch('api.php?action=save_match', {
                method: 'POST',
                body: JSON.stringify(data)
            });
            closeModal();
            loadMatches();
        };

        loadMatches();
    </script>
</body>
</html>
