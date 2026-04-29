<?php
/*
 * api/get_following.php — 获取关注列表 JSON API
 * 功能：返回当前用户关注的用户列表（含头像/用户名），
 *       供私信时选择转发对象使用。
 * 读库：follows / users
 * 权限：需登录
 */
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo json_encode([]); exit; }

$uid = intval($_SESSION['user_id']);

$result = [];
$r = $conn->query("SELECT u.id, u.username, u.avatar
    FROM follows f JOIN users u ON u.id = f.following_id
    WHERE f.follower_id = $uid
    ORDER BY u.username ASC LIMIT 100");

if ($r) while ($row = $r->fetch_assoc()) {
    $result[] = [
        'id'       => (int)$row['id'],
        'username' => $row['username'],
        'avatar'   => avatar_url($row['avatar'], '../'),
    ];
}

echo json_encode($result);
