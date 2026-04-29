<?php
/*
 * api/upload_image.php — 图片上传 JSON API
 * 功能：接收 multipart 上传，校验 MIME 类型（JPG/PNG/GIF/WebP），
 *       限制 5MB，保存到 uploads/posts/，返回 URL 供 Quill 编辑器插入。
 * 写库：uploads/posts/（文件系统）
 * 权限：需登录，被封禁用户拦截
 */
require_once '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok'=>false,'error'=>'请先登录']); exit;
}
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok'=>false,'error'=>'文件上传失败']); exit;
}

$file = $_FILES['image'];

// 用 finfo 验证真实 MIME 类型（而不是信任客户端的 type 字段）
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);
$allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
if (!isset($allowed[$mime])) {
    echo json_encode(['ok'=>false,'error'=>'仅支持 JPG / PNG / GIF / WEBP 图片']); exit;
}
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['ok'=>false,'error'=>'图片不能超过 5 MB']); exit;
}

$ext      = $allowed[$mime];
$filename = date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
$dest     = __DIR__ . '/../uploads/posts/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['ok'=>false,'error'=>'服务器保存失败']); exit;
}

$path  = '/uploads/posts/' . $filename;
$token = '[img:' . $path . ']';
echo json_encode(['ok'=>true, 'url'=>$path, 'token'=>$token]);
