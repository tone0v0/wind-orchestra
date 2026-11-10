<?php
// api/get_events.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'ログインが必要です']);
    exit;
}

$user_id = $_SESSION['user_id'];
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');

if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => '不正な年月です']);
    exit;
}

$start_date = sprintf('%04d-%02d-01', $year, $month);
$end_date = date('Y-m-t', strtotime($start_date));

try {
    $pdo = get_db_connection();

    // テーブルの自動マイグレーション確認
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM attendances LIKE 'attendance_date'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE attendances ADD COLUMN attendance_date DATE NULL AFTER event_id");
            $pdo->exec("ALTER TABLE attendances MODIFY COLUMN event_id INT NULL");
            $pdo->exec("UPDATE attendances a JOIN events e ON a.event_id = e.id SET a.attendance_date = e.event_date WHERE a.attendance_date IS NULL");
        }
    } catch (Exception $e) {
        // フォールバック
    }

    // 1. 指定月の練習イベント一覧と、ログインユーザーの出欠状況を取得
    $sql = "
        SELECT 
            e.id,
            e.title,
            e.event_date,
            DATE_FORMAT(e.start_time, '%H:%i') AS start_time,
            DATE_FORMAT(e.end_time, '%H:%i') AS end_time,
            e.location,
            e.description,
            e.deadline,
            a.status AS my_status,
            DATE_FORMAT(a.target_time, '%H:%i') AS my_target_time,
            a.reason AS my_reason,
            (SELECT COUNT(*) FROM attendances WHERE event_id = e.id AND status = 'present') AS count_present,
            (SELECT COUNT(*) FROM attendances WHERE event_id = e.id AND status = 'late') AS count_late,
            (SELECT COUNT(*) FROM attendances WHERE event_id = e.id AND status = 'early') AS count_early,
            (SELECT COUNT(*) FROM attendances WHERE event_id = e.id AND status = 'absent') AS count_absent
        FROM events e
        LEFT JOIN attendances a ON e.id = a.event_id AND a.user_id = :user_id
        WHERE e.event_date BETWEEN :start_date AND :end_date
        ORDER BY e.event_date ASC, e.start_time ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'user_id' => $user_id,
        'start_date' => $start_date,
        'end_date' => $end_date,
    ]);
    $events = $stmt->fetchAll();

    // 2. 練習予定のない日付直接の出欠登録を取得
    $sql_daily = "
        SELECT 
            id,
            attendance_date AS date,
            status,
            DATE_FORMAT(target_time, '%H:%i') AS target_time,
            reason
        FROM attendances
        WHERE user_id = :user_id 
          AND event_id IS NULL 
          AND attendance_date BETWEEN :start_date AND :end_date
    ";
    $stmt_daily = $pdo->prepare($sql_daily);
    $stmt_daily->execute([
        'user_id' => $user_id,
        'start_date' => $start_date,
        'end_date' => $end_date
    ]);
    $daily_attendances = $stmt_daily->fetchAll();

    echo json_encode([
        'success' => true,
        'events' => $events,
        'daily_attendances' => $daily_attendances,
        'current_user_id' => $user_id,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'データ取得に失敗しました: ' . $e->getMessage()]);
}
