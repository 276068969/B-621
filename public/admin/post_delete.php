<?php
declare(strict_types=1);

/*
 * 帖子删除（软删）：
 * - posts.status = 0
 * - 同时将该帖所有评论置为已删除
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
    $stmt = $pdo->prepare('UPDATE posts SET status = 0 WHERE id = ?');
    $stmt->execute([$id]);
    $stmt = $pdo->prepare('UPDATE comments SET status = 0 WHERE post_id = ?');
    $stmt->execute([$id]);
    $pdo->commit();
    flash_set('success', '帖子已删除。');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash_set('danger', '删除失败，请稍后重试。');
}

redirect('/admin/posts.php');

