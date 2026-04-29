<?php
/*
 * actions/ai_chat.php — AI 助手多轮对话 AJAX 接口
 * 功能：接收用户消息和历史记录，调用 DeepSeek API 返回 AI 回复，
 *       记录调用日志（ai_logs）。
 * 写库：ai_logs
 * 权限：需登录，Key 未配置时返回错误
 */
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['error'=>'not_logged_in']); exit; }

$message = trim($_POST['message'] ?? '');
$raw_history = $_POST['history'] ?? '[]';
if (!$message) { echo json_encode(['error'=>'empty']); exit; }
if (empty(DEEPSEEK_API_KEY)) { echo json_encode(['reply'=>'AI功能暂未配置，请联系管理员。']); exit; }

$history = json_decode($raw_history, true);
if (!is_array($history)) $history = [];

$messages = [
    ['role'=>'system', 'content'=>'你是高校智慧交流论坛的校园AI助手，专注帮助大学生解答学习、考研、求职、编程、校园生活等问题。回答简洁清晰，友好亲切，使用中文。']
];
foreach ($history as $h) {
    if (isset($h['role'], $h['content']) && in_array($h['role'], ['user','assistant']))
        $messages[] = ['role'=>$h['role'], 'content'=>mb_substr($h['content'], 0, 500)];
}
$messages[] = ['role'=>'user', 'content'=>$message];

$payload = json_encode([
    'model'       => 'deepseek-chat',
    'messages'    => $messages,
    'max_tokens'  => 800,
    'temperature' => 0.7,
]);

$ch = curl_init('https://api.deepseek.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . DEEPSEEK_API_KEY,
    ],
]);
$response = curl_exec($ch);
curl_close($ch);

$data  = json_decode($response, true);
$reply = $data['choices'][0]['message']['content'] ?? '暂时无法获取回复，请稍后重试。';

// 记录AI日志
$uid     = intval($_SESSION['user_id']);
$log_msg = $conn->real_escape_string(mb_substr($message, 0, 200));
$log_rep = $conn->real_escape_string(mb_substr($reply,   0, 500));
$conn->query("INSERT INTO ai_logs (user_id,type,prompt,result) VALUES ($uid,'chat','$log_msg','$log_rep')");

echo json_encode(['reply' => $reply]);
