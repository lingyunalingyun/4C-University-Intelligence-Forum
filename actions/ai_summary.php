<?php
/*
 * actions/ai_summary.php — AI 摘要生成 AJAX 接口
 * 功能：接收帖子标题和内容，调用 DeepSeek 生成50字摘要，
 *       更新 posts.summary 字段，写入 ai_logs。
 * 写库：posts（summary）/ ai_logs
 * 权限：需登录
 */
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['error'=>'not_logged_in']); exit; }

$action  = $_POST['action'] ?? '';
$title   = trim($_POST['title']   ?? '');
$content = trim($_POST['content'] ?? '');

if (!$title && !$content) { echo json_encode(['error'=>'empty']); exit; }
if (empty(DEEPSEEK_API_KEY))  { echo json_encode(['result'=>'']); exit; }

if ($action === 'summary') {
    $result = ai_summary($title, $content);
    echo json_encode(['result' => $result ?? '']);
} elseif ($action === 'tags') {
    $result = ai_tags($title, $content);
    echo json_encode(['result' => $result ?? '']);
} else {
    echo json_encode(['error'=>'unknown']);
}
