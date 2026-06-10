<?php
declare(strict_types=1);

/*
 * 帖子列表页：
 * - 分页展示帖子
 * - 显示作者、时间、评论数
 * - 快捷检索面板：标题关键词、作者名、评论热度、发布时间
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = null;
try {
    $pdo = db($config);
} catch (Throwable $e) {
    render_header($config, ['title' => '系统错误 - Lite Forum', 'active' => 'home']);
    echo '<div class="card card-lite p-4">';
    echo '<div class="fw-semibold mb-1">数据库连接失败</div>';
    echo '<div class="text-muted">请检查数据库配置或稍后重试。</div>';
    echo '</div>';
    render_footer();
    exit;
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$pageSize = 10;

$keyword = isset($_GET['keyword']) ? trim((string)$_GET['keyword']) : '';
$searchIn = isset($_GET['search_in']) ? trim((string)$_GET['search_in']) : 'all';
if (!in_array($searchIn, ['all', 'title', 'content'], true)) {
    $searchIn = 'all';
}
$author = isset($_GET['author']) ? trim((string)$_GET['author']) : '';
$commentMin = isset($_GET['comment_min']) ? (int)$_GET['comment_min'] : 0;
$commentMax = isset($_GET['comment_max']) ? (int)$_GET['comment_max'] : 0;
$dateFrom = isset($_GET['date_from']) ? trim((string)$_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim((string)$_GET['date_to']) : '';
$sort = isset($_GET['sort']) ? trim((string)$_GET['sort']) : 'time_desc';

$hasFilter = $keyword !== '' || $author !== '' || $commentMin > 0 || $commentMax > 0 || $dateFrom !== '' || $dateTo !== '';

$whereConditions = ['p.status = 1'];
$params = [];

if ($keyword !== '') {
    $searchConditions = [];
    if ($searchIn === 'all' || $searchIn === 'title') {
        $searchConditions[] = 'p.title LIKE :keyword_title';
        $params[':keyword_title'] = '%' . $keyword . '%';
    }
    if ($searchIn === 'all' || $searchIn === 'content') {
        $searchConditions[] = 'p.content LIKE :keyword_content';
        $params[':keyword_content'] = '%' . $keyword . '%';
    }
    if ($searchConditions) {
        $whereConditions[] = '(' . implode(' OR ', $searchConditions) . ')';
    }
}

if ($author !== '') {
    $whereConditions[] = 'u.username LIKE :author';
    $params[':author'] = '%' . $author . '%';
}

if ($dateFrom !== '') {
    $whereConditions[] = 'p.create_time >= :date_from';
    $params[':date_from'] = $dateFrom . ' 00:00:00';
}

if ($dateTo !== '') {
    $whereConditions[] = 'p.create_time <= :date_to';
    $params[':date_to'] = $dateTo . ' 23:59:59';
}

$havingConditions = [];
if ($commentMin > 0) {
    $havingConditions[] = 'comment_count >= :comment_min';
    $params[':comment_min'] = $commentMin;
}
if ($commentMax > 0) {
    $havingConditions[] = 'comment_count <= :comment_max';
    $params[':comment_max'] = $commentMax;
}

$whereSql = implode(' AND ', $whereConditions);
$havingSql = $havingConditions ? ' HAVING ' . implode(' AND ', $havingConditions) : '';

$orderSql = match($sort) {
    'time_asc' => 'ORDER BY p.create_time ASC',
    'comment_desc' => 'ORDER BY comment_count DESC',
    'comment_asc' => 'ORDER BY comment_count ASC',
    'relevance' => $keyword !== '' ? 'ORDER BY search_score DESC, p.create_time DESC' : 'ORDER BY p.create_time DESC',
    default => 'ORDER BY p.create_time DESC',
};

$totalSql = 'SELECT COUNT(*) FROM (
    SELECT p.id
    FROM posts p
    JOIN users u ON u.id = p.user_id
    LEFT JOIN (
        SELECT post_id, COUNT(*) AS cnt
        FROM comments
        WHERE status = 1
        GROUP BY post_id
    ) c ON c.post_id = p.id
    WHERE ' . $whereSql . '
    GROUP BY p.id' . $havingSql . '
) AS filtered';

$totalStmt = $pdo->prepare($totalSql);
foreach ($params as $key => $value) {
    $totalStmt->bindValue($key, $value);
}
$totalStmt->execute();
$total = (int)$totalStmt->fetchColumn();

$pg = paginate($total, $page, $pageSize);

$scoreFields = '';
if ($keyword !== '') {
    $scoreParts = [];
    if ($searchIn === 'all' || $searchIn === 'title') {
        $scoreParts[] = 'CASE WHEN p.title LIKE :keyword_title THEN 1 ELSE 0 END * 10';
    }
    if ($searchIn === 'all' || $searchIn === 'content') {
        $scoreParts[] = 'CASE WHEN p.content LIKE :keyword_content THEN 1 ELSE 0 END * 3';
    }
    if ($scoreParts) {
        $scoreFields = ', (' . implode(' + ', $scoreParts) . ') AS search_score';
    }
}

$listSql = 'SELECT p.id, p.title, p.content, p.create_time, p.update_time, u.username,
            COALESCE(c.cnt, 0) AS comment_count' . $scoreFields . '
     FROM posts p
     JOIN users u ON u.id = p.user_id
     LEFT JOIN (
         SELECT post_id, COUNT(*) AS cnt
         FROM comments
         WHERE status = 1
         GROUP BY post_id
     ) c ON c.post_id = p.id
     WHERE ' . $whereSql . '
     GROUP BY p.id' . $havingSql . '
     ' . $orderSql . '
     LIMIT :limit OFFSET :offset';

$stmt = $pdo->prepare($listSql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $pg['pageSize'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $pg['offset'], PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll();

$hotPosts = get_hot_posts($pdo, 8);

$currentUser = user();
$recentReadPosts = [];
if ($currentUser !== null) {
    $recentReadPosts = get_recent_read_posts($pdo, (int)$currentUser['id'], 6);
}
$favoritedMap = [];
if ($currentUser !== null && $posts) {
    $postIds = array_column($posts, 'id');
    if ($postIds) {
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $stmt = $pdo->prepare('SELECT post_id FROM favorites WHERE user_id = ? AND post_id IN (' . $placeholders . ')');
        $stmt->execute(array_merge([(int)$currentUser['id']], $postIds));
        $favoritedRows = $stmt->fetchAll();
        foreach ($favoritedRows as $fr) {
            $favoritedMap[(int)$fr['post_id']] = true;
        }
    }
}

function build_query_string(array $overrides = []): string
{
    $params = $_GET;
    if (isset($params['page'])) {
        unset($params['page']);
    }
    foreach ($overrides as $key => $value) {
        if ($value === '' || $value === null || $value === '0') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    if (empty($params)) {
        return '';
    }
    return '?' . http_build_query($params);
}

function highlight_keyword(string $text, string $keyword): string
{
    if ($keyword === '') {
        return e($text);
    }
    $safeText = e($text);
    $safeKeyword = preg_quote(e($keyword), '/');
    $result = preg_replace('/(' . $safeKeyword . ')/iu', '<mark class="search-highlight">$1</mark>', $safeText);
    return $result !== null ? $result : $safeText;
}

function get_context_excerpt(string $content, string $keyword, int $length = 120): string
{
    $plainText = strip_tags(sanitize_rich_html($content));
    if ($keyword === '') {
        if (function_exists('mb_substr')) {
            $excerpt = mb_substr($plainText, 0, $length);
        } else {
            $excerpt = substr($plainText, 0, $length);
        }
        if (strlen($plainText) > strlen($excerpt)) {
            $excerpt .= '...';
        }
        return $excerpt;
    }

    $keywordLower = mb_strtolower($keyword);
    $textLower = mb_strtolower($plainText);
    $pos = mb_strpos($textLower, $keywordLower);

    if ($pos === false) {
        if (function_exists('mb_substr')) {
            $excerpt = mb_substr($plainText, 0, $length);
        } else {
            $excerpt = substr($plainText, 0, $length);
        }
        if (strlen($plainText) > strlen($excerpt)) {
            $excerpt .= '...';
        }
        return $excerpt;
    }

    $keywordLen = mb_strlen($keyword);
    $halfLength = (int)floor(($length - $keywordLen) / 2);
    $start = max(0, $pos - $halfLength);
    $end = min(mb_strlen($plainText), $pos + $keywordLen + $halfLength);

    if (function_exists('mb_substr')) {
        $excerpt = mb_substr($plainText, $start, $end - $start);
    } else {
        $excerpt = substr($plainText, $start, $end - $start);
    }

    $prefix = $start > 0 ? '...' : '';
    $suffix = $end < mb_strlen($plainText) ? '...' : '';

    return $prefix . $excerpt . $suffix;
}

render_header($config, ['title' => '帖子列表 - Lite Forum', 'active' => 'home']);

echo '<style>';
echo '.filter-card{border:0;border-radius:.5rem;box-shadow:0 .125rem .25rem rgba(0,0,0,.075);background:#fff;}';
echo '.filter-label{font-weight:500;color:#495057;font-size:.875rem;margin-bottom:.25rem;}';
echo '.filter-badge{display:inline-flex;align-items:center;gap:.35rem;padding:.35rem .65rem;font-size:.8rem;border-radius:.375rem;background:#eef2ff;color:#4338ca;}';
echo '.filter-badge .remove-btn{cursor:pointer;opacity:.6;}';
echo '.filter-badge .remove-btn:hover{opacity:1;}';
echo '.filter-toggle-btn{display:none;}';
echo '.hot-rank-card{border:0;border-radius:.5rem;box-shadow:0 .125rem .25rem rgba(0,0,0,.075);background:#fff;}';
echo '.hot-rank-icon{font-size:1.1rem;}';
echo '.hot-rank-list{list-style:none;padding:0;margin:0;}';
echo '.hot-rank-item{display:flex;gap:.75rem;padding:.65rem 0;border-bottom:1px solid #f1f3f5;}';
echo '.hot-rank-item:last-child{border-bottom:0;}';
echo '.hot-rank-num{display:inline-flex;align-items:center;justify-content:center;min-width:1.5rem;height:1.5rem;font-size:.8rem;font-weight:700;color:#868e96;background:#f1f3f5;border-radius:.25rem;flex-shrink:0;}';
echo '.hot-rank-top .hot-rank-num{background:linear-gradient(135deg,#ff6b6b,#ff8787);color:#fff;}';
echo '.hot-rank-content{flex-grow:1;min-width:0;}';
echo '.hot-rank-title{display:block;color:#212529;font-size:.9rem;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;transition:color .2s;}';
echo '.hot-rank-title:hover{color:#228be6;}';
echo '.hot-rank-meta{margin-top:.35rem;display:flex;align-items:center;}';
echo '.hot-comment-badge{font-size:.75rem;color:#495057;background:#e9ecef;padding:.15rem .5rem;border-radius:1rem;}';
echo '.search-highlight{background:#fff3cd;color:#856404;padding:0 2px;border-radius:2px;font-weight:500;}';
echo '.search-match-badge{display:inline-flex;align-items:center;gap:.25rem;padding:.2rem .5rem;font-size:.7rem;border-radius:.25rem;}';
echo '.search-match-badge.title-match{background:#e7f5ff;color:#1971c2;}';
echo '.search-match-badge.content-match{background:#f3f0ff;color:#7048e8;}';
echo '@media (max-width: 768px){';
echo '  .filter-toggle-btn{display:inline-flex;align-items:center;gap:.35rem;}';
echo '  .filter-panel{display:none;}';
echo '  .filter-panel.show{display:block;}';
echo '  .filter-row{flex-direction:column;gap:.75rem;}';
echo '}';
echo '</style>';

echo '<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">';
echo '<div>';
echo '<h1 class="h4 mb-0">帖子</h1>';
echo '<div class="text-muted small mt-1">共 ' . e((string)$total) . ' 篇';
if ($hasFilter) {
    echo ' · 筛选中';
}
echo '</div>';
echo '</div>';
echo '<div class="d-flex gap-2">';
echo '<button class="btn btn-outline-secondary filter-toggle-btn" type="button" data-bs-toggle="collapse" data-bs-target="#filterPanel" aria-expanded="false" aria-controls="filterPanel">';
echo '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M6 10.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3a.5.5 0 0 1-.5-.5zm-2-3a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5zm-2-3a.5.5 0 0 1 .5-.5h11a.5.5 0 0 1 0 1h-11a.5.5 0 0 1-.5-.5z"/></svg>';
echo '筛选';
if ($hasFilter) {
    echo '<span class="badge bg-primary rounded-pill" style="font-size:.65rem;">' . (
        ($keyword !== '' ? 1 : 0) +
        ($author !== '' ? 1 : 0) +
        ($commentMin > 0 || $commentMax > 0 ? 1 : 0) +
        ($dateFrom !== '' || $dateTo !== '' ? 1 : 0)
    ) . '</span>';
}
echo '</button>';
if (user() !== null) {
    echo '<a class="btn btn-primary" href="/post_add.php">发布帖子</a>';
} else {
    echo '<a class="btn btn-outline-secondary" href="/login.php">登录后发帖</a>';
}
echo '</div>';
echo '</div>';

echo '<div class="card filter-card mb-3 filter-panel show" id="filterPanel">';
echo '<div class="card-body p-3">';
echo '<form method="get" action="/index.php" id="filterForm">';

echo '<div class="row g-3 filter-row">';

echo '<div class="col-md-4 col-12">';
echo '<label class="filter-label" for="keyword">关键词搜索</label>';
echo '<div class="input-group">';
echo '<span class="input-group-text" style="background:#f8f9fa;">';
echo '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/></svg>';
echo '</span>';
echo '<input type="text" class="form-control" id="keyword" name="keyword" placeholder="输入关键词搜索帖子..." value="' . e($keyword) . '">';
echo '</div>';
echo '<div class="d-flex gap-2 mt-2">';
$searchInOptions = [
    'all' => '标题+正文',
    'title' => '仅标题',
    'content' => '仅正文',
];
foreach ($searchInOptions as $val => $label) {
    $checked = $searchIn === $val ? ' checked' : '';
    echo '<div class="form-check form-check-inline mb-0">';
    echo '<input class="form-check-input" type="radio" name="search_in" id="search_in_' . e($val) . '" value="' . e($val) . '"' . $checked . '>';
    echo '<label class="form-check-label small" for="search_in_' . e($val) . '">' . e($label) . '</label>';
    echo '</div>';
}
echo '</div>';
echo '</div>';

echo '<div class="col-md-2 col-12">';
echo '<label class="filter-label" for="author">作者名</label>';
echo '<input type="text" class="form-control" id="author" name="author" placeholder="作者名" value="' . e($author) . '">';
echo '</div>';

echo '<div class="col-md-2 col-6">';
echo '<label class="filter-label" for="comment_min">评论数 ≥</label>';
echo '<input type="number" class="form-control" id="comment_min" name="comment_min" min="0" placeholder="最低" value="' . ($commentMin > 0 ? e((string)$commentMin) : '') . '">';
echo '</div>';

echo '<div class="col-md-2 col-6">';
echo '<label class="filter-label" for="comment_max">评论数 ≤</label>';
echo '<input type="number" class="form-control" id="comment_max" name="comment_max" min="0" placeholder="最高" value="' . ($commentMax > 0 ? e((string)$commentMax) : '') . '">';
echo '</div>';

echo '<div class="col-md-2 col-12">';
echo '<label class="filter-label" for="sort">排序方式</label>';
echo '<select class="form-select" id="sort" name="sort">';
$sortOptions = [
    'time_desc' => '最新发布',
    'time_asc' => '最早发布',
    'comment_desc' => '评论最多',
    'comment_asc' => '评论最少',
];
if ($keyword !== '') {
    $sortOptions = ['relevance' => '相关度优先'] + $sortOptions;
}
foreach ($sortOptions as $val => $label) {
    $selected = $sort === $val ? ' selected' : '';
    echo '<option value="' . e($val) . '"' . $selected . '>' . e($label) . '</option>';
}
echo '</select>';
echo '</div>';

echo '</div>';

echo '<div class="row g-3 mt-2 filter-row">';
echo '<div class="col-md-4 col-12">';
echo '<label class="filter-label" for="date_from">发布日期 从</label>';
echo '<input type="date" class="form-control" id="date_from" name="date_from" value="' . e($dateFrom) . '">';
echo '</div>';
echo '<div class="col-md-4 col-12">';
echo '<label class="filter-label" for="date_to">发布日期 至</label>';
echo '<input type="date" class="form-control" id="date_to" name="date_to" value="' . e($dateTo) . '">';
echo '</div>';
echo '<div class="col-md-4 col-12 d-flex align-items-end gap-2">';
echo '<button type="submit" class="btn btn-primary flex-grow-1">应用筛选</button>';
echo '<button type="button" class="btn btn-outline-secondary" onclick="resetFilters()">重置</button>';
echo '</div>';
echo '</div>';

echo '</form>';
echo '</div>';
echo '</div>';

if ($hasFilter) {
    echo '<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">';
    echo '<span class="text-muted small">当前筛选：</span>';

    if ($keyword !== '') {
        echo '<span class="filter-badge">';
        $searchInLabels = ['all' => '全文', 'title' => '标题', 'content' => '正文'];
        echo '搜索：' . e($keyword) . ' (' . ($searchInLabels[$searchIn] ?? '全文') . ')';
        echo '<span class="remove-btn" onclick="removeFilter(\'keyword\');removeFilter(\'search_in\')" title="移除">×</span>';
        echo '</span>';
    }
    if ($author !== '') {
        echo '<span class="filter-badge">';
        echo '作者：' . e($author);
        echo '<span class="remove-btn" onclick="removeFilter(\'author\')" title="移除">×</span>';
        echo '</span>';
    }
    if ($commentMin > 0 || $commentMax > 0) {
        $cmtLabel = '评论：';
        if ($commentMin > 0 && $commentMax > 0) {
            $cmtLabel .= $commentMin . '-' . $commentMax;
        } elseif ($commentMin > 0) {
            $cmtLabel .= '≥' . $commentMin;
        } else {
            $cmtLabel .= '≤' . $commentMax;
        }
        echo '<span class="filter-badge">';
        echo e($cmtLabel);
        echo '<span class="remove-btn" onclick="removeFilter(\'comment_min\');removeFilter(\'comment_max\')" title="移除">×</span>';
        echo '</span>';
    }
    if ($dateFrom !== '' || $dateTo !== '') {
        $dateLabel = '时间：';
        if ($dateFrom !== '' && $dateTo !== '') {
            $dateLabel .= $dateFrom . ' 至 ' . $dateTo;
        } elseif ($dateFrom !== '') {
            $dateLabel .= '从 ' . $dateFrom;
        } else {
            $dateLabel .= '至 ' . $dateTo;
        }
        echo '<span class="filter-badge">';
        echo e($dateLabel);
        echo '<span class="remove-btn" onclick="removeFilter(\'date_from\');removeFilter(\'date_to\')" title="移除">×</span>';
        echo '</span>';
    }

    echo '<a href="/index.php" class="btn btn-sm btn-link text-decoration-none p-0 ms-auto">一键清除全部</a>';
    echo '</div>';
}

echo '<div class="row g-4">';
echo '<div class="col-lg-8 col-md-12">';

if (!$posts) {
    echo '<div class="card card-lite p-5 text-center">';
    if ($hasFilter) {
        echo '<div style="font-size:3rem;margin-bottom:1rem;">🔍</div>';
        echo '<div class="h5 mb-2">没有找到匹配的帖子</div>';
        echo '<div class="text-muted mb-3">试试调整筛选条件，或清除筛选查看全部</div>';
        echo '<a class="btn btn-primary" href="/index.php">清除筛选</a>';
    } else {
        echo '<div style="font-size:3rem;margin-bottom:1rem;">📝</div>';
        echo '<div class="h5 mb-2">暂无帖子</div>';
        echo '<div class="text-muted">欢迎先注册/登录发布第一篇。</div>';
    }
    echo '</div>';
} else {
    foreach ($posts as $post) {
        $excerpt = get_context_excerpt((string)$post['content'], $keyword, 140);
        $titleDisplay = highlight_keyword((string)$post['title'], $keyword);
        $excerptDisplay = highlight_keyword($excerpt, $keyword);

        $titleMatch = false;
        $contentMatch = false;
        if ($keyword !== '') {
            $keywordLower = mb_strtolower($keyword);
            $titleLower = mb_strtolower((string)$post['title']);
            $contentLower = mb_strtolower(strip_tags(sanitize_rich_html((string)$post['content'])));
            $titleMatch = mb_strpos($titleLower, $keywordLower) !== false;
            $contentMatch = mb_strpos($contentLower, $keywordLower) !== false;
        }

        $postId = (int)$post['id'];
        $isFavorited = isset($favoritedMap[$postId]);
        $favIcon = $isFavorited ? '★' : '☆';
        $favClass = $isFavorited ? 'btn-favorite active' : 'btn-favorite';
        $favTitle = $isFavorited ? '取消收藏' : '收藏';

        echo '<div class="card card-lite mb-3">';
        echo '<div class="card-body">';
        echo '<div class="d-flex justify-content-between gap-3">';
        echo '<div class="flex-grow-1">';
        echo '<div class="d-flex align-items-center gap-2 mb-1">';
        if ($keyword !== '') {
            if ($titleMatch) {
                echo '<span class="search-match-badge title-match">标题命中</span>';
            }
            if ($contentMatch) {
                echo '<span class="search-match-badge content-match">正文命中</span>';
            }
        }
        echo '</div>';
        echo '<a class="h5 text-decoration-none" href="/post.php?id=' . e((string)$post['id']) . '">' . $titleDisplay . '</a>';
        echo '<div class="text-muted small mt-2">' . $excerptDisplay . '</div>';
        echo '<div class="text-muted small mt-3">';
                echo '<span class="me-2">作者：' . e((string)$post['username']) . '</span>';
                echo '<span class="me-2">时间：' . e((string)$post['create_time']) . '</span>';
                if (!empty($post['update_time'])) {
                    echo '<span class="me-2">更新：' . e((string)$post['update_time']) . '</span>';
                }
                echo '<span>评论：' . e((string)$post['comment_count']) . '</span>';
                echo '</div>';
        echo '</div>';
        echo '<div class="d-flex flex-column gap-2 text-end">';
        if ($currentUser !== null) {
            echo '<form method="post" action="/favorite_toggle.php" class="favorite-form" data-post-id="' . $postId . '">';
            echo '<input type="hidden" name="post_id" value="' . $postId . '">';
            echo '<button type="submit" class="btn btn-sm ' . $favClass . '" title="' . e($favTitle) . '">';
            echo '<span class="favorite-icon">' . $favIcon . '</span>';
            echo '<span class="favorite-text ms-1">' . e($favTitle) . '</span>';
            echo '</button>';
            echo '</form>';
        }
        echo '<a class="btn btn-sm btn-outline-secondary" href="/post.php?id=' . e((string)$post['id']) . '">查看</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    if ($pg['pages'] > 1) {
        echo '<nav aria-label="Page navigation">';
        echo '<ul class="pagination justify-content-center">';
        for ($i = 1; $i <= $pg['pages']; $i++) {
            $active = $i === $pg['page'] ? ' active' : '';
            $qs = build_query_string(['page' => $i]);
            echo '<li class="page-item' . $active . '"><a class="page-link" href="/index.php' . e($qs ? $qs : '?page=' . $i) . '">' . e((string)$i) . '</a></li>';
        }
        echo '</ul></nav>';
    }
}

echo '</div>';
echo '<div class="col-lg-4 col-md-12">';

if ($currentUser !== null && $recentReadPosts) {
    echo '<div class="card card-lite mb-3">';
    echo '<div class="card-body">';
    echo '<div class="d-flex align-items-center justify-content-between mb-3">';
    echo '<div class="d-flex align-items-center gap-2">';
    echo '<span style="font-size:1.1rem;">📖</span>';
    echo '<h2 class="h6 mb-0 fw-bold">最近浏览</h2>';
    echo '</div>';
    echo '<a href="/history.php" class="text-decoration-none small">查看全部</a>';
    echo '</div>';
    echo '<div class="list-group list-group-flush">';
    foreach ($recentReadPosts as $rp) {
        echo '<a href="/post.php?id=' . e((string)$rp['id']) . '" class="list-group-item list-group-item-action px-0 py-2" style="border:0;">';
        echo '<div class="d-flex w-100 justify-content-between">';
        echo '<h6 class="mb-1 small fw-medium" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;line-height:1.4;">' . e((string)$rp['title']) . '</h6>';
        echo '</div>';
        echo '<div class="d-flex align-items-center gap-2 text-muted small">';
        echo '<span>' . e((string)$rp['username']) . '</span>';
        echo '<span>·</span>';
        echo '<span>' . e((string)$rp['comment_count']) . ' 评论</span>';
        echo '</div>';
        echo '</a>';
    }
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

echo '<div class="card card-lite hot-rank-card">';
echo '<div class="card-body">';
echo '<div class="d-flex align-items-center gap-2 mb-3">';
echo '<span class="hot-rank-icon">🔥</span>';
echo '<h2 class="h6 mb-0 fw-bold">热门帖子榜</h2>';
echo '</div>';

if (!$hotPosts) {
    echo '<div class="text-muted small text-center py-3">暂无热门帖子</div>';
} else {
    echo '<ol class="hot-rank-list">';
    foreach ($hotPosts as $index => $hp) {
        $rankClass = $index < 3 ? ' hot-rank-top' : '';
        echo '<li class="hot-rank-item' . $rankClass . '">';
        echo '<span class="hot-rank-num">' . ($index + 1) . '</span>';
        echo '<div class="hot-rank-content">';
        echo '<a class="hot-rank-title text-decoration-none" href="/post.php?id=' . e((string)$hp['id']) . '">' . e((string)$hp['title']) . '</a>';
        echo '<div class="hot-rank-meta text-muted small">';
        echo '<span class="me-2">' . e((string)$hp['username']) . '</span>';
        echo '<span class="hot-comment-badge">' . e((string)$hp['comment_count']) . ' 评论</span>';
        echo '</div>';
        echo '</div>';
        echo '</li>';
    }
    echo '</ol>';
}

echo '</div>';
echo '</div>';

echo '</div>';
echo '</div>';

echo '<style>';
echo '.btn-favorite{color:#ffc107;border-color:#ffc107;background:transparent;transition:all .2s;}';
echo '.btn-favorite:hover{background:#fff3cd;border-color:#ffc107;color:#ffc107;}';
echo '.btn-favorite.active{background:#ffc107;border-color:#ffc107;color:#fff;}';
echo '.btn-favorite.active:hover{background:#ffb300;border-color:#ffb300;color:#fff;}';
echo '.favorite-icon{font-size:1rem;line-height:1;}';
echo '</style>';

echo '<script>';
echo 'function removeFilter(name) {';
echo '  const params = new URLSearchParams(window.location.search);';
echo '  params.delete(name);';
echo '  params.delete("page");';
echo '  const query = params.toString();';
echo '  window.location.href = "/index.php" + (query ? "?" + query : "");';
echo '}';
echo 'function resetFilters() { window.location.href = "/index.php"; }';

echo '(function() {';
echo '  function showToast(msg) {';
echo '    var toast = document.createElement("div");';
echo '    toast.style.cssText = "position:fixed;top:50%;left:50%;transform:translate(-50%,-50%) scale(.9);background:rgba(0,0,0,.8);color:#fff;padding:12px 24px;border-radius:8px;font-size:.9rem;z-index:9999;opacity:0;transition:all .3s ease;pointer-events:none;";';
echo '    toast.textContent = msg;';
echo '    document.body.appendChild(toast);';
echo '    setTimeout(function() { toast.style.opacity = "1"; toast.style.transform = "translate(-50%,-50%) scale(1)"; }, 10);';
echo '    setTimeout(function() {';
echo '      toast.style.opacity = "0";';
echo '      toast.style.transform = "translate(-50%,-50%) scale(.9)";';
echo '      setTimeout(function() { toast.remove(); }, 300);';
echo '    }, 2000);';
echo '  }';

echo '  var favoriteForms = document.querySelectorAll(".favorite-form");';
echo '  for (var i = 0; i < favoriteForms.length; i++) {';
echo '    (function(form) {';
echo '      form.addEventListener("submit", function(e) {';
echo '        e.preventDefault();';
echo '        var postId = form.getAttribute("data-post-id");';
echo '        var btn = form.querySelector("button[type=submit]");';
echo '        var icon = form.querySelector(".favorite-icon");';
echo '        var text = form.querySelector(".favorite-text");';
echo '        if (!btn || btn.disabled) return;';
echo '        btn.disabled = true;';
echo '        var formData = new FormData(form);';
echo '        var xhr = new XMLHttpRequest();';
echo '        xhr.open("POST", "/favorite_toggle.php", true);';
echo '        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");';
echo '        xhr.onload = function() {';
echo '          btn.disabled = false;';
echo '          try {';
echo '            var data = JSON.parse(xhr.responseText);';
echo '            if (data.success) {';
echo '              if (data.favorited) {';
echo '                btn.classList.add("active");';
echo '                if (icon) icon.textContent = "★";';
echo '                if (text) text.textContent = "取消收藏";';
echo '                btn.setAttribute("title", "取消收藏");';
echo '              } else {';
echo '                btn.classList.remove("active");';
echo '                if (icon) icon.textContent = "☆";';
echo '                if (text) text.textContent = "收藏";';
echo '                btn.setAttribute("title", "收藏");';
echo '              }';
echo '              showToast(data.message);';
echo '            }';
echo '          } catch (err) {';
echo '            console.error("收藏操作失败", err);';
echo '          }';
echo '        };';
echo '        xhr.onerror = function() {';
echo '          btn.disabled = false;';
echo '          console.error("网络错误");';
echo '        };';
echo '        xhr.send(formData);';
echo '      });';
echo '    })(favoriteForms[i]);';
echo '  }';
echo '})();';
echo '</script>';

render_footer();
