<?php
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin','owner'])) {
    echo json_encode(['ok'=>false,'error'=>'无权限']); exit;
}

$key = trim($_POST['key'] ?? '');
if (!$key) { echo json_encode(['ok'=>false,'error'=>'Key 为空']); exit; }

$data = json_encode([
    'model'      => 'deepseek-chat',
    'messages'   => [['role'=>'user','content'=>'你好，请回复"连接成功"四个字。']],
    'max_tokens' => 20,
    'temperature'=> 0.1,
]);
$ch = curl_init('https://api.deepseek.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $data,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $key,
    ],
]);
$res = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if (!$res) { echo json_encode(['ok'=>false,'error'=>$err ?: 'curl 失败']); exit; }
$json = json_decode($res, true);
if (!empty($json['error'])) {
    echo json_encode(['ok'=>false,'error'=>$json['error']['message'] ?? '认证失败']); exit;
}
$reply = $json['choices'][0]['message']['content'] ?? '';
echo json_encode(['ok'=>true,'reply'=>mb_substr($reply,0,50)]);
