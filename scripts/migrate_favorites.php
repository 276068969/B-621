<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

try {
    $pdo = db($config);
} catch (Throwable $e) {
    echo '数据库连接失败: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

$sql = "CREATE TABLE IF NOT EXISTS favorites (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  post_id INT UNSIGNED NOT NULL,
  create_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_favorites_user_post (user_id, post_id),
  KEY idx_favorites_user_id (user_id),
  KEY idx_favorites_post_id (post_id),
  KEY idx_favorites_create_time (create_time),
  CONSTRAINT fk_favorites_user_id FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_favorites_post_id FOREIGN KEY (post_id) REFERENCES posts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

try {
    $pdo->exec($sql);
    echo "favorites 表创建成功（或已存在）。" . PHP_EOL;
} catch (Throwable $e) {
    echo "创建 favorites 表失败: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
