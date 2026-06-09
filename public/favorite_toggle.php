<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

$postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
if ($postId <= 0) {
    $postId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
}

$returnUrl = $_SERVER['HTTP_REFERER'] ?? '/index.php';

if ($postId <= 0) {
    flash_set('danger', '参数错误。');
    redirect($returnUrl);
}

try {
    $pdo = db($config);
} catch (Throwable $e) {
    flash_set('danger', '数据库连接失败。');
    redirect($returnUrl);
}

$stmt = $pdo->prepare('SELECT id, status FROM posts WHERE id = ? LIMIT 1');
$stmt->execute([$postId]);
$post = $stmt->fetch();

if (!$post) {
    flash_set('danger', '帖子不存在。');
    redirect($returnUrl);
}

$u = user();
$result = toggle_favorite($pdo, (int)$u['id'], $postId);

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'favorited' => $result['favorited'],
        'action' => $result['action'],
        'message' => $result['favorited'] ? '已加入收藏' : '已取消收藏',
    ]);
    exit;
}

if ($result['favorited']) {
    flash_set('success', '已加入收藏。');
} else {
    flash_set('success', '已取消收藏。');
}

redirect($returnUrl);
