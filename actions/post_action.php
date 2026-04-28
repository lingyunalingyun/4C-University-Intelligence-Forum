<?php
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo json_encode(['error'=>'not_logged_in']); exit; }

$uid     = intval($_SESSION['user_id']);
$post_id = intval($_POST['post_id'] ?? 0);
$action  = $_POST['action'] ?? '';

if (!$post_id) { echo json_encode(['error'=>'invalid']); exit; }

if ($action === 'like') {
    $r = $conn->query("SELECT 1 FROM post_likes WHERE user_id=$uid AND post_id=$post_id");
    if ($r && $r->num_rows > 0) {
        $conn->query("DELETE FROM post_likes WHERE user_id=$uid AND post_id=$post_id");
        $conn->query("UPDATE posts SET like_count=GREATEST(0,like_count-1) WHERE id=$post_id");
        $liked = false;
    } else {
        $conn->query("INSERT IGNORE INTO post_likes (user_id,post_id) VALUES ($uid,$post_id)");
        $conn->query("UPDATE posts SET like_count=like_count+1 WHERE id=$post_id");
        $liked = true;
        // 通知作者
        $pr = $conn->query("SELECT user_id,tags FROM posts WHERE id=$post_id");
        if ($pr && ($po = $pr->fetch_assoc())) {
            if ($po['user_id'] != $uid)
                add_notification($conn, $po['user_id'], 'like', $uid, $post_id, null, '');
            if (!empty($po['tags'])) update_interest($conn, $uid, $po['tags'], 1.5);
        }
    }
    $cr = $conn->query("SELECT like_count FROM posts WHERE id=$post_id");
    $count = $cr ? (int)$cr->fetch_assoc()['like_count'] : 0;
    echo json_encode(['liked'=>$liked, 'count'=>$count]);

} elseif ($action === 'fav') {
    $r = $conn->query("SELECT 1 FROM post_favs WHERE user_id=$uid AND post_id=$post_id");
    if ($r && $r->num_rows > 0) {
        $conn->query("DELETE FROM post_favs WHERE user_id=$uid AND post_id=$post_id");
        $conn->query("UPDATE posts SET fav_count=GREATEST(0,fav_count-1) WHERE id=$post_id");
        $faved = false;
    } else {
        $conn->query("INSERT IGNORE INTO post_favs (user_id,post_id) VALUES ($uid,$post_id)");
        $conn->query("UPDATE posts SET fav_count=fav_count+1 WHERE id=$post_id");
        $faved = true;
        $tr = $conn->query("SELECT tags FROM posts WHERE id=$post_id");
        if ($tr && ($tp = $tr->fetch_assoc()) && !empty($tp['tags']))
            update_interest($conn, $uid, $tp['tags'], 2.0);
    }
    $cr = $conn->query("SELECT fav_count FROM posts WHERE id=$post_id");
    $count = $cr ? (int)$cr->fetch_assoc()['fav_count'] : 0;
    echo json_encode(['faved'=>$faved, 'count'=>$count]);

} elseif ($action === 'solve') {
    $conn->query("UPDATE posts SET is_solved=1 WHERE id=$post_id AND user_id=$uid");
    echo json_encode(['ok'=>true]);

} else {
    echo json_encode(['error'=>'unknown_action']);
}
