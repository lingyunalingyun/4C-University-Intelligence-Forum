<?php
/*
 * admin/messages.php — 私信内容查阅后台
 * 功能：查看全站群组聊天记录，根据 SCID 查询指定用户的私信记录，
 *       仅用于内容合规审核，不支持修改。
 * 读库：messages / message_groups / group_members / users
 * 权限：需 admin/owner 登录
 */
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../pages/login.php'); exit; }
if (!in_array($_SESSION['role'] ?? '', ['admin','owner'])) { header('Location: ../index.php'); exit; }

$tab = $_GET['tab'] ?? 'groups';

// 所有群组
$groups = [];
if ($tab === 'groups') {
    $gr = $conn->query("SELECT cv.*, u.username AS creator_name,
        (SELECT COUNT(*) FROM conversation_members WHERE conversation_id=cv.id) AS member_count,
        (SELECT COUNT(*) FROM messages WHERE conversation_id=cv.id) AS msg_count,
        (SELECT created_at FROM messages WHERE conversation_id=cv.id ORDER BY created_at DESC LIMIT 1) AS last_at
        FROM conversations cv JOIN users u ON u.id=cv.created_by
        WHERE cv.type='group' ORDER BY cv.created_at DESC");
    if ($gr) while ($r = $gr->fetch_assoc()) $groups[] = $r;
}

// 群组详情
$group_detail = null;
$group_msgs   = [];
$group_membs  = [];
if ($tab === 'group_detail' && isset($_GET['cid'])) {
    $gcid = intval($_GET['cid']);
    $cvr  = $conn->query("SELECT cv.*,u.username AS creator_name FROM conversations cv JOIN users u ON u.id=cv.created_by WHERE cv.id=$gcid AND cv.type='group'");
    $group_detail = $cvr ? $cvr->fetch_assoc() : null;
    if ($group_detail) {
        $mr = $conn->query("SELECT m.*,u.username,u.avatar FROM messages m JOIN users u ON u.id=m.user_id WHERE m.conversation_id=$gcid ORDER BY m.created_at ASC LIMIT 500");
        if ($mr) while ($r = $mr->fetch_assoc()) $group_msgs[] = $r;
        $mmr = $conn->query("SELECT u.id,u.username,u.avatar,u.scid FROM conversation_members cm JOIN users u ON u.id=cm.user_id WHERE cm.conversation_id=$gcid");
        if ($mmr) while ($r = $mmr->fetch_assoc()) $group_membs[] = $r;
    }
}

// SCID 查询私信
$lookup_msgs  = null;
$lookup_users = null;
$lookup_cid   = 0;
$lookup_err   = '';
if ($tab === 'lookup' && isset($_GET['scid1'], $_GET['scid2'])) {
    $s1 = trim($_GET['scid1']); $s2 = trim($_GET['scid2']);
    $u1r = $conn->query("SELECT id,username,scid,avatar FROM users WHERE scid='".$conn->real_escape_string($s1)."'");
    $u2r = $conn->query("SELECT id,username,scid,avatar FROM users WHERE scid='".$conn->real_escape_string($s2)."'");
    $u1 = $u1r ? $u1r->fetch_assoc() : null;
    $u2 = $u2r ? $u2r->fetch_assoc() : null;
    if (!$u1)      $lookup_err = "找不到 SCID：$s1";
    elseif (!$u2)  $lookup_err = "找不到 SCID：$s2";
    else {
        $lookup_users = [$u1, $u2];
        $uid1 = (int)$u1['id']; $uid2 = (int)$u2['id'];
        $cvr = $conn->query("SELECT cv.id FROM conversations cv
            JOIN conversation_members cm1 ON cm1.conversation_id=cv.id AND cm1.user_id=$uid1
            JOIN conversation_members cm2 ON cm2.conversation_id=cv.id AND cm2.user_id=$uid2
            WHERE cv.type='private' LIMIT 1");
        if ($cvr && $cvr->num_rows) {
            $lookup_cid = (int)$cvr->fetch_assoc()['id'];
            $mr = $conn->query("SELECT m.*,u.username,u.avatar FROM messages m JOIN users u ON u.id=m.user_id WHERE m.conversation_id=$lookup_cid ORDER BY m.created_at ASC LIMIT 500");
            $lookup_msgs = [];
            if ($mr) while ($r = $mr->fetch_assoc()) $lookup_msgs[] = $r;
        } else { $lookup_msgs = []; }
    }
}

$page_title = '消息记录';
$in_admin   = true;
include '../includes/header.php';
?>
<style>
.chat-log { background:var(--bg-2);border:1px solid var(--border);border-radius:var(--r-lg);padding:16px;max-height:65vh;overflow-y:auto; }
.log-row { display:flex;gap:8px;margin-bottom:12px;align-items:flex-start; }
.log-row.mine { flex-direction:row-reverse; }
.log-av { width:28px;height:28px;border-radius:50%;object-fit:cover;flex-shrink:0; }
.log-av-txt { width:28px;height:28px;border-radius:50%;background:var(--primary);color:#fff;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.log-bubble { max-width:58%;padding:8px 12px;border-radius:10px;font-size:13px;word-break:break-word; }
.log-bubble.theirs { background:var(--bg-card);border:1px solid var(--border); }
.log-bubble.mine   { background:var(--primary);color:#fff; }
.log-bubble.recalled { background:transparent;border:1px dashed var(--border);color:var(--txt-3);font-style:italic; }
.log-meta { font-size:10px;color:var(--txt-3);margin-top:3px; }
</style>

<div class="admin-page-hd">
  <div><h2>💬 消息记录</h2><div class="sub">群组 & 私信查询</div></div>
</div>

<!-- Tab -->
<div style="display:flex;gap:6px;margin-bottom:20px">
  <a href="?tab=groups" class="sub-section-tag <?= $tab==='groups'?'active':'' ?>">所有群组</a>
  <a href="?tab=lookup" class="sub-section-tag <?= $tab==='lookup'?'active':'' ?>">SCID 私信查询</a>
</div>

<!-- ── 群组列表 ── -->
<?php if ($tab === 'groups'): ?>
  <?php if (empty($groups)): ?>
    <div class="empty-state"><div class="icon">👥</div><p>暂无群组</p></div>
  <?php else: ?>
  <div class="card" style="overflow-x:auto">
    <table class="data-table">
      <thead>
        <tr><th>群组名</th><th>创建者</th><th style="width:70px">成员</th><th style="width:70px">消息数</th><th style="width:100px">最后活跃</th><th style="width:80px">操作</th></tr>
      </thead>
      <tbody>
        <?php foreach ($groups as $g): ?>
        <tr>
          <td style="font-weight:600"><?= h($g['name']) ?></td>
          <td style="font-size:13px"><?= h($g['creator_name']) ?></td>
          <td style="font-size:13px"><?= $g['member_count'] ?></td>
          <td style="font-size:13px"><?= $g['msg_count'] ?></td>
          <td style="font-size:12px;color:var(--txt-3)"><?= $g['last_at'] ? date('m-d H:i', strtotime($g['last_at'])) : '—' ?></td>
          <td><a href="?tab=group_detail&cid=<?= $g['id'] ?>" class="btn btn-outline btn-sm" style="font-size:11px">查看记录</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

<!-- ── 群组详情 ── -->
<?php elseif ($tab === 'group_detail'): ?>
  <?php if (!$group_detail): ?>
    <div class="alert-danger">群组不存在</div>
  <?php else: ?>
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
    <a href="?tab=groups" class="btn btn-outline btn-sm">← 返回列表</a>
    <h3 style="margin:0;font-size:15px">「<?= h($group_detail['name']) ?>」聊天记录</h3>
    <span style="font-size:12px;color:var(--txt-3)">创建者：<?= h($group_detail['creator_name']) ?></span>
  </div>
  <div class="layout-2col" style="gap:16px">
    <div class="col-main">
      <div class="chat-log">
        <?php if (empty($group_msgs)): ?>
          <div style="text-align:center;color:var(--txt-3);padding:30px;font-size:13px">暂无消息</div>
        <?php endif; ?>
        <?php foreach ($group_msgs as $m): ?>
        <div class="log-row">
          <?php if (!empty($m['avatar'])): ?>
            <img src="<?= avatar_url($m['avatar'],'../') ?>" class="log-av">
          <?php else: ?>
            <div class="log-av-txt"><?= mb_substr($m['username'],0,1) ?></div>
          <?php endif; ?>
          <div>
            <div class="log-meta"><?= h($m['username']) ?> · <?= date('m-d H:i', strtotime($m['created_at'])) ?></div>
            <div class="log-bubble <?= $m['is_recalled']?'recalled':'theirs' ?>">
              <?= $m['is_recalled'] ? '消息已撤回' : h($m['content']) ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="col-side">
      <div class="card">
        <div class="card-header">成员（<?= count($group_membs) ?>）</div>
        <div style="padding:8px 0">
          <?php foreach ($group_membs as $m): ?>
          <div style="display:flex;align-items:center;gap:8px;padding:7px 16px;border-bottom:1px solid var(--border)">
            <?php if (!empty($m['avatar'])): ?>
              <img src="<?= avatar_url($m['avatar'],'../') ?>" style="width:28px;height:28px;border-radius:50%;object-fit:cover;flex-shrink:0">
            <?php else: ?>
              <div class="log-av-txt"><?= mb_substr($m['username'],0,1) ?></div>
            <?php endif; ?>
            <div>
              <div style="font-size:13px;font-weight:500"><?= h($m['username']) ?></div>
              <?php if (!empty($m['scid'])): ?><div style="font-size:11px;color:var(--txt-3)"><?= h($m['scid']) ?></div><?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

<!-- ── SCID 私信查询 ── -->
<?php elseif ($tab === 'lookup'): ?>
  <div class="card" style="margin-bottom:20px">
    <div class="card-header">🔍 通过 SCID 查询两用户之间的私信记录</div>
    <div class="card-body">
      <form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
        <input type="hidden" name="tab" value="lookup">
        <div class="form-group" style="margin:0;flex:1;min-width:150px">
          <label>用户 A 的 SCID</label>
          <input type="text" name="scid1" value="<?= h($_GET['scid1']??'') ?>" placeholder="如 AB12CD34" maxlength="8">
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:150px">
          <label>用户 B 的 SCID</label>
          <input type="text" name="scid2" value="<?= h($_GET['scid2']??'') ?>" placeholder="如 EF56GH78" maxlength="8">
        </div>
        <button type="submit" class="btn btn-primary" style="flex-shrink:0">查询</button>
      </form>
    </div>
  </div>

  <?php if ($lookup_err): ?>
    <div class="alert-danger"><?= h($lookup_err) ?></div>
  <?php elseif ($lookup_users !== null): ?>
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap">
      <?php foreach ($lookup_users as $lu): ?>
      <div style="display:flex;align-items:center;gap:8px;background:var(--bg-card);border:1px solid var(--border);padding:8px 14px;border-radius:var(--r-lg)">
        <img src="<?= avatar_url($lu['avatar'],'../') ?>" style="width:30px;height:30px;border-radius:50%;object-fit:cover">
        <div>
          <div style="font-size:13px;font-weight:600"><?= h($lu['username']) ?></div>
          <div style="font-size:11px;color:var(--txt-3)"><?= h($lu['scid']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if ($lookup_cid): ?>
        <span style="font-size:13px;color:var(--txt-2)">共 <?= count($lookup_msgs) ?> 条消息</span>
      <?php endif; ?>
    </div>

    <?php if (!$lookup_cid): ?>
      <div class="empty-state"><div class="icon">💬</div><p>这两位用户之间暂无私信记录</p></div>
    <?php elseif (empty($lookup_msgs)): ?>
      <div class="empty-state"><div class="icon">💬</div><p>会话存在，但暂无消息</p></div>
    <?php else: ?>
      <?php $uid1 = (int)$lookup_users[0]['id']; ?>
      <div class="chat-log">
        <?php foreach ($lookup_msgs as $m): ?>
        <?php $mine = ($m['user_id'] == $uid1); ?>
        <div class="log-row <?= $mine?'mine':'' ?>">
          <?php if (!$mine): ?>
            <?php if (!empty($m['avatar'])): ?>
              <img src="<?= avatar_url($m['avatar'],'../') ?>" class="log-av">
            <?php else: ?>
              <div class="log-av-txt"><?= mb_substr($m['username'],0,1) ?></div>
            <?php endif; ?>
          <?php endif; ?>
          <div>
            <div class="log-meta" <?= $mine?'style="text-align:right"':'' ?>><?= h($m['username']) ?> · <?= date('m-d H:i', strtotime($m['created_at'])) ?><?= $m['is_recalled']?' [已撤回]':'' ?></div>
            <div class="log-bubble <?= $m['is_recalled']?'recalled':($mine?'mine':'theirs') ?>">
              <?= $m['is_recalled'] ? '消息已撤回' : h($m['content']) ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
