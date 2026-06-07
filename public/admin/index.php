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

// ===== 基础总量统计 =====
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

// ===== 内容质量统计：有效 vs 已隐藏 =====
$posts_total = (int)$pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn();
$posts_hidden = $posts_total - $stats['posts'];
$posts_hidden_rate = $posts_total > 0 ? round(($posts_hidden / $posts_total) * 100, 1) : 0;

$comments_total = (int)$pdo->query('SELECT COUNT(*) FROM comments')->fetchColumn();
$comments_hidden = $comments_total - $stats['comments'];
$comments_hidden_rate = $comments_total > 0 ? round(($comments_hidden / $comments_total) * 100, 1) : 0;

$content_quality = [
    'posts_total' => $posts_total,
    'posts_valid' => $stats['posts'],
    'posts_hidden' => $posts_hidden,
    'posts_hidden_rate' => $posts_hidden_rate,
    'comments_total' => $comments_total,
    'comments_valid' => $stats['comments'],
    'comments_hidden' => $comments_hidden,
    'comments_hidden_rate' => $comments_hidden_rate,
];

// ===== 近7天新增走势 =====
$days = 7;
$posts_trend = [];
$comments_trend = [];
$dates = [];

for ($i = $days - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i day"));
    $date_label = date('m/d', strtotime("-$i day"));
    $dates[] = $date_label;

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE status = 1 AND DATE(create_time) = ?');
    $stmt->execute([$date]);
    $posts_trend[] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM comments WHERE status = 1 AND DATE(create_time) = ?');
    $stmt->execute([$date]);
    $comments_trend[] = (int)$stmt->fetchColumn();
}

// 计算走势变化（今日 vs 昨日）
$posts_yesterday = $posts_trend[$days - 2] ?? 0;
$posts_today_val = $posts_trend[$days - 1] ?? 0;
$posts_change = $posts_yesterday > 0 ? round((($posts_today_val - $posts_yesterday) / $posts_yesterday) * 100, 1) : 0;

$comments_yesterday = $comments_trend[$days - 2] ?? 0;
$comments_today_val = $comments_trend[$days - 1] ?? 0;
$comments_change = $comments_yesterday > 0 ? round((($comments_today_val - $comments_yesterday) / $comments_yesterday) * 100, 1) : 0;

// ===== 评论活跃时间窗口（最近30天，按小时分布） =====
$hour_stats = array_fill(0, 24, 0);
$stmt = $pdo->query(
    'SELECT HOUR(create_time) as h, COUNT(*) as cnt FROM comments ' .
    'WHERE status = 1 AND create_time >= DATE_SUB(NOW(), INTERVAL 30 DAY) ' .
    'GROUP BY HOUR(create_time) ORDER BY h'
);
while ($row = $stmt->fetch()) {
    $hour_stats[(int)$row['h']] = (int)$row['cnt'];
}

$max_hour_count = max($hour_stats) ?: 1;

// 找出最活跃的3个时间段
$hour_ranking = [];
foreach ($hour_stats as $h => $cnt) {
    $hour_ranking[] = ['hour' => $h, 'count' => $cnt];
}
usort($hour_ranking, fn($a, $b) => $b['count'] - $a['count']);
$top_hours = array_slice($hour_ranking, 0, 3);

// ===== posts 与 comments 结构关系 =====
// 平均每篇帖子的评论数
$avg_comments_per_post = $stats['posts'] > 0 ? round($stats['comments'] / $stats['posts'], 1) : 0;

// 0评论帖子占比
$posts_without_comments = (int)$pdo->query(
    'SELECT COUNT(*) FROM posts WHERE status = 1 AND id NOT IN (SELECT DISTINCT post_id FROM comments WHERE status = 1)'
)->fetchColumn();
$posts_no_comment_rate = $stats['posts'] > 0 ? round(($posts_without_comments / $stats['posts']) * 100, 1) : 0;

// 评论最多的Top5帖子
$top_posts_stmt = $pdo->query(
    'SELECT p.id, p.title, COUNT(c.id) as comment_count ' .
    'FROM posts p LEFT JOIN comments c ON p.id = c.post_id AND c.status = 1 ' .
    'WHERE p.status = 1 ' .
    'GROUP BY p.id, p.title ORDER BY comment_count DESC LIMIT 5'
);
$top_comment_posts = $top_posts_stmt->fetchAll();

// 近30天有评论的活跃帖子数
$active_posts_30d = (int)$pdo->query(
    'SELECT COUNT(DISTINCT p.id) FROM posts p ' .
    'INNER JOIN comments c ON p.id = c.post_id ' .
    'WHERE p.status = 1 AND c.status = 1 AND c.create_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
)->fetchColumn();

render_header($config, ['title' => '后台概览 - Lite Forum', 'active' => 'admin']);

echo '<div class="d-flex align-items-center justify-content-between mb-3">';
echo '<div>';
echo '<h1 class="h4 mb-0">后台概览</h1>';
echo '<div class="text-muted small mt-1">数据统计与管理入口</div>';
echo '</div>';
echo '<a class="btn btn-outline-secondary" href="/admin/logout.php">退出后台</a>';
echo '</div>';

// ===== 第一行：总量卡片（保留原有） =====
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
$post_trend_class = $posts_change >= 0 ? 'text-success' : 'text-danger';
$post_trend_icon = $posts_change >= 0 ? '↑' : '↓';
echo '<div class="text-muted small mt-2 d-flex align-items-center">';
echo '<span class="badge bg-success bg-opacity-10 text-success me-2">+' . e((string)$stats['posts_today']) . '</span> 今日新增';
echo '<span class="ms-2 ' . $post_trend_class . ' fw-medium">(' . $post_trend_icon . abs($posts_change) . '%)</span>';
echo '</div>';
echo '</div></div></div>';

echo '<div class="col-12 col-md-4"><div class="card card-lite border-0 shadow-sm h-100"><div class="card-body p-4">';
echo '<div class="d-flex justify-content-between align-items-center mb-2">';
echo '<div class="text-muted small fw-bold text-uppercase">评论总数</div>';
echo '<div class="bg-info bg-opacity-10 text-info rounded p-2"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-chat-left-text-fill" viewBox="0 0 16 16"><path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4.414a1 1 0 0 0-.707.293L.854 15.146A.5.5 0 0 1 0 14.793V2zm3.5 1a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1h-9zm0 2.5a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1h-9zm0 2.5a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1h-5z"/></svg></div>';
echo '</div>';
echo '<div class="h2 mb-0 fw-bold">' . e((string)$stats['comments']) . '</div>';
$comment_trend_class = $comments_change >= 0 ? 'text-success' : 'text-danger';
$comment_trend_icon = $comments_change >= 0 ? '↑' : '↓';
echo '<div class="text-muted small mt-2 d-flex align-items-center">';
echo '<span class="badge bg-success bg-opacity-10 text-success me-2">+' . e((string)$stats['comments_today']) . '</span> 今日新增';
echo '<span class="ms-2 ' . $comment_trend_class . ' fw-medium">(' . $comment_trend_icon . abs($comments_change) . '%)</span>';
echo '</div>';
echo '</div></div></div>';
echo '</div>';

// ===== 第二行：内容质量 + 结构关系 =====
echo '<div class="row g-4 mb-4">';

// 内容质量统计
echo '<div class="col-12 col-lg-6"><div class="card card-lite border-0 shadow-sm h-100"><div class="card-body p-4">';
echo '<div class="d-flex align-items-center gap-2 mb-4">';
echo '<div class="bg-success bg-opacity-10 text-success rounded p-2"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-shield-check" viewBox="0 0 16 16"><path d="M5.338 1.59a61.44 61.44 0 0 0-2.837.856.48.48 0 0 0-.328.39c-.554 4.157.726 7.19 2.253 9.188a10.725 10.725 0 0 0 2.287 2.233c.346.244.652.42.893.533.12.057.218.095.293.118a.55.55 0 0 0 .199.03c.053 0 .11-.013.168-.03.075-.023.173-.06.293-.118.24-.113.547-.289.893-.533a10.7 10.7 0 0 0 2.287-2.233c1.527-1.997 2.807-5.031 2.253-9.188a.48.48 0 0 0-.328-.39c-.652-.27-1.47-.592-2.837-.856C9.687.443 8.446 0 8 0s-1.687.443-2.662 1.59zM1.5 3.058c.336-.137.784-.305 1.3-.448C4.084 2.197 5.743 2 8 2s3.916.197 5.2.61c.516.143.964.311 1.3.448-.432 3.412-1.54 5.902-2.88 7.637a9.7 9.7 0 0 1-2.07 2.012c-.28.197-.55.361-.787.478-.118.058-.21.094-.264.112a.46.46 0 0 1-.099.02c-.026 0-.057-.006-.099-.02a3.11 3.11 0 0 1-.264-.112 8.6 8.6 0 0 1-.787-.478 9.7 9.7 0 0 1-2.07-2.012C3.04 8.96 1.932 6.47 1.5 3.058z"/><path d="M10.97 5.97a.75.75 0 0 0-1.042-1.08L7.47 7.352 6.28 6.22a.75.75 0 0 0-1.06 1.06l1.75 1.75a.75.75 0 0 0 1.052-.028l3-3.5z"/></svg></div>';
echo '<h5 class="fw-bold mb-0">内容质量</h5>';
echo '</div>';

// 帖子质量进度条
echo '<div class="mb-3">';
echo '<div class="d-flex justify-content-between small mb-1">';
echo '<span class="text-muted">帖子</span>';
echo '<span class="fw-medium">' . e((string)$content_quality['posts_valid']) . ' 有效 / ' . e((string)$content_quality['posts_hidden']) . ' 已隐藏 (' . e((string)$content_quality['posts_hidden_rate']) . '%)</span>';
echo '</div>';
echo '<div class="progress" style="height: 8px;">';
$posts_valid_pct = 100 - $content_quality['posts_hidden_rate'];
echo '<div class="progress-bar bg-success" style="width: ' . $posts_valid_pct . '%" role="progressbar" aria-valuenow="' . $posts_valid_pct . '" aria-valuemin="0" aria-valuemax="100"></div>';
echo '<div class="progress-bar bg-danger bg-opacity-75" style="width: ' . $content_quality['posts_hidden_rate'] . '%" role="progressbar" aria-valuenow="' . $content_quality['posts_hidden_rate'] . '" aria-valuemin="0" aria-valuemax="100"></div>';
echo '</div>';
echo '</div>';

// 评论质量进度条
echo '<div>';
echo '<div class="d-flex justify-content-between small mb-1">';
echo '<span class="text-muted">评论</span>';
echo '<span class="fw-medium">' . e((string)$content_quality['comments_valid']) . ' 有效 / ' . e((string)$content_quality['comments_hidden']) . ' 已隐藏 (' . e((string)$content_quality['comments_hidden_rate']) . '%)</span>';
echo '</div>';
echo '<div class="progress" style="height: 8px;">';
$comments_valid_pct = 100 - $content_quality['comments_hidden_rate'];
echo '<div class="progress-bar bg-success" style="width: ' . $comments_valid_pct . '%" role="progressbar" aria-valuenow="' . $comments_valid_pct . '" aria-valuemin="0" aria-valuemax="100"></div>';
echo '<div class="progress-bar bg-danger bg-opacity-75" style="width: ' . $content_quality['comments_hidden_rate'] . '%" role="progressbar" aria-valuenow="' . $content_quality['comments_hidden_rate'] . '" aria-valuemin="0" aria-valuemax="100"></div>';
echo '</div>';
echo '</div>';

echo '</div></div></div>';

// 结构关系统计
echo '<div class="col-12 col-lg-6"><div class="card card-lite border-0 shadow-sm h-100"><div class="card-body p-4">';
echo '<div class="d-flex align-items-center gap-2 mb-4">';
echo '<div class="bg-primary bg-opacity-10 text-primary rounded p-2"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-diagram-3" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M6 3.5A1.5 1.5 0 0 1 7.5 2h1A1.5 1.5 0 0 1 10 3.5v1A1.5 1.5 0 0 1 8.5 6v1H14a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-1 0V8h-5v.5a.5.5 0 0 1-1 0V8h-5v.5a.5.5 0 0 1-1 0v-1A.5.5 0 0 1 2 7h5.5V6A1.5 1.5 0 0 1 6 4.5v-1zM8.5 5a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1zM0 11.5A1.5 1.5 0 0 1 1.5 10h1A1.5 1.5 0 0 1 4 11.5v1A1.5 1.5 0 0 1 2.5 14h-1A1.5 1.5 0 0 1 0 12.5v-1zm1.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5h-1zm4.5.5A1.5 1.5 0 0 1 7.5 10h1a1.5 1.5 0 0 1 1.5 1.5v1A1.5 1.5 0 0 1 8.5 14h-1A1.5 1.5 0 0 1 6 12.5v-1zM7.5 11a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5h-1zm4.5.5a1.5 1.5 0 0 1 1.5-1.5h1a1.5 1.5 0 0 1 1.5 1.5v1a1.5 1.5 0 0 1-1.5 1.5h-1a1.5 1.5 0 0 1-1.5-1.5v-1zm1.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5h-1z"/></svg></div>';
echo '<h5 class="fw-bold mb-0">内容结构</h5>';
echo '</div>';

echo '<div class="row g-3">';
echo '<div class="col-6">';
echo '<div class="bg-light rounded p-3 text-center">';
echo '<div class="h3 fw-bold text-primary mb-1">' . e((string)$avg_comments_per_post) . '</div>';
echo '<div class="text-muted small">平均每帖评论</div>';
echo '</div>';
echo '</div>';
echo '<div class="col-6">';
echo '<div class="bg-light rounded p-3 text-center">';
echo '<div class="h3 fw-bold text-warning mb-1">' . e((string)$posts_no_comment_rate) . '%</div>';
echo '<div class="text-muted small">零回复帖子占比</div>';
echo '</div>';
echo '</div>';
echo '<div class="col-6">';
echo '<div class="bg-light rounded p-3 text-center">';
echo '<div class="h3 fw-bold text-info mb-1">' . e((string)$active_posts_30d) . '</div>';
echo '<div class="text-muted small">近30天活跃帖</div>';
echo '</div>';
echo '</div>';
echo '<div class="col-6">';
echo '<div class="bg-light rounded p-3 text-center">';
echo '<div class="h3 fw-bold text-success mb-1">' . e((string)$posts_without_comments) . '</div>';
echo '<div class="text-muted small">零回复帖子数</div>';
echo '</div>';
echo '</div>';
echo '</div>';

echo '</div></div></div>';
echo '</div>';

// ===== 第三行：近7天走势 + 活跃时间窗口 =====
echo '<div class="row g-4 mb-4">';

// 近7天新增走势
echo '<div class="col-12 col-lg-8"><div class="card card-lite border-0 shadow-sm h-100"><div class="card-body p-4">';
echo '<div class="d-flex align-items-center gap-2 mb-4">';
echo '<div class="bg-info bg-opacity-10 text-info rounded p-2"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-graph-up" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M0 0h1v15h15v1H0V0Zm14.817 3.113a.5.5 0 0 1 .07.704l-4.5 5.5a.5.5 0 0 1-.74.037L7.06 6.767l-3.656 5.027a.5.5 0 0 1-.808-.588l4-5.5a.5.5 0 0 1 .758-.06l2.609 2.61 4.15-5.073a.5.5 0 0 1 .704-.07Z"/></svg></div>';
echo '<h5 class="fw-bold mb-0">近7天新增走势</h5>';
echo '</div>';

// 简易柱状图（CSS实现）
echo '<div class="d-flex align-items-end justify-content-between gap-2" style="height: 180px;">';
for ($i = 0; $i < $days; $i++) {
    $post_max = max($posts_trend) ?: 1;
    $post_height = ($posts_trend[$i] / $post_max) * 100;
    $comment_max = max($comments_trend) ?: 1;
    $comment_height = ($comments_trend[$i] / $comment_max) * 100;

    echo '<div class="d-flex flex-column align-items-center flex-grow-1">';
    echo '<div class="w-100 d-flex gap-1 align-items-end justify-content-center" style="height: 140px;">';
    echo '<div class="bg-warning rounded-top" style="width: 35%; height: ' . max($post_height, 2) . '%;" title="帖子: ' . $posts_trend[$i] . '"></div>';
    echo '<div class="bg-info rounded-top" style="width: 35%; height: ' . max($comment_height, 2) . '%;" title="评论: ' . $comments_trend[$i] . '"></div>';
    echo '</div>';
    echo '<div class="text-muted small mt-2">' . e($dates[$i]) . '</div>';
    echo '<div class="small fw-medium">' . e((string)$posts_trend[$i]) . ' / ' . e((string)$comments_trend[$i]) . '</div>';
    echo '</div>';
}
echo '</div>';

// 图例
echo '<div class="d-flex gap-4 justify-content-center mt-3 small text-muted">';
echo '<span class="d-flex align-items-center gap-1"><span class="d-inline-block bg-warning rounded" style="width:12px;height:12px;"></span> 帖子</span>';
echo '<span class="d-flex align-items-center gap-1"><span class="d-inline-block bg-info rounded" style="width:12px;height:12px;"></span> 评论</span>';
echo '</div>';

echo '</div></div></div>';

// 评论活跃时间窗口
echo '<div class="col-12 col-lg-4"><div class="card card-lite border-0 shadow-sm h-100"><div class="card-body p-4">';
echo '<div class="d-flex align-items-center gap-2 mb-4">';
echo '<div class="bg-warning bg-opacity-10 text-warning rounded p-2"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-clock-history" viewBox="0 0 16 16"><path d="M8.515 1.019A7 7 0 0 0 8 1V0a8 8 0 0 1 .589.022l-.074.997zm2.004.45a7.003 7.003 0 0 0-.985-.299l.219-.976c.383.086.76.2 1.126.342l-.36.933zm1.37.71a7.01 7.01 0 0 0-.439-.27l.493-.87a8.025 8.025 0 0 1 .979.654l-.615.789a6.996 6.996 0 0 0-.418-.302zm1.834 1.79a6.99 6.99 0 0 0-.653-.796l.724-.69c.27.285.52.59.747.91l-.818.576zm.744 1.352a7.08 7.08 0 0 0-.214-.468l.893-.45a7.976 7.976 0 0 1 .45 1.088l-.95.313a7.023 7.023 0 0 0-.179-.483zm.622 2.247a6.99 6.99 0 0 0-.1-1.025l.995-.1a8.014 8.014 0 0 1 .087 1.074l-.982.051zM14.988 9a6.967 6.967 0 0 0-.232-1.634l.98-.198a8.009 8.009 0 0 1 .198 1.832h-.946zm-.668 2.068a7.004 7.004 0 0 0 .379-1.068l.977.208a8.001 8.001 0 0 1-.452 1.27l-.904-.41zM13.42 12.99a6.97 6.97 0 0 0 .602-.91l.804.593a8.025 8.025 0 0 1-.743 1.115l-.663-.798zm-1.412 1.727a6.98 6.98 0 0 0 .789-.663l.678.735a8.007 8.007 0 0 1-.978.81l-.489-.882zM10.431 14.97a7.01 7.01 0 0 0 .944-.29l.42.907a8.008 8.008 0 0 1-1.125.346l-.239-.963zM9.05 15.667A7.004 7.004 0 0 0 10 15.5v1a8.02 8.02 0 0 1-1 .031v-.998l.05.034z"/><path d="M8 13.5a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11zM8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1z"/><path d="M7.5 3a.5.5 0 0 1 .5.5v4.25l2.5 1.5a.5.5 0 0 1-.5.866L7.5 8.536V3.5a.5.5 0 0 1 .5-.5z"/></svg></div>';
echo '<h5 class="fw-bold mb-0">活跃时间窗口</h5>';
echo '</div>';

// 24小时分布（横向柱状图）
echo '<div class="d-flex flex-column gap-1" style="height: 180px; overflow-y: auto;">';
for ($h = 0; $h < 24; $h++) {
    $count = $hour_stats[$h];
    $width = $max_hour_count > 0 ? ($count / $max_hour_count) * 100 : 0;
    $is_top = in_array($h, array_column($top_hours, 'hour'));
    $bar_class = $is_top ? 'bg-warning' : 'bg-info bg-opacity-50';
    $hour_label = str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':00';

    echo '<div class="d-flex align-items-center gap-2">';
    echo '<span class="text-muted small" style="width: 45px; flex-shrink: 0;">' . e($hour_label) . '</span>';
    echo '<div class="flex-grow-1 bg-light rounded" style="height: 16px;">';
    echo '<div class="' . $bar_class . ' rounded" style="height: 100%; width: ' . max($width, 1) . '%;"></div>';
    echo '</div>';
    echo '<span class="small fw-medium" style="width: 30px; text-align: right; flex-shrink: 0;">' . e((string)$count) . '</span>';
    echo '</div>';
}
echo '</div>';

// Top3 高峰时段
echo '<div class="mt-3 pt-3 border-top">';
echo '<div class="text-muted small mb-2">高峰时段 Top3（近30天）</div>';
echo '<div class="d-flex flex-wrap gap-2">';
foreach ($top_hours as $idx => $th) {
    $hour_str = str_pad((string)$th['hour'], 2, '0', STR_PAD_LEFT) . ':00-' . str_pad((string)($th['hour'] + 1), 2, '0', STR_PAD_LEFT) . ':00';
    $badge_class = $idx === 0 ? 'bg-warning text-dark' : 'bg-info';
    echo '<span class="badge ' . $badge_class . ' fs-6 py-1 px-2">' . e($hour_str) . ' · ' . e((string)$th['count']) . '条</span>';
}
echo '</div>';
echo '</div>';

echo '</div></div></div>';
echo '</div>';

// ===== 第四行：评论最多的帖子 + 快捷管理 =====
echo '<div class="row g-4 mb-4">';

// 评论最多的帖子
echo '<div class="col-12 col-lg-8"><div class="card card-lite border-0 shadow-sm h-100"><div class="card-body p-4">';
echo '<div class="d-flex align-items-center gap-2 mb-4">';
echo '<div class="bg-danger bg-opacity-10 text-danger rounded p-2"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-trophy" viewBox="0 0 16 16"><path d="M2.5.5A.5.5 0 0 1 3 0h10a.5.5 0 0 1 .5.5q0 .807-.034 1.536a3 3 0 1 1-3.133 3.268 5 5 0 0 1-1.337.76V8.5h1a.5.5 0 0 1 0 1h-5a.5.5 0 0 1 0-1h1v-2.436a5 5 0 0 1-1.337-.76A3 3 0 1 1 2.034 2.036.5.5 0 0 1 2.5.5zM3 3a2 2 0 1 0 4 0 2 2 0 0 0-4 0zm6 0a2 2 0 1 0 4 0 2 2 0 0 0-4 0z"/><path d="M3.02 6.758a5 5 0 0 0 2.046 1.343c.304.533.708 1.001 1.184 1.387V13h-1.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h5a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5H9v-3.512c.476-.386.88-.854 1.184-1.387a5 5 0 0 0 2.046-1.343C12.66 6.398 13 5.68 13 5V2.08l.117-.21q.043-.372.07-.77H14v3.9q0 .85-.382 1.618a3.2 3.2 0 0 1-1.087 1.282 5.2 5.2 0 0 1-1.623.797c-.19.602-.522 1.156-.98 1.636-.457.48-.977.855-1.528 1.111V14H7.5v-1.512c-.551-.256-1.071-.631-1.528-1.11a5.5 5.5 0 0 1-.98-1.637 5.2 5.2 0 0 1-1.623-.797A3.2 3.2 0 0 1 2.382 7.9 3.1 3.1 0 0 1 2 6.28V2.08h.117L3 2.08V5c0 .68.34 1.398.02 1.758z"/></svg></div>';
echo '<h5 class="fw-bold mb-0">讨论最热帖子 Top5</h5>';
echo '</div>';

if (count($top_comment_posts) > 0) {
    echo '<div class="list-group list-group-flush">';
    foreach ($top_comment_posts as $idx => $post) {
        $rank_color = match(true) {
            $idx === 0 => 'text-warning',
            $idx === 1 => 'text-secondary',
            $idx === 2 => 'text-danger',
            default => 'text-muted',
        };
        $rank_bg = match(true) {
            $idx === 0 => 'bg-warning bg-opacity-10',
            $idx === 1 => 'bg-secondary bg-opacity-10',
            $idx === 2 => 'bg-danger bg-opacity-10',
            default => 'bg-light',
        };

        echo '<div class="list-group-item d-flex align-items-center gap-3 px-0">';
        echo '<div class="' . $rank_bg . ' ' . $rank_color . ' rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 28px; height: 28px; font-size: 12px; flex-shrink: 0;">' . ($idx + 1) . '</div>';
        echo '<a href="/post.php?id=' . (int)$post['id'] . '" class="text-decoration-none text-dark flex-grow-1 text-truncate" target="_blank">' . e($post['title']) . '</a>';
        echo '<span class="badge bg-info bg-opacity-10 text-info fs-6 py-1 px-2 flex-shrink-0">' . e((string)$post['comment_count']) . ' 评论</span>';
        echo '</div>';
    }
    echo '</div>';
} else {
    echo '<div class="text-muted text-center py-4">暂无数据</div>';
}

echo '</div></div></div>';

// 快捷管理
echo '<div class="col-12 col-lg-4"><div class="card card-lite border-0 shadow-sm h-100"><div class="card-body p-4">';
echo '<div class="fw-bold mb-4 fs-5">快捷管理</div>';
echo '<div class="d-flex flex-column gap-3">';
echo '<a class="btn btn-primary px-4 py-2 rounded-pill fw-semibold shadow-sm" href="/admin/posts.php">帖子管理</a>';
echo '<a class="btn btn-info px-4 py-2 rounded-pill fw-semibold shadow-sm text-white" href="/admin/comments.php">评论管理</a>';
echo '<a class="btn btn-outline-secondary px-4 py-2 rounded-pill fw-semibold" href="/index.php">返回前台</a>';
echo '</div>';
echo '</div></div></div>';
echo '</div>';

render_footer();
