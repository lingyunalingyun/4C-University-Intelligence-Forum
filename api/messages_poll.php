<?php
/*
 * api/messages_poll.php — 消息轮询 JSON API
 * 功能：返回当前用户未读消息总数（私信 + 群组），
 *       由 header.php 每3秒 AJAX 调用，更新导航栏徽章。
 * 读库：messages / message_groups / group_members
 * 权限：需登录，未登录返回 {"unread":0}
 */
require_once '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo '[]'; exit; }

$uid   = intval($_SESSION['user_id']);
$cid   = intval($_GET['cid']   ?? 0);
$after = intval($_GET['after'] ?? 0);

if (!$cid) { echo '[]'; exit; }

// 验证成员身份
$chk = $conn->query("SELECT 1 FROM conversation_members WHERE conversation_id=$cid AND user_id=$uid");
if (!$chk || $chk->num_rows === 0) { echo '[]'; exit; }

// 标记已读
$conn->query("UPDATE conversation_members SET last_read_at=NOW() WHERE conversation_id=$cid AND user_id=$uid");

$msgs = [];
$mr = $conn->query("SELECT m.id, m.user_id, m.content, m.is_recalled,
    UNIX_TIMESTAMP(m.created_at) AS ts,
    DATE_FORMAT(m.created_at,'%H:%i') AS time,
    u.username, u.avatar
    FROM messages m JOIN users u ON u.id=m.user_id
    WHERE m.conversation_id=$cid AND m.id > $after
    ORDER BY m.created_at ASC LIMIT 50");

if ($mr) while ($r = $mr->fetch_assoc()) {
    $msgs[] = [
        'id'         => (int)$r['id'],
        'user_id'    => (int)$r['user_id'],
        'content'    => $r['is_recalled'] ? '' : $r['content'],
        'is_recalled'=> (int)$r['is_recalled'],
        'uname'      => $r['username'],
        'avatar'     => !empty($r['avatar'])
            ? '../uploads/avatars/' . $r['avatar']
            : '../assets/default_avatar.svg',
        'time'       => $r['time'],
        'ts'         => (int)$r['ts'],
    ];
}

echo json_encode($msgs);
