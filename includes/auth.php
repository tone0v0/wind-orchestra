<?php
// includes/auth.php

// ログイン保持期間：30日間（約1ヶ月間、ブラウザを閉じても維持）
if (!defined('SESSION_LIFETIME')) {
    define('SESSION_LIFETIME', 30 * 24 * 60 * 60);
}

if (session_status() === PHP_SESSION_NONE) {
    // サーバー側のセッション有効期限を30日に設定
    ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);

    // クライアント側（ブラウザ）のCookie有効期限を30日に設定
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

// ログイン中の場合、アクセスするたびにクッキーの有効期限を更新（最終アクセスから30日間維持）
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    if (!isset($_SESSION['last_cookie_refresh']) || (time() - $_SESSION['last_cookie_refresh']) > 86400) {
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
    }
}

/**
 * XSS対策エスケープ
 */
function h(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * ログイン中かどうか判定
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * 管理者かどうか判定
 */
function is_admin(): bool {
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

/**
 * ログイン必須ガード
 */
function require_login(string $redirect_url = '/wind-orchestra/login.php'): void {
    if (!is_logged_in()) {
        // パス解決：相対パスでも安全にリダイレクトできるようにする
        $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        $uri = dirname($_SERVER['SCRIPT_NAME']);
        // ルート検出
        $login_path = str_contains($_SERVER['SCRIPT_NAME'], '/admin/') ? '../login.php' : 'login.php';
        header("Location: " . $login_path);
        exit;
    }
}

/**
 * 管理者必須ガード
 */
function require_admin(): void {
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        die('管理者権限が必要です。<a href="../index.php">トップへ戻る</a>');
    }
}

/**
 * 現在ログインしているユーザー情報を取得
 */
function get_current_user_data(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? '',
        'login_id' => $_SESSION['login_id'] ?? '',
        'role' => $_SESSION['role'] ?? 'student',
        'instrument_name' => $_SESSION['instrument_name'] ?? '',
    ];
}

/**
 * CSRFトークン生成・取得
 */
function get_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRFトークン検証
 */
function verify_csrf_token(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
