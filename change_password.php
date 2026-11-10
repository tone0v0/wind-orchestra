<?php
// change_password.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($token)) {
        $error = '不正な送信です。もう一度お試しください。';
    } elseif ($current_password === '' || $new_password === '' || $confirm_password === '') {
        $error = 'すべての項目を入力してください。';
    } elseif ($new_password !== $confirm_password) {
        $error = '新しいパスワードと確認用パスワードが一致しません。';
    } elseif (strlen($new_password) < 6) {
        $error = '新しいパスワードは6文字以上で設定してください。';
    } else {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user && password_verify($current_password, $user['password_hash'])) {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
            $update->execute(['hash' => $new_hash, 'id' => $_SESSION['user_id']]);
            $success = 'パスワードを正常に変更しました。';
        } else {
            $error = '現在のパスワードが正しくありません。';
        }
    }
}

$page_title = 'パスワード変更';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 480px; margin: 2rem auto;">
    <div class="card">
        <h2 class="card-title" style="justify-content: center; text-align: center;">パスワード変更</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= h($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= h($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="change_password.php">
            <input type="hidden" name="csrf_token" value="<?= h(get_csrf_token()) ?>">

            <div class="form-group">
                <label for="current_password">現在のパスワード <span class="required">*</span></label>
                <input type="password" id="current_password" name="current_password" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="new_password">新しいパスワード（6文字以上） <span class="required">*</span></label>
                <input type="password" id="new_password" name="new_password" class="form-control" required minlength="6">
            </div>

            <div class="form-group">
                <label for="confirm_password">新しいパスワード（確認用） <span class="required">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="6">
            </div>

            <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem;">
                <a href="index.php" class="btn btn-secondary">戻る</a>
                <button type="submit" class="btn btn-primary">変更を保存</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
