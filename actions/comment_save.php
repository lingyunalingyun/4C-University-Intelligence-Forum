<?php
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../pages/login.php'); exit; }
if (!empty($_SESSION['is_banned'])) { header('Location: ../index.php'); exit; }

$uid       = intval($_SESSION['user_id']);
$post_id   = intval($_POST['post_id']  ?? 0);
$parent_id = intval($_POST['parent_id'] ?? 0);
$content   = trim($_POST['content'] ?? '');

if (!$post_id || !$content) { header('Location: ../pages/post.php?id='.$post_id.'&error=empty'); exit; }

$clean = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

$stmt = $conn->prepare("INSERT INTO comments (post_id,user_id,parent_id,content) VALUES (?,?,?,?)");
$stmt->bind_param('iiis', $post_id, $uid, $parent_id, $clean);
$stmt->execute();
$cid = $stmt->insert_id;
$stmt->close();

// 更新帖子评论数
$conn->query("UPDATE posts SET comment_count=comment_count+1 WHERE id=$post_id");

// 通知
if ($cid) {
    $pr = $conn->query("SELECT user_id FROM posts WHERE id=$post_id");
    if ($pr && ($po = $pr->fetch_assoc()) && $po['user_id'] != $uid) {
        add_notification($conn, $po['user_id'], 'comment', $uid, $post_id, $cid, '');
    }
    if ($parent_id) {
        $cr = $conn->query("SELECT user_id FROM comments WHERE id=$parent_id");
        if ($cr && ($co = $cr->fetch_assoc()) && $co['user_id'] != $uid) {
            add_notification($conn, $co['user_id'], 'reply', $uid, $post_id, $cid, '');
        }
    }
}

header("Location: ../pages/post.php?id=$post_id#c$cid"); exit;
