-- ===============================================
-- Brass Note (吹奏楽部 出欠管理システム) データベーススキーマ
-- ※レンタルサーバー（XREA等）でもそのままインポート可能です
-- ===============================================

-- 1. 楽器 / パートテーブル
CREATE TABLE IF NOT EXISTS `instruments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL COMMENT '楽器名',
    `section` ENUM('Woodwind', 'Brass', 'Percussion', 'String', 'Other') NOT NULL DEFAULT 'Woodwind' COMMENT 'セクション',
    `display_order` INT NOT NULL DEFAULT 0 COMMENT '表示順',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. ユーザーテーブル
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `login_id` VARCHAR(50) NOT NULL UNIQUE COMMENT 'ログインID（学籍番号や識別子）',
    `password_hash` VARCHAR(255) NOT NULL COMMENT 'パスワードハッシュ',
    `name` VARCHAR(100) NOT NULL COMMENT '氏名',
    `instrument_id` INT NULL COMMENT '担当楽器ID',
    `grade` VARCHAR(50) NOT NULL DEFAULT '1' COMMENT '学年 (1~4, teacher, other)',
    `campus` VARCHAR(50) NOT NULL DEFAULT '津校' COMMENT '所属校舎（津校 / 名古屋校）',
    `role` ENUM('admin', 'student') NOT NULL DEFAULT 'student' COMMENT '権限 (admin: 管理者, student: 一般部員)',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '有効フラグ (1: 有効, 0: 停止/卒業)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`instrument_id`) REFERENCES `instruments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. 練習イベントテーブル
CREATE TABLE IF NOT EXISTS `events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(100) NOT NULL COMMENT '練習名（全体合奏、分奏、ホール練など）',
    `event_date` DATE NOT NULL COMMENT '開催日',
    `start_time` TIME NOT NULL COMMENT '開始時刻',
    `end_time` TIME NOT NULL COMMENT '終了時刻',
    `location` VARCHAR(100) NOT NULL DEFAULT '音楽室' COMMENT '練習場所',
    `description` TEXT NULL COMMENT '連絡事項・練習予定曲など',
    `deadline` DATETIME NULL COMMENT '出欠回答締切',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. 出欠回答テーブル
CREATE TABLE IF NOT EXISTS `attendances` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `event_id` INT NULL COMMENT 'イベントID（全体練習等がない場合はNULL）',
    `attendance_date` DATE NULL COMMENT '対象日付',
    `user_id` INT NOT NULL COMMENT '部員ID',
    `status` ENUM('present', 'late', 'early', 'absent') NOT NULL DEFAULT 'present' COMMENT '出欠 (出席/遅刻/早退/欠席)',
    `target_time` TIME NULL COMMENT '遅刻到着予定時刻 / 早退予定時刻',
    `reason` TEXT NULL COMMENT '欠席・遅刻・早退の理由・補足',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_event_user` (`event_id`, `user_id`),
    KEY `idx_date_user` (`attendance_date`, `user_id`),
    FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===============================================
-- 初期マスタデータ投入
-- ===============================================

-- 楽器マスタ（主要15パート 日本語表記）
INSERT INTO `instruments` (`id`, `name`, `section`, `display_order`) VALUES
(1, 'ピッコロ / フルート', 'Woodwind', 10),
(2, 'オーボエ', 'Woodwind', 20),
(3, 'ファゴット', 'Woodwind', 30),
(4, 'B♭クラリネット', 'Woodwind', 40),
(5, 'バスクラリネット', 'Woodwind', 50),
(6, 'アルトサックス', 'Woodwind', 60),
(7, 'テナーサックス', 'Woodwind', 70),
(8, 'バリトンサックス', 'Woodwind', 80),
(9, 'トランペット', 'Brass', 90),
(10, 'ホルン', 'Brass', 100),
(11, 'トロンボーン', 'Brass', 110),
(12, 'ユーフォニアム', 'Brass', 120),
(13, 'チューバ', 'Brass', 130),
(14, 'コントラバス', 'String', 140),
(15, '打楽器（パーカッション）', 'Percussion', 150)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 初期管理者アカウント
-- ログインID: admin / パスワード: admin123
INSERT INTO `users` (`id`, `login_id`, `password_hash`, `name`, `instrument_id`, `grade`, `campus`, `role`) VALUES
(1, 'admin', '$2y$12$3trRsIBOezhOAZEn5fKD5.7WFndM/ZAIrP1DMCit.wwm.pqTI8mJC', '管理者（顧問/部長）', NULL, '3', '津校', 'admin')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- サンプル部員アカウント（初期パスワード: admin123）
INSERT INTO `users` (`id`, `login_id`, `password_hash`, `name`, `instrument_id`, `grade`, `campus`, `role`) VALUES
(2, 'student01', '$2y$12$3trRsIBOezhOAZEn5fKD5.7WFndM/ZAIrP1DMCit.wwm.pqTI8mJC', '山田 太郎', 4, '2', '津校', 'student'),
(3, 'student02', '$2y$12$3trRsIBOezhOAZEn5fKD5.7WFndM/ZAIrP1DMCit.wwm.pqTI8mJC', '佐藤 花子', 9, '1', '名古屋校', 'student')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);
