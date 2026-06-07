<?php
declare(strict_types=1);

/*
 * 后台统计数据辅助函数：
 * - 各类统计数据的查询与计算
 * - 供后台概览页面调用
 */

/**
 * 获取基础总量统计（用户、帖子、评论总数及今日新增）
 */
function get_basic_stats(PDO $pdo): array
{
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

    return $stats;
}

/**
 * 获取内容质量统计（有效 vs 已隐藏）
 */
function get_content_quality_stats(PDO $pdo, array $basicStats): array
{
    $posts_total = (int)$pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn();
    $posts_hidden = $posts_total - $basicStats['posts'];
    $posts_hidden_rate = $posts_total > 0 ? round(($posts_hidden / $posts_total) * 100, 1) : 0;

    $comments_total = (int)$pdo->query('SELECT COUNT(*) FROM comments')->fetchColumn();
    $comments_hidden = $comments_total - $basicStats['comments'];
    $comments_hidden_rate = $comments_total > 0 ? round(($comments_hidden / $comments_total) * 100, 1) : 0;

    return [
        'posts_total' => $posts_total,
        'posts_valid' => $basicStats['posts'],
        'posts_hidden' => $posts_hidden,
        'posts_hidden_rate' => $posts_hidden_rate,
        'posts_valid_rate' => 100 - $posts_hidden_rate,
        'comments_total' => $comments_total,
        'comments_valid' => $basicStats['comments'],
        'comments_hidden' => $comments_hidden,
        'comments_hidden_rate' => $comments_hidden_rate,
        'comments_valid_rate' => 100 - $comments_hidden_rate,
    ];
}

/**
 * 获取近N天新增走势数据
 * 返回包含日期标签、帖子数、评论数、环比变化的完整数据
 */
function get_trend_stats(PDO $pdo, int $days = 7): array
{
    $posts_trend = [];
    $comments_trend = [];
    $dates = [];
    $date_full = [];

    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i day"));
        $date_label = date('m/d', strtotime("-$i day"));
        $dates[] = $date_label;
        $date_full[] = $date;

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE status = 1 AND DATE(create_time) = ?');
        $stmt->execute([$date]);
        $posts_trend[] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM comments WHERE status = 1 AND DATE(create_time) = ?');
        $stmt->execute([$date]);
        $comments_trend[] = (int)$stmt->fetchColumn();
    }

    $posts_yesterday = $days >= 2 ? ($posts_trend[$days - 2] ?? 0) : 0;
    $posts_today_val = $posts_trend[$days - 1] ?? 0;
    if ($posts_yesterday > 0) {
        $posts_change = round((($posts_today_val - $posts_yesterday) / $posts_yesterday) * 100, 1);
    } elseif ($posts_today_val > 0) {
        $posts_change = 100.0;
    } else {
        $posts_change = 0.0;
    }

    $comments_yesterday = $days >= 2 ? ($comments_trend[$days - 2] ?? 0) : 0;
    $comments_today_val = $comments_trend[$days - 1] ?? 0;
    if ($comments_yesterday > 0) {
        $comments_change = round((($comments_today_val - $comments_yesterday) / $comments_yesterday) * 100, 1);
    } elseif ($comments_today_val > 0) {
        $comments_change = 100.0;
    } else {
        $comments_change = 0.0;
    }

    $posts_max = max($posts_trend) ?: 1;
    $comments_max = max($comments_trend) ?: 1;
    $overall_max = max($posts_max, $comments_max);

    return [
        'days' => $days,
        'dates' => $dates,
        'date_full' => $date_full,
        'posts' => $posts_trend,
        'comments' => $comments_trend,
        'posts_yesterday' => $posts_yesterday,
        'posts_today' => $posts_today_val,
        'posts_change' => $posts_change,
        'comments_yesterday' => $comments_yesterday,
        'comments_today' => $comments_today_val,
        'comments_change' => $comments_change,
        'posts_max' => $posts_max,
        'comments_max' => $comments_max,
        'overall_max' => $overall_max,
        'has_data' => array_sum($posts_trend) + array_sum($comments_trend) > 0,
    ];
}

/**
 * 获取评论活跃时间窗口统计（按小时分布）
 */
function get_hourly_stats(PDO $pdo, int $days = 30): array
{
    $hour_stats = array_fill(0, 24, 0);

    $stmt = $pdo->prepare(
        'SELECT HOUR(create_time) as h, COUNT(*) as cnt FROM comments ' .
        'WHERE status = 1 AND create_time >= DATE_SUB(NOW(), INTERVAL ? DAY) ' .
        'GROUP BY HOUR(create_time) ORDER BY h'
    );
    $stmt->execute([$days]);
    while ($row = $stmt->fetch()) {
        $hour_stats[(int)$row['h']] = (int)$row['cnt'];
    }

    $total_comments = array_sum($hour_stats);
    $max_hour_count = max($hour_stats) ?: 1;

    $hour_ranking = [];
    foreach ($hour_stats as $h => $cnt) {
        $hour_ranking[] = ['hour' => $h, 'count' => $cnt];
    }
    usort($hour_ranking, fn($a, $b) => $b['count'] <=> $a['count']);

    $top_hours = array_values(array_filter($hour_ranking, fn($item) => $item['count'] > 0));
    $top_hours = array_slice($top_hours, 0, 3);

    $peak_hours_text = '';
    if (count($top_hours) > 0) {
        $peak_labels = [];
        foreach ($top_hours as $th) {
            $peak_labels[] = sprintf('%02d:00', $th['hour']);
        }
        $peak_hours_text = implode('、', $peak_labels);
    }

    return [
        'hours' => $hour_stats,
        'max_count' => $max_hour_count,
        'total' => $total_comments,
        'top_hours' => $top_hours,
        'peak_hours_text' => $peak_hours_text,
        'has_data' => $total_comments > 0,
        'days_range' => $days,
    ];
}

/**
 * 获取帖子与评论结构关系统计
 */
function get_structure_stats(PDO $pdo, array $basicStats): array
{
    $avg_comments_per_post = $basicStats['posts'] > 0
        ? round($basicStats['comments'] / $basicStats['posts'], 1)
        : 0;

    $posts_without_comments = (int)$pdo->query(
        'SELECT COUNT(*) FROM posts WHERE status = 1 AND id NOT IN (SELECT DISTINCT post_id FROM comments WHERE status = 1)'
    )->fetchColumn();

    $posts_no_comment_rate = $basicStats['posts'] > 0
        ? round(($posts_without_comments / $basicStats['posts']) * 100, 1)
        : 0;

    $active_posts_30d = (int)$pdo->query(
        'SELECT COUNT(DISTINCT p.id) FROM posts p ' .
        'INNER JOIN comments c ON p.id = c.post_id ' .
        'WHERE p.status = 1 AND c.status = 1 AND c.create_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
    )->fetchColumn();

    return [
        'avg_comments_per_post' => $avg_comments_per_post,
        'posts_without_comments' => $posts_without_comments,
        'posts_no_comment_rate' => $posts_no_comment_rate,
        'active_posts_30d' => $active_posts_30d,
        'total_posts' => $basicStats['posts'],
    ];
}

/**
 * 获取评论最多的 Top N 帖子
 */
function get_top_comment_posts(PDO $pdo, int $limit = 5): array
{
    $stmt = $pdo->prepare(
        'SELECT p.id, p.title, COUNT(c.id) as comment_count ' .
        'FROM posts p LEFT JOIN comments c ON p.id = c.post_id AND c.status = 1 ' .
        'WHERE p.status = 1 ' .
        'GROUP BY p.id, p.title ORDER BY comment_count DESC LIMIT ?'
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}
