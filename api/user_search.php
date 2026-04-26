<?php
require_once '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo '[]'; exit; }

$uid = intval($_SESSION['user_id']);
$q   = trim($_GET['q'] ?? '');
if (strlen($q) < 1) { echo '[]'; exit; }

$q_esc = $conn->real_escape_string($q);
$users = [];
$r = $conn->query("SELECT id, username, avatar, scid FROM users
    WHERE id != $uid AND (username LIKE '%$q_esc%' OR scid LIKE '%$q_esc%')
    ORDER BY username LIMIT 10");

if ($r) while ($row = $r->fetch_assoc()) {
    $users[] = [
        'id'       => (int)$row['id'],
        'username' => $row['username'],
        'scid'     => $row['scid'] ?? '',
        'avatar'   => !empty($row['avatar'])
            ? '../uploads/avatars/' . $row['avatar']
            : '',
    ];
}

echo json_encode($users);
