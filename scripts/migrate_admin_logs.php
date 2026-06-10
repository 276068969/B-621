<?php
declare(strict_types=1);

/*
 * 迁移脚本：创建管理员操作日志表
 * 用于已部署环境的数据库升级
 */

require_once __DIR__ . '/../includes/bootstrap.php';

try {
    $pdo = db($config);

    $sql = 'CREATE TABLE IF NOT EXISTS admin_operation_logs (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        admin_username VARCHAR(64) NOT NULL,
        action VARCHAR(64) NOT NULL,
        target_type VARCHAR(32) NULL,
        target_id INT UNSIGNED NULL,
        detail TEXT NULL,
        ip VARCHAR(45) NOT NULL,
        user_agent VARCHAR(512) NULL,
        create_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_admin_logs_action (action),
        KEY idx_admin_logs_create_time (create_time),
        KEY idx_admin_logs_admin (admin_username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    $pdo->exec($sql);
    echo "admin_operation_logs 表创建成功（或已存在）。\n";
} catch (Throwable $e) {
    echo "迁移失败: " . $e->getMessage() . "\n";
    exit(1);
}
