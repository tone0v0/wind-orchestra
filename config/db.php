<?php
// config/db.php

// MAMP用のデフォルト設定
// Windows MAMPのMySQL標準ポートは3306、Mac MAMPは8889の場合があります。
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'wind_orchestra');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'root');

/**
 * データベース接続を取得する
 * @return PDO
 */
function get_db_connection() {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // MAMPのポートが8889の場合のフォールバック試行
            if (DB_PORT === '3306') {
                try {
                    $fallback_dsn = sprintf('mysql:host=%s;port=8889;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
                    $pdo = new PDO($fallback_dsn, DB_USER, DB_PASS, $options);
                    return $pdo;
                } catch (PDOException $e2) {
                    // 両方失敗した場合は元のエラー
                }
            }
            die('データベース接続エラー: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        }
    }

    return $pdo;
}
