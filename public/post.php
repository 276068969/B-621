<?php
declare(strict_types=1);

/*
 * 帖子详情页：
 * - 渲染帖子富文本内容（基础白名单清洗）
 * - 展示评论区
 * - 登录用户可发表评论（自动关联用户）
 */

require_once __DIR__ . '/../includes/bootstrap.php';

try {
    $pdo = db($config);
} catch (Throwable $e) {
    render_header($config, ['title' => '系统错误 - Lite Forum', 'active' => 'home']);
    echo '<div class="card card-lite p-4">数据库连接失败</div>';
    render_footer();
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    render_header($config, ['title' => '帖子不存在 - Lite Forum', 'active' => 'home']);
    echo '<div class="card card-lite p-4">帖子不存在或参数错误。</div>';
    render_footer();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    $content = trim((string)($_POST['content'] ?? ''));
    if ($content === '') {
        flash_set('danger', '评论内容不能为空。');
        redirect('/post.php?id=' . $id);
    }

    $modResult = moderate_comment($content);
    if (!$modResult['passed']) {
        flash_set('danger', $modResult['message']);
        redirect('/post.php?id=' . $id);
    }

    $u = user();
    $stmt = $pdo->prepare('INSERT INTO comments (post_id, user_id, content, status) VALUES (?, ?, ?, 1)');
    $stmt->execute([$id, (int)$u['id'], $content]);
    flash_set('success', '评论已发布。');
    redirect('/post.php?id=' . $id);
}

$stmt = $pdo->prepare(
    'SELECT p.id, p.user_id, p.title, p.content, p.create_time, p.update_time, u.username
     FROM posts p
     JOIN users u ON u.id = p.user_id
     WHERE p.id = ? AND p.status = 1
     LIMIT 1'
);
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) {
    render_header($config, ['title' => '帖子不存在 - Lite Forum', 'active' => 'home']);
    echo '<div class="card card-lite p-4">帖子不存在或已被删除。</div>';
    render_footer();
    exit;
}

$stmt = $pdo->prepare(
    'SELECT c.id, c.content, c.create_time, u.username
     FROM comments c
     JOIN users u ON u.id = c.user_id
     WHERE c.post_id = ? AND c.status = 1
     ORDER BY c.create_time ASC'
);
$stmt->execute([$id]);
$comments = $stmt->fetchAll();

$hotPosts = get_hot_posts($pdo, 6, $id);

$currentUser = user();
$isFavorited = false;
if ($currentUser !== null) {
    $isFavorited = is_post_favorited($pdo, (int)$currentUser['id'], $id);
}

render_header($config, ['title' => (string)$post['title'] . ' - Lite Forum', 'active' => 'home']);

$canEdit = can_user_edit_post($config, user(), $post, count($comments));

echo '<div class="card card-lite mb-3" id="post-top">';
echo '<div class="card-body">';
$favIcon = $isFavorited ? '★' : '☆';
$favClass = $isFavorited ? 'btn-favorite active' : 'btn-favorite';
$favTitle = $isFavorited ? '取消收藏' : '收藏';

echo '<div class="d-flex justify-content-between flex-wrap gap-2">';
echo '<h1 class="h4 mb-0">' . e((string)$post['title']) . '</h1>';
echo '<div class="d-flex gap-2">';
if ($currentUser !== null) {
    echo '<form method="post" action="/favorite_toggle.php" class="favorite-form" data-post-id="' . $id . '">';
    echo '<input type="hidden" name="post_id" value="' . $id . '">';
    echo '<button type="submit" class="btn btn-sm ' . $favClass . '" title="' . e($favTitle) . '">';
    echo '<span class="favorite-icon">' . $favIcon . '</span>';
    echo '<span class="favorite-text ms-1">' . e($favTitle) . '</span>';
    echo '</button>';
    echo '</form>';
}
if ($canEdit) {
    echo '<a class="btn btn-sm btn-outline-primary" href="/post_edit.php?id=' . e((string)$id) . '">编辑</a>';
}
echo '<a class="btn btn-sm btn-outline-secondary" href="/index.php">返回列表</a>';
echo '</div>';
echo '</div>';
echo '<div class="text-muted small mt-2">';
echo '作者：' . e((string)$post['username']) . ' · 发布：' . e((string)$post['create_time']);
if (!empty($post['update_time'])) {
    echo ' · 更新：' . e((string)$post['update_time']);
}
echo '</div>';
echo '<hr>';
echo '<article class="post-content" id="post-content">' . sanitize_rich_html((string)$post['content']) . '</article>';
echo '</div>';
echo '</div>';

echo '<div class="card card-lite mb-3" id="comments-section">';
echo '<div class="card-body">';
echo '<div class="d-flex align-items-center justify-content-between">';
echo '<div class="fw-semibold">评论</div>';
echo '<div class="d-flex align-items-center gap-3">';
echo '<div class="form-check form-switch mb-0">';
echo '<input class="form-check-input" type="checkbox" id="author-only-toggle">';
echo '<label class="form-check-label text-muted small" for="author-only-toggle">只看作者</label>';
echo '</div>';
echo '<div class="text-muted small">' . e((string)count($comments)) . ' 条</div>';
echo '</div>';
echo '</div>';
echo '<div class="mt-3" id="comments-list">';

if (!$comments) {
    echo '<div class="text-muted">暂无评论。</div>';
} else {
    $authorUsername = (string)$post['username'];
    foreach ($comments as $idx => $c) {
        $isAuthor = $c['username'] === $authorUsername;
        $commentClass = 'border rounded-3 p-3 mb-2 comment-item';
        if ($isAuthor) {
            $commentClass .= ' comment-is-author';
        }
        echo '<div class="' . $commentClass . '" id="comment-' . ((int)$idx + 1) . '" data-username="' . e((string)$c['username']) . '" data-is-author="' . ($isAuthor ? '1' : '0') . '">';
        echo '<div class="d-flex justify-content-between flex-wrap gap-2">';
        echo '<div class="d-flex align-items-center gap-2">';
        echo '<div class="fw-semibold">' . e((string)$c['username']) . '</div>';
        if ($isAuthor) {
            echo '<span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size:.7rem;">作者</span>';
        }
        echo '</div>';
        echo '<div class="d-flex align-items-center gap-2">';
        echo '<a href="#comment-' . ((int)$idx + 1) . '" class="text-muted small text-decoration-none comment-anchor" title="复制此评论链接">#' . ((int)$idx + 1) . '</a>';
        echo '<div class="text-muted small">' . e((string)$c['create_time']) . '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="mt-2">' . e((string)$c['content']) . '</div>';
        echo '</div>';
    }
}

echo '</div>';
echo '</div>';
echo '</div>';

echo '<div class="card card-lite mb-3 border-0 shadow-sm" id="reply-section">';
echo '<div class="card-body p-4">';
echo '<div class="fw-semibold mb-3 fs-5">发表评论</div>';
if (user() === null) {
    echo '<div class="alert alert-light border d-flex align-items-center" role="alert">';
    echo '<span class="me-2">🔒</span>';
    echo '<div>未登录用户仅可查看。请先 <a href="/login.php?return=' . urlencode('/post.php?id=' . $id) . '" class="fw-bold text-decoration-none">登录</a> 后参与讨论。</div>';
    echo '</div>';
} else {
    echo '<form method="post" class="needs-validation" novalidate>'; 
    echo '<div class="mb-3 position-relative">';
    echo '<textarea class="form-control" name="content" rows="4" placeholder="写下你的看法..." required style="resize:none;"></textarea>';
    echo '<div class="invalid-feedback">评论内容不能为空。</div>';
    echo '<div class="form-text text-end mt-1"><small>支持纯文本，发布后自动关联账号</small></div>';
    echo '</div>';
    echo '<div class="text-end">';
    echo '<button class="btn btn-primary px-4 rounded-pill fw-semibold" type="submit">发布评论</button>';
    echo '</div>';
    echo '</form>';
}
echo '</div>';
echo '</div>';

if ($hotPosts) {
    echo '<div class="card card-lite mb-3 border-0 shadow-sm">';
    echo '<div class="card-body p-4">';
    echo '<div class="d-flex align-items-center gap-2 mb-3">';
    echo '<span style="font-size:1.1rem;">🔥</span>';
    echo '<div class="fw-semibold fs-5">热门讨论</div>';
    echo '</div>';
    echo '<div class="row g-3">';
    foreach ($hotPosts as $hp) {
        echo '<div class="col-md-6 col-12">';
        echo '<a href="/post.php?id=' . e((string)$hp['id']) . '" class="text-decoration-none">';
        echo '<div class="hot-recommend-item">';
        echo '<div class="hot-recommend-title">' . e((string)$hp['title']) . '</div>';
        echo '<div class="hot-recommend-meta text-muted small">';
        echo '<span class="me-2">' . e((string)$hp['username']) . '</span>';
        echo '<span class="hot-comment-badge">' . e((string)$hp['comment_count']) . ' 评论</span>';
        echo '</div>';
        echo '</div>';
        echo '</a>';
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

// 阅读辅助工具栏
echo '<div class="reading-toolbar" id="readingToolbar">';
echo '<div class="reading-toolbar-progress" id="readingProgressBar"></div>';
echo '<button class="reading-toolbar-btn" id="btnBackToTop" title="回到顶部">';
echo '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"></polyline></svg>';
echo '</button>';
if ($currentUser !== null) {
    $favToolbarClass = $isFavorited ? 'favorite-btn active' : 'favorite-btn';
    $favToolbarTitle = $isFavorited ? '取消收藏' : '收藏';
    $favToolbarIcon = $isFavorited ? '#ffc107' : 'currentColor';
    echo '<button class="reading-toolbar-btn ' . $favToolbarClass . '" id="btnFavorite" title="' . e($favToolbarTitle) . '" data-post-id="' . $id . '" data-favorited="' . ($isFavorited ? '1' : '0') . '">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="' . ($isFavorited ? 'currentColor' : 'none') . '" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>';
    echo '</button>';
}
echo '<button class="reading-toolbar-btn" id="btnCopyLink" title="复制链接">';
echo '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>';
echo '</button>';
echo '<button class="reading-toolbar-btn" id="btnGoReply" title="去评论">';
echo '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>';
echo '</button>';
echo '<button class="reading-toolbar-btn" id="btnAuthorOnly" title="只看作者">';
echo '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>';
echo '</button>';
echo '<div class="reading-toolbar-percent" id="readingPercent">0%</div>';
echo '</div>';

echo '<script>';
echo '(function() {';
echo '"use strict";';
echo 'var forms = document.querySelectorAll(".needs-validation");';
echo 'Array.prototype.forEach.call(forms, function(form) {';
echo '  form.addEventListener("submit", function(event) {';
echo '    if (!form.checkValidity()) {';
echo '      event.preventDefault();';
echo '      event.stopPropagation();';
echo '      showModal("提示", "请输入评论内容。");';
echo '    }';
echo '    form.classList.add("was-validated");';
echo '  }, false);';
echo '});';
echo 'var progressBar = document.getElementById("readingProgressBar");';
echo 'var progressPercent = document.getElementById("readingPercent");';
echo 'var toolbar = document.getElementById("readingToolbar");';
echo 'function updateReadingProgress() {';
echo '  var postContent = document.getElementById("post-content");';
echo '  if (!postContent) return;';
echo '  var windowHeight = window.innerHeight;';
echo '  var docHeight = document.documentElement.scrollHeight;';
echo '  var scrollTop = window.scrollY || document.documentElement.scrollTop;';
echo '  var scrollPercent = Math.min(100, Math.max(0, Math.round((scrollTop / (docHeight - windowHeight)) * 100)));';
echo '  if (progressBar) progressBar.style.height = scrollPercent + "%";';
echo '  if (progressPercent) progressPercent.textContent = scrollPercent + "%";';
echo '  if (toolbar) {';
echo '    if (scrollTop > 200) {';
echo '      toolbar.style.opacity = "1";';
echo '      toolbar.style.pointerEvents = "auto";';
echo '    } else {';
echo '      toolbar.style.opacity = "0";';
echo '      toolbar.style.pointerEvents = "none";';
echo '    }';
echo '  }';
echo '}';
echo 'window.addEventListener("scroll", updateReadingProgress);';
echo 'updateReadingProgress();';
echo 'var btnBackToTop = document.getElementById("btnBackToTop");';
echo 'if (btnBackToTop) {';
echo '  btnBackToTop.addEventListener("click", function() {';
echo '    window.scrollTo({ top: 0, behavior: "smooth" });';
echo '  });';
echo '}';
echo 'var btnCopyLink = document.getElementById("btnCopyLink");';
echo 'if (btnCopyLink) {';
echo '  btnCopyLink.addEventListener("click", function() {';
echo '    var url = window.location.href;';
echo '    var input = document.createElement("input");';
echo '    input.value = url;';
echo '    document.body.appendChild(input);';
echo '    input.select();';
echo '    try { document.execCommand("copy"); showToast("链接已复制到剪贴板"); }';
echo '    catch (e) { showToast("复制失败，请手动复制"); }';
echo '    document.body.removeChild(input);';
echo '  });';
echo '}';
echo 'var btnGoReply = document.getElementById("btnGoReply");';
echo 'if (btnGoReply) {';
echo '  btnGoReply.addEventListener("click", function() {';
echo '    var replySection = document.getElementById("reply-section");';
echo '    if (replySection) {';
echo '      replySection.scrollIntoView({ behavior: "smooth", block: "center" });';
echo '      var textarea = replySection.querySelector("textarea");';
echo '      if (textarea) textarea.focus();';
echo '    }';
echo '  });';
echo '}';
echo 'var authorOnlyToggle = document.getElementById("author-only-toggle");';
echo 'var btnAuthorOnly = document.getElementById("btnAuthorOnly");';
echo 'var authorOnlyMode = false;';

echo 'var btnFavorite = document.getElementById("btnFavorite");';
echo 'function updateFavoriteUI(isFavorited) {';
echo '  var headerForm = document.querySelector(".favorite-form");';
echo '  var headerBtn = headerForm ? headerForm.querySelector("button[type=submit]") : null;';
echo '  var headerIcon = headerForm ? headerForm.querySelector(".favorite-icon") : null;';
echo '  var headerText = headerForm ? headerForm.querySelector(".favorite-text") : null;';
echo '  if (headerBtn) {';
echo '    if (isFavorited) { headerBtn.classList.add("active"); headerBtn.setAttribute("title", "取消收藏"); }';
echo '    else { headerBtn.classList.remove("active"); headerBtn.setAttribute("title", "收藏"); }';
echo '  }';
echo '  if (headerIcon) headerIcon.textContent = isFavorited ? "★" : "☆";';
echo '  if (headerText) headerText.textContent = isFavorited ? "取消收藏" : "收藏";';
echo '  if (btnFavorite) {';
echo '    if (isFavorited) { btnFavorite.classList.add("active"); btnFavorite.setAttribute("title", "取消收藏"); btnFavorite.setAttribute("data-favorited", "1"); }';
echo '    else { btnFavorite.classList.remove("active"); btnFavorite.setAttribute("title", "收藏"); btnFavorite.setAttribute("data-favorited", "0"); }';
echo '    var svg = btnFavorite.querySelector("svg");';
echo '    if (svg) svg.setAttribute("fill", isFavorited ? "currentColor" : "none");';
echo '  }';
echo '}';
echo 'function toggleFavorite(postId) {';
echo '  if (!postId || btnFavorite && btnFavorite.disabled) return;';
echo '  if (btnFavorite) btnFavorite.disabled = true;';
echo '  var formData = new FormData();';
echo '  formData.append("post_id", postId);';
echo '  var xhr = new XMLHttpRequest();';
echo '  xhr.open("POST", "/favorite_toggle.php", true);';
echo '  xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");';
echo '  xhr.onload = function() {';
echo '    if (btnFavorite) btnFavorite.disabled = false;';
echo '    try {';
echo '      var data = JSON.parse(xhr.responseText);';
echo '      if (data.success) { updateFavoriteUI(data.favorited); showToast(data.message); }';
echo '    } catch (err) { console.error("收藏操作失败", err); }';
echo '  };';
echo '  xhr.onerror = function() { if (btnFavorite) btnFavorite.disabled = false; console.error("网络错误"); };';
echo '  xhr.send(formData);';
echo '}';
echo 'if (btnFavorite) {';
echo '  btnFavorite.addEventListener("click", function() {';
echo '    var postId = btnFavorite.getAttribute("data-post-id");';
echo '    toggleFavorite(postId);';
echo '  });';
echo '}';
echo 'var favForm = document.querySelector(".favorite-form");';
echo 'if (favForm) {';
echo '  favForm.addEventListener("submit", function(e) {';
echo '    e.preventDefault();';
echo '    var postId = favForm.getAttribute("data-post-id");';
echo '    toggleFavorite(postId);';
echo '  });';
echo '}';

echo 'function toggleAuthorOnly() {';
echo '  authorOnlyMode = !authorOnlyMode;';
echo '  var commentItems = document.querySelectorAll(".comment-item");';
echo '  for (var i = 0; i < commentItems.length; i++) {';
echo '    var item = commentItems[i];';
echo '    if (authorOnlyMode) {';
echo '      if (item.getAttribute("data-is-author") === "1") {';
echo '        item.style.display = "";';
echo '      } else {';
echo '        item.style.display = "none";';
echo '      }';
echo '    } else {';
echo '      item.style.display = "";';
echo '    }';
echo '  }';
echo '  if (btnAuthorOnly) {';
echo '    if (authorOnlyMode) btnAuthorOnly.classList.add("active");';
echo '    else btnAuthorOnly.classList.remove("active");';
echo '  }';
echo '  if (authorOnlyToggle) authorOnlyToggle.checked = authorOnlyMode;';
echo '}';
echo 'if (authorOnlyToggle) {';
echo '  authorOnlyToggle.addEventListener("change", toggleAuthorOnly);';
echo '}';
echo 'if (btnAuthorOnly) {';
echo '  btnAuthorOnly.addEventListener("click", toggleAuthorOnly);';
echo '}';
echo 'var commentAnchors = document.querySelectorAll(".comment-anchor");';
echo 'for (var j = 0; j < commentAnchors.length; j++) {';
echo '  (function(anchor) {';
echo '    anchor.addEventListener("click", function(e) {';
echo '      e.preventDefault();';
echo '      var href = anchor.getAttribute("href");';
echo '      var fullUrl = window.location.origin + window.location.pathname + window.location.search + href;';
echo '      var input = document.createElement("input");';
echo '      input.value = fullUrl;';
echo '      document.body.appendChild(input);';
echo '      input.select();';
echo '      try { document.execCommand("copy"); showToast("评论链接已复制"); }';
echo '      catch (err) { showToast("复制失败"); }';
echo '      document.body.removeChild(input);';
echo '      if (history.pushState) history.pushState(null, "", href);';
echo '      var target = document.querySelector(href);';
echo '      if (target) target.scrollIntoView({ behavior: "smooth", block: "center" });';
echo '    });';
echo '  })(commentAnchors[j]);';
echo '}';
echo 'function showToast(msg) {';
echo '  var toast = document.createElement("div");';
echo '  toast.className = "reading-toast";';
echo '  toast.textContent = msg;';
echo '  document.body.appendChild(toast);';
echo '  setTimeout(function() { toast.classList.add("show"); }, 10);';
echo '  setTimeout(function() {';
echo '    toast.classList.remove("show");';
echo '    setTimeout(function() { toast.remove(); }, 300);';
echo '  }, 2000);';
echo '}';
echo 'window.showToast = showToast;';
echo 'if (window.location.hash) {';
echo '  setTimeout(function() {';
echo '    var target = document.querySelector(window.location.hash);';
echo '    if (target) target.scrollIntoView({ behavior: "smooth", block: "center" });';
echo '  }, 100);';
echo '}';
echo '})();';
echo '</script>';

echo '<style>';
echo '.hot-recommend-item{padding:.75rem 1rem;border-radius:.5rem;background:#f8f9fa;transition:all .2s;height:100%;}';
echo '.hot-recommend-item:hover{background:#e9ecef;transform:translateY(-2px);}';
echo '.hot-recommend-title{color:#212529;font-size:.95rem;font-weight:500;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;line-height:1.4;}';
echo '.hot-recommend-meta{margin-top:.5rem;display:flex;align-items:center;}';
echo '.hot-comment-badge{font-size:.75rem;color:#495057;background:#dee2e6;padding:.15rem .5rem;border-radius:1rem;}';
echo '';
echo '.btn-favorite{color:#ffc107;border-color:#ffc107;background:transparent;transition:all .2s;}';
echo '.btn-favorite:hover{background:#fff3cd;border-color:#ffc107;color:#ffc107;}';
echo '.btn-favorite.active{background:#ffc107;border-color:#ffc107;color:#fff;}';
echo '.btn-favorite.active:hover{background:#ffb300;border-color:#ffb300;color:#fff;}';
echo '.favorite-icon{font-size:1rem;line-height:1;}';
echo '';
echo '.comment-item{transition:all .2s ease;}';
echo '.comment-is-author{background:linear-gradient(135deg, rgba(44,62,80,.03) 0%, rgba(26,188,156,.03) 100%);border-color:rgba(44,62,80,.15)!important;}';
echo '.comment-anchor{transition:color .2s;}';
echo '.comment-anchor:hover{color:var(--bs-primary)!important;}';
echo '';
echo '.reading-toolbar{position:fixed;right:24px;top:50%;transform:translateY(-50%);z-index:1050;display:flex;flex-direction:column;align-items:center;gap:8px;padding:12px 8px;background:#fff;border-radius:999px;box-shadow:0 8px 24px rgba(0,0,0,.12);border:1px solid rgba(0,0,0,.06);opacity:0;pointer-events:none;transition:opacity .3s ease;}';
echo '.reading-toolbar-progress{position:absolute;left:0;top:0;width:3px;background:linear-gradient(180deg,var(--bs-primary),var(--bs-success));border-radius:3px 0 0 3px;transition:height .1s linear;}';
echo '.reading-toolbar-btn{width:36px;height:36px;border:none;background:transparent;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#6c757d;cursor:pointer;transition:all .2s ease;}';
echo '.reading-toolbar-btn:hover{background:rgba(44,62,80,.08);color:var(--bs-primary);transform:scale(1.1);}';
echo '.reading-toolbar-btn.active{background:var(--bs-primary);color:#fff;}';
echo '.reading-toolbar-btn.active:hover{background:var(--bs-primary);color:#fff;transform:scale(1.1);}';
echo '.reading-toolbar-btn.favorite-btn.active{background:#ffc107;color:#fff;}';
echo '.reading-toolbar-btn.favorite-btn.active:hover{background:#ffb300;color:#fff;}';
echo '.reading-toolbar-percent{font-size:.65rem;color:#adb5bd;font-weight:500;line-height:1;}';
echo '';
echo '.reading-toast{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%) scale(.9);background:rgba(0,0,0,.8);color:#fff;padding:12px 24px;border-radius:8px;font-size:.9rem;z-index:9999;opacity:0;transition:all .3s ease;pointer-events:none;}';
echo '.reading-toast.show{opacity:1;transform:translate(-50%,-50%) scale(1);}';
echo '';
echo '@media (max-width:768px){';
echo '  .reading-toolbar{right:12px;padding:10px 6px;gap:6px;}';
echo '  .reading-toolbar-btn{width:32px;height:32px;}';
echo '  .reading-toolbar-percent{display:none;}';
echo '}';
echo '</style>';

render_footer();

