<?php
declare(strict_types=1);

/*
 * 后台仪表盘：数据统计概览。
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/admin_stats.php';

admin_require_login();

try {
    $pdo = db($config);
} catch (Throwable $e) {
    render_header($config, ['title' => '后台 - Lite Forum', 'active' => 'admin']);
    echo '<div class="card card-lite p-4">数据库连接失败</div>';
    render_footer();
    exit;
}

// ===== 加载各类统计数据 =====
$basicStats = get_basic_stats($pdo);
$contentQuality = get_content_quality_stats($pdo, $basicStats);
$trendStats = get_trend_stats($pdo, 7);
$hourlyStats = get_hourly_stats($pdo, 30);
$structureStats = get_structure_stats($pdo, $basicStats);
$topPosts = get_top_comment_posts($pdo, 5);
$recentLogs = admin_get_recent_logs($pdo, 10);

render_header($config, ['title' => '后台概览 - Lite Forum', 'active' => 'admin']);

echo '<div class="d-flex align-items-center justify-content-between mb-3">';
echo '<div>';
echo '<h1 class="h4 mb-0">后台概览</h1>';
echo '<div class="text-muted small mt-1">数据统计与管理入口</div>';
echo '</div>';
echo '<a class="btn btn-outline-secondary" href="/admin/logout.php">退出后台</a>';
echo '</div>';

// ===== 第一行：总量卡片 =====
echo '<div class="row g-4 mb-4">';
render_stat_card(
    '总用户数',
    (string)$basicStats['users'],
    '+' . $basicStats['users_today'] . ' 今日新增',
    'people-fill',
    'primary'
);

$postChangeText = format_change_text($trendStats['posts_change']);
render_stat_card(
    '帖子总数',
    (string)$basicStats['posts'],
    '+' . $basicStats['posts_today'] . ' 今日新增 ' . $postChangeText,
    'file-text-fill',
    'warning'
);

$commentChangeText = format_change_text($trendStats['comments_change']);
render_stat_card(
    '评论总数',
    (string)$basicStats['comments'],
    '+' . $basicStats['comments_today'] . ' 今日新增 ' . $commentChangeText,
    'chat-left-text-fill',
    'info'
);
echo '</div>';

// ===== 第二行：内容质量 + 结构关系 =====
echo '<div class="row g-4 mb-4">';

// 内容质量
echo '<div class="col-12 col-lg-6"><div class="card card-lite border-0 shadow-sm h-100"><div class="card-body p-4">';
render_section_header('内容质量', 'shield-check', 'success');

render_quality_progress('帖子', $contentQuality['posts_valid'], $contentQuality['posts_hidden'], $contentQuality['posts_hidden_rate']);
echo '<div class="mb-3"></div>';
render_quality_progress('评论', $contentQuality['comments_valid'], $contentQuality['comments_hidden'], $contentQuality['comments_hidden_rate']);

echo '</div></div></div>';

// 结构关系
echo '<div class="col-12 col-lg-6"><div class="card card-lite border-0 shadow-sm h-100"><div class="card-body p-4">';
render_section_header('内容结构', 'diagram-3', 'primary');

echo '<div class="row g-3">';
render_metric_card('平均每帖评论', (string)$structureStats['avg_comments_per_post'], 'primary');
render_metric_card('零回复占比', $structureStats['posts_no_comment_rate'] . '%', 'warning');
render_metric_card('近30天活跃帖', (string)$structureStats['active_posts_30d'], 'info');
render_metric_card('零回复帖子数', (string)$structureStats['posts_without_comments'], 'success');
echo '</div>';

echo '</div></div></div>';
echo '</div>';

// ===== 第三行：近7天走势 + 活跃时间窗口 =====
echo '<div class="row g-4 mb-4">';

// 近7天走势
echo '<div class="col-12 col-lg-8"><div class="card card-lite border-0 shadow-sm h-100"><div class="card-body p-4">';
render_section_header('近7天新增走势', 'graph-up', 'info');

if ($trendStats['has_data']) {
    render_trend_chart($trendStats);
} else {
    render_empty_state('暂无走势数据', '随着帖子和评论的增加，这里会展示近7天的变化趋势');
}

echo '</div></div></div>';

// 活跃时间窗口
echo '<div class="col-12 col-lg-4"><div class="card card-lite border-0 shadow-sm h-100"><div class="card-body p-4">';
render_section_header('活跃时间窗口', 'clock-history', 'warning');

if ($hourlyStats['has_data']) {
    render_hourly_chart($hourlyStats);
} else {
    render_empty_state('暂无活跃数据', '近30天暂无评论数据，无法分析活跃时段');
}

echo '</div></div></div>';
echo '</div>';

// ===== 第四行：最热帖子 + 快捷管理 =====
echo '<div class="row g-4 mb-4">';

// 讨论最热帖子
echo '<div class="col-12 col-lg-8"><div class="card card-lite border-0 shadow-sm h-100"><div class="card-body p-4">';
render_section_header('讨论最热帖子 Top5', 'trophy', 'danger');

if (count($topPosts) > 0) {
    render_top_posts_list($topPosts);
} else {
    render_empty_state('暂无热门帖子', '当有评论的帖子出现后，这里会展示讨论最热烈的内容');
}

echo '</div></div></div>';

// 快捷管理
echo '<div class="col-12 col-lg-4"><div class="card card-lite border-0 shadow-sm h-100"><div class="card-body p-4">';
echo '<div class="fw-bold mb-4 fs-5">快捷管理</div>';
echo '<div class="d-flex flex-column gap-3">';
echo '<a class="btn btn-primary px-4 py-2 rounded-pill fw-semibold shadow-sm" href="/admin/posts.php">帖子管理</a>';
echo '<a class="btn btn-info px-4 py-2 rounded-pill fw-semibold shadow-sm text-white" href="/admin/comments.php">评论管理</a>';
echo '<a class="btn btn-success px-4 py-2 rounded-pill fw-semibold shadow-sm text-white" href="/admin/boards.php">版块管理</a>';
echo '<a class="btn btn-outline-secondary px-4 py-2 rounded-pill fw-semibold" href="/index.php">返回前台</a>';
echo '</div></div></div>';
echo '</div>';
echo '</div>';

// ===== 第五行：最近管理行为 =====
echo '<div class="row g-4 mb-4">';
echo '<div class="col-12"><div class="card card-lite border-0 shadow-sm"><div class="card-body p-4">';
render_section_header('最近管理行为', 'journal-text', 'primary');

if (count($recentLogs) > 0) {
    render_admin_logs_list($recentLogs);
} else {
    render_empty_state('暂无操作记录', '管理员进行登录、编辑、删除等操作后，这里会显示操作时间线');
}

echo '</div></div></div>';
echo '</div>';

render_footer();

// ===== 以下为渲染辅助函数 =====

/**
 * 渲染单个总量统计卡片
 */
function render_stat_card(string $title, string $value, string $subtitle, string $icon, string $color): void
{
    $iconSvg = get_bi_icon($icon);
    echo '<div class="col-12 col-md-4"><div class="card card-lite border-0 shadow-sm h-100"><div class="card-body p-4">';
    echo '<div class="d-flex justify-content-between align-items-center mb-2">';
    echo '<div class="text-muted small fw-bold text-uppercase">' . e($title) . '</div>';
    echo '<div class="bg-' . $color . ' bg-opacity-10 text-' . $color . ' rounded p-2">' . $iconSvg . '</div>';
    echo '</div>';
    echo '<div class="h2 mb-0 fw-bold">' . e($value) . '</div>';
    echo '<div class="text-muted small mt-2">' . $subtitle . '</div>';
    echo '</div></div></div>';
}

/**
 * 渲染区块标题头
 */
function render_section_header(string $title, string $icon, string $color): void
{
    $iconSvg = get_bi_icon($icon, 18);
    echo '<div class="d-flex align-items-center gap-2 mb-4">';
    echo '<div class="bg-' . $color . ' bg-opacity-10 text-' . $color . ' rounded p-2">' . $iconSvg . '</div>';
    echo '<h5 class="fw-bold mb-0">' . e($title) . '</h5>';
    echo '</div>';
}

/**
 * 渲染质量进度条
 */
function render_quality_progress(string $label, int $valid, int $hidden, float $hiddenRate): void
{
    $validRate = 100 - $hiddenRate;
    echo '<div>';
    echo '<div class="d-flex justify-content-between small mb-1">';
    echo '<span class="text-muted">' . e($label) . '</span>';
    echo '<span class="fw-medium">' . e((string)$valid) . ' 有效 / ' . e((string)$hidden) . ' 已隐藏 (' . e((string)$hiddenRate) . '%)</span>';
    echo '</div>';
    echo '<div class="progress" style="height: 8px;">';
    echo '<div class="progress-bar bg-success" style="width: ' . $validRate . '%" role="progressbar"></div>';
    echo '<div class="progress-bar bg-danger bg-opacity-75" style="width: ' . $hiddenRate . '%" role="progressbar"></div>';
    echo '</div>';
    echo '</div>';
}

/**
 * 渲染结构指标小卡片
 */
function render_metric_card(string $label, string $value, string $color): void
{
    echo '<div class="col-6">';
    echo '<div class="bg-light rounded p-3 text-center">';
    echo '<div class="h3 fw-bold text-' . $color . ' mb-1">' . e($value) . '</div>';
    echo '<div class="text-muted small">' . e($label) . '</div>';
    echo '</div>';
    echo '</div>';
}

/**
 * 渲染近7天走势柱状图
 */
function render_trend_chart(array $trend): void
{
    $days = $trend['days'];
    $overall_max = $trend['overall_max'] > 0 ? $trend['overall_max'] : 1;

    echo '<div class="d-flex align-items-end justify-content-between gap-2" style="height: 180px;">';
    for ($i = 0; $i < $days; $i++) {
        $post_count = $trend['posts'][$i];
        $comment_count = $trend['comments'][$i];
        $post_height = $overall_max > 0 ? ($post_count / $overall_max) * 100 : 0;
        $comment_height = $overall_max > 0 ? ($comment_count / $overall_max) * 100 : 0;

        echo '<div class="d-flex flex-column align-items-center flex-grow-1">';
        echo '<div class="w-100 d-flex gap-1 align-items-end justify-content-center" style="height: 140px;">';
        echo '<div class="bg-warning rounded-top" style="width: 35%; height: ' . max($post_height, 2) . '%;" title="帖子: ' . $post_count . '"></div>';
        echo '<div class="bg-info rounded-top" style="width: 35%; height: ' . max($comment_height, 2) . '%;" title="评论: ' . $comment_count . '"></div>';
        echo '</div>';
        echo '<div class="text-muted small mt-2">' . e($trend['dates'][$i]) . '</div>';
        echo '<div class="small fw-medium">' . e((string)$post_count) . ' / ' . e((string)$comment_count) . '</div>';
        echo '</div>';
    }
    echo '</div>';

    echo '<div class="d-flex gap-4 justify-content-center mt-3 small text-muted">';
    echo '<span class="d-flex align-items-center gap-1"><span class="d-inline-block bg-warning rounded" style="width:12px;height:12px;"></span> 帖子</span>';
    echo '<span class="d-flex align-items-center gap-1"><span class="d-inline-block bg-info rounded" style="width:12px;height:12px;"></span> 评论</span>';
    echo '</div>';
}

/**
 * 渲染24小时活跃分布图
 */
function render_hourly_chart(array $hourly): void
{
    $max_count = $hourly['max_count'] > 0 ? $hourly['max_count'] : 1;
    $top_hours = $hourly['top_hours'];
    $top_hour_ids = array_column($top_hours, 'hour');

    echo '<div class="d-flex flex-column gap-1" style="height: 180px; overflow-y: auto;">';
    for ($h = 0; $h < 24; $h++) {
        $count = $hourly['hours'][$h];
        $width = $max_count > 0 ? ($count / $max_count) * 100 : 0;
        $is_top = in_array($h, $top_hour_ids, true);
        $bar_class = $is_top ? 'bg-warning' : 'bg-info bg-opacity-50';
        $hour_label = sprintf('%02d:00', $h);

        echo '<div class="d-flex align-items-center gap-2">';
        echo '<span class="text-muted small" style="width: 45px; flex-shrink: 0;">' . e($hour_label) . '</span>';
        echo '<div class="flex-grow-1 bg-light rounded" style="height: 16px;">';
        echo '<div class="' . $bar_class . ' rounded" style="height: 100%; width: ' . max($width, 1) . '%;"></div>';
        echo '</div>';
        echo '<span class="small fw-medium" style="width: 30px; text-align: right; flex-shrink: 0;">' . e((string)$count) . '</span>';
        echo '</div>';
    }
    echo '</div>';

    echo '<div class="mt-3 pt-3 border-top">';
    echo '<div class="text-muted small mb-2">高峰时段 Top3（近' . $hourly['days_range'] . '天）</div>';
    if (count($top_hours) > 0) {
        echo '<div class="d-flex flex-wrap gap-2">';
        foreach ($top_hours as $idx => $th) {
            $hour_str = sprintf('%02d:00-%02d:00', $th['hour'], $th['hour'] + 1);
            $badge_class = $idx === 0 ? 'bg-warning text-dark' : 'bg-info';
            echo '<span class="badge ' . $badge_class . ' fs-6 py-1 px-2">' . e($hour_str) . ' · ' . e((string)$th['count']) . '条</span>';
        }
        echo '</div>';
    } else {
        echo '<div class="text-muted small">暂无高峰时段数据</div>';
    }
    echo '</div>';
}

/**
 * 渲染最热帖子列表
 */
function render_top_posts_list(array $posts): void
{
    echo '<div class="list-group list-group-flush">';
    foreach ($posts as $idx => $post) {
        $rank_colors = ['text-warning', 'text-secondary', 'text-danger', 'text-muted', 'text-muted'];
        $rank_bgs = ['bg-warning bg-opacity-10', 'bg-secondary bg-opacity-10', 'bg-danger bg-opacity-10', 'bg-light', 'bg-light'];
        $rank_color = $rank_colors[$idx] ?? 'text-muted';
        $rank_bg = $rank_bgs[$idx] ?? 'bg-light';

        echo '<div class="list-group-item d-flex align-items-center gap-3 px-0">';
        echo '<div class="' . $rank_bg . ' ' . $rank_color . ' rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 28px; height: 28px; font-size: 12px; flex-shrink: 0;">' . ($idx + 1) . '</div>';
        echo '<a href="/post.php?id=' . (int)$post['id'] . '" class="text-decoration-none text-dark flex-grow-1 text-truncate" target="_blank">' . e($post['title']) . '</a>';
        echo '<span class="badge bg-info bg-opacity-10 text-info fs-6 py-1 px-2 flex-shrink-0">' . e((string)$post['comment_count']) . ' 评论</span>';
        echo '</div>';
    }
    echo '</div>';
}

/**
 * 渲染空状态提示
 */
function render_empty_state(string $title, string $desc): void
{
    echo '<div class="text-center py-5">';
    echo '<div class="text-muted mb-3">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" class="bi bi-bar-chart-line opacity-25" viewBox="0 0 16 16">';
    echo '<path d="M11 2a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h6zM5 1a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2H5z"/>';
    echo '<path fill-rule="evenodd" d="M0 14.5a.5.5 0 0 1 .5-.5h15a.5.5 0 0 1 0 1H.5a.5.5 0 0 1-.5-.5z"/>';
    echo '</svg>';
    echo '</div>';
    echo '<div class="fw-medium text-muted">' . e($title) . '</div>';
    echo '<div class="small text-muted mt-1">' . e($desc) . '</div>';
    echo '</div>';
}

/**
 * 格式化变化百分比文本
 */
function format_change_text(float $change): string
{
    if ($change > 0) {
        return '<span class="ms-2 text-success fw-medium">(↑' . e((string)abs($change)) . '%)</span>';
    }
    if ($change < 0) {
        return '<span class="ms-2 text-danger fw-medium">(↓' . e((string)abs($change)) . '%)</span>';
    }
    return '<span class="ms-2 text-muted fw-medium">(持平)</span>';
}

/**
 * 渲染最近管理行为时间线
 */
function render_admin_logs_list(array $logs): void
{
    echo '<div class="position-relative">';
    echo '<div class="position-absolute top-0 bottom-0" style="left: 15px; width: 2px; background-color: #e9ecef;"></div>';

    foreach ($logs as $log) {
        $actionLabel = admin_get_action_label((string)$log['action']);
        $badgeClass = admin_get_action_badge_class((string)$log['action']);
        $createTime = (string)$log['create_time'];
        $adminName = (string)$log['admin_username'];
        $detail = $log['detail'] !== null ? (string)$log['detail'] : '';
        $ip = (string)$log['ip'];

        $timeStr = '';
        $timestamp = strtotime($createTime);
        if ($timestamp !== false) {
            $now = time();
            $diff = $now - $timestamp;
            if ($diff < 60) {
                $timeStr = $diff . ' 秒前';
            } elseif ($diff < 3600) {
                $timeStr = (int)($diff / 60) . ' 分钟前';
            } elseif ($diff < 86400) {
                $timeStr = (int)($diff / 3600) . ' 小时前';
            } else {
                $timeStr = date('m-d H:i', $timestamp);
            }
        }

        echo '<div class="d-flex gap-3 mb-3 position-relative">';
        echo '<div class="bg-white border rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 z-1" style="width: 32px; height: 32px;">';
        echo '<span class="badge ' . $badgeClass . ' rounded-circle" style="width: 10px; height: 10px; display: inline-block;"></span>';
        echo '</div>';
        echo '<div class="flex-grow-1">';
        echo '<div class="d-flex flex-wrap align-items-center gap-2 mb-1">';
        echo '<span class="badge ' . $badgeClass . ' fs-6 py-1 px-2">' . e($actionLabel) . '</span>';
        echo '<span class="fw-medium">' . e($adminName) . '</span>';
        echo '<span class="text-muted small ms-auto">' . e($timeStr) . '</span>';
        echo '</div>';
        if ($detail !== '') {
            echo '<div class="text-muted small mb-1">' . e($detail) . '</div>';
        }
        echo '<div class="text-muted small">';
        echo '<span class="me-3">IP: ' . e($ip) . '</span>';
        echo '<span>时间: ' . e($createTime) . '</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    echo '</div>';
}

/**
 * 获取 Bootstrap Icons SVG 字符串
 */
function get_bi_icon(string $name, int $size = 20): string
{
    $icons = [
        'people-fill' => '<path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7Zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm-5.784 6A2.238 2.238 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.325 6.325 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1h4.216ZM4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/>',
        'file-text-fill' => '<path d="M12 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2zM5 4h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1 0-1zm-.5 2.5A.5.5 0 0 1 5 6h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1-.5-.5zM5 8h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1 0-1zm0 2h3a.5.5 0 0 1 0 1H5a.5.5 0 0 1 0-1z"/>',
        'chat-left-text-fill' => '<path d="M0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4.414a1 1 0 0 0-.707.293L.854 15.146A.5.5 0 0 1 0 14.793V2zm3.5 1a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1h-9zm0 2.5a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1h-9zm0 2.5a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1h-5z"/>',
        'shield-check' => '<path d="M5.338 1.59a61.44 61.44 0 0 0-2.837.856.48.48 0 0 0-.328.39c-.554 4.157.726 7.19 2.253 9.188a10.725 10.725 0 0 0 2.287 2.233c.346.244.652.42.893.533.12.057.218.095.293.118a.55.55 0 0 0 .199.03c.053 0 .11-.013.168-.03.075-.023.173-.06.293-.118.24-.113.547-.289.893-.533a10.7 10.7 0 0 0 2.287-2.233c1.527-1.997 2.807-5.031 2.253-9.188a.48.48 0 0 0-.328-.39c-.652-.27-1.47-.592-2.837-.856C9.687.443 8.446 0 8 0s-1.687.443-2.662 1.59z"/><path d="M10.97 5.97a.75.75 0 0 0-1.042-1.08L7.47 7.352 6.28 6.22a.75.75 0 0 0-1.06 1.06l1.75 1.75a.75.75 0 0 0 1.052-.028l3-3.5z"/>',
        'diagram-3' => '<path fill-rule="evenodd" d="M6 3.5A1.5 1.5 0 0 1 7.5 2h1A1.5 1.5 0 0 1 10 3.5v1A1.5 1.5 0 0 1 8.5 6v1H14a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-1 0V8h-5v.5a.5.5 0 0 1-1 0V8h-5v.5a.5.5 0 0 1-1 0v-1A.5.5 0 0 1 2 7h5.5V6A1.5 1.5 0 0 1 6 4.5v-1zM8.5 5a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1zM0 11.5A1.5 1.5 0 0 1 1.5 10h1A1.5 1.5 0 0 1 4 11.5v1A1.5 1.5 0 0 1 2.5 14h-1A1.5 1.5 0 0 1 0 12.5v-1zm1.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5h-1zm4.5.5A1.5 1.5 0 0 1 7.5 10h1a1.5 1.5 0 0 1 1.5 1.5v1A1.5 1.5 0 0 1 8.5 14h-1A1.5 1.5 0 0 1 6 12.5v-1zM7.5 11a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5h-1zm4.5.5a1.5 1.5 0 0 1 1.5-1.5h1a1.5 1.5 0 0 1 1.5 1.5v1a1.5 1.5 0 0 1-1.5 1.5h-1a1.5 1.5 0 0 1-1.5-1.5v-1zm1.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5h-1z"/>',
        'graph-up' => '<path fill-rule="evenodd" d="M0 0h1v15h15v1H0V0Zm14.817 3.113a.5.5 0 0 1 .07.704l-4.5 5.5a.5.5 0 0 1-.74.037L7.06 6.767l-3.656 5.027a.5.5 0 0 1-.808-.588l4-5.5a.5.5 0 0 1 .758-.06l2.609 2.61 4.15-5.073a.5.5 0 0 1 .704-.07Z"/>',
        'clock-history' => '<path d="M8.515 1.019A7 7 0 0 0 8 1V0a8 8 0 0 1 .589.022l-.074.997zm2.004.45a7.003 7.003 0 0 0-.985-.299l.219-.976c.383.086.76.2 1.126.342l-.36.933zm1.37.71a7.01 7.01 0 0 0-.439-.27l.493-.87a8.025 8.025 0 0 1 .979.654l-.615.789a6.996 6.996 0 0 0-.418-.302zm1.834 1.79a6.99 6.99 0 0 0-.653-.796l.724-.69c.27.285.52.59.747.91l-.818.576zm.744 1.352a7.08 7.08 0 0 0-.214-.468l.893-.45a7.976 7.976 0 0 1 .45 1.088l-.95.313a7.023 7.023 0 0 0-.179-.483zm.622 2.247a6.99 6.99 0 0 0-.1-1.025l.995-.1a8.014 8.014 0 0 1 .087 1.074l-.982.051zM14.988 9a6.967 6.967 0 0 0-.232-1.634l.98-.198a8.009 8.009 0 0 1 .198 1.832h-.946zm-.668 2.068a7.004 7.004 0 0 0 .379-1.068l.977.208a8.001 8.001 0 0 1-.452 1.27l-.904-.41zM13.42 12.99a6.97 6.97 0 0 0 .602-.91l.804.593a8.025 8.025 0 0 1-.743 1.115l-.663-.798zm-1.412 1.727a6.98 6.98 0 0 0 .789-.663l.678.735a8.007 8.007 0 0 1-.978.81l-.489-.882zM10.431 14.97a7.01 7.01 0 0 0 .944-.29l.42.907a8.008 8.008 0 0 1-1.125.346l-.239-.963zM9.05 15.667A7.004 7.004 0 0 0 10 15.5v1a8.02 8.02 0 0 1-1 .031v-.998l.05.034z"/><path d="M8 13.5a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11zM8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1z"/><path d="M7.5 3a.5.5 0 0 1 .5.5v4.25l2.5 1.5a.5.5 0 0 1-.5.866L7.5 8.536V3.5a.5.5 0 0 1 .5-.5z"/>',
        'trophy' => '<path d="M2.5.5A.5.5 0 0 1 3 0h10a.5.5 0 0 1 .5.5q0 .807-.034 1.536a3 3 0 1 1-3.133 3.268 5 5 0 0 1-1.337.76V8.5h1a.5.5 0 0 1 0 1h-5a.5.5 0 0 1 0-1h1v-2.436a5 5 0 0 1-1.337-.76A3 3 0 1 1 2.034 2.036.5.5 0 0 1 2.5.5zM3 3a2 2 0 1 0 4 0 2 2 0 0 0-4 0zm6 0a2 2 0 1 0 4 0 2 2 0 0 0-4 0z"/><path d="M3.02 6.758a5 5 0 0 0 2.046 1.343c.304.533.708 1.001 1.184 1.387V13h-1.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h5a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5H9v-3.512c.476-.386.88-.854 1.184-1.387a5 5 0 0 0 2.046-1.343C12.66 6.398 13 5.68 13 5V2.08l.117-.21q.043-.372.07-.77H14v3.9q0 .85-.382 1.618a3.2 3.2 0 0 1-1.087 1.282 5.2 5.2 0 0 1-1.623.797c-.19.602-.522 1.156-.98 1.636-.457.48-.977.855-1.528 1.111V14H7.5v-1.512c-.551-.256-1.071-.631-1.528-1.11a5.5 5.5 0 0 1-.98-1.637 5.2 5.2 0 0 1-1.623-.797A3.2 3.2 0 0 1 2.382 7.9 3.1 3.1 0 0 1 2 6.28V2.08h.117L3 2.08V5c0 .68.34 1.398.02 1.758z"/>',
        'journal-text' => '<path d="M5 10.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5zm0-3a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5zm0-3a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5z"/><path fill-rule="evenodd" d="M1 1v14a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H2a1 1 0 0 0-1 1zm2-1a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H3z"/>',
    ];

    $path = $icons[$name] ?? '';
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" fill="currentColor" class="bi bi-' . $name . '" viewBox="0 0 16 16">' . $path . '</svg>';
}
