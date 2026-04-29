<?php
/*
 * actions/support_action.php — 客服工单操作接口
 * 功能：创建工单（create）、追加回复（reply）、关闭工单（close）。
 *       管理员回复时自动更新状态为 replied 并推送通知。
 * 写库：support_tickets / support_replies / notifications
 * 权限：需登录；管理员可操作任意工单
 */
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo json_encode(['ok'=>false,'msg'=>'请先登录']); exit; }

$uid    = intval($_SESSION['user_id']);
$role   = $_SESSION['role'] ?? 'user';
$is_admin = in_array($role, ['admin','owner']);
$action = $_POST['action'] ?? '';

// ── 创建工单 ──────────────────────────────────────────
if ($action === 'create') {
    $category   = trim($_POST['category']   ?? '其他');
    $subject    = trim($_POST['subject']    ?? '');
    $content    = trim($_POST['content']    ?? '');
    $ai_context = trim($_POST['ai_context'] ?? '');

    if (!$subject || !$content) { echo json_encode(['ok'=>false,'msg'=>'请填写主题和详细描述']); exit; }

    $stmt = $conn->prepare("INSERT INTO support_tickets (user_id,category,subject,content,ai_context) VALUES (?,?,?,?,?)");
    $stmt->bind_param('issss', $uid, $category, $subject, $content, $ai_context);
    if ($stmt->execute()) {
        echo json_encode(['ok'=>true,'msg'=>'工单已提交，请耐心等待客服回复','ticket_id'=>$stmt->insert_id]);
    } else {
        echo json_encode(['ok'=>false,'msg'=>'提交失败，请稍后再试']);
    }
    $stmt->close();

// ── 追加回复 ──────────────────────────────────────────
} elseif ($action === 'reply') {
    $ticket_id = intval($_POST['ticket_id'] ?? 0);
    $content   = trim($_POST['content']    ?? '');
    if (!$ticket_id || !$content) { echo json_encode(['ok'=>false,'msg'=>'参数错误']); exit; }

    // 权限：管理员可回复任意工单；用户只能回复自己未关闭的工单
    if ($is_admin) {
        $chk = $conn->query("SELECT user_id,subject FROM support_tickets WHERE id=$ticket_id AND status!='closed'");
    } else {
        $chk = $conn->query("SELECT user_id,subject FROM support_tickets WHERE id=$ticket_id AND user_id=$uid AND status!='closed'");
    }
    if (!$chk || $chk->num_rows === 0) { echo json_encode(['ok'=>false,'msg'=>'工单不存在或已关闭']); exit; }
    $tk_row = $chk->fetch_assoc();

    $is_admin_int = $is_admin ? 1 : 0;
    $stmt = $conn->prepare("INSERT INTO support_replies (ticket_id,user_id,content,is_admin) VALUES (?,?,?,?)");
    $stmt->bind_param('iisi', $ticket_id, $uid, $content, $is_admin_int);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        if ($is_admin) {
            // 管理员回复 → 状态改为 replied，通知用户
            $conn->query("UPDATE support_tickets SET status='replied',updated_at=NOW() WHERE id=$ticket_id");
            $tu   = intval($tk_row['user_id']);
            $subj = $conn->real_escape_string(mb_substr($tk_row['subject'], 0, 50));
            $conn->query("INSERT INTO notifications (user_id,type,from_user_id,message)
                VALUES ($tu,'support_reply',$uid,'您的客服工单「$subj」有新回复，请查看')");
        } else {
            // 用户回复 → 状态改回 open（等待管理员）
            $conn->query("UPDATE support_tickets SET status='open',updated_at=NOW() WHERE id=$ticket_id");
        }
        echo json_encode(['ok'=>true,'msg'=>'回复成功']);
    } else {
        echo json_encode(['ok'=>false,'msg'=>'回复失败']);
    }

// ── 关闭工单 ──────────────────────────────────────────
} elseif ($action === 'close') {
    $ticket_id = intval($_POST['ticket_id'] ?? 0);
    if (!$ticket_id) { echo json_encode(['ok'=>false,'msg'=>'参数错误']); exit; }

    if ($is_admin) {
        $conn->query("UPDATE support_tickets SET status='closed',updated_at=NOW() WHERE id=$ticket_id");
    } else {
        $conn->query("UPDATE support_tickets SET status='closed',updated_at=NOW() WHERE id=$ticket_id AND user_id=$uid");
    }
    echo json_encode(['ok'=>true,'msg'=>'工单已关闭']);

} else {
    echo json_encode(['ok'=>false,'msg'=>'未知操作']);
}
