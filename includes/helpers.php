<?php
function get_level($exp) {
    if ($exp >= 50000) return 6;
    if ($exp >= 30000) return 5;
    if ($exp >= 15000) return 4;
    if ($exp >= 5000)  return 3;
    if ($exp >= 1000)  return 2;
    return 1;
}

function level_next($exp) {
    $thresholds = [1000, 5000, 15000, 30000, 50000];
    foreach ($thresholds as $t) {
        if ($exp < $t) return $t;
    }
    return 50000;
}

function level_badge($exp) {
    $lv = get_level($exp);
    $colors = ['', '#6b7280','#3b82f6','#8b5cf6','#f59e0b','#ef4444','#ec4899'];
    return "<span class='lv-badge' style='background:{$colors[$lv]}'>Lv{$lv}</span>";
}

function role_badge($role) {
    $map = [
        'owner' => ['站长', '#dc2626'],
        'admin' => ['管理员', '#7c3aed'],
        'user'  => ['', ''],
    ];
    if (empty($map[$role][0])) return '';
    return "<span class='role-badge' style='background:{$map[$role][1]}'>{$map[$role][0]}</span>";
}

function time_ago($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return '刚刚';
    if ($diff < 3600)   return floor($diff/60)   . '分钟前';
    if ($diff < 86400)  return floor($diff/3600)  . '小时前';
    if ($diff < 604800) return floor($diff/86400) . '天前';
    return date('Y-m-d', strtotime($datetime));
}

function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function avatar_url($avatar, $base = '') {
    if (!empty($avatar) && file_exists($_SERVER['DOCUMENT_ROOT'] . parse_url(SITE_URL, PHP_URL_PATH) . '/uploads/avatars/' . $avatar)) {
        return $base . 'uploads/avatars/' . $avatar;
    }
    return $base . 'assets/default_avatar.svg';
}

function add_notification($conn, $user_id, $type, $from_user_id, $post_id, $comment_id, $message) {
    if ($user_id == $from_user_id) return;
    $stmt = $conn->prepare("INSERT INTO notifications (user_id,type,from_user_id,post_id,comment_id,message) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param("isiiss", $user_id, $type, $from_user_id, $post_id, $comment_id, $message);
    $stmt->execute();
    $stmt->close();
}

// DeepSeek API 调用
function deepseek_request($prompt, $max_tokens = 500) {
    $key = DEEPSEEK_API_KEY;
    if (empty($key)) return null;
    $data = json_encode([
        'model'    => 'deepseek-chat',
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'max_tokens' => $max_tokens,
        'temperature' => 0.7,
    ]);
    $ch = curl_init('https://api.deepseek.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key,
        ],
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    if (!$res) return null;
    $json = json_decode($res, true);
    return $json['choices'][0]['message']['content'] ?? null;
}

function ai_summary($title, $content) {
    $text  = mb_substr(strip_tags($content), 0, 800);
    $prompt = "请用50字以内，为以下论坛帖子生成一段简洁摘要，只输出摘要内容：\n标题：{$title}\n内容：{$text}";
    return deepseek_request($prompt, 100);
}

function ai_tags($title, $content) {
    $text   = mb_substr(strip_tags($content), 0, 500);
    $prompt = "请为以下帖子生成3-5个关键标签（逗号分隔，每个标签不超过5字，只输出标签）：\n标题：{$title}\n内容：{$text}";
    $result = deepseek_request($prompt, 60);
    return $result ? $result : '';
}

// 更新用户兴趣权重（浏览/点赞时调用）
function update_interest($conn, $user_id, $tags_str, $delta = 1.0) {
    if (empty($tags_str) || !$user_id) return;
    $tags = array_slice(array_filter(array_map('trim', explode(',', $tags_str))), 0, 10);
    $stmt = $conn->prepare("INSERT INTO user_interests (user_id,tag,weight) VALUES (?,?,?)
        ON DUPLICATE KEY UPDATE weight = weight + ?");
    foreach ($tags as $tag) {
        $stmt->bind_param("isdd", $user_id, $tag, $delta, $delta);
        $stmt->execute();
    }
    $stmt->close();
}
