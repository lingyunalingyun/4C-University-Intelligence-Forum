<?php
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
