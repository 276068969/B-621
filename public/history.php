<?php
declare(strict_types=1);

/*
 * 最近浏览页：
 * - 分页展示阅读历史
 * - 显示浏览时间、作者、评论数
 * - 支持删除单条历史记录
 * - 支持清空全部历史
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

$pdo = null;
try {
    $pdo = db($config);
} catch (Throwable $e) {
    render_header($config, ['title' => '系统错误 - Lite Forum', 'active' => 'history']);
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

$total = get_user_reading_history_count($pdo, $userId);
$pg = paginate($total, $page, $pageSize);
$history = get_user_reading_history($pdo, $userId, $pg['page'], $pg['pageSize']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete' && isset($_POST['post_id'])) {
        $postId = (int)$_POST['post_id'];
        $deleted = delete_read_history($pdo, $userId, $postId);
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => $deleted, 'message' => $deleted ? '已从阅读历史中移除' : '删除失败']);
            exit;
        }
        flash_set('success', '已从阅读历史中移除');
        redirect('/history.php');
    }

    if ($action === 'clear') {
        $count = clear_read_history($pdo, $userId);
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'count' => $count, 'message' => "已清空 {$count} 条阅读历史"]);
            exit;
        }
        flash_set('success', "已清空 {$count} 条阅读历史");
        redirect('/history.php');
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

render_header($config, ['title' => '最近浏览 - Lite Forum', 'active' => 'history']);

echo '<style>';
echo '.history-time{color:#6c757d;font-size:.8rem;}';
echo '.btn-delete-history{color:#dc3545;border-color:#dc3545;background:transparent;transition:all .2s;}';
echo '.btn-delete-history:hover{background:#f8d7da;border-color:#dc3545;color:#dc3545;}';
echo '.empty-state-icon{font-size:3rem;margin-bottom:1rem;}';
echo '.history-card{transition:all .3s ease;}';
echo '.history-card.removing{opacity:0;transform:translateX(30px);}';
echo '</style>';

echo '<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">';
echo '<div>';
echo '<h1 class="h4 mb-0">最近浏览</h1>';
echo '<div class="text-muted small mt-1">共 ' . e((string)$total) . ' 条阅读记录</div>';
echo '</div>';
echo '<div class="d-flex gap-2">';
echo '<a class="btn btn-outline-secondary" href="/index.php">返回列表</a>';
if ($total > 0) {
    echo '<button class="btn btn-outline-danger" id="btnClearAll">清空历史</button>';
}
echo '</div>';
echo '</div>';

if (!$history) {
    echo '<div class="card card-lite p-5 text-center">';
    echo '<div class="empty-state-icon">📖</div>';
    echo '<div class="h5 mb-2">暂无阅读记录</div>';
    echo '<div class="text-muted mb-3">去逛逛帖子，开启你的阅读之旅吧</div>';
    echo '<a class="btn btn-primary" href="/index.php">去发现好帖</a>';
    echo '</div>';
} else {
    foreach ($history as $item) {
        $postId = (int)$item['id'];

        $excerpt = get_post_excerpt((string)$item['content'], '', 120);

        echo '<div class="card card-lite mb-3 history-card" data-post-id="' . $postId . '">';
        echo '<div class="card-body">';
        echo '<div class="d-flex justify-content-between gap-3">';
        echo '<div class="flex-grow-1">';
        echo '<a class="h5 text-decoration-none" href="/post.php?id=' . e((string)$postId) . '">' . e((string)$item['title']) . '</a>';
        echo '<div class="text-muted small mt-2">' . e((string)$excerpt) . '</div>';
        echo '<div class="text-muted small mt-3">';
        echo '<span class="me-2">作者：' . e((string)$item['username']) . '</span>';
        echo '<span class="me-2">发布：' . e((string)$item['create_time']) . '</span>';
        if (!empty($item['update_time'])) {
            echo '<span class="me-2">更新：' . e((string)$item['update_time']) . '</span>';
        }
        echo '<span class="me-2">评论：' . e((string)$item['comment_count']) . '</span>';
        echo '<span class="history-time">浏览于：' . e((string)$item['view_time']) . '</span>';
        echo '</div>';
        echo '</div>';
        echo '<div class="d-flex flex-column gap-2 text-end">';
        echo '<form method="post" class="delete-history-form" data-post-id="' . $postId . '">';
        echo '<input type="hidden" name="action" value="delete">';
        echo '<input type="hidden" name="post_id" value="' . $postId . '">';
        echo '<button type="submit" class="btn btn-sm btn-delete-history" title="从历史中移除">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:-2px;margin-right:4px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>';
        echo '移除';
        echo '</button>';
        echo '</form>';
        echo '<a class="btn btn-sm btn-outline-secondary" href="/post.php?id=' . e((string)$postId) . '">查看</a>';
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
            echo '<li class="page-item' . $active . '"><a class="page-link" href="/history.php' . e($qs ? $qs : '?page=' . $i) . '">' . e((string)$i) . '</a></li>';
        }
        echo '</ul></nav>';
    }
}

echo '<script>';
echo '(function() {';
echo '  function showToast(msg, type) {';
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

echo '  var deleteForms = document.querySelectorAll(".delete-history-form");';
echo '  for (var i = 0; i < deleteForms.length; i++) {';
echo '    (function(form) {';
echo '      form.addEventListener("submit", function(e) {';
echo '        e.preventDefault();';
echo '        var postId = form.getAttribute("data-post-id");';
echo '        var btn = form.querySelector("button[type=submit]");';
echo '        if (!btn || btn.disabled) return;';
echo '        btn.disabled = true;';
echo '        var formData = new FormData(form);';
echo '        var xhr = new XMLHttpRequest();';
echo '        xhr.open("POST", "/history.php", true);';
echo '        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");';
echo '        xhr.onload = function() {';
echo '          btn.disabled = false;';
echo '          try {';
echo '            var data = JSON.parse(xhr.responseText);';
echo '            if (data.success) {';
echo '              var card = form.closest(".history-card");';
echo '              if (card) {';
echo '                card.classList.add("removing");';
echo '                setTimeout(function() {';
echo '                  card.remove();';
echo '                  updateCount();';
echo '                  checkEmptyState();';
echo '                }, 300);';
echo '              }';
echo '              showToast(data.message);';
echo '            }';
echo '          } catch (err) {';
echo '            console.error("删除失败", err);';
echo '          }';
echo '        };';
echo '        xhr.onerror = function() {';
echo '          btn.disabled = false;';
echo '          console.error("网络错误");';
echo '        };';
echo '        xhr.send(formData);';
echo '      });';
echo '    })(deleteForms[i]);';
echo '  }';

echo '  function updateCount() {';
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
echo '    var cards = document.querySelectorAll(".history-card");';
echo '    if (cards.length === 0) {';
echo '      location.reload();';
echo '    }';
echo '  }';

echo '  var btnClearAll = document.getElementById("btnClearAll");';
echo '  if (btnClearAll) {';
echo '    btnClearAll.addEventListener("click", function() {';
echo '      if (!confirm("确定要清空所有阅读历史吗？此操作不可恢复。")) return;';
echo '      btnClearAll.disabled = true;';
echo '      var formData = new FormData();';
echo '      formData.append("action", "clear");';
echo '      var xhr = new XMLHttpRequest();';
echo '      xhr.open("POST", "/history.php", true);';
echo '      xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");';
echo '      xhr.onload = function() {';
echo '        btnClearAll.disabled = false;';
echo '        try {';
echo '          var data = JSON.parse(xhr.responseText);';
echo '          if (data.success) {';
echo '            location.reload();';
echo '          }';
echo '        } catch (err) {';
echo '          console.error("清空失败", err);';
echo '        }';
echo '      };';
echo '      xhr.onerror = function() {';
echo '        btnClearAll.disabled = false;';
echo '        console.error("网络错误");';
echo '      };';
echo '      xhr.send(formData);';
echo '    });';
echo '  }';
echo '})();';
echo '</script>';

render_footer();
