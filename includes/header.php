<?php
// includes/header.php
if (!defined('APP_NAME')) {
    define('APP_NAME', 'Brass Note');
}

// アプリアイコンの自動生成チェック（未作成の場合のみ）
if (!file_exists(__DIR__ . '/../assets/icon-192.png')) {
    @include_once __DIR__ . '/../generate_icons.php';
}

$current_user = get_current_user_data();
$is_admin_area = str_contains($_SERVER['SCRIPT_NAME'], '/admin/');
$base_path = $is_admin_area ? '../' : './';
$current_script = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? h($page_title) . ' | ' . APP_NAME : APP_NAME ?></title>
    
    <!-- PWA & Webアプリ（ホーム画面追加）対応 -->
    <link rel="manifest" href="<?= $base_path ?>manifest.json">
    <meta name="theme-color" content="#1e3a8a">
    
    <!-- iOS Safari向けフルスクリーン & アイコン設定 -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Brass Note">
    <link rel="apple-touch-icon" href="<?= $base_path ?>assets/icon.png?v=1">
    
    <!-- ファビコン & アプリアイコン（512x512px共通） -->
    <link rel="icon" type="image/png" href="<?= $base_path ?>assets/icon.png?v=1">
    <link rel="shortcut icon" href="<?= $base_path ?>assets/icon.png?v=1">

    <link rel="stylesheet" href="<?= $base_path ?>css/style.css">
</head>
<body>
<header>
    <div class="header-inner">
        <!-- ロゴ & スマホ用上部バー -->
        <div class="header-bar">
            <a href="<?= $base_path ?>index.php" class="logo">
                <img src="<?= $base_path ?>assets/icon.png?v=1" alt="Brass Note" style="width: 28px; height: 28px; object-fit: contain; vertical-align: middle;">
                <span class="logo-text"><?= APP_NAME ?></span>
            </a>

            <?php if ($current_user): ?>
                <div class="header-bar-right">
                    <button type="button" class="menu-toggle" id="menu-toggle" aria-label="メニュー開閉">
                        <span class="menu-bar"></span>
                        <span class="menu-bar"></span>
                        <span class="menu-bar"></span>
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- ナビゲーションメニュー（PCは横並び、スマホは折りたたみ） -->
        <div class="nav-collapse" id="nav-collapse">
            <?php if ($current_user): ?>
                <!-- スマホ時: カレンダーの上に名前・バッジを配置 -->
                <div class="mobile-user-profile">
                    <div class="user-badge">
                        <?php if ($current_user['role'] === 'admin'): ?>
                            <span class="tag-admin">管理者</span>
                        <?php endif; ?>
                        <strong><?= h($current_user['name']) ?></strong>
                        <?php if (!empty($current_user['instrument_name'])): ?>
                            <span class="user-inst">(<?= h($current_user['instrument_name']) ?>)</span>
                        <?php endif; ?>
                    </div>
                </div>

                <nav class="nav-menu">
                    <a href="<?= $base_path ?>index.php" class="nav-item <?= (!$is_admin_area && $current_script === 'index.php') ? 'active' : '' ?>">
                        カレンダー
                    </a>

                    <?php if ($current_user['role'] === 'admin'): ?>
                        <a href="<?= $base_path ?>admin/events.php" class="nav-item <?= ($is_admin_area && ($current_script === 'events.php' || $current_script === 'event_detail.php')) ? 'active' : '' ?>">
                            練習日程
                        </a>
                        <a href="<?= $base_path ?>admin/users.php" class="nav-item <?= ($is_admin_area && $current_script === 'users.php') ? 'active' : '' ?>">
                            部員管理
                        </a>
                    <?php endif; ?>

                    <!-- スマホ用設定リンク（学生・管理者共通） -->
                    <a href="<?= $base_path ?>settings.php" class="nav-item nav-settings-mobile <?= (!$is_admin_area && $current_script === 'settings.php') ? 'active' : '' ?>">
                        設定
                    </a>

                    <!-- スマホ用ログアウト表示 -->
                    <a href="<?= $base_path ?>logout.php" class="nav-item nav-logout-mobile">
                        ログアウト
                    </a>
                </nav>

                <div class="header-right desktop-header-right">
                    <div class="user-badge">
                        <?php if ($current_user['role'] === 'admin'): ?>
                            <span class="tag-admin">管理者</span>
                        <?php endif; ?>
                        <strong><?= h($current_user['name']) ?></strong>
                        <?php if (!empty($current_user['instrument_name'])): ?>
                            <span class="user-inst">(<?= h($current_user['instrument_name']) ?>)</span>
                        <?php endif; ?>
                    </div>
                    <!-- PC用操作ボタン -->
                    <div class="user-actions desktop-user-actions">
                        <a href="<?= $base_path ?>settings.php" class="btn btn-secondary btn-sm">設定</a>
                        <a href="<?= $base_path ?>logout.php" class="btn btn-secondary btn-sm">ログアウト</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="header-right">
                    <a href="<?= $base_path ?>login.php" class="btn btn-primary btn-sm">ログイン</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('menu-toggle');
    const collapse = document.getElementById('nav-collapse');
    let isAnimating = false;

    if (toggle && collapse) {
        function openMenu() {
            if (isAnimating) return;
            isAnimating = true;
            collapse.classList.remove('is-closing');
            collapse.classList.add('is-open');
            toggle.classList.add('is-active');
            setTimeout(() => { isAnimating = false; }, 200);
        }

        function closeMenu() {
            if (isAnimating || !collapse.classList.contains('is-open')) return;
            isAnimating = true;
            collapse.classList.add('is-closing');
            toggle.classList.remove('is-active');
            setTimeout(() => {
                collapse.classList.remove('is-open');
                collapse.classList.remove('is-closing');
                isAnimating = false;
            }, 180);
        }

        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            if (collapse.classList.contains('is-open')) {
                closeMenu();
            } else {
                openMenu();
            }
        });

        // 画面外クリックで閉じる
        document.addEventListener('click', function(e) {
            if (!collapse.contains(e.target) && !toggle.contains(e.target)) {
                closeMenu();
            }
        });
    }
});
</script>
<main class="main-container">
