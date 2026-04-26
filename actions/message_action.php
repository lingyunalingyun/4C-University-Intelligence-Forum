<?php
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { echo json_encode(['ok'=>false,'error'=>'未登录']); exit; }

$uid    = intval($_SESSION['user_id']);
$action = $_POST['action'] ?? '';

function json_ok($extra=[]) { echo json_encode(array_merge(['ok'=>true],$extra)); exit; }
function json_err($msg)     { echo json_encode(['ok'=>false,'error'=>$msg]); exit; }

// 验证用户在某会话中
function in_conv($conn,$cid,$uid) {
    $r = $conn->query("SELECT 1 FROM conversation_members WHERE conversation_id=$cid AND user_id=$uid");
    return $r && $r->num_rows > 0;
}

// ── 发起私信 ─────────────────────────────────────────────
if ($action === 'create_private') {
    $tid = intval($_POST['target_id'] ?? 0);
    if (!$tid || $tid === $uid) json_err('无效用户');

    $tr = $conn->query("SELECT id FROM users WHERE id=$tid");
    if (!$tr || !$tr->num_rows) json_err('用户不存在');

    // 已有私信会话则直接返回
    $ex = $conn->query("
        SELECT cv.id FROM conversations cv
        JOIN conversation_members cm1 ON cm1.conversation_id=cv.id AND cm1.user_id=$uid
        JOIN conversation_members cm2 ON cm2.conversation_id=cv.id AND cm2.user_id=$tid
        WHERE cv.type='private' LIMIT 1");
    if ($ex && $ex->num_rows) {
        json_ok(['cid' => (int)$ex->fetch_assoc()['id']]);
    }

    $conn->query("INSERT INTO conversations (type,created_by) VALUES ('private',$uid)");
    $cid = $conn->insert_id;
    $conn->query("INSERT INTO conversation_members (conversation_id,user_id) VALUES ($cid,$uid),($cid,$tid)");
    json_ok(['cid' => $cid]);
}

// ── 创建群组 ─────────────────────────────────────────────
if ($action === 'create_group') {
    $name    = trim($_POST['name'] ?? '');
    $members = array_unique(array_map('intval', $_POST['members'] ?? []));
    if (!$name)          json_err('群组名称不能为空');
    if (empty($members)) json_err('请至少添加一名成员');

    $name_esc = $conn->real_escape_string($name);
    $conn->query("INSERT INTO conversations (type,name,created_by) VALUES ('group','$name_esc',$uid)");
    $cid = $conn->insert_id;

    // 加入创建者 + 选中成员
    $vals = ["($cid,$uid)"];
    foreach ($members as $mid) {
        if ($mid && $mid !== $uid) {
            $tr = $conn->query("SELECT id FROM users WHERE id=$mid");
            if ($tr && $tr->num_rows) $vals[] = "($cid,$mid)";
        }
    }
    $conn->query("INSERT IGNORE INTO conversation_members (conversation_id,user_id) VALUES " . implode(',',$vals));
    json_ok(['cid' => $cid]);
}

// ── 发送消息（JSON 响应）──────────────────────────────────
if ($action === 'send') {
    $cid     = intval($_POST['cid'] ?? 0);
    $content = trim($_POST['content'] ?? '');
    if (!$cid)    json_err('无效会话');
    if (!$content) json_err('内容不能为空');
    if (mb_strlen($content) > 2000) json_err('消息过长');
    if (!in_conv($conn,$cid,$uid)) json_err('无权限');

    $c_esc = $conn->real_escape_string($content);
    $conn->query("INSERT INTO messages (conversation_id,user_id,content) VALUES ($cid,$uid,'$c_esc')");
    $mid = $conn->insert_id;

    $ur = $conn->query("SELECT username,avatar FROM users WHERE id=$uid");
    $u  = $ur ? $ur->fetch_assoc() : ['username'=>'?','avatar'=>''];

    $avatar_url = !empty($u['avatar'])
        ? '../uploads/avatars/' . $u['avatar']
        : '../assets/default_avatar.svg';

    json_ok(['msg' => [
        'id'      => $mid,
        'user_id' => $uid,
        'content' => $content,
        'uname'   => $u['username'],
        'avatar'  => $avatar_url,
        'time'    => date('H:i'),
        'ts'      => time(),
        'is_recalled' => 0,
    ]]);
}

// ── 撤回消息（2 分钟内）──────────────────────────────────
if ($action === 'recall') {
    $mid = intval($_POST['message_id'] ?? 0);
    $mr  = $conn->query("SELECT * FROM messages WHERE id=$mid AND user_id=$uid AND is_recalled=0");
    $msg = $mr ? $mr->fetch_assoc() : null;
    if (!$msg) json_err('消息不存在或已撤回');
    if (time() - strtotime($msg['created_at']) > 120) json_err('只能撤回 2 分钟内的消息');

    $conn->query("UPDATE messages SET is_recalled=1, recalled_at=NOW() WHERE id=$mid");
    json_ok();
}

// ── 添加群成员（群主） ───────────────────────────────────
if ($action === 'add_member') {
    $cid = intval($_POST['cid']     ?? 0);
    $tid = intval($_POST['user_id'] ?? 0);
    if (!$cid || !$tid) json_err('参数错误');

    $cvr = $conn->query("SELECT * FROM conversations WHERE id=$cid AND type='group' AND created_by=$uid");
    if (!$cvr || !$cvr->num_rows) json_err('无权限');

    $tr = $conn->query("SELECT id FROM users WHERE id=$tid");
    if (!$tr || !$tr->num_rows) json_err('用户不存在');

    $conn->query("INSERT IGNORE INTO conversation_members (conversation_id,user_id) VALUES ($cid,$tid)");
    json_ok();
}

json_err('未知操作');
