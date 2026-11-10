<?php
// api/save_attendance.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'ログインが必要です']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

$csrf_token = $data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!verify_csrf_token($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'CSRFトークンが無効です']);
    exit;
}

$event_id = isset($data['event_id']) && $data['event_id'] !== '' ? (int)$data['event_id'] : null;
$attendance_date = !empty($data['date']) ? trim($data['date']) : null;
$status = trim($data['status'] ?? '');
$target_time = !empty($data['target_time']) ? trim($data['target_time']) : null;
$reason = trim($data['reason'] ?? '');
$user_id = $_SESSION['user_id'];

// バリデーション
$valid_statuses = ['present', 'late', 'early', 'absent'];
if (!in_array($status, $valid_statuses, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => '不正なステータスです']);
    exit;
}

if (empty($event_id) && empty($attendance_date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => '練習イベントまたは日付が指定されていません']);
    exit;
}

try {
    $pdo = get_db_connection();

    // テーブルの自動マイグレーション
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM attendances LIKE 'attendance_date'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE attendances ADD COLUMN attendance_date DATE NULL AFTER event_id");
            $pdo->exec("ALTER TABLE attendances MODIFY COLUMN event_id INT NULL");
            // 既存レコードの日付を補完
            $pdo->exec("UPDATE attendances a JOIN events e ON a.event_id = e.id SET a.attendance_date = e.event_date WHERE a.attendance_date IS NULL");
        }
    } catch (Exception $e) {
        // フォールバック
    }

    if (!empty($event_id)) {
        // イベント存在確認
        $chk = $pdo->prepare('SELECT id, event_date, title FROM events WHERE id = :id');
        $chk->execute(['id' => $event_id]);
        $event = $chk->fetch();
        if (!$event) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => '該当の練習が見つかりません']);
            exit;
        }
        $attendance_date = $event['event_date'];

        // 既存の出欠回答があるかチェック
        $find = $pdo->prepare('SELECT id FROM attendances WHERE event_id = :event_id AND user_id = :user_id LIMIT 1');
        $find->execute(['event_id' => $event_id, 'user_id' => $user_id]);
        $existing = $find->fetch();

        if ($existing) {
            $update = $pdo->prepare('
                UPDATE attendances 
                SET status = :status, target_time = :target_time, reason = :reason, attendance_date = :date, updated_at = NOW() 
                WHERE id = :id
            ');
            $update->execute([
                'status' => $status,
                'target_time' => $target_time,
                'reason' => $reason !== '' ? $reason : null,
                'date' => $attendance_date,
                'id' => $existing['id']
            ]);
        } else {
            $insert = $pdo->prepare('
                INSERT INTO attendances (event_id, user_id, attendance_date, status, target_time, reason, created_at, updated_at) 
                VALUES (:event_id, :user_id, :date, :status, :target_time, :reason, NOW(), NOW())
            ');
            $insert->execute([
                'event_id' => $event_id,
                'user_id' => $user_id,
                'date' => $attendance_date,
                'status' => $status,
                'target_time' => $target_time,
                'reason' => $reason !== '' ? $reason : null
            ]);
        }
    } else {
        // 練習予定がない日付直接の出欠登録
        $find = $pdo->prepare('SELECT id FROM attendances WHERE event_id IS NULL AND attendance_date = :date AND user_id = :user_id LIMIT 1');
        $find->execute(['date' => $attendance_date, 'user_id' => $user_id]);
        $existing = $find->fetch();

        if ($existing) {
            $update = $pdo->prepare('
                UPDATE attendances 
                SET status = :status, target_time = :target_time, reason = :reason, updated_at = NOW() 
                WHERE id = :id
            ');
            $update->execute([
                'status' => $status,
                'target_time' => $target_time,
                'reason' => $reason !== '' ? $reason : null,
                'id' => $existing['id']
            ]);
        } else {
            $insert = $pdo->prepare('
                INSERT INTO attendances (event_id, user_id, attendance_date, status, target_time, reason, created_at, updated_at) 
                VALUES (NULL, :user_id, :date, :status, :target_time, :reason, NOW(), NOW())
            ');
            $insert->execute([
                'user_id' => $user_id,
                'date' => $attendance_date,
                'status' => $status,
                'target_time' => $target_time,
                'reason' => $reason !== '' ? $reason : null
            ]);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => '出欠を登録しました',
        'attendance' => [
            'event_id' => $event_id,
            'date' => $attendance_date,
            'status' => $status,
            'target_time' => $target_time,
            'reason' => $reason,
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'データベースエラー: ' . $e->getMessage()]);
}
