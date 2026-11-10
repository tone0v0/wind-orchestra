<?php
// admin/users.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = get_db_connection();
$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// テーブル構造の自動アップデート（マイグレーション）
try {
    // campusカラムの存在チェック
    $columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'campus'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN campus VARCHAR(50) NOT NULL DEFAULT '津校' AFTER grade");
    }
    // gradeカラムの型確認（VARCHARへ変更）
    $grade_col = $pdo->query("SHOW COLUMNS FROM users LIKE 'grade'")->fetch();
    if ($grade_col && strpos(strtolower($grade_col['Type']), 'varchar') === false) {
        $pdo->exec("ALTER TABLE users MODIFY COLUMN grade VARCHAR(20) NOT NULL DEFAULT '1'");
    }

    // 楽器名の日本語化マイグレーション
    $instrument_name_map = [
        'Piccolo / Flute' => 'ピッコロ / フルート',
        'Oboe' => 'オーボエ',
        'Bassoon' => 'ファゴット',
        'B♭ Clarinet' => 'B♭クラリネット',
        'Bass Clarinet' => 'バスクラリネット',
        'Alto Saxophone' => 'アルトサックス',
        'Tenor Saxophone' => 'テナーサックス',
        'Baritone Saxophone' => 'バリトンサックス',
        'Trumpet' => 'トランペット',
        'Horn' => 'ホルン',
        'Trombone' => 'トロンボーン',
        'Euphonium' => 'ユーフォニアム',
        'Tuba' => 'チューバ',
        'Contrabass' => 'コントラバス',
        'Percussion' => '打楽器（パーカッション）'
    ];
    $stmt_update_inst = $pdo->prepare('UPDATE instruments SET name = :ja WHERE name = :en');
    foreach ($instrument_name_map as $en => $ja) {
        $stmt_update_inst->execute(['ja' => $ja, 'en' => $en]);
    }
} catch (Exception $e) {
    // 既存テーブルの場合のフォールバック
}

// セクション表示用日本語マスタ
$section_labels = [
    'Woodwind' => '木管',
    'Brass' => '金管',
    'Percussion' => '打楽器',
    'String' => '弦楽器',
    'Other' => 'その他'
];

// 学年・校舎のマスタ定義
$grade_options = [
    '1' => '1年生',
    '2' => '2年生',
    '3' => '3年生',
    '4' => '4年生',
    'teacher' => '教師',
    'other' => 'その他'
];

$campus_options = ['津校', '名古屋校'];

function format_grade($grade, $grade_options) {
    return $grade_options[$grade] ?? ($grade ? $grade . '年' : '-');
}

// 楽器一覧を取得（セレクトボックス用）
$stmt = $pdo->query('SELECT * FROM instruments ORDER BY display_order ASC');
$instruments = $stmt->fetchAll();

// 削除処理
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($token)) {
        $error = '不正な送信です。もう一度お試しください。';
    } elseif ($id <= 0) {
        $error = '削除対象のユーザーが指定されていません。';
    } elseif ($id === (int)$_SESSION['user_id']) {
        $error = '現在ログインしている自身のアカウントは削除できません。';
    } else {
        $del = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $del->execute(['id' => $id]);
        header('Location: users.php?msg=deleted');
        exit;
    }
}

// 登録・更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'create' || $action === 'edit')) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = '不正な送信です。もう一度お試しください。';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $login_id = trim($_POST['login_id'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $instrument_id = !empty($_POST['instrument_id']) ? (int)$_POST['instrument_id'] : null;
        $grade = trim($_POST['grade'] ?? '1');
        $campus = trim($_POST['campus'] ?? '津校');
        $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'student';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $password = $_POST['password'] ?? '';

        if (!in_array($campus, $campus_options, true)) {
            $campus = '津校';
        }

        if ($login_id === '' || $name === '') {
            $error = 'ログインIDと氏名は必須です。';
        } elseif ($action === 'create' && empty($password)) {
            $error = '新規登録時はパスワードの入力が必須です。';
        } else {
            // 重複チェック
            $chk = $pdo->prepare('SELECT id FROM users WHERE login_id = :login_id AND id != :id');
            $chk->execute(['login_id' => $login_id, 'id' => $id]);
            if ($chk->fetch()) {
                $error = '指定されたログインIDは既に使用されています。';
            } else {
                if ($action === 'create') {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $sql = 'INSERT INTO users (login_id, password_hash, name, instrument_id, grade, campus, role, is_active)
                            VALUES (:login_id, :password_hash, :name, :instrument_id, :grade, :campus, :role, :is_active)';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        'login_id' => $login_id,
                        'password_hash' => $password_hash,
                        'name' => $name,
                        'instrument_id' => $instrument_id,
                        'grade' => $grade,
                        'campus' => $campus,
                        'role' => $role,
                        'is_active' => $is_active
                    ]);
                    header('Location: users.php?msg=created');
                    exit;
                } else {
                    // 編集（パスワードが入力されていれば更新）
                    if (!empty($password)) {
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $sql = 'UPDATE users SET 
                                    login_id = :login_id, 
                                    name = :name, 
                                    instrument_id = :instrument_id, 
                                    grade = :grade, 
                                    campus = :campus, 
                                    role = :role, 
                                    is_active = :is_active, 
                                    password_hash = :password_hash 
                                WHERE id = :id';
                        $params = [
                            'login_id' => $login_id,
                            'name' => $name,
                            'instrument_id' => $instrument_id,
                            'grade' => $grade,
                            'campus' => $campus,
                            'role' => $role,
                            'is_active' => $is_active,
                            'password_hash' => $password_hash,
                            'id' => $id
                        ];
                    } else {
                        $sql = 'UPDATE users SET 
                                    login_id = :login_id, 
                                    name = :name, 
                                    instrument_id = :instrument_id, 
                                    grade = :grade, 
                                    campus = :campus, 
                                    role = :role, 
                                    is_active = :is_active 
                                WHERE id = :id';
                        $params = [
                            'login_id' => $login_id,
                            'name' => $name,
                            'instrument_id' => $instrument_id,
                            'grade' => $grade,
                            'campus' => $campus,
                            'role' => $role,
                            'is_active' => $is_active,
                            'id' => $id
                        ];
                    }
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    header('Location: users.php?msg=updated');
                    exit;
                }
            }
        }
    }
}

// 編集データ取得
$edit_user = null;
if ($action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $edit_user = $stmt->fetch();
    if (!$edit_user) {
        header('Location: users.php');
        exit;
    }
}

// メッセージ表示
if (($_GET['msg'] ?? '') === 'created') $success = '部員アカウントを登録しました。';
if (($_GET['msg'] ?? '') === 'updated') $success = '部員情報を更新しました。';
if (($_GET['msg'] ?? '') === 'deleted') $success = '部員アカウントを削除しました。';

// フィルター条件
$filter_campus = trim($_GET['campus'] ?? '');
$filter_grade = trim($_GET['grade'] ?? '');
$filter_instrument = trim($_GET['instrument_id'] ?? '');

$where = [];
$params = [];

if ($filter_campus !== '' && in_array($filter_campus, $campus_options, true)) {
    $where[] = 'u.campus = :campus';
    $params['campus'] = $filter_campus;
}

if ($filter_grade !== '' && array_key_exists($filter_grade, $grade_options)) {
    $where[] = 'u.grade = :grade';
    $params['grade'] = $filter_grade;
}

if ($filter_instrument !== '') {
    $where[] = 'u.instrument_id = :instrument_id';
    $params['instrument_id'] = (int)$filter_instrument;
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// 全体の総部員数
$total_users_count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

// 部員一覧取得（校舎、学年順）
$sql = "
    SELECT u.*, i.name AS instrument_name, i.section AS instrument_section
    FROM users u
    LEFT JOIN instruments i ON u.instrument_id = i.id
    {$where_sql}
    ORDER BY u.is_active DESC, u.campus ASC, COALESCE(i.display_order, 999) ASC, u.grade ASC, u.name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$all_users = $stmt->fetchAll();

$page_title = '部員・名簿管理';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>部員・名簿の管理</h1>
    <p>部員アカウントの発行、所属校舎（津校・名古屋校）、担当楽器や学年の管理を行います。</p>
    <div class="page-header-actions">
        <?php if ($action === 'list'): ?>
            <a href="users.php?action=create" class="btn btn-primary">部員を新規登録</a>
        <?php else: ?>
            <a href="users.php" class="btn btn-secondary">一覧へ戻る</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>

<?php if ($action === 'create' || $action === 'edit'): ?>
    <div class="card" style="max-width: 600px; margin: 0 auto;">
        <h2 class="card-title"><?= $action === 'create' ? '部員アカウントの新規登録' : '部員情報の編集' ?></h2>

        <form method="POST" action="users.php?action=<?= $action ?>" id="user-form">
            <input type="hidden" name="csrf_token" value="<?= h(get_csrf_token()) ?>">
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="id" value="<?= (int)$edit_user['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="campus">所属校舎 <span class="required">*</span></label>
                <select id="campus" name="campus" class="form-control" required>
                    <?php foreach ($campus_options as $c): ?>
                        <option value="<?= h($c) ?>" <?= ($edit_user['campus'] ?? '津校') === $c ? 'selected' : '' ?>>
                            <?= h($c) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="login_id">ログインID（学籍番号など） <span class="required">*</span></label>
                <input type="text" id="login_id" name="login_id" class="form-control" required
                       value="<?= h($edit_user['login_id'] ?? '') ?>" placeholder="例: s2026001">
            </div>

            <div class="form-group">
                <label for="name">氏名 <span class="required">*</span></label>
                <input type="text" id="name" name="name" class="form-control" required
                       value="<?= h($edit_user['name'] ?? '') ?>" placeholder="例: 山田 太郎">
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="instrument_id">担当楽器（パート）</label>
                    <select id="instrument_id" name="instrument_id" class="form-control">
                        <option value="">-- 選択してください --</option>
                        <?php foreach ($instruments as $inst): ?>
                            <option value="<?= $inst['id'] ?>" <?= ($edit_user['instrument_id'] ?? '') == $inst['id'] ? 'selected' : '' ?>>
                                <?= h($inst['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="grade">学年・区分</label>
                    <select id="grade" name="grade" class="form-control">
                        <?php foreach ($grade_options as $val => $label): ?>
                            <option value="<?= h($val) ?>" <?= ($edit_user['grade'] ?? '1') == $val ? 'selected' : '' ?>>
                                <?= h($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="password">
                    パスワード <?= $action === 'create' ? '<span class="required">*</span>' : '（変更する場合のみ入力）' ?>
                </label>
                <input type="password" id="password" name="password" class="form-control"
                       <?= $action === 'create' ? 'required minlength="6"' : 'minlength="6"' ?> placeholder="6文字以上">
            </div>

            <div class="form-group">
                <label for="role">ユーザー権限</label>
                <select id="role" name="role" class="form-control">
                    <option value="student" <?= ($edit_user['role'] ?? '') !== 'admin' ? 'selected' : '' ?>>一般部員（出欠登録のみ）</option>
                    <option value="admin" <?= ($edit_user['role'] ?? '') === 'admin' ? 'selected' : '' ?>>管理者（日程・部員管理が可能）</option>
                </select>
            </div>

            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" name="is_active" value="1" <?= ($edit_user['is_active'] ?? 1) == 1 ? 'checked' : '' ?>>
                    有効アカウント（チェックを外すとログインできなくなります）
                </label>
            </div>

            <!-- アクションボタン（2つ横並びで中央配置） -->
            <div style="display: flex; justify-content: center; align-items: center; margin-top: 2rem; gap: 1rem;">
                <a href="users.php" class="btn btn-secondary" style="min-width: 120px;">キャンセル</a>
                <button type="submit" class="btn btn-primary" style="min-width: 140px;">
                    <?= $action === 'create' ? '部員を登録する' : '変更を保存する' ?>
                </button>
            </div>

            <!-- 削除リンク（ボタンではなく小さく文字で下部に配置） -->
            <?php if ($action === 'edit' && $edit_user['id'] != $_SESSION['user_id']): ?>
                <div style="text-align: center; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px dashed var(--border);">
                    <button type="button" onclick="confirmDeleteUser(<?= (int)$edit_user['id'] ?>, '<?= addslashes(h($edit_user['name'])) ?>')" style="background: none; border: none; color: #dc2626; font-size: 0.85rem; text-decoration: underline; cursor: pointer; padding: 0.25rem 0.5rem;">
                        このユーザーを削除する
                    </button>
                </div>
            <?php endif; ?>
        </form>

        <?php if ($action === 'edit' && $edit_user['id'] != $_SESSION['user_id']): ?>
            <form id="delete-user-form" method="POST" action="users.php?action=delete" style="display: none;">
                <input type="hidden" name="csrf_token" value="<?= h(get_csrf_token()) ?>">
                <input type="hidden" name="id" id="delete-user-id" value="<?= (int)$edit_user['id'] ?>">
            </form>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- 絞り込みフィルターカード -->
    <div class="card" style="padding: 1rem 1.25rem; margin-bottom: 1.25rem;">
        <form method="GET" action="users.php" id="filter-form" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: flex-end;">
            <!-- 校舎フィルター -->
            <div style="flex: 1; min-width: 130px;">
                <label for="filter_campus" style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 0.3rem;">
                    校舎
                </label>
                <select name="campus" id="filter_campus" class="form-control" onchange="this.form.submit()">
                    <option value="">すべての校舎</option>
                    <?php foreach ($campus_options as $c): ?>
                        <option value="<?= h($c) ?>" <?= $filter_campus === $c ? 'selected' : '' ?>>
                            <?= h($c) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- 学年フィルター -->
            <div style="flex: 1; min-width: 130px;">
                <label for="filter_grade" style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 0.3rem;">
                    学年
                </label>
                <select name="grade" id="filter_grade" class="form-control" onchange="this.form.submit()">
                    <option value="">すべての学年</option>
                    <?php foreach ($grade_options as $val => $lbl): ?>
                        <option value="<?= h($val) ?>" <?= $filter_grade === (string)$val ? 'selected' : '' ?>>
                            <?= h($lbl) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- パート（楽器）フィルター -->
            <div style="flex: 1; min-width: 150px;">
                <label for="filter_instrument" style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 0.3rem;">
                    担当パート
                </label>
                <select name="instrument_id" id="filter_instrument" class="form-control" onchange="this.form.submit()">
                    <option value="">すべてのパート</option>
                    <?php foreach ($instruments as $inst): ?>
                        <option value="<?= $inst['id'] ?>" <?= $filter_instrument === (string)$inst['id'] ? 'selected' : '' ?>>
                            <?= h($inst['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($filter_campus !== '' || $filter_grade !== '' || $filter_instrument !== ''): ?>
                <div>
                    <a href="users.php" class="btn btn-secondary btn-sm" style="height: 38px; display: inline-flex; align-items: center; padding: 0 0.85rem;">
                        条件をクリア
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- 部員名簿カード（一覧） -->
    <div class="card">
        <h2 class="card-title" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
            <span>
                部員名簿（<?= count($all_users) ?> 名<?= ($filter_campus !== '' || $filter_grade !== '' || $filter_instrument !== '') ? ' / 全 ' . $total_users_count . ' 名' : '' ?>）
            </span>
            <?php if ($filter_campus !== '' || $filter_grade !== '' || $filter_instrument !== ''): ?>
                <span style="font-size: 0.85rem; color: var(--primary); font-weight: normal;">
                    ※絞り込み表示中
                </span>
            <?php endif; ?>
        </h2>

        <?php if (empty($all_users)): ?>
            <p style="text-align: center; color: var(--text-muted); padding: 2.5rem 1rem;">
                条件に一致する部員が見つかりませんでした。<br>
                <a href="users.php" style="color: var(--primary); margin-top: 0.5rem; display: inline-block;">すべての部員を表示する</a>
            </p>
        <?php else: ?>

        <!-- 部員名簿カードリスト（PC・スマホ共通） -->
        <div class="user-cards-mobile" style="display: flex; flex-direction: column; gap: 0.85rem;">
            <?php foreach ($all_users as $u): ?>
                <div class="user-card-item <?= $u['is_active'] ? '' : 'user-card-inactive' ?>">
                    <div class="user-card-top">
                        <div style="display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;">
                            <span class="badge" style="background: #e2e8f0; color: var(--text-main); font-size: 0.75rem;">
                                <?= h($u['campus'] ?? '津校') ?>
                            </span>
                            <span style="font-size: 0.8rem; color: var(--text-muted);">
                                <?= h(format_grade($u['grade'] ?? '1', $grade_options)) ?>
                            </span>
                            <strong style="font-size: 1.05rem; margin-left: 0.2rem;">
                                <?= h($u['name']) ?>
                            </strong>
                            <?php if (!$u['is_active']): ?>
                                <span style="color: #ef4444; font-size: 0.75rem; font-weight: 700; background: #fee2e2; padding: 0.1rem 0.35rem; border-radius: 4px;">
                                    停止中
                                </span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="tag-admin">管理者</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="user-card-details">
                        <div><strong>パート:</strong> <?= h($u['instrument_name'] ?? '未設定') ?></div>
                        <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
                            ログインID: <code><?= h($u['login_id']) ?></code>
                        </div>
                    </div>

                    <div class="user-card-btns">
                        <a href="users.php?action=edit&id=<?= $u['id'] ?>" class="btn btn-secondary btn-sm" style="flex: 1; text-align: center;">編集</a>
                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" action="users.php?action=delete" style="flex: 1;" onsubmit="return confirm('部員「<?= addslashes(h($u['name'])) ?>」と出欠記録を完全に削除しますか？\n※この操作は取り消せません。');">
                                <input type="hidden" name="csrf_token" value="<?= h(get_csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" style="width: 100%;">削除</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
function confirmDeleteUser(userId, userName) {
    if (confirm('部員「' + userName + '」と関連するすべての出欠記録を完全に削除しますか？\n※この操作は元に戻せません。')) {
        const form = document.getElementById('delete-user-form');
        if (form) {
            form.submit();
        }
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
