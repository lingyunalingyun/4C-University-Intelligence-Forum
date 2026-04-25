<?php
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
