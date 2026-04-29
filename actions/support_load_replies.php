<?php
/*
 * actions/support_load_replies.php — 加载工单回复（AJAX GET）
 * 功能：返回指定工单的所有回复列表及工单状态，供前端动态渲染。
 * 读库：support_tickets / support_replies / users
 * 权限：需登录；用户只能查看自己的工单，管理员可查看所有
 */
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo json_encode(['ok'=>false]); exit; }

$uid       = intval($_SESSION['user_id']);
$role      = $_SESSION['role'] ?? 'user';
$is_admin  = in_array($role, ['admin','owner']);
$ticket_id = intval($_GET['ticket_id'] ?? 0);
if (!$ticket_id) { echo json_encode(['ok'=>false]); exit; }

if ($is_admin) {
    $chk = $conn->query("SELECT status,subject,content,ai_context,category,created_at FROM support_tickets WHERE id=$ticket_id");
} else {
    $chk = $conn->query("SELECT status,subject,content,ai_context,category,created_at FROM support_tickets WHERE id=$ticket_id AND user_id=$uid");
}
if (!$chk || $chk->num_rows === 0) { echo json_encode(['ok'=>false]); exit; }
$tk = $chk->fetch_assoc();

$replies = [];
$rr = $conn->query("SELECT r.content,r.is_admin,r.created_at,u.username
    FROM support_replies r JOIN users u ON u.id=r.user_id
    WHERE r.ticket_id=$ticket_id ORDER BY r.created_at ASC");
if ($rr) while ($row = $rr->fetch_assoc()) $replies[] = $row;

echo json_encode([
    'ok'      => true,
    'status'  => $tk['status'],
    'subject' => $tk['subject'],
    'content' => $tk['content'],
    'ai_context' => $tk['ai_context'],
    'replies' => $replies,
]);
