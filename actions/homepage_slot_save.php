<?php
require_once '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin','owner'])) {
    echo json_encode(['ok' => false, 'error' => '无权限']); exit;
}

$action   = $_POST['action']   ?? '';
$position = intval($_POST['position'] ?? 0);

if ($position < 1 || $position > 6) {
    echo json_encode(['ok' => false, 'error' => '无效的槽位编号']); exit;
}

if ($action === 'clear') {
    $conn->query("DELETE FROM homepage_slots WHERE position = $position");
    echo json_encode(['ok' => true]); exit;
}

if ($action === 'set') {
    $post_id = intval($_POST['post_id'] ?? 0);
    if (!$post_id) {
        echo json_encode(['ok' => false, 'error' => '缺少帖子 ID']); exit;
    }
    $chk = $conn->query("SELECT id FROM posts WHERE id = $post_id AND status = 'published'");
    if (!$chk || $chk->num_rows === 0) {
        echo json_encode(['ok' => false, 'error' => '帖子不存在或未发布']); exit;
    }
    $conn->query("INSERT INTO homepage_slots (position, post_id) VALUES ($position, $post_id)
        ON DUPLICATE KEY UPDATE post_id = $post_id, updated_at = NOW()");
    echo json_encode(['ok' => true]); exit;
}

echo json_encode(['ok' => false, 'error' => '未知操作']);
