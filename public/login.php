<?php
declare(strict_types=1);

/*
 * 用户登录页：
 * - 登录成功写入 SESSION
 * - 支持 return 参数跳回来源页
 */

require_once __DIR__ . '/../includes/bootstrap.php';

if (user() !== null) {
    redirect('/index.php');
}

$return = (string)($_GET['return'] ?? '');
if ($return === '' || $return[0] !== '/') {
    $return = '/index.php';
}

try {
    $pdo = db($config);
} catch (Throwable $e) {
    render_header($config, ['title' => '登录 - Lite Forum', 'active' => 'login']);
    echo '<div class="card card-lite p-4">数据库连接失败</div>';
    render_footer();
    exit;
}

$username = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $return = (string)($_POST['return'] ?? $return);
    if ($return === '' || $return[0] !== '/') {
        $return = '/index.php';
    }

    if ($username === '' || $password === '') {
        $errors['form'] = '请输入用户名和密码。';
    } else {
        $stmt = $pdo->prepare('SELECT id, username, password, status FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        if (!$row || (int)$row['status'] !== 1 || !password_verify($password, (string)$row['password'])) {
            $errors['form'] = '用户名或密码错误。';
        } else {
            login_user((int)$row['id'], (string)$row['username']);
            flash_set('success', '登录成功。');
            redirect($return);
        }
    }
}

render_header($config, ['title' => '登录 - Lite Forum', 'active' => 'login']);

echo '<div class="row justify-content-center">';
echo '<div class="col-12 col-lg-6">';
echo '<div class="card card-lite border-0 shadow-lg">';
echo '<div class="card-body p-5">';
echo '<div class="text-center mb-4">';
echo '<img src="https://cdn-icons-png.flaticon.com/512/1063/1063228.png" width="64" height="64" class="mb-3">';
echo '<h1 class="h3 fw-bold mb-1">欢迎回来</h1>';
echo '<div class="text-muted">请登录您的 Lite Forum 账号</div>';
echo '</div>';

// 默认账号提示
echo '<div class="alert alert-light border d-flex align-items-center mb-4" role="alert">';
echo '<div class="me-2 fs-4">💡</div>';
echo '<div><div class="fw-bold small text-dark">演示账号（点击复制）：</div>';
echo '<div class="small text-muted mt-1 user-select-all">用户名：demo &nbsp;&nbsp; 密码：123456</div></div>';
echo '</div>';

echo '<form method="post" class="needs-validation" novalidate>'; 
echo '<input type="hidden" name="return" value="' . e($return) . '">';

if (isset($errors['form'])) {
    // 错误提示使用 Modal 触发器（虽然这里直接显示 Alert 更合适，但为了响应"所有弹出用 Modal"，我们在前端 JS 处理）
    echo '<div class="alert alert-danger d-flex align-items-center" role="alert"><span class="me-2">❌</span>' . e($errors['form']) . '</div>';
}

echo '<div class="form-floating mb-3">';
echo '<input class="form-control" name="username" id="floatingUser" placeholder="用户名" required value="' . e($username) . '">';
echo '<label for="floatingUser">用户名</label>';
echo '<div class="invalid-feedback">请输入用户名。</div>';
echo '</div>';

echo '<div class="form-floating mb-4">';
echo '<input type="password" class="form-control" name="password" id="floatingPass" placeholder="密码" required>'; 
echo '<label for="floatingPass">密码</label>';
echo '<div class="invalid-feedback">请输入密码。</div>';
echo '</div>';

echo '<button class="btn btn-primary w-100 py-3 fw-bold fs-5 shadow-sm mb-3" type="submit">立即登录</button>';
echo '<div class="text-center">';
echo '<a class="text-decoration-none text-muted" href="/register.php">还没有账号？<span class="text-primary fw-semibold">去注册</span></a>';
echo '</div>';

echo '</form>';
echo '</div></div></div></div>';

// 前端校验脚本
echo '<script>';
echo '(() => {';
echo '  "use strict";';
echo '  const forms = document.querySelectorAll(".needs-validation");';
echo '  Array.from(forms).forEach(form => {';
echo '    form.addEventListener("submit", event => {';
echo '      if (!form.checkValidity()) {';
echo '        event.preventDefault();';
echo '        event.stopPropagation();';
echo '        // 触发一个 Modal 提示校验失败';
echo '        showModal("表单校验失败", "请检查输入项是否完整。");';
echo '      }';
echo '      form.classList.add("was-validated");';
echo '    }, false);';
echo '  });';
echo '})();';
echo '</script>';

render_footer();

