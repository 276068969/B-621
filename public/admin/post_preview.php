<?php
declare(strict_types=1);

/*
 * 帖子预览接口：
 * - 获取帖子完整详情（标题、内容、作者、时间、状态）
 * - 获取评论数量等前台呈现重点
 * - 返回 JSON 格式，供即时预览面板使用
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

admin_require_login();

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = db($config);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => '数据库连接失败']);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT p.id, p.user_id, p.title, p.content, p.create_time, p.update_time, p.status, u.username
     FROM posts p
     JOIN users u ON u.id = p.user_id
     WHERE p.id = ?
     LIMIT 1'
);
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) {
    echo json_encode(['success' => false, 'message' => '帖子不存在']);
    exit;
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM comments WHERE post_id = ? AND status = 1');
$stmt->execute([$id]);
$commentCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM favorites WHERE post_id = ?');
$stmt->execute([$id]);
$favoriteCount = (int)$stmt->fetchColumn();

$contentText = trim(strip_tags((string)$post['content']));
$wordCount = mb_strlen($contentText, 'UTF-8');

$headings = [];
if (preg_match_all('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/i', (string)$post['content'], $matches)) {
    foreach ($matches[1] as $heading) {
        $headingText = trim(strip_tags($heading));
        if ($headingText !== '') {
            $headings[] = $headingText;
        }
    }
}

$result = [
    'success' => true,
    'data' => [
        'id' => (int)$post['id'],
        'title' => (string)$post['title'],
        'content' => sanitize_rich_html((string)$post['content']),
        'content_raw' => (string)$post['content'],
        'username' => (string)$post['username'],
        'user_id' => (int)$post['user_id'],
        'create_time' => (string)$post['create_time'],
        'update_time' => $post['update_time'] ? (string)$post['update_time'] : null,
        'status' => (int)$post['status'],
        'comment_count' => $commentCount,
        'favorite_count' => $favoriteCount,
        'word_count' => $wordCount,
        'headings' => $headings,
        'front_url' => '/post.php?id=' . (int)$post['id'],
    ],
];

echo json_encode($result, JSON_UNESCAPED_UNICODE);
