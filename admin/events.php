<?php
// admin/events.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = get_db_connection();
$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// 削除処理
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $token = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($token) && $id > 0) {
        $stmt = $pdo->prepare('DELETE FROM events WHERE id = :id');
        $stmt->execute(['id' => $id]);
        header('Location: events.php?msg=deleted');
        exit;
    } else {
        $error = '無効なリクエストです。';
    }
}

// 保存処理（新規登録 or 編集）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'create' || $action === 'edit')) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = '不正な送信です。もう一度お試しください。';
    } else {
        $mode = $_POST['create_mode'] ?? 'single'; // single (単日) or repeat (曜日一括)
        $title = trim($_POST['title'] ?? '');
        $start_time = trim($_POST['start_time'] ?? '17:00');
        $end_time = trim($_POST['end_time'] ?? '19:00');
        $location = trim($_POST['location'] ?? '音楽室');
        $description = trim($_POST['description'] ?? '');

        if ($title === '' || $start_time === '' || $end_time === '') {
            $error = '練習名、開始時刻、終了時刻は必須です。';
        } elseif ($action === 'create' && $mode === 'repeat') {
            // 曜日指定の一括登録
            $start_date = trim($_POST['repeat_start_date'] ?? '');
            $end_date = trim($_POST['repeat_end_date'] ?? '');
            $weekdays = $_POST['weekdays'] ?? []; // 例: [1, 2, 3] (月, 火, 水)

            if ($start_date === '' || $end_date === '') {
                $error = '一括登録の開始日と終了日を指定してください。';
            } elseif (strtotime($start_date) > strtotime($end_date)) {
                $error = '終了日は開始日以降の日付を指定してください。';
            } elseif (empty($weekdays)) {
                $error = '練習を実施する曜日を少なくとも1つ選択してください。';
            } else {
                $current = strtotime($start_date);
                $end = strtotime($end_date);
                $inserted_count = 0;

                $stmt = $pdo->prepare('
                    INSERT INTO events (title, event_date, start_time, end_time, location, description) 
                    VALUES (:title, :event_date, :start_time, :end_time, :location, :description)
                ');

                $chk = $pdo->prepare('SELECT id FROM events WHERE event_date = :event_date AND title = :title AND start_time = :start_time LIMIT 1');

                while ($current <= $end) {
                    $w = (int)date('w', $current); // 0(日) ~ 6(土)
                    if (in_array((string)$w, $weekdays, true) || in_array($w, $weekdays, true)) {
                        $d_str = date('Y-m-d', $current);

                        // 重複チェック（同一日の同一時間帯）
                        $chk->execute(['event_date' => $d_str, 'title' => $title, 'start_time' => $start_time]);
                        if (!$chk->fetch()) {
                            $stmt->execute([
                                'title' => $title,
                                'event_date' => $d_str,
                                'start_time' => $start_time,
                                'end_time' => $end_time,
                                'location' => $location,
                                'description' => $description !== '' ? $description : null
                            ]);
                            $inserted_count++;
                        }
                    }
                    $current = strtotime('+1 day', $current);
                }

                header('Location: events.php?msg=bulk_created&count=' . $inserted_count);
                exit;
            }
        } else {
            // 単日登録 or 編集
            $event_date = trim($_POST['event_date'] ?? '');
            if ($event_date === '') {
                $error = '開催日を指定してください。';
            } else {
                if ($action === 'create') {
                    $sql = 'INSERT INTO events (title, event_date, start_time, end_time, location, description) 
                            VALUES (:title, :event_date, :start_time, :end_time, :location, :description)';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        'title' => $title,
                        'event_date' => $event_date,
                        'start_time' => $start_time,
                        'end_time' => $end_time,
                        'location' => $location,
                        'description' => $description !== '' ? $description : null
                    ]);
                    header('Location: events.php?msg=created');
                    exit;
                } else {
                    $id = (int)($_POST['id'] ?? 0);
                    $sql = 'UPDATE events SET 
                                title = :title, 
                                event_date = :event_date, 
                                start_time = :start_time, 
                                end_time = :end_time, 
                                location = :location, 
                                description = :description 
                            WHERE id = :id';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        'title' => $title,
                        'event_date' => $event_date,
                        'start_time' => $start_time,
                        'end_time' => $end_time,
                        'location' => $location,
                        'description' => $description !== '' ? $description : null,
                        'id' => $id
                    ]);
                    header('Location: events.php?msg=updated');
                    exit;
                }
            }
        }
    }
}

// 編集時のデータ取得
$edit_event = null;
if ($action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM events WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $edit_event = $stmt->fetch();
    if (!$edit_event) {
        header('Location: events.php');
        exit;
    }
}

// メッセージ表示
if (($_GET['msg'] ?? '') === 'created') $success = '練習予定を登録しました。';
if (($_GET['msg'] ?? '') === 'bulk_created') {
    $count = (int)($_GET['count'] ?? 0);
    $success = "曜日指定により {$count} 件の練習予定を一括登録しました。";
}
if (($_GET['msg'] ?? '') === 'updated') $success = '練習予定を更新しました。';
if (($_GET['msg'] ?? '') === 'deleted') $success = '練習予定を削除しました。';

// 一覧取得
$stmt = $pdo->query('SELECT * FROM events ORDER BY event_date DESC, start_time DESC');
$all_events = $stmt->fetchAll();

$page_title = '練習日程管理';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>練習日程の管理</h1>
    <p>定期練習（曜日指定での一括作成）や個別練習の日程を登録・編集します。</p>
    <div class="page-header-actions">
        <?php if ($action === 'list'): ?>
            <a href="events.php?action=create" class="btn btn-accent">新しい練習を登録</a>
        <?php else: ?>
            <a href="events.php" class="btn btn-secondary">一覧へ戻る</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" style="max-width: 650px; margin: 0 auto 1.5rem auto;"><?= h($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success" style="max-width: 650px; margin: 0 auto 1.5rem auto;"><?= h($success) ?></div>
<?php endif; ?>

<?php if ($action === 'create' || $action === 'edit'): ?>
    <!-- 登録・編集フォーム -->
    <div class="card" style="max-width: 650px; margin: 0 auto 3rem auto;">
        <h2 class="card-title" style="justify-content: center; text-align: center;">
            <?= $action === 'create' ? '練習日程の登録' : '練習日程の編集' ?>
        </h2>

        <form method="POST" action="events.php?action=<?= $action ?>">
            <input type="hidden" name="csrf_token" value="<?= h(get_csrf_token()) ?>">
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="id" value="<?= (int)$edit_event['id'] ?>">
            <?php endif; ?>

            <?php if ($action === 'create'): ?>
                <!-- 登録モード切り替え（単日 or 曜日一括） -->
                <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: var(--radius); padding: 0.85rem 1rem; margin-bottom: 1.5rem;">
                    <label style="font-weight: 700; font-size: 0.95rem; margin-bottom: 0.5rem; display: block;">登録方法の選択</label>
                    <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                        <label style="display: flex; align-items: center; gap: 0.4rem; cursor: pointer;">
                            <input type="radio" name="create_mode" value="repeat" checked id="mode-repeat">
                            <strong>曜日指定でまとめて一括登録（定期練習）</strong>
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.4rem; cursor: pointer;">
                            <input type="radio" name="create_mode" value="single" id="mode-single">
                            指定日のみ登録（単日）
                        </label>
                    </div>
                </div>

                <!-- 曜日指定一括登録ブロック -->
                <div id="repeat-block" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: var(--radius); padding: 1rem; margin-bottom: 1.5rem;">
                    <div style="font-weight: 700; font-size: 0.95rem; margin-bottom: 0.75rem; color: var(--primary);">
                        定期練習の曜日と期間を指定
                    </div>

                    <div class="form-grid-2" style="margin-bottom: 1rem;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="repeat_start_date">開始日 <span class="required">*</span></label>
                            <input type="date" id="repeat_start_date" name="repeat_start_date" class="form-control"
                                   value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="repeat_end_date">終了日 <span class="required">*</span></label>
                            <input type="date" id="repeat_end_date" name="repeat_end_date" class="form-control"
                                   value="<?= date('Y-m-t', strtotime('+1 month')) ?>">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="margin-bottom: 0.4rem; display: block;">
                            実施曜日を選択 <span class="required">*</span>（例: 火、水、木）
                        </label>
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <?php 
                                $days = [
                                    1 => '月曜',
                                    2 => '火曜',
                                    3 => '水曜',
                                    4 => '木曜',
                                    5 => '金曜',
                                    6 => '土曜',
                                    0 => '日曜'
                                ];
                                // デフォルトで「火・水・木」にチェック
                                $default_checked = [2, 3, 4];
                            ?>
                            <?php foreach ($days as $w_num => $w_name): ?>
                                <label style="display: flex; align-items: center; gap: 0.35rem; background: #f8fafc; border: 1px solid var(--border); padding: 0.45rem 0.75rem; border-radius: var(--radius); cursor: pointer; user-select: none;">
                                    <input type="checkbox" name="weekdays[]" value="<?= $w_num ?>" <?= in_array($w_num, $default_checked, true) ? 'checked' : '' ?>>
                                    <span><?= $w_name ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- 単日登録ブロック -->
                <div id="single-block" style="display: none;">
                    <div class="form-group">
                        <label for="event_date">開催日 <span class="required">*</span></label>
                        <input type="date" id="event_date" name="event_date" class="form-control"
                               value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

            <?php else: ?>
                <!-- 編集時（単日） -->
                <div class="form-group">
                    <label for="event_date">開催日 <span class="required">*</span></label>
                    <input type="date" id="event_date" name="event_date" class="form-control" required
                           value="<?= h($edit_event['event_date']) ?>">
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="title">練習名・区分 <span class="required">*</span></label>
                <input type="text" id="title" name="title" class="form-control" required
                       value="<?= h($edit_event['title'] ?? '全体合奏') ?>" placeholder="例: 全体合奏、定期練習、パート練">
            </div>

            <!-- 練習時間はデフォルトで 17:00 〜 19:00 -->
            <div class="form-grid-2">
                <div class="form-group">
                    <label for="start_time">開始時刻 <span class="required">*</span></label>
                    <input type="time" id="start_time" name="start_time" class="form-control" required
                           value="<?= h(substr($edit_event['start_time'] ?? '17:00', 0, 5)) ?>">
                </div>
                <div class="form-group">
                    <label for="end_time">終了時刻 <span class="required">*</span></label>
                    <input type="time" id="end_time" name="end_time" class="form-control" required
                           value="<?= h(substr($edit_event['end_time'] ?? '19:00', 0, 5)) ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="location">場所</label>
                <input type="text" id="location" name="location" class="form-control"
                       value="<?= h($edit_event['location'] ?? '音楽室') ?>" placeholder="例: 音楽室、多目的ホール">
            </div>

            <div class="form-group">
                <label for="description">練習内容・連絡事項</label>
                <textarea id="description" name="description" class="form-control" rows="3"
                          placeholder="例: チューナーと譜面台を持参してください。"><?= h($edit_event['description'] ?? '') ?></textarea>
            </div>

            <div style="display: flex; justify-content: center; align-items: center; gap: 1rem; margin-top: 2rem;">
                <a href="events.php" class="btn btn-secondary" style="min-width: 120px;">キャンセル</a>
                <button type="submit" class="btn btn-primary" style="min-width: 140px;">
                    <?= $action === 'create' ? '練習日程を登録する' : '変更を保存する' ?>
                </button>
            </div>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const modeRepeat = document.getElementById('mode-repeat');
        const modeSingle = document.getElementById('mode-single');
        const repeatBlock = document.getElementById('repeat-block');
        const singleBlock = document.getElementById('single-block');
        const eventDateInput = document.getElementById('event_date');

        if (modeRepeat && modeSingle) {
            function updateMode() {
                if (modeRepeat.checked) {
                    repeatBlock.style.display = 'block';
                    singleBlock.style.display = 'none';
                    if (eventDateInput) eventDateInput.required = false;
                } else {
                    repeatBlock.style.display = 'none';
                    singleBlock.style.display = 'block';
                    if (eventDateInput) eventDateInput.required = true;
                }
            }
            modeRepeat.addEventListener('change', updateMode);
            modeSingle.addEventListener('change', updateMode);
            updateMode();
        }
    });
    </script>

<?php else: ?>
    <!-- 練習日程一覧（カード表示） -->
    <div class="card">
        <h2 class="card-title">練習一覧（全 <?= count($all_events) ?> 件）</h2>

        <?php if (empty($all_events)): ?>
            <p style="text-align: center; color: var(--text-muted); padding: 2rem;">
                登録されている練習はありません。「新しい練習を登録」から追加してください。
            </p>
        <?php else: ?>
            <!-- 練習日程カードリスト（PC・スマホ共通） -->
            <div class="user-cards-mobile" style="display: flex; flex-direction: column; gap: 0.85rem;">
                <?php foreach ($all_events as $event): ?>
                    <div class="user-card-item">
                        <div class="user-card-top">
                            <div>
                                <strong style="font-size: 1.05rem;">
                                    <?= date('Y/m/d', strtotime($event['event_date'])) ?>
                                    (<?= ['日','月','火','水','木','金','土'][date('w', strtotime($event['event_date']))] ?>)
                                </strong>
                                <span style="font-size: 0.9rem; color: var(--text-muted); margin-left: 0.5rem;">
                                    <?= substr($event['start_time'], 0, 5) ?> 〜 <?= substr($event['end_time'], 0, 5) ?>
                                </span>
                            </div>
                            <div>
                                <a href="event_detail.php?id=<?= $event['id'] ?>" class="btn btn-primary btn-sm">
                                    出欠集計
                                </a>
                            </div>
                        </div>

                        <div class="user-card-details">
                            <div>
                                <strong style="color: var(--primary); font-size: 1.05rem;"><?= h($event['title']) ?></strong>
                                <?php if (!empty($event['location'])): ?>
                                    <span style="font-size: 0.85rem; color: var(--text-muted); margin-left: 0.6rem;">
                                        場所: <?= h($event['location']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($event['description'])): ?>
                                <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.35rem; line-height: 1.5;">
                                    <?= nl2br(h($event['description'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="user-card-btns">
                            <a href="events.php?action=edit&id=<?= $event['id'] ?>" class="btn btn-secondary btn-sm" style="flex: 1; text-align: center;">編集</a>
                            <form method="POST" action="events.php?action=delete" style="flex: 1;" onsubmit="return confirm('この練習と関連する出欠記録をすべて削除しますか？');">
                                <input type="hidden" name="csrf_token" value="<?= h(get_csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= $event['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" style="width: 100%;">削除</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
