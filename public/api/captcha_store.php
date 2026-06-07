<?php
declare(strict_types=1);

/*
 * 前端生成验证码后写入 Session，用于后端验证。
 * - POST: code=1234
 * - Response: { ok: true }
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

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

