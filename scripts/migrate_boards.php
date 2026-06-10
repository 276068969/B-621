<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

try {
    $pdo = db($config);
} catch (Throwable $e) {
    echo '数据库连接失败: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

echo "开始迁移：版块分区功能..." . PHP_EOL;

$boardsTableExists = $pdo->query("SHOW TABLES LIKE 'boards'")->rowCount() > 0;

if (!$boardsTableExists) {
    echo "创建 boards 表..." . PHP_EOL;
    $pdo->exec("
        CREATE TABLE boards (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(50) NOT NULL,
            description VARCHAR(200) NOT NULL DEFAULT '',
            sort_order INT NOT NULL DEFAULT 0,
            status TINYINT NOT NULL DEFAULT 1,
            create_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            update_time DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_boards_sort_order (sort_order),
            KEY idx_boards_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ boards 表创建成功" . PHP_EOL;
} else {
    echo "✓ boards 表已存在，跳过创建" . PHP_EOL;
}

$columns = $pdo->query("SHOW COLUMNS FROM posts LIKE 'board_id'")->fetchAll();
if (empty($columns)) {
    echo "为 posts 表添加 board_id 字段..." . PHP_EOL;
    $pdo->exec("ALTER TABLE posts ADD COLUMN board_id INT UNSIGNED NULL AFTER id");
    echo "✓ board_id 字段添加成功" . PHP_EOL;

    echo "添加 board_id 索引..." . PHP_EOL;
    $pdo->exec("ALTER TABLE posts ADD KEY idx_posts_board_id (board_id)");
    echo "✓ board_id 索引添加成功" . PHP_EOL;

    echo "添加外键约束..." . PHP_EOL;
    $pdo->exec("
        ALTER TABLE posts ADD CONSTRAINT fk_posts_board_id 
        FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE SET NULL
    ");
    echo "✓ 外键约束添加成功" . PHP_EOL;
} else {
    echo "✓ board_id 字段已存在，跳过" . PHP_EOL;
}

$boardCount = (int)$pdo->query("SELECT COUNT(*) FROM boards")->fetchColumn();
if ($boardCount === 0) {
    echo "插入默认版块..." . PHP_EOL;
    $defaultBoards = [
        ['综合讨论', '综合讨论区，欢迎分享各种话题'],
        ['技术交流', '技术相关的讨论和分享'],
        ['生活随笔', '记录生活，分享感悟'],
    ];
    $stmt = $pdo->prepare("INSERT INTO boards (name, description, sort_order, status) VALUES (?, ?, ?, 1)");
    foreach ($defaultBoards as $index => $board) {
        $stmt->execute([$board[0], $board[1], $index]);
    }
    echo "✓ 默认版块插入成功" . PHP_EOL;
} else {
    echo "✓ 已有 {$boardCount} 个版块，跳过默认数据" . PHP_EOL;
}

echo PHP_EOL . "迁移完成！" . PHP_EOL;
