<?php
declare(strict_types=1);

/*
 * 帖子管理：
 * - 列表（分页）
 * - 编辑/删除
 * - 回收站视图切换与恢复
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

admin_require_login();

try {
    $pdo = db($config);
} catch (Throwable $e) {
    render_header($config, ['title' => '帖子管理 - Lite Forum', 'active' => 'posts']);
    echo '<div class="card card-lite p-4">数据库连接失败</div>';
    render_footer();
    exit;
}

$status = isset($_GET['status']) ? $_GET['status'] : 'all';
if (!in_array($status, ['all', 'active', 'deleted'], true)) {
    $status = 'all';
}

$boardId = isset($_GET['board_id']) ? (int)$_GET['board_id'] : 0;
$boards = get_boards($pdo, false);

$keyword = isset($_GET['keyword']) ? trim((string)$_GET['keyword']) : '';
$searchIn = isset($_GET['search_in']) ? trim((string)$_GET['search_in']) : 'all';
if (!in_array($searchIn, ['all', 'title', 'content'], true)) {
    $searchIn = 'all';
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$pageSize = 10;

$whereSql = '';
$whereConditions = [];
$params = [];

if ($status === 'active') {
    $whereConditions[] = 'p.status = 1';
} elseif ($status === 'deleted') {
    $whereConditions[] = 'p.status = 0';
}

if ($boardId > 0) {
    $whereConditions[] = 'p.board_id = :board_id';
    $params[':board_id'] = $boardId;
}

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

if ($whereConditions) {
    $whereSql = ' WHERE ' . implode(' AND ', $whereConditions);
}

$countWhereSql = '';
$countParams = [];
if ($status === 'active') {
    $countWhereSql = ' WHERE status = 1';
} elseif ($status === 'deleted') {
    $countWhereSql = ' WHERE status = 0';
}

$countAllSql = 'SELECT COUNT(*) FROM posts';
$countActiveSql = 'SELECT COUNT(*) FROM posts WHERE status = 1';

$totalAll = (int)$pdo->query($countAllSql)->fetchColumn();
$totalActive = (int)$pdo->query($countActiveSql)->fetchColumn();
$totalDeleted = $totalAll - $totalActive;

$filteredCountSql = 'SELECT COUNT(*) FROM posts p JOIN users u ON u.id = p.user_id' . $whereSql;
$countStmt = $pdo->prepare($filteredCountSql);
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value);
}
$countStmt->execute();
$total = (int)$countStmt->fetchColumn();

$pg = paginate($total, $page, $pageSize);

$scoreFields = '';
$orderSql = 'ORDER BY p.create_time DESC';
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
        $orderSql = 'ORDER BY search_score DESC, p.create_time DESC';
    }
}

$stmt = $pdo->prepare(
    'SELECT p.id, p.board_id, p.title, p.content, p.create_time, p.update_time, p.status, u.username,
            b.name AS board_name' . $scoreFields . '
     FROM posts p
     JOIN users u ON u.id = p.user_id
     LEFT JOIN boards b ON b.id = p.board_id'
    . $whereSql .
    ' ' . $orderSql . '
     LIMIT :limit OFFSET :offset'
);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $pg['pageSize'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $pg['offset'], PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

render_header($config, ['title' => '帖子管理 - Lite Forum', 'active' => 'posts']);

echo '<div class="d-flex align-items-center justify-content-between mb-3">';
echo '<div>';
echo '<h1 class="h4 mb-0">帖子管理</h1>';
echo '<div class="text-muted small mt-1">共 ' . e((string)$total) . ' 条' . ($status === 'all' ? '（含已删除）' : '') . ($keyword ? ' · 搜索：' . e($keyword) : '') . '</div>';
echo '</div>';
echo '<div class="d-flex gap-2">';
echo '<a class="btn btn-outline-secondary" href="/admin/index.php">返回概览</a>';
echo '<a class="btn btn-outline-secondary" href="/admin/logout.php">退出后台</a>';
echo '</div>';
echo '</div>';

echo '<div class="card card-lite mb-3">';
echo '<div class="card-body p-3">';
echo '<form method="get" action="/admin/posts.php" id="searchForm">';
echo '<input type="hidden" name="status" value="' . e($status) . '">';
echo '<div class="row g-3 align-items-end">';
echo '<div class="col-md-3 col-12">';
echo '<label class="form-label small fw-medium mb-1" for="board_id">版块筛选</label>';
echo '<select class="form-select" id="board_id" name="board_id" onchange="document.getElementById(\'searchForm\').submit()">';
echo '<option value="">全部版块</option>';
foreach ($boards as $b) {
    $selected = $boardId === (int)$b['id'] ? ' selected' : '';
    echo '<option value="' . e((string)$b['id']) . '"' . $selected . '>' . e((string)$b['name']) . '</option>';
}
echo '</select>';
echo '</div>';
echo '<div class="col-md-5 col-12">';
echo '<label class="form-label small fw-medium mb-1" for="keyword">关键词搜索</label>';
echo '<div class="input-group">';
echo '<span class="input-group-text" style="background:#f8f9fa;">';
echo '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/></svg>';
echo '</span>';
echo '<input type="text" class="form-control" id="keyword" name="keyword" placeholder="输入关键词搜索帖子..." value="' . e($keyword) . '">';
echo '<button type="submit" class="btn btn-primary">搜索</button>';
if ($keyword || $boardId > 0) {
    echo '<a href="/admin/posts.php?status=' . e($status) . '" class="btn btn-outline-secondary">清除</a>';
}
echo '</div>';
echo '</div>';
echo '<div class="col-md-4 col-12">';
echo '<label class="form-label small fw-medium mb-1">搜索范围</label>';
echo '<div class="d-flex gap-3">';
$searchInOptions = [
    'all' => '标题+正文',
    'title' => '仅标题',
    'content' => '仅正文',
];
foreach ($searchInOptions as $val => $label) {
    $checked = $searchIn === $val ? ' checked' : '';
    echo '<div class="form-check form-check-inline mb-0">';
    echo '<input class="form-check-input" type="radio" name="search_in" id="search_in_' . e($val) . '" value="' . e($val) . '"' . $checked . ' onchange="document.getElementById(\'searchForm\').submit()">';
    echo '<label class="form-check-label small" for="search_in_' . e($val) . '">' . e($label) . '</label>';
    echo '</div>';
}
echo '</div>';
echo '</div>';
echo '</div>';
echo '</form>';
echo '</div>';
echo '</div>';

echo '<ul class="nav nav-tabs mb-3">';
$tabs = [
    'all' => ['label' => '全部', 'count' => $totalAll],
    'active' => ['label' => '正常', 'count' => $totalActive],
    'deleted' => ['label' => '回收站', 'count' => $totalDeleted],
];

function build_admin_search_params(string $status, string $keyword, string $searchIn): string
{
    $params = [];
    $params['status'] = $status;
    if ($keyword !== '') {
        $params['keyword'] = $keyword;
        $params['search_in'] = $searchIn;
    }
    return '?' . http_build_query($params);
}

function admin_highlight_keyword(string $text, string $keyword): string
{
    if ($keyword === '') {
        return e($text);
    }
    $safeText = e($text);
    $safeKeyword = preg_quote(e($keyword), '/');
    $result = preg_replace('/(' . $safeKeyword . ')/iu', '<mark style="background:#fff3cd;color:#856404;padding:0 2px;border-radius:2px;font-weight:500;">$1</mark>', $safeText);
    return $result !== null ? $result : $safeText;
}

foreach ($tabs as $key => $tab) {
    $active = $status === $key ? ' active' : '';
    $url = build_admin_search_params($key, $keyword, $searchIn);
    echo '<li class="nav-item">';
    echo '<a class="nav-link' . $active . '" href="/admin/posts.php' . e($url) . '">';
    echo e($tab['label']) . ' <span class="badge bg-secondary rounded-pill">' . e((string)$tab['count']) . '</span>';
    echo '</a></li>';
}
echo '</ul>';

echo '<div class="card card-lite">';
echo '<div class="card-body p-0">';
echo '<div class="table-responsive">';
echo '<table class="table table-hover mb-0">';
echo '<thead class="table-light"><tr>'; 
echo '<th class="ps-3">标题</th><th>版块</th><th>作者</th><th>发布时间</th><th>更新时间</th><th>状态</th><th class="text-end pe-3">操作</th>';
echo '</tr></thead><tbody>';

if (!$rows) {
    echo '<tr><td class="ps-3 py-4 text-muted" colspan="7">';
    if ($keyword) {
        echo '没有找到匹配的帖子';
    } else {
        echo '暂无数据';
    }
    echo '</td></tr>';
} else {
    foreach ($rows as $r) {
        $statusBadge = ((int)$r['status'] === 1)
            ? '<span class="badge text-bg-success">正常</span>'
            : '<span class="badge text-bg-secondary">已删除</span>';

        $titleDisplay = admin_highlight_keyword((string)$r['title'], $keyword);
        $excerpt = get_post_excerpt((string)$r['content'], $keyword, 100);
        $excerptDisplay = admin_highlight_keyword($excerpt, $keyword);

        $titleMatch = false;
        $contentMatch = false;
        if ($keyword !== '') {
            $keywordLower = mb_strtolower($keyword);
            $titleLower = mb_strtolower((string)$r['title']);
            $contentLower = mb_strtolower(strip_tags(sanitize_rich_html((string)$r['content'])));
            $titleMatch = mb_strpos($titleLower, $keywordLower) !== false;
            $contentMatch = mb_strpos($contentLower, $keywordLower) !== false;
        }

        $boardName = !empty($r['board_name']) ? e((string)$r['board_name']) : '<span class="text-muted">未分类</span>';

        echo '<tr>'; 
        echo '<td class="ps-3">';
        echo '<div class="fw-medium">' . $titleDisplay . '</div>';
        if ($keyword) {
            echo '<div class="small text-muted mt-1">' . $excerptDisplay . '</div>';
            echo '<div class="d-flex gap-2 mt-1">';
            if ($titleMatch) {
                echo '<span class="badge text-bg-info" style="font-size:.65rem;">标题命中</span>';
            }
            if ($contentMatch) {
                echo '<span class="badge text-bg-purple" style="background:#7048e8;font-size:.65rem;">正文命中</span>';
            }
            echo '</div>';
        }
        echo '</td>';
        echo '<td>' . $boardName . '</td>';
        echo '<td>' . e((string)$r['username']) . '</td>';
        echo '<td class="text-muted small">' . e((string)$r['create_time']) . '</td>';
        echo '<td class="text-muted small">' . (!empty($r['update_time']) ? e((string)$r['update_time']) : '-') . '</td>';
        echo '<td>' . $statusBadge . '</td>';
        echo '<td class="text-end pe-3">';
        echo '<button class="btn btn-sm btn-outline-primary" onclick="openPostPreview(' . e((string)$r['id']) . ')">预览</button> ';
        if ((int)$r['status'] === 1) {
            echo '<a class="btn btn-sm btn-outline-secondary" href="/admin/post_edit.php?id=' . e((string)$r['id']) . '">编辑</a> ';
            $delUrl = '/admin/post_delete.php?id=' . e((string)$r['id']);
            $safeTitle = e(addslashes((string)$r['title']));
            echo '<button class="btn btn-sm btn-outline-danger" onclick="showConfirmModal(\'删除确认\', \'确定要删除帖子 <strong>' . $safeTitle . '</strong> 吗？此操作将同步隐藏所有相关评论。\', \'' . $delUrl . '\', \'确认删除\', \'btn-danger\')">删除</button>';
        } else {
            $restoreUrl = '/admin/post_restore.php?id=' . e((string)$r['id']);
            $safeTitle = e(addslashes((string)$r['title']));
            echo '<button class="btn btn-sm btn-outline-success" onclick="showConfirmModal(\'恢复确认\', \'确定要从回收站恢复帖子 <strong>' . $safeTitle . '</strong> 吗？此操作将同步恢复所有相关评论。\', \'' . $restoreUrl . '\', \'确认恢复\', \'btn-success\')">恢复</button>';
        }
        echo '</td>';
        echo '</tr>';
    }
}

echo '</tbody></table></div></div></div>';

if ($pg['pages'] > 1) {
    echo '<nav class="mt-3" aria-label="Page navigation">';
    echo '<ul class="pagination justify-content-center">';
    for ($i = 1; $i <= $pg['pages']; $i++) {
        $active = $i === $pg['page'] ? ' active' : '';
        $pageUrl = build_admin_search_params($status, $keyword, $searchIn) . '&page=' . $i;
        echo '<li class="page-item' . $active . '"><a class="page-link" href="/admin/posts.php' . e($pageUrl) . '">' . e((string)$i) . '</a></li>';
    }
    echo '</ul></nav>';
}

echo '<div class="preview-overlay" id="previewOverlay" onclick="closePostPreview()"></div>';
echo '<div class="preview-panel" id="previewPanel">';
echo '<div class="preview-panel-header">';
echo '<div class="d-flex align-items-center gap-2">';
echo '<h2 class="h6 mb-0 fw-bold">帖子预览</h2>';
echo '<span class="badge text-bg-secondary" id="previewStatusBadge">加载中</span>';
echo '</div>';
echo '<button class="preview-close-btn" onclick="closePostPreview()" aria-label="关闭">';
echo '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
echo '</button>';
echo '</div>';
echo '<div class="preview-panel-body" id="previewPanelBody">';
echo '<div class="preview-loading">';
echo '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">加载中...</span></div>';
echo '<div class="mt-2 text-muted">正在加载帖子详情...</div>';
echo '</div>';
echo '</div>';
echo '</div>';

echo '<style>';
echo '.preview-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.4);z-index:1040;opacity:0;visibility:hidden;transition:opacity .3s ease,visibility .3s ease;}';
echo '.preview-overlay.show{opacity:1;visibility:visible;}';
echo '.preview-panel{position:fixed;top:0;right:0;width:520px;height:100%;background:#fff;z-index:1050;transform:translateX(100%);transition:transform .3s ease;display:flex;flex-direction:column;box-shadow:-4px 0 20px rgba(0,0,0,.1);}';
echo '.preview-panel.show{transform:translateX(0);}';
echo '.preview-panel-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #e9ecef;background:#f8f9fa;flex-shrink:0;}';
echo '.preview-close-btn{width:36px;height:36px;border:none;background:transparent;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#6c757d;cursor:pointer;transition:all .2s ease;}';
echo '.preview-close-btn:hover{background:#e9ecef;color:#212529;}';
echo '.preview-panel-body{flex:1;overflow-y:auto;padding:0;}';
echo '.preview-loading{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px 20px;}';
echo '.preview-error{padding:40px 20px;text-align:center;color:#dc3545;}';
echo '.preview-content-inner{padding:20px;}';
echo '.preview-title{font-size:1.25rem;font-weight:600;margin-bottom:12px;line-height:1.4;color:#212529;}';
echo '.preview-meta{display:flex;flex-wrap:wrap;gap:12px;padding-bottom:12px;border-bottom:1px solid #e9ecef;margin-bottom:16px;color:#6c757d;font-size:.875rem;}';
echo '.preview-meta-item{display:flex;align-items:center;gap:4px;}';
echo '.preview-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px;}';
echo '.preview-stat-card{background:#f8f9fa;border-radius:8px;padding:12px;text-align:center;}';
echo '.preview-stat-value{font-size:1.25rem;font-weight:600;color:#2c3e50;}';
echo '.preview-stat-label{font-size:.75rem;color:#6c757d;margin-top:2px;}';
echo '.preview-section{margin-bottom:20px;}';
echo '.preview-section-title{font-size:.875rem;font-weight:600;color:#495057;margin-bottom:8px;display:flex;align-items:center;gap:6px;}';
echo '.preview-section-title::before{content:"";width:3px;height:14px;background:#2c3e50;border-radius:2px;}';
echo '.preview-outline-list{list-style:none;padding:0;margin:0;}';
echo '.preview-outline-item{padding:6px 10px;border-radius:6px;font-size:.875rem;color:#495057;cursor:default;transition:background .2s ease;}';
echo '.preview-outline-item:hover{background:#f8f9fa;}';
echo '.preview-outline-empty{color:#adb5bd;font-size:.875rem;font-style:italic;}';
echo '.preview-article{border:1px solid #e9ecef;border-radius:8px;padding:16px;background:#fff;}';
echo '.preview-article .post-content{line-height:1.75;font-size:.95rem;color:#343a40;}';
echo '.preview-article .post-content p{margin-bottom:.75rem;}';
echo '.preview-article .post-content ul,.preview-article .post-content ol{margin-bottom:.75rem;padding-left:1.5rem;}';
echo '.preview-article .post-content a{color:#0d6efd;text-decoration:none;}';
echo '.preview-article .post-content a:hover{text-decoration:underline;}';
echo '.preview-article .post-content strong{font-weight:600;}';
echo '.preview-article .post-content h1,.preview-article .post-content h2,.preview-article .post-content h3,.preview-article .post-content h4,.preview-article .post-content h5,.preview-article .post-content h6{margin-top:1rem;margin-bottom:.5rem;font-weight:600;}';
echo '.preview-article .post-content blockquote{border-left:4px solid #dee2e6;padding-left:1rem;margin-left:0;margin-right:0;color:#6c757d;}';
echo '.preview-article .post-content code,.preview-article .post-content pre{background:#f8f9fa;border-radius:4px;font-family:Consolas,Monaco,monospace;}';
echo '.preview-article .post-content code{padding:2px 6px;font-size:.9em;}';
echo '.preview-article .post-content pre{padding:12px;overflow-x:auto;}';
echo '.preview-actions{display:flex;gap:8px;flex-wrap:wrap;}';
echo '.preview-actions .btn{flex:1;min-width:0;}';
echo 'body.preview-open{overflow:hidden;}';
echo '@media (max-width:768px){';
echo '  .preview-panel{width:100%;}';
echo '  .preview-stats{grid-template-columns:repeat(3,1fr);}';
echo '}';
echo '</style>';

echo '<script>';
echo '(function() {';
echo '"use strict";';
echo 'var currentPreviewId = null;';
echo 'var previewData = null;';

echo 'function openPostPreview(postId) {';
echo '  currentPreviewId = postId;';
echo '  var overlay = document.getElementById("previewOverlay");';
echo '  var panel = document.getElementById("previewPanel");';
echo '  var body = document.getElementById("previewPanelBody");';
echo '  var statusBadge = document.getElementById("previewStatusBadge");';
echo '  if (overlay) overlay.classList.add("show");';
echo '  if (panel) panel.classList.add("show");';
echo '  document.body.classList.add("preview-open");';
echo '  if (statusBadge) { statusBadge.textContent = "加载中"; statusBadge.className = "badge text-bg-secondary"; }';
echo '  if (body) {';
echo '    body.innerHTML = \'<div class="preview-loading"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">加载中...</span></div><div class="mt-2 text-muted">正在加载帖子详情...</div></div>\';';
echo '  }';
echo '  loadPostData(postId);';
echo '}';

echo 'function closePostPreview() {';
echo '  var overlay = document.getElementById("previewOverlay");';
echo '  var panel = document.getElementById("previewPanel");';
echo '  if (overlay) overlay.classList.remove("show");';
echo '  if (panel) panel.classList.remove("show");';
echo '  document.body.classList.remove("preview-open");';
echo '  currentPreviewId = null;';
echo '  previewData = null;';
echo '}';

echo 'function loadPostData(postId) {';
echo '  var xhr = new XMLHttpRequest();';
echo '  xhr.open("GET", "/admin/post_preview.php?id=" + postId, true);';
echo '  xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");';
echo '  xhr.onload = function() {';
echo '    if (currentPreviewId !== postId) return;';
echo '    try {';
echo '      var data = JSON.parse(xhr.responseText);';
echo '      if (data.success) {';
echo '        previewData = data.data;';
echo '        renderPreview(data.data);';
echo '      } else {';
echo '        renderError(data.message || "加载失败");';
echo '      }';
echo '    } catch (e) {';
echo '      renderError("数据解析失败");';
echo '    }';
echo '  };';
echo '  xhr.onerror = function() {';
echo '    if (currentPreviewId !== postId) return;';
echo '    renderError("网络错误，请稍后重试");';
echo '  };';
echo '  xhr.send();';
echo '}';

echo 'function renderError(message) {';
echo '  var body = document.getElementById("previewPanelBody");';
echo '  var statusBadge = document.getElementById("previewStatusBadge");';
echo '  if (statusBadge) { statusBadge.textContent = "加载失败"; statusBadge.className = "badge text-bg-danger"; }';
echo '  if (body) {';
echo '    body.innerHTML = \'<div class="preview-error"><div class="fs-1 mb-2">⚠️</div><div>\' + escapeHtml(message) + \'</div></div>\';';
echo '  }';
echo '}';

echo 'function renderPreview(data) {';
echo '  var body = document.getElementById("previewPanelBody");';
echo '  var statusBadge = document.getElementById("previewStatusBadge");';
echo '  if (statusBadge) {';
echo '    statusBadge.textContent = data.status === 1 ? "正常" : "已删除";';
echo '    statusBadge.className = "badge " + (data.status === 1 ? "text-bg-success" : "text-bg-secondary");';
echo '  }';
echo '  var statusClass = data.status === 1 ? "badge text-bg-success" : "badge text-bg-secondary";';
echo '  var statusText = data.status === 1 ? "正常" : "已删除";';
echo '  var outlineHtml = "";';
echo '  if (data.headings && data.headings.length > 0) {';
echo '    outlineHtml = \'<ul class="preview-outline-list">\';';
echo '    for (var i = 0; i < data.headings.length; i++) {';
echo '      outlineHtml += \'<li class="preview-outline-item">\' + escapeHtml(data.headings[i]) + \'</li>\';';
echo '    }';
echo '    outlineHtml += \'</ul>\';';
echo '  } else {';
echo '    outlineHtml = \'<div class="preview-outline-empty">暂无章节标题</div>\';';
echo '  }';
echo '  var html = \'<div class="preview-content-inner">\';';
echo '  html += \'<div class="preview-title">\' + escapeHtml(data.title) + \'</div>\';';
echo '  html += \'<div class="preview-meta">\';';
echo '  html += \'<span class="preview-meta-item">👤 \' + escapeHtml(data.username) + \'</span>\';';
echo '  html += \'<span class="preview-meta-item">📅 \' + escapeHtml(data.create_time) + \'</span>\';';
echo '  if (data.update_time) { html += \'<span class="preview-meta-item">✏️ \' + escapeHtml(data.update_time) + \'</span>\'; }';
echo '  html += \'<span class="preview-meta-item"><span class="\' + statusClass + \'">\' + statusText + \'</span></span>\';';
echo '  html += \'</div>\';';
echo '  html += \'<div class="preview-stats">\';';
echo '  html += \'<div class="preview-stat-card"><div class="preview-stat-value">\' + data.comment_count + \'</div><div class="preview-stat-label">评论数</div></div>\';';
echo '  html += \'<div class="preview-stat-card"><div class="preview-stat-value">\' + data.favorite_count + \'</div><div class="preview-stat-label">收藏数</div></div>\';';
echo '  html += \'<div class="preview-stat-card"><div class="preview-stat-value">\' + data.word_count + \'</div><div class="preview-stat-label">字数</div></div>\';';
echo '  html += \'</div>\';';
echo '  html += \'<div class="preview-section"><div class="preview-section-title">正文结构</div>\';';
echo '  html += outlineHtml;';
echo '  html += \'</div>\';';
echo '  html += \'<div class="preview-section"><div class="preview-section-title">完整内容</div>\';';
echo '  html += \'<div class="preview-article"><article class="post-content">\' + data.content + \'</article></div>\';';
echo '  html += \'</div>\';';
echo '  html += \'<div class="preview-section"><div class="preview-section-title">快捷操作</div>\';';
echo '  html += \'<div class="preview-actions">\';';
echo '  if (data.status === 1) {';
echo '    html += \'<a class="btn btn-outline-primary btn-sm" href="/admin/post_edit.php?id=\' + data.id + \'">编辑</a>\';';
echo '    html += \'<a class="btn btn-outline-secondary btn-sm" href="\' + data.front_url + \'" target="_blank" rel="noopener">前台查看</a>\';';
echo '  } else {';
echo '    html += \'<a class="btn btn-outline-success btn-sm" href="/admin/post_restore.php?id=\' + data.id + \'">恢复</a>\';';
echo '  }';
echo '  html += \'</div></div>\';';
echo '  html += \'</div>\';';
echo '  if (body) body.innerHTML = html;';
echo '}';

echo 'function escapeHtml(text) {';
echo '  var div = document.createElement("div");';
echo '  div.appendChild(document.createTextNode(text));';
echo '  return div.innerHTML;';
echo '}';

echo 'document.addEventListener("keydown", function(e) {';
echo '  if (e.key === "Escape" && currentPreviewId !== null) {';
echo '    closePostPreview();';
echo '  }';
echo '});';

echo 'window.openPostPreview = openPostPreview;';
echo 'window.closePostPreview = closePostPreview;';
echo '})();';
echo '</script>';

render_footer();

