<?php
/*
 * actions/support_ai_chat.php — 客服 AI 问答接口
 * 功能：专用于客服场景的 DeepSeek 对话接口，系统提示聚焦论坛使用帮助。
 * 写库：ai_logs
 * 权限：需登录
 */
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['error'=>'not_logged_in']); exit; }

$message     = trim($_POST['message'] ?? '');
$raw_history = $_POST['history'] ?? '[]';
if (!$message) { echo json_encode(['error'=>'empty']); exit; }
if (empty(DEEPSEEK_API_KEY)) {
    echo json_encode(['reply'=>'AI客服暂未配置，请直接点击「转人工客服」联系我们。']);
    exit;
}

$history = json_decode($raw_history, true);
if (!is_array($history)) $history = [];

$messages = [['role'=>'system','content'=>
    '你是高校智慧交流论坛的AI客服助手。帮助用户解答论坛相关问题，包括：'.
    '账号注册/登录/密码找回、发帖/编辑/删帖、评论与举报、社团申请/加入/退出、'.
    '积分与等级规则、头像/昵称设置、消息/私信功能、AI助手使用。'.
    '如遇需要人工处理的问题（账号误封、数据异常、投诉等），请引导用户点击「转人工客服」按钮提交工单。'.
    '回答简洁友好，中文，每次不超过150字。']];

foreach ($history as $h) {
    if (isset($h['role'],$h['content']) && in_array($h['role'],['user','assistant']))
        $messages[] = ['role'=>$h['role'],'content'=>mb_substr($h['content'],0,300)];
}
$messages[] = ['role'=>'user','content'=>$message];

$payload = json_encode(['model'=>'deepseek-chat','messages'=>$messages,'max_tokens'=>400,'temperature'=>0.6]);

$ch = curl_init('https://api.deepseek.com/v1/chat/completions');
curl_setopt_array($ch,[
    CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payload, CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_TIMEOUT=>30,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.DEEPSEEK_API_KEY],
]);
$response = curl_exec($ch);
curl_close($ch);

$data  = json_decode($response, true);
$reply = $data['choices'][0]['message']['content'] ?? '暂时无法回复，请点击「转人工客服」联系我们。';

$uid = intval($_SESSION['user_id']);
$lm  = $conn->real_escape_string(mb_substr($message,0,200));
$lr  = $conn->real_escape_string(mb_substr($reply,0,500));
$conn->query("INSERT INTO ai_logs (user_id,type,prompt,result) VALUES ($uid,'support','$lm','$lr')");

echo json_encode(['reply'=>$reply]);
