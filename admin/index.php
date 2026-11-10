<?php
// admin/index.php
// 管理者ダッシュボード廃止に伴い、練習日程へ自動転送
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

header('Location: events.php');
exit;
