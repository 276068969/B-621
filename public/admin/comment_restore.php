<?php
declare(strict_types=1);

/*
 * 评论恢复（从回收站恢复）：comments.status = 1
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

admin_require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    flash_set('danger', '参数错误。');
    redirect('/admin/comments.php');
}

try {
    $pdo = db($config);

    $stmt = $pdo->prepare('SELECT content, post_id FROM comments WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $comment = $stmt->fetch();
    $content = $comment ? (string)$comment['content'] : '';
    $postId = $comment ? (int)$comment['post_id'] : 0;

    $stmt = $pdo->prepare('UPDATE comments SET status = 1 WHERE id = ?');
    $stmt->execute([$id]);

    $detail = $content !== '' ? '恢复评论: "' . mb_substr(strip_tags($content), 0, 50) . '"' : '恢复评论 ID: ' . $id;
    admin_log_operation($pdo, 'comment_restore', 'comment', $id, $detail . ' (帖子ID: ' . $postId . ')');

    flash_set('success', '评论已从回收站恢复。');
} catch (Throwable $e) {
    flash_set('danger', '恢复失败，请稍后重试。');
}

redirect('/admin/comments.php');
