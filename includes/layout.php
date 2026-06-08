<?php
declare(strict_types=1);

/*
 * 统一布局：
 * - Bootstrap 5.3 CDN + 主题色覆盖
 * - 固定顶部导航 + 居中内容区 + 极简底部
 */

function render_header(array $config, array $options = []): void
{
    $title = $options['title'] ?? 'Lite Forum';
    $active = $options['active'] ?? '';
    $u = user();
    $isAdmin = admin_is_logged_in();
    $flash = flash_get();
    $ui = $config['ui'];

    $primary = $ui['primary'];
    $success = $ui['success'];
    $danger = $ui['danger'];
    $maxWidth = $ui['max_width'];

    echo '<!doctype html><html lang="zh-CN"><head>';
    echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title) . '</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<style>';
    echo ':root{--bs-primary:#2c3e50;--bs-success:#1abc9c;--bs-danger:#dc3545;}';
    echo 'body{background:#f6f8fb;}';
    echo '.app-container{max-width:1200px;}';
    echo '.card-lite{border:0;border-radius:.25rem;box-shadow:0 .125rem .25rem rgba(0,0,0,.075);transition:transform .2s ease-in-out, box-shadow .2s ease-in-out;}';
    echo '.card-lite:hover{transform:translateY(-4px);box-shadow:0 .5rem 1rem rgba(0,0,0,.15);}';
    echo '.btn-primary{background-color:var(--bs-primary);border-color:var(--bs-primary);}';
    echo '.btn-outline-primary{color:var(--bs-primary);border-color:var(--bs-primary);}';
    echo '.btn-outline-primary:hover{background-color:var(--bs-primary);color:#fff;}';
    echo '.text-primary{color:var(--bs-primary)!important;}';
    echo '.bg-primary{background-color:var(--bs-primary)!important;}';
    echo '.text-success{color:var(--bs-success)!important;}';
    echo '.bg-success{background-color:var(--bs-success)!important;}';
    echo '.text-bg-success{background-color:var(--bs-success)!important;}';
    echo '.btn-success{background-color:var(--bs-success);border-color:var(--bs-success);}';
    echo '.btn-outline-secondary:hover{background-color:#6c757d;color:#fff;}';
    echo '.btn{border-radius:.25rem;}';
    echo '.btn:hover{opacity:0.9;}';
    echo '.form-control, .form-select{border-radius:.25rem;}';
    echo '.form-control:focus, .form-select:focus{border-color:var(--bs-primary);box-shadow:0 0 0 .2rem rgba(44,62,80,.15);}';
    echo '.required-star{color:var(--bs-danger);margin-left:.25rem;}';
    echo '.tiny-editor-shell{border:1px solid #dee2e6;border-radius:.25rem;overflow:hidden;background:#fff;}';
    echo '.invalid-feedback{display:none;width:100%;margin-top:.25rem;font-size:.875em;color:var(--bs-danger);}';
    echo '.was-validated .form-control:invalid ~ .invalid-feedback, .was-validated .form-select:invalid ~ .invalid-feedback{display:block;}';
    echo '</style>';
    echo '</head><body>';

    $isBackend = str_starts_with(current_url_path(), '/admin/');

    if ($isBackend) {
        // 后台专用 Topbar
        echo '<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top shadow-sm">';
        echo '<div class="container-fluid app-container">';
        echo '<a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="/admin/index.php">';
        echo '<div class="bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center" style="width:28px;height:28px;"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-shield-lock-fill" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8 0c-.69 0-1.843.265-2.928.56-1.11.3-2.229.655-2.887.87a1.54 1.54 0 0 0-1.044 1.262c-.596 4.477.787 7.795 2.465 9.99a11.777 11.777 0 0 0 2.517 2.453c.386.273.744.482 1.048.625.28.132.581.24.829.24s.548-.108.829-.24a7.159 7.159 0 0 0 1.048-.625 11.775 11.775 0 0 0 2.517-2.453c1.678-2.195 3.061-5.513 2.465-9.99a1.541 1.541 0 0 0-1.044-1.263 62.467 62.467 0 0 0-2.887-.87C9.843.266 8.69 0 8 0zm0 5a1.5 1.5 0 0 1 .5 2.915l.385 1.99a.5.5 0 0 1-.491.595h-.788a.5.5 0 0 1-.49-.595l.384-1.99A1.5 1.5 0 0 1 8 5z"/></svg></div>';
        echo 'Lite Forum 后台管理</a>';
        echo '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navAdmin" aria-controls="navAdmin" aria-expanded="false" aria-label="Toggle navigation">';
        echo '<span class="navbar-toggler-icon"></span></button>';
        echo '<div class="collapse navbar-collapse" id="navAdmin">';
        echo '<ul class="navbar-nav me-auto mb-2 mb-lg-0">';
        echo '<li class="nav-item"><a class="nav-link ' . ($active === 'admin' ? 'active fw-bold text-white' : '') . '" href="/admin/index.php">概览</a></li>';
        echo '<li class="nav-item"><a class="nav-link ' . ($active === 'posts' ? 'active fw-bold text-white' : '') . '" href="/admin/posts.php">帖子管理</a></li>';
        echo '<li class="nav-item"><a class="nav-link ' . ($active === 'comments' ? 'active fw-bold text-white' : '') . '" href="/admin/comments.php">评论管理</a></li>';
        echo '</ul>';
        echo '<ul class="navbar-nav ms-auto align-items-center gap-3">';
        echo '<li class="nav-item"><a class="btn btn-outline-light btn-sm rounded-pill px-3" href="/index.php" target="_blank">预览前台</a></li>';
        echo '<li class="nav-item"><a class="nav-link text-danger fw-semibold" href="/admin/logout.php">退出后台</a></li>';
        echo '</ul></div></div></nav>';
    } else {
        // 前台 Topbar (原有逻辑)
        echo '<nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top shadow-sm" style="background-color: var(--bs-primary) !important;">';
        echo '<div class="container-fluid app-container">';
        echo '<a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="/index.php">';
        echo '<img src="https://cdn-icons-png.flaticon.com/512/1063/1063228.png" alt="Logo" width="28" height="28" class="d-inline-block align-text-top">';
        echo 'Lite Forum</a>';
        echo '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain" aria-controls="navMain" aria-expanded="false" aria-label="Toggle navigation">';
        echo '<span class="navbar-toggler-icon"></span></button>';
        echo '<div class="collapse navbar-collapse" id="navMain">';
        echo '<ul class="navbar-nav me-auto mb-2 mb-lg-0">';
        
        // 导航项样式：高亮+下划线
        $navItemClass = 'nav-link px-3 position-relative';
        $activeStyle = ' font-weight:600; color:#fff !important;';
        $activeIndicator = '<span class="position-absolute bottom-0 start-0 w-100 bg-white" style="height:3px; border-radius:3px 3px 0 0;"></span>';
        
        echo '<li class="nav-item position-relative">';
        echo '<a class="' . $navItemClass . '" style="' . ($active === 'home' ? $activeStyle : '') . '" href="/index.php">帖子列表</a>';
        if ($active === 'home') echo $activeIndicator;
        echo '</li>';
        
        if ($u !== null) {
            echo '<li class="nav-item position-relative">';
            echo '<a class="' . $navItemClass . '" style="' . ($active === 'post_add' ? $activeStyle : '') . '" href="/post_add.php">发布新帖</a>';
            if ($active === 'post_add') echo $activeIndicator;
            echo '</li>';
        }
        
        echo '</ul>';
        echo '<ul class="navbar-nav ms-auto align-items-center gap-2">';
        if ($u === null) {
            echo '<li class="nav-item position-relative">';
            echo '<a class="' . $navItemClass . '" style="' . ($active === 'register' ? $activeStyle : '') . '" href="/register.php">注册账号</a>';
            if ($active === 'register') echo $activeIndicator;
            echo '</li>';
            
            echo '<li class="nav-item position-relative">';
            echo '<a class="' . $navItemClass . '" style="' . ($active === 'login' ? $activeStyle : '') . '" href="/login.php">立即登录</a>';
            if ($active === 'login') echo $activeIndicator;
            echo '</li>';
        } else {
            echo '<li class="nav-item dropdown">';
            echo '<a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">';
            echo '你好，' . e($u['username']);
            echo '</a>';
            echo '<ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg animate-slide-up">';
            echo '<li><a class="dropdown-item" href="/profile.php">我的主页</a></li>';
            echo '<li><hr class="dropdown-divider"></li>';
            echo '<li><a class="dropdown-item" href="/logout.php">退出登录</a></li>';
            echo '</ul>';
            echo '</li>';
        }
        if ($isAdmin) {
            echo '<li class="nav-item position-relative ms-lg-2">';
            echo '<a class="btn btn-sm btn-warning fw-semibold px-3 rounded-pill" href="/admin/index.php">后台管理</a>';
            echo '</li>';
        }
        echo '</ul></div></div></nav>';
    }

    echo '<main class="container-fluid app-container" style="padding-top:6rem; min-height: 80vh;">';
    
    // Toast 容器
    echo '<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080; margin-top: 4rem;">';
    if ($flash) {
        $type = in_array($flash['type'], ['success', 'danger', 'warning', 'info'], true) ? $flash['type'] : 'info';
        $bgClass = 'text-bg-' . $type;
        $icon = match($type) {
            'success' => '✅',
            'danger' => '❌',
            'warning' => '⚠️',
            default => 'ℹ️'
        };
        
        echo '<div id="flashToast" class="toast align-items-center ' . $bgClass . ' border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">';
        echo '<div class="d-flex">';
        echo '<div class="toast-body fw-medium fs-6">';
        echo '<span class="me-2">' . $icon . '</span>' . e($flash['message']);
        echo '</div>';
        echo '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>';
        echo '</div></div>';
    }
    echo '</div>';

}

function render_footer(): void
{
    echo '</main>';
    echo '<footer class="py-4 text-center text-muted small">';
    echo '<div class="container-fluid app-container">';
    echo '版权所有 © 2026 Lite Forum';
    echo '</div></footer>';
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>';
    echo '<script>';
    echo 'document.addEventListener("DOMContentLoaded", function() {';
    echo '  var toastEl = document.getElementById("flashToast");';
    echo '  if (toastEl) {';
    echo '    var toast = new bootstrap.Toast(toastEl, { delay: 3000 });';
    echo '    toast.show();';
    echo '  }';
    echo '});';
    
    // 全局 Modal 封装（替代 window.alert）
    echo 'function showModal(title, message) {';
    echo '  const modalHtml = `';
    echo '    <div class="modal fade" id="globalModal" tabindex="-1" aria-hidden="true">';
    echo '      <div class="modal-dialog modal-dialog-centered">';
    echo '        <div class="modal-content border-0 shadow-lg">';
    echo '          <div class="modal-header border-0">';
    echo '            <h5 class="modal-title fw-bold">${title}</h5>';
    echo '            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>';
    echo '          </div>';
    echo '          <div class="modal-body text-muted">${message}</div>';
    echo '          <div class="modal-footer border-0">';
    echo '            <button type="button" class="btn btn-primary px-4 rounded-pill" data-bs-dismiss="modal">确定</button>';
    echo '          </div>';
    echo '        </div>';
    echo '      </div>';
    echo '    </div>`;';
    echo '  const existingModal = document.getElementById("globalModal");';
    echo '  if (existingModal) existingModal.remove();';
    echo '  document.body.insertAdjacentHTML("beforeend", modalHtml);';
    echo '  const modal = new bootstrap.Modal(document.getElementById("globalModal"));';
    echo '  modal.show();';
    echo '}';
    
    // 覆盖原生 alert
    echo 'window.alert = function(msg) { showModal("提示", msg); };';
    
    // 全局 Confirm Modal 封装
    echo 'function showConfirmModal(title, message, confirmUrl, confirmText, confirmClass) {';
    echo '  confirmText = confirmText || "确认删除";';
    echo '  confirmClass = confirmClass || "btn-danger";';
    echo '  const titleClass = confirmClass.includes("danger") ? "text-danger" : "text-success";';
    echo '  const modalHtml = `';
    echo '    <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">';
    echo '      <div class="modal-dialog modal-dialog-centered">';
    echo '        <div class="modal-content border-0 shadow-lg">';
    echo '          <div class="modal-header border-0">';
    echo '            <h5 class="modal-title fw-bold ${titleClass}">${title}</h5>';
    echo '            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>';
    echo '          </div>';
    echo '          <div class="modal-body text-muted">${message}</div>';
    echo '          <div class="modal-footer border-0">';
    echo '            <button type="button" class="btn btn-outline-secondary px-4 rounded-pill" data-bs-dismiss="modal">取消</button>';
    echo '            <a href="${confirmUrl}" class="btn ${confirmClass} px-4 rounded-pill">${confirmText}</a>';
    echo '          </div>';
    echo '        </div>';
    echo '      </div>';
    echo '    </div>`;';
    echo '  const existingModal = document.getElementById("confirmModal");';
    echo '  if (existingModal) existingModal.remove();';
    echo '  document.body.insertAdjacentHTML("beforeend", modalHtml);';
    echo '  const modal = new bootstrap.Modal(document.getElementById("confirmModal"));';
    echo '  modal.show();';
    echo '}';
    
    echo '</script>';
    echo '</body></html>';
}

