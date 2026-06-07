<?php
declare(strict_types=1);

/*
 * 容器启动时的演示数据填充（Seed）：
 * - 确保空库首次启动时页面有可见内容
 * - 使用真实 DB 读写
 */

$config = require __DIR__ . '/../config/config.php';

$maxAttempts = 120;
$attempt = 0;
$pdo = null;
$lastError = null;

while ($attempt < $maxAttempts) {
    try {
        $attempt++;
        $db = $config['db'];
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $db['host'], $db['port'], $db['name'], $db['charset']);
        $pdo = new PDO(
            $dsn,
            $db['user'],
            $db['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        break;
    } catch (Throwable $e) {
        $lastError = $e->getMessage();
        usleep(500000);
    }
}

if (!$pdo instanceof PDO) {
    fwrite(STDERR, sprintf("[seed] DB not ready after %d attempts: %s\n", $attempt, $lastError ?? 'unknown error'));
    exit(1);
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $stmt->execute(['demo']);
    $demoUser = $stmt->fetch();

    if (!$demoUser) {
        $passwordHash = password_hash('123456', PASSWORD_DEFAULT);
        $insert = $pdo->prepare('INSERT INTO users (username, password, mobile, status) VALUES (?, ?, ?, 1)');
        $insert->execute(['demo', $passwordHash, null]);
        $demoUserId = (int)$pdo->lastInsertId();
    } else {
        $demoUserId = (int)$demoUser['id'];
    }

    $stmt = $pdo->prepare('SELECT id FROM posts WHERE title = ? LIMIT 1');
    $stmt->execute(['欢迎来到 Lite Forum']);
    $demoPost = $stmt->fetch();

    if (!$demoPost) {
        $content = '<p><strong>这是演示帖子</strong>，你可以注册登录后发布新内容。</p><ul><li>Bootstrap 5.3 UI</li><li>TinyMCE 富文本编辑</li><li>MySQL + PDO</li></ul>';
        $insert = $pdo->prepare('INSERT INTO posts (user_id, title, content, status) VALUES (?, ?, ?, 1)');
        $insert->execute([$demoUserId, '欢迎来到 Lite Forum', $content]);
        $demoPostId = (int)$pdo->lastInsertId();
    } else {
        $demoPostId = (int)$demoPost['id'];
    }

    $stmt = $pdo->prepare('SELECT id FROM comments WHERE post_id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$demoPostId, $demoUserId]);
    $demoComment = $stmt->fetch();

    if (!$demoComment) {
        $insert = $pdo->prepare('INSERT INTO comments (post_id, user_id, content, status) VALUES (?, ?, ?, 1)');
        $insert->execute([$demoPostId, $demoUserId, '这是一条演示评论。']);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "[seed] failed\n");
    exit(1);
}
