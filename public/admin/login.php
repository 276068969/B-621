<?php
declare(strict_types=1);

/*
 * 后台登录：
 * - 简单账号密码验证（独立于用户体系）
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

if (admin_is_logged_in()) {
    redirect('/admin/index.php');
}

try {
    $pdo = db($config);
} catch (Throwable $e) {
    render_header($config, ['title' => '后台登录 - Lite Forum', 'active' => 'admin']);
    echo '<div class="card card-lite p-4">数据库连接失败</div>';
    render_footer();
    exit;
}

$errors = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $rlConfig = $config['rate_limit']['admin_login'];
    $ipLimiter = new RateLimiter($pdo, 'admin_login_ip', $rlConfig['ip_max'], $rlConfig['ip_window']);
    $accountLimiter = new RateLimiter($pdo, 'admin_login_account', $rlConfig['account_max'], $rlConfig['account_window']);

    if ($ipLimiter->isLimited()) {
        $retryAfter = $ipLimiter->getRetryAfterSeconds();
        $errors['form'] = '登录过于频繁，请 ' . $retryAfter . ' 秒后再试。';
    } elseif ($username !== '' && $accountLimiter->isLimitedByIdentifier('admin:' . $username)) {
        $retryAfter = $accountLimiter->getRetryAfterSecondsByIdentifier('admin:' . $username);
        $errors['form'] = '该管理员账号登录失败次数过多，请 ' . $retryAfter . ' 秒后再试。';
    }

    if ($username === '' || $password === '') {
        if (!isset($errors['form'])) {
            $errors['form'] = '请输入管理员账号和密码。';
        }
    } elseif (!isset($errors['form'])) {
        $ipLimiter->increment();
        $admin = $config['admin'];
        if ($username === (string)$admin['username'] && $password === (string)$admin['password']) {
            admin_login();
            flash_set('success', '后台登录成功。');
            redirect('/admin/index.php');
        }
        $accountLimiter->incrementByIdentifier('admin:' . $username);
        $errors['form'] = '账号或密码错误。';
    }
}

render_header($config, ['title' => '后台登录 - Lite Forum', 'active' => 'admin']);

echo '<div class="row justify-content-center">';
echo '<div class="col-12 col-lg-6">';
echo '<div class="card card-lite border-0 shadow-lg">';
echo '<div class="card-body p-5">';
echo '<div class="text-center mb-4">';
echo '<div class="bg-warning bg-opacity-10 d-inline-block rounded-circle p-3 mb-3">';
echo '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="text-warning bi bi-shield-lock-fill" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8 0c-.69 0-1.843.265-2.928.56-1.11.3-2.229.655-2.887.87a1.54 1.54 0 0 0-1.044 1.262c-.596 4.477.787 7.795 2.465 9.99a11.777 11.777 0 0 0 2.517 2.453c.386.273.744.482 1.048.625.28.132.581.24.829.24s.548-.108.829-.24a7.159 7.159 0 0 0 1.048-.625 11.775 11.775 0 0 0 2.517-2.453c1.678-2.195 3.061-5.513 2.465-9.99a1.541 1.541 0 0 0-1.044-1.263 62.467 62.467 0 0 0-2.887-.87C9.843.266 8.69 0 8 0zm0 5a1.5 1.5 0 0 1 .5 2.915l.385 1.99a.5.5 0 0 1-.491.595h-.788a.5.5 0 0 1-.49-.595l.384-1.99A1.5 1.5 0 0 1 8 5z"/></svg>';
echo '</div>';
echo '<h1 class="h3 fw-bold mb-1">后台管理</h1>';
echo '<div class="text-muted">请输入管理员凭证</div>';
echo '</div>';

echo '<form method="post" class="needs-validation" novalidate>'; 
if (isset($errors['form'])) {
    echo '<div class="alert alert-danger d-flex align-items-center" role="alert"><span class="me-2">❌</span>' . e($errors['form']) . '</div>';
}

echo '<div class="form-floating mb-3">';
echo '<input class="form-control" name="username" id="adminUser" placeholder="管理员账号" required value="' . e($username) . '">';
echo '<label for="adminUser">管理员账号</label>';
echo '<div class="invalid-feedback">请输入管理员账号。</div>';
echo '</div>';

echo '<div class="form-floating mb-4">';
echo '<input type="password" class="form-control" name="password" id="adminPass" placeholder="密码" required>'; 
echo '<label for="adminPass">密码</label>';
echo '<div class="invalid-feedback">请输入密码。</div>';
echo '</div>';

echo '<button class="btn btn-primary w-100 py-3 fw-bold fs-5 shadow-sm mb-3" type="submit">进入后台</button>';
echo '<div class="text-center">';
echo '<a class="text-decoration-none text-muted" href="/index.php">返回前台首页</a>';
echo '</div>';

echo '</form>';
echo '</div></div></div></div>';

echo '<script>';
echo '(() => {';
echo '  "use strict";';
echo '  const forms = document.querySelectorAll(".needs-validation");';
echo '  Array.from(forms).forEach(form => {';
echo '    form.addEventListener("submit", event => {';
echo '      if (!form.checkValidity()) {';
echo '        event.preventDefault();';
echo '        event.stopPropagation();';
echo '        showModal("登录失败", "请输入完整的账号和密码。");';
echo '      }';
echo '      form.classList.add("was-validated");';
echo '    }, false);';
echo '  });';
echo '})();';
echo '</script>';

render_footer();

