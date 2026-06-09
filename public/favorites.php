<?php
declare(strict_types=1);

/*
 * 我的收藏页：
 * - 分页展示已收藏的帖子
 * - 显示收藏时间、作者、评论数
 * - 支持取消收藏
 * - 标识已失效（已删除）的帖子
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

$pdo = null;
try {
    $pdo = db($config);
} catch (Throwable $e) {
    render_header($config, ['title' => '系统错误 - Lite Forum', 'active' => 'favorites']);
    echo '<div class="card card-lite p-4">';
    echo '<div class="fw-semibold mb-1">数据库连接失败</div>';
    echo '<div class="text-muted">请检查数据库配置或稍后重试。</div>';
    echo '</div>';
    render_footer();
    exit;
}

$u = user();
$userId = (int)$u['id'];

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$pageSize = 10;

$filter = isset($_GET['filter']) ? trim((string)$_GET['filter']) : 'all';
if (!in_array($filter, ['all', 'valid', 'expired'], true)) {
    $filter = 'all';
}

$total = get_user_favorite_count($pdo, $userId);

$validCount = 0;
$expiredCount = 0;
$stmt = $pdo->prepare('
    SELECT 
        SUM(CASE WHEN p.status = 1 THEN 1 ELSE 0 END) AS valid_count,
        SUM(CASE WHEN p.status = 0 THEN 1 ELSE 0 END) AS expired_count
    FROM favorites f
    JOIN posts p ON p.id = f.post_id
    WHERE f.user_id = ?
');
$stmt->execute([$userId]);
$counts = $stmt->fetch();
if ($counts) {
    $validCount = (int)$counts['valid_count'];
    $expiredCount = (int)$counts['expired_count'];
}

if ($filter === 'valid') {
    $displayTotal = $validCount;
} elseif ($filter === 'expired') {
    $displayTotal = $expiredCount;
} else {
    $displayTotal = $total;
}

$pg = paginate($displayTotal, $page, $pageSize);

$favorites = get_user_favorites($pdo, $userId, $pg['page'], $pg['pageSize']);

$filteredFavorites = [];
if ($filter !== 'all') {
    foreach ($favorites as $fav) {
        $status = (int)$fav['status'];
        if ($filter === 'valid' && $status === 1) {
            $filteredFavorites[] = $fav;
        } elseif ($filter === 'expired' && $status === 0) {
            $filteredFavorites[] = $fav;
        }
    }
} else {
    $filteredFavorites = $favorites;
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

render_header($config, ['title' => '我的收藏 - Lite Forum', 'active' => 'favorites']);

echo '<style>';
echo '.favorite-tab-active{font-weight:600;color:var(--bs-primary)!important;border-bottom:3px solid var(--bs-primary);}';
echo '.expired-post{opacity:.6;}';
echo '.expired-badge{background:#6c757d;color:#fff;font-size:.75rem;padding:.2rem .5rem;border-radius:.25rem;}';
echo '.favorite-time{color:#6c757d;font-size:.8rem;}';
echo '.btn-favorite{color:#ffc107;border-color:#ffc107;background:transparent;transition:all .2s;}';
echo '.btn-favorite:hover{background:#fff3cd;border-color:#ffc107;color:#ffc107;}';
echo '.btn-favorite.active{background:#ffc107;border-color:#ffc107;color:#fff;}';
echo '.btn-favorite.active:hover{background:#ffb300;border-color:#ffb300;color:#fff;}';
echo '.favorite-icon{font-size:1rem;line-height:1;}';
echo '.empty-state-icon{font-size:3rem;margin-bottom:1rem;}';
echo '</style>';

echo '<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">';
echo '<div>';
echo '<h1 class="h4 mb-0">我的收藏</h1>';
echo '<div class="text-muted small mt-1">共 ' . e((string)$total) . ' 篇收藏';
if ($filter === 'valid') {
    echo ' · 有效 ' . e((string)$validCount) . ' 篇';
} elseif ($filter === 'expired') {
    echo ' · 已失效 ' . e((string)$expiredCount) . ' 篇';
}
echo '</div>';
echo '</div>';
echo '<div class="d-flex gap-2">';
echo '<a class="btn btn-outline-secondary" href="/index.php">返回列表</a>';
echo '</div>';
echo '</div>';

echo '<ul class="nav nav-tabs mb-3">';
$tabs = [
    'all' => ['label' => '全部收藏', 'count' => $total],
    'valid' => ['label' => '有效内容', 'count' => $validCount],
    'expired' => ['label' => '已失效', 'count' => $expiredCount],
];
foreach ($tabs as $key => $tab) {
    $active = $filter === $key ? ' favorite-tab-active' : '';
    echo '<li class="nav-item">';
    echo '<a class="nav-link' . $active . '" href="/favorites.php?filter=' . e($key) . '">';
    echo e($tab['label']) . ' <span class="badge bg-secondary rounded-pill">' . e((string)$tab['count']) . '</span>';
    echo '</a></li>';
}
echo '</ul>';

if (!$filteredFavorites) {
    echo '<div class="card card-lite p-5 text-center">';
    if ($filter === 'expired') {
        echo '<div class="empty-state-icon">✨</div>';
        echo '<div class="h5 mb-2">没有失效的收藏</div>';
        echo '<div class="text-muted mb-3">你收藏的内容都还在，真好～</div>';
    } elseif ($filter === 'valid') {
        echo '<div class="empty-state-icon">📭</div>';
        echo '<div class="h5 mb-2">还没有有效的收藏</div>';
        echo '<div class="text-muted mb-3">去帖子列表逛逛，收藏感兴趣的讨论吧</div>';
        echo '<a class="btn btn-primary" href="/index.php">去逛逛</a>';
    } else {
        echo '<div class="empty-state-icon">⭐</div>';
        echo '<div class="h5 mb-2">还没有收藏任何帖子</div>';
        echo '<div class="text-muted mb-3">在浏览帖子时，点击「收藏」按钮，稍后可以在这里统一回看</div>';
        echo '<a class="btn btn-primary" href="/index.php">去发现好帖</a>';
    }
    echo '</div>';
} else {
    foreach ($filteredFavorites as $fav) {
        $postId = (int)$fav['id'];
        $status = (int)$fav['status'];
        $isExpired = $status === 0;

        $excerptSource = strip_tags(sanitize_rich_html((string)$fav['content']));
        if (function_exists('mb_substr')) {
            $excerpt = mb_substr($excerptSource, 0, 120);
        } else {
            $excerpt = substr($excerptSource, 0, 120);
        }
        if (strlen($excerptSource) > strlen($excerpt)) {
            $excerpt .= '...';
        }

        $cardClass = 'card card-lite mb-3';
        if ($isExpired) {
            $cardClass .= ' expired-post';
        }

        echo '<div class="' . $cardClass . '">';
        echo '<div class="card-body">';
        echo '<div class="d-flex justify-content-between gap-3">';
        echo '<div class="flex-grow-1">';
        
        echo '<div class="d-flex align-items-center gap-2 flex-wrap">';
        if ($isExpired) {
            echo '<a class="h5 text-decoration-none text-muted" href="#" onclick="return false;">' . e((string)$fav['title']) . '</a>';
            echo '<span class="expired-badge">已失效</span>';
        } else {
            echo '<a class="h5 text-decoration-none" href="/post.php?id=' . e((string)$postId) . '">' . e((string)$fav['title']) . '</a>';
        }
        echo '</div>';

        echo '<div class="text-muted small mt-2">' . e((string)$excerpt) . '</div>';
        
        echo '<div class="text-muted small mt-3">';
        echo '<span class="me-2">作者：' . e((string)$fav['username']) . '</span>';
        echo '<span class="me-2">发布：' . e((string)$fav['create_time']) . '</span>';
        if (!empty($fav['update_time'])) {
            echo '<span class="me-2">更新：' . e((string)$fav['update_time']) . '</span>';
        }
        echo '<span class="me-2">评论：' . e((string)$fav['comment_count']) . '</span>';
        echo '<span class="favorite-time">收藏于：' . e((string)$fav['favorite_time']) . '</span>';
        echo '</div>';

        if ($isExpired) {
            echo '<div class="text-muted small mt-2" style="color:#dc3545!important;">';
            echo '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:-2px;margin-right:4px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>';
            echo '该帖子已被作者或管理员删除，建议取消收藏';
            echo '</div>';
        }
        
        echo '</div>';
        
        echo '<div class="d-flex flex-column gap-2 text-end">';
        echo '<form method="post" action="/favorite_toggle.php" class="favorite-form" data-post-id="' . $postId . '">';
        echo '<input type="hidden" name="post_id" value="' . $postId . '">';
        echo '<button type="submit" class="btn btn-sm btn-favorite active" title="取消收藏">';
        echo '<span class="favorite-icon">★</span>';
        echo '<span class="favorite-text ms-1">取消收藏</span>';
        echo '</button>';
        echo '</form>';
        if (!$isExpired) {
            echo '<a class="btn btn-sm btn-outline-secondary" href="/post.php?id=' . e((string)$postId) . '">查看</a>';
        }
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
            $qs = build_query_string(['page' => $i, 'filter' => $filter]);
            echo '<li class="page-item' . $active . '"><a class="page-link" href="/favorites.php' . e($qs ? $qs : '?page=' . $i) . '">' . e((string)$i) . '</a></li>';
        }
        echo '</ul></nav>';
    }
}

echo '<script>';
echo '(function() {';
echo '  var favoriteForms = document.querySelectorAll(".favorite-form");';
echo '  for (var i = 0; i < favoriteForms.length; i++) {';
echo '    (function(form) {';
echo '      form.addEventListener("submit", function(e) {';
echo '        e.preventDefault();';
echo '        var postId = form.getAttribute("data-post-id");';
echo '        var btn = form.querySelector("button[type=submit]");';
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
echo '            if (data.success && !data.favorited) {';
echo '              var card = form.closest(".card");';
echo '              if (card) {';
echo '                card.style.transition = "all .3s";';
echo '                card.style.opacity = "0";';
echo '                card.style.transform = "translateX(20px)";';
echo '                setTimeout(function() {';
echo '                  card.remove();';
echo '                  updateCounts();';
echo '                  checkEmptyState();';
echo '                }, 300);';
echo '              }';
echo '              showToast(data.message);';
echo '            }';
echo '          } catch (err) {';
echo '            console.error("取消收藏失败", err);';
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
echo '  function updateCounts() {';
echo '    var tabs = document.querySelectorAll(".nav-tabs .nav-link");';
echo '    tabs.forEach(function(tab) {';
echo '      var badge = tab.querySelector(".badge");';
echo '      if (badge) {';
echo '        var text = badge.textContent;';
echo '        var num = parseInt(text, 10);';
echo '        if (!isNaN(num) && num > 0) {';
echo '          badge.textContent = (num - 1).toString();';
echo '        }';
echo '      }';
echo '    });';
echo '    var headerCount = document.querySelector("h1 + .text-muted");';
echo '    if (headerCount) {';
echo '      var txt = headerCount.textContent;';
echo '      var match = txt.match(/(\d+)/);';
echo '      if (match) {';
echo '        var num = parseInt(match[1], 10);';
echo '        if (num > 0) headerCount.textContent = txt.replace(match[1], (num - 1).toString());';
echo '      }';
echo '    }';
echo '  }';
echo '  function checkEmptyState() {';
echo '    var cards = document.querySelectorAll(".card-lite.mb-3");';
echo '    if (cards.length === 0) {';
echo '      location.reload();';
echo '    }';
echo '  }';
echo '  function showToast(msg) {';
echo '    var toast = document.createElement("div");';
echo '    toast.className = "reading-toast";';
echo '    toast.textContent = msg;';
echo '    document.body.appendChild(toast);';
echo '    setTimeout(function() { toast.classList.add("show"); }, 10);';
echo '    setTimeout(function() {';
echo '      toast.classList.remove("show");';
echo '      setTimeout(function() { toast.remove(); }, 300);';
echo '    }, 2000);';
echo '  }';
echo '})();';
echo '</script>';

render_footer();
