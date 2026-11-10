<?php
// settings.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

$pdo = get_db_connection();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// 学年・区分のマスタ定義
$grade_options = [
    '1' => '1年生',
    '2' => '2年生',
    '3' => '3年生',
    '4' => '4年生',
    'teacher' => '教師',
    'other' => 'その他'
];

// 楽器一覧を取得
$stmt = $pdo->query('SELECT * FROM instruments ORDER BY display_order ASC');
$instruments = $stmt->fetchAll();

// 現在のユーザー情報を取得
$stmt = $pdo->prepare('
    SELECT u.*, i.name AS instrument_name 
    FROM users u 
    LEFT JOIN instruments i ON u.instrument_id = i.id 
    WHERE u.id = :id 
    LIMIT 1
');
$stmt->execute(['id' => $user_id]);
$current_user = $stmt->fetch();

if (!$current_user) {
    die('ユーザー情報が見つかりません。<a href="login.php">再ログイン</a>');
}

// 設定保存処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = '不正な送信です。もう一度お試しください。';
    } else {
        $instrument_id = !empty($_POST['instrument_id']) ? (int)$_POST['instrument_id'] : null;
        $grade = trim($_POST['grade'] ?? '1');
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (!array_key_exists($grade, $grade_options)) {
            $grade = '1';
        }

        // パスワード変更がある場合の検証
        $update_password = false;
        if (!empty($new_password) || !empty($confirm_password)) {
            if ($new_password !== $confirm_password) {
                $error = '新しいパスワードと確認用パスワードが一致しません。';
            } elseif (strlen($new_password) < 6) {
                $error = 'パスワードは6文字以上で入力してください。';
            } else {
                $update_password = true;
            }
        }

        if (empty($error)) {
            if ($update_password) {
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('
                    UPDATE users 
                    SET instrument_id = :instrument_id, grade = :grade, password_hash = :hash, updated_at = NOW() 
                    WHERE id = :id
                ');
                $stmt->execute([
                    'instrument_id' => $instrument_id,
                    'grade' => $grade,
                    'hash' => $password_hash,
                    'id' => $user_id
                ]);
            } else {
                $stmt = $pdo->prepare('
                    UPDATE users 
                    SET instrument_id = :instrument_id, grade = :grade, updated_at = NOW() 
                    WHERE id = :id
                ');
                $stmt->execute([
                    'instrument_id' => $instrument_id,
                    'grade' => $grade,
                    'id' => $user_id
                ]);
            }

            // セッション情報のパート名を更新
            if ($instrument_id) {
                $inst_chk = $pdo->prepare('SELECT name FROM instruments WHERE id = :id');
                $inst_chk->execute(['id' => $instrument_id]);
                $_SESSION['instrument_name'] = $inst_chk->fetchColumn() ?: '';
            } else {
                $_SESSION['instrument_name'] = '';
            }

            $success = '設定を正常に保存しました。';

            // 最新情報を再取得
            $stmt = $pdo->prepare('SELECT u.*, i.name AS instrument_name FROM users u LEFT JOIN instruments i ON u.instrument_id = i.id WHERE u.id = :id LIMIT 1');
            $stmt->execute(['id' => $user_id]);
            $current_user = $stmt->fetch();
        }
    }
}

$page_title = '個人設定';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>個人設定</h1>
    <p>担当パート、学年、パスワードの変更を行えます。</p>
</div>

<div style="max-width: 520px; margin: 0 auto 3rem auto;">
    <div class="card">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= h($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= h($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="settings.php">
            <input type="hidden" name="csrf_token" value="<?= h(get_csrf_token()) ?>">

            <!-- 変更不可の項目（読み取り専用表示） -->
            <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: var(--radius); padding: 0.85rem 1rem; margin-bottom: 1.5rem; font-size: 0.9rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem;">
                    <span style="color: var(--text-muted);">ログインID:</span>
                    <strong><code><?= h($current_user['login_id']) ?></code></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem;">
                    <span style="color: var(--text-muted);">氏名:</span>
                    <strong><?= h($current_user['name']) ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-muted);">所属校舎:</span>
                    <span class="badge" style="background: #e2e8f0; color: var(--text-main); font-size: 0.75rem;">
                        <?= h($current_user['campus'] ?? '津校') ?>
                    </span>
                </div>
                <div style="margin-top: 0.5rem; padding-top: 0.4rem; border-top: 1px dashed var(--border); font-size: 0.75rem; color: var(--text-muted); text-align: center;">
                    ※ID、氏名、所属校舎の変更は管理者にお問い合わせください。
                </div>
            </div>

            <!-- 変更可能な項目 -->
            <div class="form-group">
                <label for="instrument_id">担当パート（楽器）</label>
                <select id="instrument_id" name="instrument_id" class="form-control">
                    <option value="">-- 未設定 --</option>
                    <?php foreach ($instruments as $inst): ?>
                        <option value="<?= $inst['id'] ?>" <?= ($current_user['instrument_id'] == $inst['id']) ? 'selected' : '' ?>>
                            <?= h($inst['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="grade">学年・区分</label>
                <select id="grade" name="grade" class="form-control">
                    <?php foreach ($grade_options as $val => $label): ?>
                        <option value="<?= h($val) ?>" <?= ($current_user['grade'] == $val) ? 'selected' : '' ?>>
                            <?= h($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--border);">
                <label style="font-weight: 700; font-size: 0.95rem; margin-bottom: 0.35rem; display: block;">
                    パスワードの変更
                </label>
                <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem;">
                    ※パスワードを変更する場合のみ入力してください（そのまま維持する場合は空欄）。
                </p>

                <div class="form-group">
                    <label for="new_password">新しいパスワード（6文字以上）</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" minlength="6" placeholder="変更しない場合は空欄">
                </div>

                <div class="form-group">
                    <label for="confirm_password">新しいパスワード（確認用）</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="6" placeholder="もう一度入力">
                </div>
            </div>

            <div style="display: flex; justify-content: center; align-items: center; gap: 1rem; margin-top: 2rem;">
                <a href="index.php" class="btn btn-secondary" style="min-width: 120px;">戻る</a>
                <button type="submit" class="btn btn-primary" style="min-width: 140px;">設定を保存する</button>
            </div>
        </form>
    </div>

    <!-- ホーム画面追加ガイドカード -->
    <div class="card" style="background: #ffffff; border: 1px solid var(--border); padding: 1.25rem;">
        <h3 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--primary);">
            Webアプリとしてホーム画面に追加する方法
        </h3>
        <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.6; margin-bottom: 0.75rem;">
            ホーム画面に追加すると、アドレスバーのない全画面アプリとして利用できます。ログインも約1ヶ月間維持されます。
        </p>
        <div style="font-size: 0.85rem; color: var(--text-main); line-height: 1.6; background: #f8fafc; padding: 0.75rem 1rem; border-radius: var(--radius); border: 1px solid #f1f5f9;">
            <div style="margin-bottom: 0.5rem;">
                <strong>iPhone（Safari）の場合:</strong><br>
                画面下の「共有ボタン（四角から上矢印）」をタップし、「<strong>ホーム画面に追加</strong>」を押します。
            </div>
            <div>
                <strong>Android（Chrome）の場合:</strong><br>
                画面右上の「メニュー（3点リーダー）」をタップし、「<strong>アプリをインストール</strong>」または「<strong>ホーム画面に追加</strong>」を押します。
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
