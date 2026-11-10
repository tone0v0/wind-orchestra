<?php
// config/db.php

// 接続設定（環境変数が設定されていればそれを優先、なければローカルMAMP設定）
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

        // クラウドDB（TiDB Cloud等）用のSSL暗号化通信設定
        if (DB_HOST !== '127.0.0.1' && DB_HOST !== 'localhost') {
            $pem_file = __DIR__ . '/cacert.pem';
            if (file_exists($pem_file)) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = $pem_file;
            } elseif (file_exists('/etc/ssl/certs/ca-certificates.crt')) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = '/etc/ssl/certs/ca-certificates.crt';
            }
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
        }

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            // テーブルが未作成なら自動初期化
            ensure_tables_exist($pdo);
        } catch (PDOException $e) {
            // MAMPのポートが8889の場合のフォールバック試行
            if (DB_PORT === '3306' && DB_HOST === '127.0.0.1') {
                try {
                    $fallback_dsn = sprintf('mysql:host=%s;port=8889;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
                    $pdo = new PDO($fallback_dsn, DB_USER, DB_PASS, $options);
                    ensure_tables_exist($pdo);
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

/**
 * 初回アクセス時にテーブルを自動作成する
 */
function ensure_tables_exist($pdo) {
    static $initialized = false;
    if ($initialized) return;
    $initialized = true;

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        if (!$stmt->fetch()) {
            $schema_file = __DIR__ . '/../db/schema.sql';
            if (file_exists($schema_file)) {
                $sql = file_get_contents($schema_file);
                // セミコロンで分割して1文ずつ実行
                $queries = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($queries as $query) {
                    if (!empty($query)) {
                        $pdo->exec($query);
                    }
                }
            }
        }
    } catch (Exception $e) {
        // エラー時はスキップ
    }
}
