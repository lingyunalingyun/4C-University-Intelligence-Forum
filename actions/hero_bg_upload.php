<?php
/*
 * actions/hero_bg_upload.php
 * 功能：上传或删除首页 Hero 背景大图
 * 权限：需登录 + admin/owner 角色
 * 写库：site_settings（key='hero_bg'）
 */
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

// 权限检查
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'owner'])) {
    echo json_encode(['ok' => false, 'error' => '无权限']); exit;
}

$action = $_POST['action'] ?? '';

/* ── 删除背景图 ── */
if ($action === 'delete') {
    // 查出当前路径，删除文件
    $r = $conn->query("SELECT `value` FROM site_settings WHERE `key`='hero_bg' LIMIT 1");
    if ($r && ($row = $r->fetch_assoc()) && $row['value']) {
        $old_path = __DIR__ . '/../' . $row['value'];
        if (file_exists($old_path)) @unlink($old_path);
    }
    $conn->query("DELETE FROM site_settings WHERE `key`='hero_bg'");
    log_admin_action($conn, $_SESSION['user_id'], 'delete_hero_bg', 'site_settings', 0, '删除首页背景图');
    echo json_encode(['ok' => true]); exit;
}

/* ── 上传新背景图 ── */
if ($action === 'upload') {
    if (empty($_FILES['hero_bg']) || $_FILES['hero_bg']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok' => false, 'error' => '文件上传失败']); exit;
    }
    $file = $_FILES['hero_bg'];

    // 校验文件类型（MIME + 扩展名双重校验）
    $allowed_mime = ['image/jpeg', 'image/png', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed_mime)) {
        echo json_encode(['ok' => false, 'error' => '仅支持 JPG/PNG/WebP 格式']); exit;
    }

    // 校验文件大小（5MB 限制）
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['ok' => false, 'error' => '文件大小不能超过 5MB']); exit;
    }

    // 确保 uploads/hero/ 目录存在
    $dir = __DIR__ . '/../uploads/hero/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    // 按 MIME 确定扩展名，固定文件名（每次上传覆盖旧图）
    $ext_map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $ext     = $ext_map[$mime];
    $filename = 'hero-bg.' . $ext;
    $dest     = $dir . $filename;

    // 删除同目录下可能存在的其他格式旧文件
    foreach (['jpg', 'png', 'webp'] as $e) {
        $old = $dir . 'hero-bg.' . $e;
        if (file_exists($old) && $old !== $dest) @unlink($old);
    }

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        echo json_encode(['ok' => false, 'error' => '文件保存失败']); exit;
    }

    // 存储相对路径（相对于项目根目录）到 site_settings
    $rel_path = 'uploads/hero/' . $filename;
    $safe_path = $conn->real_escape_string($rel_path);
    $conn->query("INSERT INTO site_settings (`key`,`value`) VALUES ('hero_bg','$safe_path')
        ON DUPLICATE KEY UPDATE `value`='$safe_path'");

    log_admin_action($conn, $_SESSION['user_id'], 'upload_hero_bg', 'site_settings', 0, $rel_path);
    echo json_encode(['ok' => true, 'path' => $rel_path]); exit;
}

echo json_encode(['ok' => false, 'error' => '未知操作']);
