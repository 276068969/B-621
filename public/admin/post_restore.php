<?php
declare(strict_types=1);

/*
 * 帖子恢复（从回收站恢复）：
 * - posts.status = 1
 * - 同时将该帖所有评论恢复为正常（级联恢复）
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

admin_require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    flash_set('danger', '参数错误。');
    redirect('/admin/posts.php');
}

try {
    $pdo = db($config);
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT title FROM posts WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $post = $stmt->fetch();
    $postTitle = $post ? (string)$post['title'] : '';

    $stmt = $pdo->prepare('UPDATE posts SET status = 1 WHERE id = ?');
    $stmt->execute([$id]);
    $stmt = $pdo->prepare('UPDATE comments SET status = 1 WHERE post_id = ?');
    $stmt->execute([$id]);
    $pdo->commit();

    $detail = $postTitle !== '' ? '恢复帖子: "' . mb_substr($postTitle, 0, 50) . '"' : '恢复帖子 ID: ' . $id;
    admin_log_operation($pdo, 'post_restore', 'post', $id, $detail);

    flash_set('success', '帖子已从回收站恢复。');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash_set('danger', '恢复失败，请稍后重试。');
}

redirect('/admin/posts.php');
