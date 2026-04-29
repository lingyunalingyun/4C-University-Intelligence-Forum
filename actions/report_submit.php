<?php
/*
 * actions/report_submit.php — 提交举报
 * 功能：接收帖子/用户举报，校验重复（24h内同一对象只能举报一次），写入 reports 表，返回 JSON。
 * 写库：reports
 * 权限：需登录
 */
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok'=>false,'msg'=>'请先登录']);
    exit;
}

$uid    = intval($_SESSION['user_id']);
$type   = $_POST['type']      ?? '';
$tid    = intval($_POST['target_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$detail = trim($_POST['detail'] ?? '');

if (!in_array($type, ['post','user','comment']) || $tid <= 0 || $reason === '') {
    echo json_encode(['ok'=>false,'msg'=>'参数错误']);
    exit;
}

// 不能举报自己
if ($type === 'user' && $tid === $uid) {
    echo json_encode(['ok'=>false,'msg'=>'不能举报自己']);
    exit;
}

// 验证被举报对象是否存在
if ($type === 'post') {
    $chk = $conn->query("SELECT id FROM posts WHERE id=$tid AND status='published'");
} elseif ($type === 'user') {
    $chk = $conn->query("SELECT id FROM users WHERE id=$tid");
} else {
    $chk = $conn->query("SELECT id FROM comments WHERE id=$tid");
}
if (!$chk || $chk->num_rows === 0) {
    echo json_encode(['ok'=>false,'msg'=>'举报对象不存在']);
    exit;
}

// 24 小时内重复举报检测
$dup = $conn->query("SELECT id FROM reports
    WHERE reporter_id=$uid AND type='$type' AND target_id=$tid
    AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
if ($dup && $dup->num_rows > 0) {
    echo json_encode(['ok'=>false,'msg'=>'您已举报过该内容，24小时内请勿重复举报']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO reports (reporter_id,type,target_id,reason,detail) VALUES (?,?,?,?,?)");
$stmt->bind_param('isiss', $uid, $type, $tid, $reason, $detail);
if ($stmt->execute()) {
    echo json_encode(['ok'=>true,'msg'=>'举报已提交，我们将尽快处理']);
} else {
    echo json_encode(['ok'=>false,'msg'=>'提交失败，请稍后再试']);
}
$stmt->close();
