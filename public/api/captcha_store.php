<?php
declare(strict_types=1);

/*
 * 前端生成验证码后写入 Session，用于后端验证。
 * - POST: code=1234
 * - Response: { ok: true }
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = db($config);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'server_error']);
    exit;
}

$rlConfig = $config['rate_limit']['captcha_store'];
$limiter = new RateLimiter($pdo, 'captcha_store_ip', $rlConfig['ip_max'], $rlConfig['ip_window']);
if ($limiter->isLimited()) {
    $retryAfter = $limiter->getRetryAfterSeconds();
    http_response_code(429);
    echo json_encode(['ok' => false, 'message' => 'rate_limited', 'retry_after' => $retryAfter]);
    exit;
}
$limiter->increment();

$code = trim((string)($_POST['code'] ?? ''));
if (!preg_match('/^\d{4}$/', $code)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'invalid_code']);
    exit;
}

$_SESSION['captcha'] = [
    'code' => $code,
    'ts' => time(),
];

echo json_encode(['ok' => true]);

