<?php
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php?redirect=messages.php'); exit; }

$uid = intval($_SESSION['user_id']);
$cid = intval($_GET['cid'] ?? 0);

// 验证当前用户在此会话中
if ($cid) {
    $chk = $conn->query("SELECT 1 FROM conversation_members WHERE conversation_id=$cid AND user_id=$uid");
    if (!$chk || $chk->num_rows === 0) $cid = 0;
}

// 标记已读
if ($cid) {
    $conn->query("UPDATE conversation_members SET last_read_at=NOW() WHERE conversation_id=$cid AND user_id=$uid");
}

// ── 会话列表 ──────────────────────────────────────────────
$conversations = [];
$cr = $conn->query("
    SELECT cv.*,
      (SELECT m.content FROM messages m WHERE m.conversation_id=cv.id ORDER BY m.created_at DESC LIMIT 1) AS last_msg,
      (SELECT m.is_recalled FROM messages m WHERE m.conversation_id=cv.id ORDER BY m.created_at DESC LIMIT 1) AS last_recalled,
      (SELECT m.created_at FROM messages m WHERE m.conversation_id=cv.id ORDER BY m.created_at DESC LIMIT 1) AS last_msg_time,
      (SELECT COUNT(*) FROM messages m2
       JOIN conversation_members cm2 ON cm2.conversation_id=m2.conversation_id AND cm2.user_id=$uid
       WHERE m2.conversation_id=cv.id AND m2.user_id!=$uid AND m2.is_recalled=0
         AND (cm2.last_read_at IS NULL OR m2.created_at > cm2.last_read_at)
      ) AS unread,
      CASE WHEN cv.type='private'
        THEN (SELECT u2.username FROM conversation_members cm3 JOIN users u2 ON u2.id=cm3.user_id WHERE cm3.conversation_id=cv.id AND cm3.user_id!=$uid LIMIT 1)
        ELSE cv.name END AS disp_name,
      CASE WHEN cv.type='private'
        THEN (SELECT u2.avatar FROM conversation_members cm3 JOIN users u2 ON u2.id=cm3.user_id WHERE cm3.conversation_id=cv.id AND cm3.user_id!=$uid LIMIT 1)
        ELSE cv.avatar END AS disp_avatar,
      CASE WHEN cv.type='private'
        THEN (SELECT cm3.user_id FROM conversation_members cm3 WHERE cm3.conversation_id=cv.id AND cm3.user_id!=$uid LIMIT 1)
        ELSE NULL END AS other_uid,
      (SELECT COUNT(*) FROM conversation_members cm4 WHERE cm4.conversation_id=cv.id) AS member_count
    FROM conversations cv
    JOIN conversation_members cm ON cm.conversation_id=cv.id AND cm.user_id=$uid
    ORDER BY last_msg_time DESC
");
if ($cr) while ($r = $cr->fetch_assoc()) $conversations[] = $r;

// ── 当前会话信息 & 消息 ───────────────────────────────────
$conv       = null;
$messages   = [];
$members    = [];
$is_creator = false;

if ($cid) {
    $cvr = $conn->query("SELECT cv.*,
        (SELECT COUNT(*) FROM conversation_members WHERE conversation_id=cv.id) AS member_count
        FROM conversations cv WHERE cv.id=$cid");
    $conv = $cvr ? $cvr->fetch_assoc() : null;
    if ($conv) {
        $is_creator = ($conv['created_by'] == $uid);

        $mr = $conn->query("SELECT m.*, u.username, u.avatar
            FROM messages m JOIN users u ON u.id=m.user_id
            WHERE m.conversation_id=$cid
            ORDER BY m.created_at ASC LIMIT 200");
        if ($mr) while ($r = $mr->fetch_assoc()) $messages[] = $r;

        $memr = $conn->query("SELECT u.id, u.username, u.avatar, u.scid
            FROM conversation_members cm JOIN users u ON u.id=cm.user_id
            WHERE cm.conversation_id=$cid ORDER BY cm.joined_at ASC");
        if ($memr) while ($r = $memr->fetch_assoc()) $members[] = $r;

        // 群组：计算显示名（私信用对方名字）
        if ($conv['type'] === 'private') {
            foreach ($members as $m) {
                if ($m['id'] != $uid) { $conv['disp_name'] = $m['username']; $conv['disp_avatar'] = $m['avatar']; break; }
            }
        } else {
            $conv['disp_name']   = $conv['name'];
            $conv['disp_avatar'] = $conv['avatar'];
        }
    }
}

$last_msg_id = empty($messages) ? 0 : end($messages)['id'];

$page_title = '私信';
include '../includes/header.php';
?>
<style>
.main-wrap { max-width:100% !important; padding:0 !important; }
.msg-layout { display:flex; height:calc(100vh - 60px); overflow:hidden; }

/* 左侧会话列表 */
.msg-sidebar {
    width:280px; flex-shrink:0; border-right:1px solid var(--border);
    display:flex; flex-direction:column; background:var(--bg-card);
}
.msg-sidebar-top {
    padding:12px; border-bottom:1px solid var(--border);
    display:flex; gap:6px; align-items:center;
}
.msg-sidebar-top input {
    flex:1; padding:7px 10px; border:1.5px solid var(--border); border-radius:8px;
    font-size:13px; background:var(--bg-2);
}
.msg-conv-list { flex:1; overflow-y:auto; }
.msg-conv-item {
    display:flex; align-items:center; gap:10px;
    padding:10px 12px; cursor:pointer; border-bottom:1px solid var(--border);
    text-decoration:none; transition:background .12s;
}
.msg-conv-item:hover, .msg-conv-item.active { background:var(--primary-lt); }
.msg-conv-item .conv-ava {
    width:42px; height:42px; border-radius:50%; object-fit:cover;
    flex-shrink:0; border:1.5px solid var(--border);
}
.msg-conv-item .conv-ava-txt {
    width:42px; height:42px; border-radius:50%; flex-shrink:0;
    background:var(--primary); color:#fff; font-size:16px; font-weight:700;
    display:flex; align-items:center; justify-content:center;
}
.msg-conv-name { font-size:13px; font-weight:600; color:var(--txt); }
.msg-conv-preview { font-size:12px; color:var(--txt-3); overflow:hidden; white-space:nowrap; text-overflow:ellipsis; max-width:140px; }
.msg-unread-badge { font-size:10px; background:#ef4444; color:#fff; border-radius:10px; padding:1px 6px; flex-shrink:0; }

/* 右侧聊天区 */
.msg-main { flex:1; display:flex; flex-direction:column; min-width:0; }
.msg-header {
    padding:12px 18px; border-bottom:1px solid var(--border);
    display:flex; align-items:center; gap:10px; background:var(--bg-card);
    flex-shrink:0;
}
.msg-body { flex:1; overflow-y:auto; padding:16px 20px; display:flex; flex-direction:column; gap:4px; }
.msg-footer {
    padding:10px 16px; border-top:1px solid var(--border);
    background:var(--bg-card); display:flex; gap:8px; align-items:flex-end; flex-shrink:0;
}
.msg-footer textarea {
    flex:1; resize:none; padding:9px 13px; border:1.5px solid var(--border);
    border-radius:10px; font-size:14px; font-family:inherit;
    max-height:120px; min-height:42px;
}
.msg-footer textarea:focus { border-color:var(--primary); outline:none; }

/* 消息气泡 */
.bubble-row { display:flex; gap:8px; align-items:flex-end; margin-bottom:8px; }
.bubble-row.mine { flex-direction:row-reverse; }
.bubble-ava { width:32px; height:32px; border-radius:50%; object-fit:cover; flex-shrink:0; }
.bubble-ava-txt {
    width:32px; height:32px; border-radius:50%; flex-shrink:0;
    background:var(--primary); color:#fff; font-size:12px; font-weight:700;
    display:flex; align-items:center; justify-content:center;
}
.bubble-col { display:flex; flex-direction:column; max-width:62%; }
.bubble-row.mine .bubble-col { align-items:flex-end; }
.bubble-sender { font-size:11px; color:var(--txt-3); margin-bottom:3px; }
.bubble-wrap { position:relative; }
.bubble {
    padding:9px 13px; border-radius:14px; font-size:14px; line-height:1.55;
    word-break:break-word; white-space:pre-wrap;
}
.bubble.theirs { background:var(--bg-card); border:1px solid var(--border); border-bottom-left-radius:4px; color:var(--txt); }
.bubble.mine   { background:var(--primary); color:#fff; border-bottom-right-radius:4px; }
.bubble.recalled { background:var(--bg-2); color:var(--txt-3); font-style:italic; font-size:13px; border:1px dashed var(--border); }
.bubble-time { font-size:11px; color:var(--txt-3); margin-top:3px; }
.recall-btn {
    display:none; position:absolute; top:-24px;
    background:rgba(0,0,0,.65); color:#fff; font-size:11px;
    padding:2px 8px; border-radius:6px; cursor:pointer; white-space:nowrap;
    border:none; right:0;
}
.bubble-row.mine .recall-btn { right:0; }
.bubble-wrap:hover .recall-btn { display:block; }

/* 空状态 */
.msg-empty { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; color:var(--txt-3); gap:10px; }

/* 成员面板 */
.member-panel { width:220px; flex-shrink:0; border-left:1px solid var(--border); overflow-y:auto; background:var(--bg-card); padding:12px; display:none; }
.member-panel.open { display:block; }
.member-row { display:flex; align-items:center; gap:8px; padding:6px 0; border-bottom:1px solid var(--border); font-size:13px; }
.member-row:last-child { border-bottom:none; }
</style>

<div class="msg-layout">

<!-- ── 左侧会话列表 ──────────────────────────── -->
<div class="msg-sidebar">
  <div class="msg-sidebar-top">
    <input type="text" id="conv-search" placeholder="搜索会话…" oninput="filterConvs(this.value)">
    <button class="btn btn-primary btn-sm" onclick="openNewPrivate()" title="新私信">✉</button>
    <button class="btn btn-outline btn-sm" onclick="openNewGroup()" title="新群组">👥</button>
  </div>
  <div class="msg-conv-list" id="conv-list">
    <?php if (empty($conversations)): ?>
      <div style="padding:24px 16px;text-align:center;font-size:13px;color:var(--txt-3)">暂无会话<br>点击上方按钮开始</div>
    <?php endif; ?>
    <?php foreach ($conversations as $cv): ?>
    <?php $isActive = ($cv['id'] == $cid); ?>
    <a href="messages.php?cid=<?= $cv['id'] ?>"
       class="msg-conv-item <?= $isActive ? 'active' : '' ?>"
       data-name="<?= h(strtolower($cv['disp_name'])) ?>">
      <?php if (!empty($cv['disp_avatar'])): ?>
        <img class="conv-ava" src="<?= avatar_url($cv['disp_avatar'],'../') ?>" alt="">
      <?php else: ?>
        <div class="conv-ava-txt"><?= mb_substr($cv['disp_name'] ?: '?', 0, 1) ?></div>
      <?php endif; ?>
      <div style="flex:1;min-width:0">
        <div style="display:flex;align-items:center;justify-content:space-between">
          <div class="msg-conv-name"><?= h($cv['disp_name']) ?>
            <?php if ($cv['type']==='group'): ?>
              <span style="font-size:10px;color:var(--txt-3);margin-left:4px">(<?= $cv['member_count'] ?>)</span>
            <?php endif; ?>
          </div>
          <?php if ($cv['unread'] > 0): ?>
            <span class="msg-unread-badge"><?= min($cv['unread'],99) ?></span>
          <?php elseif ($cv['last_msg_time']): ?>
            <span style="font-size:10px;color:var(--txt-3)"><?= date('H:i', strtotime($cv['last_msg_time'])) ?></span>
          <?php endif; ?>
        </div>
        <div class="msg-conv-preview">
          <?php if (!$cv['last_msg']): ?>
            <em>暂无消息</em>
          <?php elseif ($cv['last_recalled']): ?>
            <em>消息已撤回</em>
          <?php else: ?>
            <?= h(mb_substr($cv['last_msg'], 0, 30)) ?>
          <?php endif; ?>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- ── 右侧聊天区 ──────────────────────────────── -->
<div class="msg-main">
<?php if (!$cid || !$conv): ?>
  <div class="msg-empty">
    <div style="font-size:48px">💬</div>
    <div style="font-size:15px">选择一个会话开始聊天</div>
    <div style="display:flex;gap:8px">
      <button class="btn btn-primary btn-sm" onclick="openNewPrivate()">发起私信</button>
      <button class="btn btn-outline btn-sm" onclick="openNewGroup()">创建群组</button>
    </div>
  </div>
<?php else: ?>

  <!-- 会话头部 -->
  <div class="msg-header">
    <?php if (!empty($conv['disp_avatar'])): ?>
      <img src="<?= avatar_url($conv['disp_avatar'],'../') ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover">
    <?php else: ?>
      <div style="width:36px;height:36px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700">
        <?= mb_substr($conv['disp_name'] ?: '?', 0, 1) ?>
      </div>
    <?php endif; ?>
    <div style="flex:1;min-width:0">
      <div style="font-size:15px;font-weight:700;color:var(--txt)"><?= h($conv['disp_name']) ?></div>
      <div style="font-size:12px;color:var(--txt-3)">
        <?= $conv['type']==='group' ? ('群组 · '.$conv['member_count'].' 人') : '私信' ?>
      </div>
    </div>
    <?php if ($conv['type']==='group'): ?>
      <button class="btn btn-outline btn-sm" onclick="toggleMembers()" id="member-btn">成员</button>
    <?php endif; ?>
  </div>

  <!-- 消息区 -->
  <div class="msg-body" id="msg-body">
    <?php foreach ($messages as $msg): ?>
      <?php $isMine = ($msg['user_id'] == $uid); ?>
      <div class="bubble-row <?= $isMine?'mine':'' ?>" id="msg-<?= $msg['id'] ?>">
        <?php if (!$isMine): ?>
          <?php if (!empty($msg['avatar'])): ?>
            <img class="bubble-ava" src="<?= avatar_url($msg['avatar'],'../') ?>" alt="">
          <?php else: ?>
            <div class="bubble-ava-txt"><?= mb_substr($msg['username'],0,1) ?></div>
          <?php endif; ?>
        <?php endif; ?>
        <div class="bubble-col">
          <?php if ($conv['type']==='group' && !$isMine): ?>
            <div class="bubble-sender"><?= h($msg['username']) ?></div>
          <?php endif; ?>
          <div class="bubble-wrap">
            <?php if ($msg['is_recalled']): ?>
              <div class="bubble recalled">消息已撤回</div>
            <?php else: ?>
              <div class="bubble <?= $isMine?'mine':'theirs' ?>"><?= h($msg['content']) ?></div>
              <?php if ($isMine): ?>
                <button class="recall-btn" data-id="<?= $msg['id'] ?>"
                        data-time="<?= strtotime($msg['created_at']) ?>"
                        onclick="recallMsg(<?= $msg['id'] ?>)">撤回</button>
              <?php endif; ?>
            <?php endif; ?>
          </div>
          <div class="bubble-time"><?= date('H:i', strtotime($msg['created_at'])) ?></div>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (empty($messages)): ?>
      <div style="text-align:center;color:var(--txt-3);font-size:13px;margin-top:40px">暂无消息，发一条开始聊天吧</div>
    <?php endif; ?>
  </div>

  <!-- 输入区 -->
  <div class="msg-footer">
    <textarea id="msg-input" placeholder="输入消息… (Enter 发送，Shift+Enter 换行)" rows="1"
              onkeydown="handleKey(event)" oninput="autoResize(this)"></textarea>
    <button class="btn btn-primary" style="padding:9px 18px" onclick="sendMsg()">发送</button>
  </div>

<?php endif; ?>
</div>

<!-- 成员面板（群组） -->
<?php if ($cid && $conv && $conv['type']==='group'): ?>
<div class="member-panel" id="member-panel">
  <div style="font-size:13px;font-weight:600;margin-bottom:10px;color:var(--txt)">👥 群成员 (<?= count($members) ?>)</div>
  <?php foreach ($members as $m): ?>
  <div class="member-row">
    <?php if (!empty($m['avatar'])): ?>
      <img src="<?= avatar_url($m['avatar'],'../') ?>" style="width:28px;height:28px;border-radius:50%;object-fit:cover">
    <?php else: ?>
      <div style="width:28px;height:28px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;font-size:11px;color:#fff;font-weight:700"><?= mb_substr($m['username'],0,1) ?></div>
    <?php endif; ?>
    <div style="flex:1;min-width:0;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($m['username']) ?>
      <?php if ($m['id'] == $conv['created_by']): ?><span style="font-size:10px;color:#f59e0b;margin-left:3px">群主</span><?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if ($is_creator): ?>
  <div style="margin-top:12px">
    <button class="btn btn-outline btn-sm" style="width:100%" onclick="openAddMember()">+ 添加成员</button>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

</div><!-- msg-layout -->

<!-- ── 发起私信弹窗 ─────────────────────────────── -->
<div id="modal-private" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
  <div class="card" style="width:400px;max-width:94vw;padding:0">
    <div class="card-header">✉ 发起私信</div>
    <div class="card-body">
      <div class="form-group">
        <label>搜索用户（用户名或 SCID）</label>
        <input type="text" id="private-search" placeholder="输入用户名或 SCID…" oninput="searchUsers(this.value,'private-results')">
      </div>
      <div id="private-results"></div>
      <div style="text-align:right;margin-top:12px">
        <button class="btn btn-outline" onclick="closeModal('modal-private')">取消</button>
      </div>
    </div>
  </div>
</div>

<!-- ── 创建群组弹窗 ─────────────────────────────── -->
<div id="modal-group" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
  <div class="card" style="width:440px;max-width:94vw;padding:0">
    <div class="card-header">👥 创建群组</div>
    <div class="card-body">
      <div class="form-group">
        <label>群组名称</label>
        <input type="text" id="group-name" maxlength="50" placeholder="输入群组名称…">
      </div>
      <div class="form-group">
        <label>添加成员（用户名或 SCID）</label>
        <input type="text" id="group-search" placeholder="搜索用户…" oninput="searchUsers(this.value,'group-results')">
        <div id="group-results" style="margin-top:8px"></div>
      </div>
      <div id="group-selected" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px"></div>
      <div style="display:flex;justify-content:flex-end;gap:8px">
        <button class="btn btn-outline" onclick="closeModal('modal-group')">取消</button>
        <button class="btn btn-primary" onclick="createGroup()">创建群组</button>
      </div>
    </div>
  </div>
</div>

<!-- ── 添加群成员弹窗 ──────────────────────────── -->
<div id="modal-add-member" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
  <div class="card" style="width:380px;max-width:94vw;padding:0">
    <div class="card-header">添加成员</div>
    <div class="card-body">
      <div class="form-group">
        <input type="text" id="add-member-search" placeholder="用户名或 SCID…" oninput="searchUsers(this.value,'add-member-results')">
        <div id="add-member-results" style="margin-top:8px"></div>
      </div>
      <div style="text-align:right">
        <button class="btn btn-outline" onclick="closeModal('modal-add-member')">关闭</button>
      </div>
    </div>
  </div>
</div>

<script>
var CID      = <?= $cid ?>;
var UID      = <?= $uid ?>;
var LAST_ID  = <?= $last_msg_id ?>;
var IS_GROUP = <?= ($conv && $conv['type']==='group') ? 'true' : 'false' ?>;
var selectedMembers = {}; // uid→name for group creation

// 自动滚到底部
function scrollBottom() {
    var b = document.getElementById('msg-body');
    if (b) b.scrollTop = b.scrollHeight;
}
scrollBottom();

// 隐藏超过2分钟的撤回按钮
function checkRecallBtns() {
    var now = Math.floor(Date.now()/1000);
    document.querySelectorAll('.recall-btn').forEach(function(btn) {
        var t = parseInt(btn.dataset.time);
        if (now - t > 120) btn.style.display = 'none !important';
        // also set display:none with style override
        if (now - t > 120) { btn.style.setProperty('display','none','important'); }
    });
}
checkRecallBtns();
setInterval(checkRecallBtns, 10000);

// textarea 自动高度
function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

// Enter 发送 / Shift+Enter 换行
function handleKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMsg(); }
}

// 发送消息
async function sendMsg() {
    if (!CID) return;
    var input   = document.getElementById('msg-input');
    var content = input.value.trim();
    if (!content) return;
    input.value = '';
    input.style.height = 'auto';

    var fd = new FormData();
    fd.append('action', 'send');
    fd.append('cid', CID);
    fd.append('content', content);

    var res = await fetch('../actions/message_action.php', {method:'POST', body:fd});
    var d   = await res.json();
    if (d.ok) appendMsg(d.msg);
}

// 追加一条消息到气泡区
function appendMsg(m) {
    var body  = document.getElementById('msg-body');
    var isMine = (m.user_id == UID);
    var recallable = (isMine && !m.is_recalled && (Math.floor(Date.now()/1000) - m.ts) <= 120);

    var html = '<div class="bubble-row '+(isMine?'mine':'')+'" id="msg-'+m.id+'">';
    if (!isMine) {
        html += m.avatar
            ? '<img class="bubble-ava" src="'+m.avatar+'" alt="">'
            : '<div class="bubble-ava-txt">'+m.uname.charAt(0)+'</div>';
    }
    html += '<div class="bubble-col">';
    if (IS_GROUP && !isMine) html += '<div class="bubble-sender">'+escHtml(m.uname)+'</div>';
    html += '<div class="bubble-wrap"><div class="bubble '+(isMine?'mine':'theirs')+'">'+escHtml(m.content)+'</div>';
    if (recallable) html += '<button class="recall-btn" data-id="'+m.id+'" data-time="'+m.ts+'" onclick="recallMsg('+m.id+')">撤回</button>';
    html += '</div><div class="bubble-time">'+m.time+'</div></div></div>';

    body.insertAdjacentHTML('beforeend', html);
    body.scrollTop = body.scrollHeight;
    LAST_ID = m.id;
}

// 撤回消息
async function recallMsg(mid) {
    if (!confirm('撤回这条消息？')) return;
    var fd = new FormData();
    fd.append('action','recall'); fd.append('message_id', mid);
    var res = await fetch('../actions/message_action.php',{method:'POST',body:fd});
    var d = await res.json();
    if (d.ok) {
        var row = document.getElementById('msg-'+mid);
        if (row) {
            var bw = row.querySelector('.bubble-wrap');
            bw.innerHTML = '<div class="bubble recalled">消息已撤回</div>';
        }
    } else { alert(d.error || '撤回失败'); }
}

// 轮询新消息
function pollMsgs() {
    if (!CID) return;
    fetch('../api/messages_poll.php?cid='+CID+'&after='+LAST_ID)
        .then(r=>r.json()).then(function(msgs) {
            msgs.forEach(function(m) { appendMsg(m); });
        }).catch(()=>{});
}
if (CID) setInterval(pollMsgs, 3000);

// XSS 转义
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// 搜索用户（私信/群组/加成员共用）
var searchTimer;
function searchUsers(val, targetId) {
    clearTimeout(searchTimer);
    var el = document.getElementById(targetId);
    if (!val.trim()) { el.innerHTML = ''; return; }
    searchTimer = setTimeout(function() {
        fetch('../api/user_search.php?q='+encodeURIComponent(val))
            .then(r=>r.json()).then(function(users) {
                if (!users.length) { el.innerHTML='<div style="font-size:12px;color:var(--txt-3);padding:6px">未找到用户</div>'; return; }
                el.innerHTML = users.map(function(u) {
                    var action = targetId==='private-results'
                        ? 'onclick="startPrivate('+u.id+')"'
                        : (targetId==='add-member-results'
                            ? 'onclick="addMemberToGroup('+u.id+',\''+escHtml(u.username)+'\')"'
                            : 'onclick="selectGroupMember('+u.id+',\''+escHtml(u.username)+'\')"');
                    return '<div class="member-row" style="cursor:pointer;padding:6px 0" '+action+'>'
                        +(u.avatar?'<img src="'+u.avatar+'" style="width:28px;height:28px;border-radius:50%;object-fit:cover">':'<div style="width:28px;height:28px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;font-size:11px;color:#fff;font-weight:700">'+escHtml(u.username.charAt(0))+'</div>')
                        +'<div><div style="font-size:13px;font-weight:600">'+escHtml(u.username)+'</div><div style="font-size:11px;color:var(--txt-3)">'+escHtml(u.scid)+'</div></div></div>';
                }).join('');
            });
    }, 300);
}

// 发起私信
function startPrivate(targetUid) {
    var fd = new FormData();
    fd.append('action','create_private'); fd.append('target_id',targetUid);
    fetch('../actions/message_action.php',{method:'POST',body:fd})
        .then(r=>r.json()).then(function(d) {
            if (d.ok) window.location.href='messages.php?cid='+d.cid;
            else alert(d.error||'失败');
        });
}

// 群组成员选择
function selectGroupMember(uid, uname) {
    if (uid == UID) { alert('不能把自己加入列表'); return; }
    if (selectedMembers[uid]) return;
    selectedMembers[uid] = uname;
    renderSelected();
    document.getElementById('group-search').value='';
    document.getElementById('group-results').innerHTML='';
}
function removeSelected(uid) { delete selectedMembers[uid]; renderSelected(); }
function renderSelected() {
    var el = document.getElementById('group-selected');
    var html = Object.entries(selectedMembers).map(function(e) {
        return '<span style="background:var(--primary-lt);color:var(--primary);padding:3px 10px;border-radius:12px;font-size:12px;display:flex;align-items:center;gap:4px">'
            +escHtml(e[1])+'<span onclick="removeSelected('+e[0]+')" style="cursor:pointer;font-weight:700">×</span></span>';
    }).join('');
    el.innerHTML = html;
}

// 创建群组
function createGroup() {
    var name = document.getElementById('group-name').value.trim();
    if (!name) { alert('请输入群组名称'); return; }
    var mids = Object.keys(selectedMembers);
    if (!mids.length) { alert('请至少添加一名成员'); return; }
    var fd = new FormData();
    fd.append('action','create_group'); fd.append('name',name);
    mids.forEach(function(m){ fd.append('members[]',m); });
    fetch('../actions/message_action.php',{method:'POST',body:fd})
        .then(r=>r.json()).then(function(d){
            if (d.ok) window.location.href='messages.php?cid='+d.cid;
            else alert(d.error||'创建失败');
        });
}

// 添加成员到现有群组
function addMemberToGroup(targetUid, uname) {
    if (!confirm('添加 '+uname+' 进入群组？')) return;
    var fd = new FormData();
    fd.append('action','add_member'); fd.append('cid',CID); fd.append('user_id',targetUid);
    fetch('../actions/message_action.php',{method:'POST',body:fd})
        .then(r=>r.json()).then(function(d){
            if (d.ok) { alert('已添加'); location.reload(); }
            else alert(d.error||'失败');
        });
}

// 弹窗控制
function openNewPrivate() { document.getElementById('modal-private').style.display='flex'; }
function openNewGroup()   { document.getElementById('modal-group').style.display='flex'; }
function openAddMember()  { document.getElementById('modal-add-member').style.display='flex'; }
function closeModal(id)   { document.getElementById(id).style.display='none'; }

['modal-private','modal-group','modal-add-member'].forEach(function(id) {
    var m = document.getElementById(id);
    if (!m) return;
    m.addEventListener('click', function(e){ if(e.target===m) m.style.display='none'; });
});

// 成员面板切换（群组）
function toggleMembers() {
    var p = document.getElementById('member-panel');
    if (p) p.classList.toggle('open');
}

// 左侧会话搜索过滤
function filterConvs(val) {
    val = val.toLowerCase();
    document.querySelectorAll('.msg-conv-item').forEach(function(el) {
        el.style.display = (!val || el.dataset.name.includes(val)) ? '' : 'none';
    });
}
</script>

<?php include '../includes/footer.php'; ?>
