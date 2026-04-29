<?php
/*
 * includes/helpers.php — 全局工具函数库
 * 功能：等级/角色徽章、时间格式化、HTML净化、头像URL、通知、
 *       DeepSeek AI调用（摘要/标签/扩词）、帖子渲染、兴趣推荐等公共函数。
 * 权限：全局 include，无直接访问限制
 */
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
    return !empty($avatar)
        ? $base . 'uploads/avatars/' . $avatar
        : $base . 'assets/default_avatar.svg';
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

// AI 搜索扩词：将自然语言查询解析为关键词列表
function ai_expand_query($query, $conn = null, $uid = 0) {
    $q = mb_substr(trim($query), 0, 100);
    $prompt = "用户在高校论坛搜索「{$q}」，提取3-5个最相关的中文搜索关键词（逗号分隔），只输出关键词，不要解释。";
    $key = DEEPSEEK_API_KEY;
    if (empty($key)) return [];
    $data = json_encode([
        'model'       => 'deepseek-chat',
        'messages'    => [['role'=>'user','content'=>$prompt]],
        'max_tokens'  => 60,
        'temperature' => 0.3,
    ]);
    $ch = curl_init('https://api.deepseek.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json','Authorization: Bearer '.$key],
    ]);
    $res  = curl_exec($ch);
    curl_close($ch);
    if (!$res) return [];
    $json = json_decode($res, true);
    $text = $json['choices'][0]['message']['content'] ?? '';
    $keywords = array_filter(array_map('trim', explode(',', $text)));
    // 写调用日志
    if ($conn && $text) {
        $lp = $conn->real_escape_string($q);
        $lr = $conn->real_escape_string($text);
        $conn->query("INSERT INTO ai_logs (user_id,type,prompt,result) VALUES ($uid,'search','$lp','$lr')");
    }
    return $keywords;
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

// 从帖子 HTML 内容中提取第一张图片 URL
function extract_cover_image($content) {
    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $content, $m)) {
        return $m[1];
    }
    return '';
}

// 渲染帖子列表条目（广场/推荐列表共用）
function render_post_item($p, $base, $kw = '') {
    $tags_arr = array_filter(array_map('trim', explode(',', $p['tags'])));
    ob_start(); ?>
    <div class="post-item <?= $p['is_pinned'] ? 'post-pinned' : ($p['is_featured'] ? 'post-featured' : '') ?>">
      <div class="post-item-top">
        <div class="post-meta-left">
          <a href="<?= $base ?>pages/post.php?id=<?= $p['id'] ?>" class="post-title-link">
            <?= $p['is_pinned'] ? '<span style="color:var(--warning)">📌 </span>' : '' ?>
            <?= h($p['title']) ?>
            <?= $p['is_solved'] ? '<span class="post-solved"></span>' : '' ?>
          </a>
          <?php if (!empty($p['summary'])): ?>
            <div class="post-summary"><?= h($p['summary']) ?></div>
          <?php endif; ?>
          <div class="post-tags">
            <span class="tag tag-section"><?= h($p['section_name']) ?></span>
            <?php foreach (array_slice($tags_arr, 0, 3) as $tag): ?>
              <span class="tag"><?= h($tag) ?></span>
            <?php endforeach; ?>
          </div>
          <div class="post-footer">
            <span class="author">
              <img src="<?= avatar_url($p['avatar'], $base) ?>"
                   onerror="this.src='<?= $base ?>assets/default_avatar.svg'" alt="">
              <?= h($p['username']) ?>
            </span>
            <span><?= time_ago($p['created_at']) ?></span>
            <div class="post-stats">
              <span>👁 <?= $p['views'] ?></span>
              <span>👍 <?= $p['like_count'] ?></span>
              <span>💬 <?= $p['comment_count'] ?></span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php return ob_get_clean();
}

// 记录管理员操作日志
function log_admin_action($conn, $admin_id, $action, $target_type = '', $target_id = 0, $detail = '') {
    $a  = $conn->real_escape_string($action);
    $tt = $conn->real_escape_string($target_type);
    $d  = $conn->real_escape_string($detail);
    $conn->query("INSERT INTO admin_logs (admin_id,action,target_type,target_id,detail)
        VALUES ($admin_id,'$a','$tt',$target_id,'$d')");
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

// 富文本内容渲染：新帖子含 <p> 标签（Quill），旧帖子用 nl2br 兼容
function render_post_content($content) {
    if (preg_match('/<(p|h[1-6]|ul|ol|blockquote|pre)\b/i', $content)) {
        return $content;
    }
    return nl2br($content);
}

// 富文本内容净化：允许 Quill 生成的安全 HTML 标签
function sanitize_rich_html($html) {
    $html = strip_tags($html, '<p><br><strong><em><u><s><h1><h2><h3><ol><ul><li><blockquote><pre><code><a><img>');
    // 删除事件处理属性
    $html = preg_replace('/\s+on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $html);
    // 净化 <a>：只保留 href，强制安全协议
    $html = preg_replace_callback('/<a\b[^>]*>/i', function($m) {
        $href = '';
        if (preg_match('/\bhref\s*=\s*"([^"]*)"/i', $m[0], $hm)) $href = $hm[1];
        if ($href && !preg_match('/^(https?:\/\/|\/|\.\.\/|#)/i', $href)) $href = '#';
        $safe = $href ? ' href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer"' : '';
        return '<a' . $safe . '>';
    }, $html);
    // 净化 <img>：只允许来自 /uploads/ 的路径
    $html = preg_replace_callback('/<img\b[^>]*>/i', function($m) {
        $src = '';
        if (preg_match('/\bsrc\s*=\s*"([^"]*)"/i', $m[0], $sm)) $src = $sm[1];
        if ($src && strpos($src, '/uploads/') !== false) {
            return '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="" style="max-width:100%;border-radius:8px;margin:6px 0" loading="lazy">';
        }
        return '';
    }, $html);
    return $html;
}

// 个性化推荐：根据 user_interests + 点赞/收藏行为，返回推荐帖列表
function get_recommended_posts($conn, $uid, $limit = 8) {
    $base_sql = "SELECT p.id,p.title,p.content,p.tags,p.like_count,p.views,p.fav_count,
        p.comment_count,p.created_at,u.username,u.avatar,
        s.name as section_name,s.color as section_color
        FROM posts p
        JOIN users u ON u.id=p.user_id
        JOIN sections s ON s.id=p.section_id
        WHERE p.status='published'";

    if (!$uid) {
        $res = $conn->query("$base_sql ORDER BY (p.like_count*3+p.fav_count*5+p.comment_count*2+p.views*0.05) DESC LIMIT $limit");
        $out = [];
        if ($res) while ($r = $res->fetch_assoc()) $out[] = $r;
        return $out;
    }

    // 读取用户兴趣标签权重（最多15个）
    $interests = [];
    $ir = $conn->query("SELECT tag,weight FROM user_interests WHERE user_id=$uid ORDER BY weight DESC LIMIT 15");
    if ($ir) while ($r = $ir->fetch_assoc()) $interests[$r['tag']] = (float)$r['weight'];

    if (empty($interests)) {
        $res = $conn->query("$base_sql ORDER BY (p.like_count*3+p.fav_count*5+p.comment_count*2+p.views*0.05) DESC LIMIT $limit");
        $out = [];
        if ($res) while ($r = $res->fetch_assoc()) $out[] = $r;
        return $out;
    }

    // 构建标签 LIKE 条件
    $conds = [];
    foreach (array_keys($interests) as $tag) {
        $t = $conn->real_escape_string($tag);
        $conds[] = "p.tags LIKE '%$t%'";
    }
    $tag_where = '(' . implode(' OR ', $conds) . ')';

    $fetch = $limit * 4;
    $res = $conn->query("$base_sql
        AND p.user_id != $uid
        AND $tag_where
        AND p.id NOT IN (SELECT post_id FROM post_likes WHERE user_id=$uid)
        AND p.id NOT IN (SELECT post_id FROM post_favs  WHERE user_id=$uid)
        ORDER BY p.created_at DESC
        LIMIT $fetch");

    if (!$res) {
        $res2 = $conn->query("$base_sql ORDER BY (p.like_count*3+p.fav_count*5+p.comment_count*2+p.views*0.05) DESC LIMIT $limit");
        $out = [];
        if ($res2) while ($r = $res2->fetch_assoc()) $out[] = $r;
        return $out;
    }

    // PHP 端打分：兴趣标签匹配 + 热度加成
    $scored = [];
    while ($p = $res->fetch_assoc()) {
        $post_tags = array_filter(array_map('trim', explode(',', $p['tags'])));
        $score = 0;
        foreach ($post_tags as $pt) {
            $ptl = mb_strtolower($pt);
            foreach ($interests as $it => $w) {
                if ($ptl === mb_strtolower($it)) { $score += $w; break; }
            }
        }
        $score += $p['like_count'] * 0.5 + $p['fav_count'] * 0.8 + $p['comment_count'] * 0.3;
        $p['_score'] = $score;
        $scored[] = $p;
    }

    usort($scored, function($a, $b) { return $b['_score'] <=> $a['_score']; });
    return array_slice($scored, 0, $limit);
}
