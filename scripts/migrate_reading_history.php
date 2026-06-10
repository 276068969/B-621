<?php
declare(strict_types=1);

/*
 * 阅读历史表迁移脚本
 * 用于在已部署的系统上创建 reading_history 表
 * 用法：php scripts/migrate_reading_history.php
 */

require_once __DIR__ . '/../includes/bootstrap.php';

try {
    $pdo = db($config);
    echo "数据库连接成功\n";

    $sql = "CREATE TABLE IF NOT EXISTS reading_history (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        post_id INT UNSIGNED NOT NULL,
        view_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_reading_history_user_post (user_id, post_id),
        KEY idx_reading_history_user_time (user_id, view_time),
        KEY idx_reading_history_post_id (post_id),
        CONSTRAINT fk_reading_history_user_id FOREIGN KEY (user_id) REFERENCES users(id),
        CONSTRAINT fk_reading_history_post_id FOREIGN KEY (post_id) REFERENCES posts(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql);
    echo "reading_history 表创建成功\n";

    $stmt = $pdo->query("SELECT COUNT(*) FROM reading_history");
    $count = (int)$stmt->fetchColumn();
    echo "当前记录数：{$count}\n";

    echo "迁移完成！\n";
} catch (Throwable $e) {
    echo "迁移失败：" . $e->getMessage() . "\n";
    exit(1);
}
