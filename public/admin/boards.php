<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/admin_log.php';

admin_require_login();

try {
    $pdo = db($config);
} catch (Throwable $e) {
    render_header($config, ['title' => '版块管理 - Lite Forum', 'active' => 'boards']);
    echo '<div class="card card-lite p-4">数据库连接失败</div>';
    render_footer();
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
        $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;

        if ($name === '' || strlen($name) > 50) {
            $errors['name'] = '版块名称为必填，且不超过 50 字。';
        }
        if (strlen($description) > 200) {
            $errors['description'] = '版块描述不超过 200 字。';
        }

        if (!$errors) {
            $stmt = $pdo->prepare('INSERT INTO boards (name, description, sort_order, status) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $description, $sortOrder, $status]);
            $newId = (int)$pdo->lastInsertId();

            admin_log($pdo, 'board_create', 'board', $newId, "创建版块：{$name}");

            flash_set('success', '版块创建成功。');
            redirect('/admin/boards.php');
        }
    } elseif ($action === 'edit' && $id > 0) {
        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
        $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;

        if ($name === '' || strlen($name) > 50) {
            $errors['name'] = '版块名称为必填，且不超过 50 字。';
        }
        if (strlen($description) > 200) {
            $errors['description'] = '版块描述不超过 200 字。';
        }

        if (!$errors) {
            $stmt = $pdo->prepare('UPDATE boards SET name = ?, description = ?, sort_order = ?, status = ?, update_time = NOW() WHERE id = ?');
            $stmt->execute([$name, $description, $sortOrder, $status, $id]);

            admin_log($pdo, 'board_edit', 'board', $id, "编辑版块：{$name}");

            flash_set('success', '版块更新成功。');
            redirect('/admin/boards.php');
        }
    }
}

if ($action === 'delete' && $id > 0) {
    $board = get_board_by_id($pdo, $id);
    if ($board) {
        $stmt = $pdo->prepare('DELETE FROM boards WHERE id = ?');
        $stmt->execute([$id]);

        admin_log($pdo, 'board_delete', 'board', $id, "删除版块：{$board['name']}");

        flash_set('success', '版块已删除。');
    }
    redirect('/admin/boards.php');
}

if ($action === 'toggle' && $id > 0) {
    $board = get_board_by_id($pdo, $id);
    if ($board) {
        $newStatus = (int)$board['status'] === 1 ? 0 : 1;
        $stmt = $pdo->prepare('UPDATE boards SET status = ?, update_time = NOW() WHERE id = ?');
        $stmt->execute([$newStatus, $id]);

        $statusText = $newStatus === 1 ? '启用' : '禁用';
        admin_log($pdo, 'board_toggle', 'board', $id, "{$statusText}版块：{$board['name']}");

        flash_set('success', "版块已{$statusText}。");
    }
    redirect('/admin/boards.php');
}

if ($action === 'move' && $id > 0) {
    $direction = isset($_GET['dir']) ? $_GET['dir'] : '';
    $board = get_board_by_id($pdo, $id);
    if ($board && in_array($direction, ['up', 'down'], true)) {
        $allBoards = get_boards($pdo, false);
        $currentIndex = -1;
        foreach ($allBoards as $index => $b) {
            if ((int)$b['id'] === $id) {
                $currentIndex = $index;
                break;
            }
        }

        if ($currentIndex !== -1) {
            $targetIndex = $direction === 'up' ? $currentIndex - 1 : $currentIndex + 1;
            if ($targetIndex >= 0 && $targetIndex < count($allBoards)) {
                $targetBoard = $allBoards[$targetIndex];
                $currentOrder = (int)$board['sort_order'];
                $targetOrder = (int)$targetBoard['sort_order'];

                $stmt = $pdo->prepare('UPDATE boards SET sort_order = ? WHERE id = ?');
                $stmt->execute([$targetOrder, $id]);
                $stmt->execute([$currentOrder, (int)$targetBoard['id']]);

                admin_log($pdo, 'board_sort', 'board', $id, "调整版块顺序：{$board['name']} 向" . ($direction === 'up' ? '上' : '下'));

                flash_set('success', '版块顺序已调整。');
            }
        }
    }
    redirect('/admin/boards.php');
}

$boardData = null;
if ($action === 'edit' && $id > 0) {
    $boardData = get_board_by_id($pdo, $id);
    if (!$boardData) {
        flash_set('danger', '版块不存在。');
        redirect('/admin/boards.php');
    }
}

$boards = get_boards($pdo, false);

render_header($config, ['title' => '版块管理 - Lite Forum', 'active' => 'boards']);

echo '<div class="d-flex align-items-center justify-content-between mb-3">';
echo '<div>';
echo '<h1 class="h4 mb-0">版块管理</h1>';
echo '<div class="text-muted small mt-1">共 ' . count($boards) . ' 个版块</div>';
echo '</div>';
echo '<div class="d-flex gap-2">';
if ($action === 'list') {
    echo '<a class="btn btn-primary" href="/admin/boards.php?action=create">+ 新版块</a>';
} else {
    echo '<a class="btn btn-outline-secondary" href="/admin/boards.php">返回列表</a>';
}
echo '<a class="btn btn-outline-secondary" href="/admin/index.php">返回概览</a>';
echo '</div>';
echo '</div>';

if ($action === 'create' || $action === 'edit') {
    $formTitle = $action === 'create' ? '创建新版块' : '编辑版块';
    $nameValue = $boardData ? (string)$boardData['name'] : '';
    $descValue = $boardData ? (string)$boardData['description'] : '';
    $sortValue = $boardData ? (int)$boardData['sort_order'] : 0;
    $statusValue = $boardData ? (int)$boardData['status'] : 1;

    if (isset($_POST['name'])) {
        $nameValue = trim((string)$_POST['name']);
        $descValue = trim((string)$_POST['description']);
        $sortValue = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
        $statusValue = isset($_POST['status']) ? (int)$_POST['status'] : 1;
    }

    echo '<div class="card card-lite">';
    echo '<div class="card-body p-4">';
    echo '<h5 class="fw-bold mb-4">' . e($formTitle) . '</h5>';

    echo '<form method="post" class="needs-validation" novalidate>';

    echo '<div class="mb-3">';
    echo '<label class="form-label fw-semibold" for="name">版块名称 <span class="required-star">*</span></label>';
    echo '<input type="text" class="form-control" id="name" name="name" maxlength="50" required value="' . e($nameValue) . '">';
    echo '<div class="form-text">简短的版块名称，不超过 50 字。</div>';
    echo '<div class="invalid-feedback">请输入版块名称。</div>';
    if (isset($errors['name'])) {
        echo '<div class="text-danger small mt-1">❌ ' . e($errors['name']) . '</div>';
    }
    echo '</div>';

    echo '<div class="mb-3">';
    echo '<label class="form-label fw-semibold" for="description">版块描述</label>';
    echo '<input type="text" class="form-control" id="description" name="description" maxlength="200" value="' . e($descValue) . '">';
    echo '<div class="form-text">简要描述版块内容，不超过 200 字。</div>';
    if (isset($errors['description'])) {
        echo '<div class="text-danger small mt-1">❌ ' . e($errors['description']) . '</div>';
    }
    echo '</div>';

    echo '<div class="row g-3 mb-4">';
    echo '<div class="col-md-6">';
    echo '<label class="form-label fw-semibold" for="sort_order">显示顺序</label>';
    echo '<input type="number" class="form-control" id="sort_order" name="sort_order" value="' . e((string)$sortValue) . '">';
    echo '<div class="form-text">数值越小，排序越靠前。</div>';
    echo '</div>';

    echo '<div class="col-md-6">';
    echo '<label class="form-label fw-semibold">状态</label>';
    echo '<div class="d-flex gap-3 mt-2">';
    echo '<div class="form-check">';
    echo '<input class="form-check-input" type="radio" name="status" id="status_active" value="1"' . ($statusValue === 1 ? ' checked' : '') . '>';
    echo '<label class="form-check-label" for="status_active">启用</label>';
    echo '</div>';
    echo '<div class="form-check">';
    echo '<input class="form-check-input" type="radio" name="status" id="status_disabled" value="0"' . ($statusValue === 0 ? ' checked' : '') . '>';
    echo '<label class="form-check-label" for="status_disabled">禁用</label>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="d-flex gap-2">';
    $submitText = $action === 'create' ? '创建版块' : '保存修改';
    echo '<button type="submit" class="btn btn-primary px-4">' . e($submitText) . '</button>';
    echo '<a class="btn btn-outline-secondary px-4" href="/admin/boards.php">取消</a>';
    echo '</div>';

    echo '</form>';
    echo '</div></div>';
} else {
    echo '<div class="card card-lite">';
    echo '<div class="card-body p-0">';
    echo '<div class="table-responsive">';
    echo '<table class="table table-hover mb-0">';
    echo '<thead class="table-light"><tr>';
    echo '<th class="ps-3" style="width:60px;">排序</th>';
    echo '<th>版块名称</th>';
    echo '<th>描述</th>';
    echo '<th style="width:100px;">帖子数</th>';
    echo '<th style="width:80px;">状态</th>';
    echo '<th class="text-end pe-3" style="width:200px;">操作</th>';
    echo '</tr></thead><tbody>';

    if (!$boards) {
        echo '<tr><td colspan="6" class="text-center py-4 text-muted">暂无版块</td></tr>';
    } else {
        foreach ($boards as $b) {
            $bid = (int)$b['id'];
            $postCount = get_board_post_count($pdo, $bid);
            $statusBadge = (int)$b['status'] === 1
                ? '<span class="badge text-bg-success">启用</span>'
                : '<span class="badge text-bg-secondary">禁用</span>';

            echo '<tr>';
            echo '<td class="ps-3">';
            echo '<div class="d-flex flex-column gap-1">';
            echo '<a class="btn btn-sm btn-outline-secondary" href="/admin/boards.php?action=move&amp;id=' . $bid . '&amp;dir=up" title="上移">↑</a>';
            echo '<a class="btn btn-sm btn-outline-secondary" href="/admin/boards.php?action=move&amp;id=' . $bid . '&amp;dir=down" title="下移">↓</a>';
            echo '</div>';
            echo '</td>';
            echo '<td>';
            echo '<div class="fw-medium">' . e((string)$b['name']) . '</div>';
            echo '<div class="small text-muted">ID: ' . $bid . ' · 排序值: ' . (int)$b['sort_order'] . '</div>';
            echo '</td>';
            echo '<td class="text-muted small">' . e((string)$b['description']) . '</td>';
            echo '<td>' . e((string)$postCount) . '</td>';
            echo '<td>' . $statusBadge . '</td>';
            echo '<td class="text-end pe-3">';
            echo '<a class="btn btn-sm btn-outline-primary" href="/admin/boards.php?action=edit&amp;id=' . $bid . '">编辑</a> ';
            $toggleText = (int)$b['status'] === 1 ? '禁用' : '启用';
            $toggleUrl = '/admin/boards.php?action=toggle&amp;id=' . $bid;
            echo '<a class="btn btn-sm btn-outline-warning" href="' . e($toggleUrl) . '">' . e($toggleText) . '</a> ';
            $delUrl = '/admin/boards.php?action=delete&amp;id=' . $bid;
            $safeName = e(addslashes((string)$b['name']));
            echo '<button class="btn btn-sm btn-outline-danger" onclick="showConfirmModal(\'删除确认\', \'确定要删除版块 <strong>' . $safeName . '</strong> 吗？该版块下的帖子将变为未分类状态。\', \'' . $delUrl . '\', \'确认删除\', \'btn-danger\')">删除</button>';
            echo '</td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table></div></div></div>';
}

render_footer();
