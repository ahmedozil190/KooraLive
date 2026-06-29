<?php
header('Content-Type: application/json; charset=utf-8');

// مسار ملف المباريات
$matchesFile = 'data/matches.json';

if (file_exists($matchesFile)) {
    $matches = json_decode(file_get_contents($matchesFile), true) ?: [];
    
    $result = [];
    foreach ($matches as $m) {
        $result[] = [
            'id'           => $m['id'] ?? '',
            'homeTeam'     => $m['homeTeam'] ?? '',
            'awayTeam'     => $m['awayTeam'] ?? '',
            'homeScore'    => $m['homeScore'] ?? '0',
            'awayScore'    => $m['awayScore'] ?? '0',
            'status'       => $m['status'] ?? '',
            'status_text'  => $m['status_text'] ?? 'لم تبدأ بعد',
            'time'         => $m['time'] ?? '--:--',
            'league'       => $m['league'] ?? ''
        ];
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    echo json_encode(['error' => 'ملف البيانات غير موجود'], JSON_UNESCAPED_UNICODE);
}
?>
