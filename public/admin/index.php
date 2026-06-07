<?php
declare(strict_types=1);

/*
 * 后台仪表盘：数据统计概览。
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

admin_require_login();

try {
    $pdo = db($config);
} catch (Throwable $e) {
    render_header($config, ['title' => '后台 - Lite Forum', 'active' => 'admin']);
    echo '<div class="card card-lite p-4">数据库连接失败</div>';
    render_footer();
    exit;
}

$today = date('Y-m-d');

$stats = [
    'users' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'posts' => (int)$pdo->query('SELECT COUNT(*) FROM posts WHERE status = 1')->fetchColumn(),
    'comments' => (int)$pdo->query('SELECT COUNT(*) FROM comments WHERE status = 1')->fetchColumn(),
];

$stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE DATE(create_time) = ?');
$stmt->execute([$today]);
$stats['users_today'] = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE status = 1 AND DATE(create_time) = ?');
$stmt->execute([$today]);
$stats['posts_today'] = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM comments WHERE status = 1 AND DATE(create_time) = ?');
$stmt->execute([$today]);
$stats['comments_today'] = (int)$stmt->fetchColumn();

render_header($config, ['title' => '后台概览 - Lite Forum', 'active' => 'admin']);

echo '<div class="d-flex align-items-center justify-content-between mb-3">';
echo '<div>'; 
echo '<h1 class="h4 mb-0">后台概览</h1>';
echo '<div class="text-muted small mt-1">数据统计与管理入口</div>';
echo '</div>';
echo '<a class="btn btn-outline-secondary" href="/admin/logout.php">退出后台</a>';
echo '</div>';

echo '<div class="row g-4 mb-4">';
echo '<div class="col-12 col-md-4"><div class="card card-lite border-0 shadow-sm h-100"><div class="card-body p-4">';
echo '<div class="d-flex justify-content-between align-items-center mb-2">';
echo '<div class="text-muted small fw-bold text-uppercase">总用户数</div>';
echo '<div class="bg-primary bg-opacity-10 text-primary rounded p-2"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-people-fill" viewBox="0 0 16 16"><path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7Zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm-5.784 6A2.238 2.238 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.325 6.325 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1h4.216ZM4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/></svg></div>';
echo '</div>';
echo '<div class="h2 mb-0 fw-bold">' . e((string)$stats['users']) . '</div>';
echo '<div class="text-muted small mt-2 d-flex align-items-center"><span class="badge bg-success bg-opacity-10 text-success me-2">+' . e((string)$stats['users_today']) . '</span> 今日新增</div>';
echo '</div></div></div>';

echo '<div class="col-12 col-md-4"><div class="card card-lite border-0 shadow-sm h-100"><div class="card-body p-4">';
echo '<div class="d-flex justify-content-between align-items-center mb-2">';
echo '<div class="text-muted small fw-bold text-uppercase">帖子总数</div>';
echo '<div class="bg-warning bg-opacity-10 text-warning rounded p-2"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-file-text-fill" viewBox="0 0 16 16"><path d="M12 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2zM5 4h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1 0-1zm-.5 2.5A.5.5 0 0 1 5 6h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1-.5-.5zM5 8h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1 0-1zm0 2h3a.5.5 0 0 1 0 1H5a.5.5 0 0 1 0-1z"/></svg></div>';
echo '</div>';
echo '<div class="h2 mb-0 fw-bold">' . e((string)$stats['posts']) . '</div>';
echo '<div class="text-muted small mt-2 d-flex align-items-center"><span class="badge bg-success bg-opacity-10 text-success me-2">+' . e((string)$stats['posts_today']) . '</span> 今日新增</div>';
echo '</div></div></div>';

echo '<div class="col-12 col-md-4"><div class="card card-lite border-0 shadow-sm h-100"><div class="card-body p-4">';
echo '<div class="d-flex justify-content-between align-items-center mb-2">';
echo '<div class="text-muted small fw-bold text-uppercase">评论总数</div>';
echo '<div class="bg-info bg-opacity-10 text-info rounded p-2"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-chat-left-text-fill" viewBox="0 0 16 16"><path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4.414a1 1 0 0 0-.707.293L.854 15.146A.5.5 0 0 1 0 14.793V2zm3.5 1a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1h-9zm0 2.5a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1h-9zm0 2.5a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1h-5z"/></svg></div>';
echo '</div>';
echo '<div class="h2 mb-0 fw-bold">' . e((string)$stats['comments']) . '</div>';
echo '<div class="text-muted small mt-2 d-flex align-items-center"><span class="badge bg-success bg-opacity-10 text-success me-2">+' . e((string)$stats['comments_today']) . '</span> 今日新增</div>';
echo '</div></div></div>';
echo '</div>';

echo '<div class="card card-lite border-0 shadow-sm">';
echo '<div class="card-body p-4">';
echo '<div class="fw-bold mb-4 fs-5">快捷管理</div>';
echo '<div class="d-flex flex-wrap gap-3">';
echo '<a class="btn btn-primary px-4 py-2 rounded-pill fw-semibold shadow-sm" href="/admin/posts.php">帖子管理</a>';
echo '<a class="btn btn-info px-4 py-2 rounded-pill fw-semibold shadow-sm text-white" href="/admin/comments.php">评论管理</a>';
echo '<a class="btn btn-outline-secondary px-4 py-2 rounded-pill fw-semibold" href="/index.php">返回前台</a>';
echo '</div>';
echo '</div>';
echo '</div>';

render_footer();

