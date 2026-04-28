<?php
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../pages/login.php'); exit; }
if (!empty($_SESSION['is_banned'])) { header('Location: ../index.php'); exit; }

$uid        = intval($_SESSION['user_id']);
$edit_id    = intval($_POST['edit_id']   ?? 0);
$section_id = intval($_POST['section_id'] ?? 0);
$title      = trim($_POST['title']   ?? '');
$content    = trim($_POST['content'] ?? '');
$tags       = trim($_POST['tags']    ?? '');

if (!$title || !$content || !$section_id) {
    $back = $edit_id ? "../pages/publish.php?edit=$edit_id&error=empty" : '../pages/publish.php?error=empty';
    header("Location: $back"); exit;
}

// 验证板块存在
$sr = $conn->prepare("SELECT id FROM sections WHERE id=?");
$sr->bind_param('i', $section_id); $sr->execute();
if (!$sr->get_result()->fetch_assoc()) { header('Location: ../pages/publish.php?error=section'); exit; }
$sr->close();

// 净化富文本内容（Quill HTML）和标签
$clean_content = sanitize_rich_html($content);
$clean_tags    = htmlspecialchars($tags, ENT_QUOTES, 'UTF-8');

if ($edit_id) {
    // 编辑模式：验证权限
    $role = $_SESSION['role'] ?? 'user';
    $check = $conn->prepare("SELECT id FROM posts WHERE id=? AND (user_id=? OR ? IN ('admin','owner'))");
    $check->bind_param('iis', $edit_id, $uid, $role); $check->execute();
    if (!$check->get_result()->fetch_assoc()) { header('Location: ../index.php'); exit; }
    $check->close();

    $stmt = $conn->prepare("UPDATE posts SET section_id=?,title=?,content=?,tags=? WHERE id=?");
    $stmt->bind_param('isssi', $section_id, $title, $clean_content, $clean_tags, $edit_id);
    $stmt->execute(); $stmt->close();
    $post_id = $edit_id;
} else {
    // 新发帖
    // AI摘要（失败不影响发帖）
    $summary = '';
    if (!empty(DEEPSEEK_API_KEY)) {
        $summary = ai_summary($title, $content) ?? '';
        if (empty($tags)) {
            $tags       = ai_tags($title, $content) ?? '';
            $clean_tags = htmlspecialchars($tags, ENT_QUOTES, 'UTF-8');
        }
    }

    $stmt = $conn->prepare("INSERT INTO posts (user_id,section_id,title,content,summary,tags,status) VALUES (?,?,?,?,?,?,'published')");
    $stmt->bind_param('iissss', $uid, $section_id, $title, $clean_content, $summary, $clean_tags);
    $stmt->execute();
    $post_id = $stmt->insert_id;
    $stmt->close();

    if ($post_id && !empty($clean_tags)) update_interest($conn, $uid, $clean_tags, 2.0);
}

header("Location: ../pages/post.php?id=$post_id"); exit;
