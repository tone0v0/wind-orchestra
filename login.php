<?php
// login.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

// 既にログイン済みの場合はトップへ
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
$login_id = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_id = trim($_POST['login_id'] ?? '');
    $password = $_POST['password'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($token)) {
        $error = '不正な送信です。もう一度お試しください。';
    } elseif ($login_id === '' || $password === '') {
        $error = 'ログインIDとパスワードを入力してください。';
    } else {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare('
            SELECT u.*, i.name AS instrument_name 
            FROM users u
            LEFT JOIN instruments i ON u.instrument_id = i.id
            WHERE u.login_id = :login_id AND u.is_active = 1
            LIMIT 1
        ');
        $stmt->execute(['login_id' => $login_id]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // セッション再生成（セッションハイジャック防止）
            session_regenerate_id(true);

            // 1ヶ月間（30日）クッキーを保持するよう明示的にセット
            setcookie(
                session_name(),
                session_id(),
                [
                    'expires' => time() + SESSION_LIFETIME,
                    'path' => '/',
                    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );
            $_SESSION['last_cookie_refresh'] = time();

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['login_id'] = $user['login_id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['instrument_name'] = $user['instrument_name'] ?? '';

            // 学生も管理者もトップ（カレンダー）へ
            header('Location: index.php');
            exit;
        } else {
            $error = 'ログインIDまたはパスワードが正しくありません。';
        }
    }
}

$page_title = 'ログイン';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 440px; margin: 3rem auto;">
    <div class="card">
        <div style="text-align: center; margin-bottom: 0.75rem;">
            <img src="assets/icon.png?v=1" alt="Brass Note" style="width: 72px; height: 72px; object-fit: contain;">
        </div>
        <h2 style="font-size: 1.5rem; font-weight: 700; text-align: center; margin-bottom: 0.35rem; color: var(--primary);">
            Brass Note
        </h2>
        <p style="text-align: center; color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.5rem;">
            名古屋大原学園吹奏楽団 出欠管理
        </p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <input type="hidden" name="csrf_token" value="<?= h(get_csrf_token()) ?>">

            <div class="form-group">
                <label for="login_id">ログインID</label>
                <input type="text" id="login_id" name="login_id" class="form-control" value="<?= h($login_id) ?>" placeholder="学籍番号やID" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">パスワード</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="パスワードを入力" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem; padding: 0.75rem;">
                ログインする
            </button>
            <p style="text-align: center; color: var(--text-muted); font-size: 0.8rem; margin-top: 0.75rem;">
                ※ログイン状態は約1ヶ月間（30日間）維持されます
            </p>
        </form>

        <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border); font-size: 0.85rem; color: var(--text-muted); text-align: center;">
            <p>※アカウントは管理者（顧問・部長）のみが登録できます。</p>
            <p style="margin-top: 0.25rem;">初期管理者: <code>admin</code> / <code>admin123</code></p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
