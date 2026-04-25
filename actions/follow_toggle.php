<?php
require_once '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['error'=>'not_logged_in']); exit; }

$uid    = intval($_SESSION['user_id']);
$target = intval($_POST['target_id'] ?? 0);

if (!$target || $target === $uid) { echo json_encode(['error'=>'invalid']); exit; }

$r = $conn->query("SELECT 1 FROM follows WHERE follower_id=$uid AND following_id=$target");
if ($r && $r->num_rows > 0) {
    $conn->query("DELETE FROM follows WHERE follower_id=$uid AND following_id=$target");
    echo json_encode(['followed'=>false]);
} else {
    $conn->query("INSERT IGNORE INTO follows (follower_id,following_id) VALUES ($uid,$target)");
    echo json_encode(['followed'=>true]);
}
