<?php
require_once '../config.php';
require_once '../includes/helpers.php';

$club_id = intval($_GET['id'] ?? 0);
if (!$club_id) { header('Location: clubs.php'); exit; }

$cr = $conn->query("SELECT c.*, u.username AS president_name
    FROM clubs c JOIN users u ON u.id = c.president_id
    WHERE c.id = $club_id AND c.status = 'active'");
$club = $cr ? $cr->fetch_assoc() : null;
if (!$club) { header('Location: clubs.php'); exit; }

$uid = intval($_SESSION['user_id'] ?? 0);

// 当前用户角色
$my_role = null;
if ($uid) {
    $mr = $conn->query("SELECT role FROM club_members WHERE club_id=$club_id AND user_id=$uid");
    if ($mr && $mr->num_rows) $my_role = $mr->fetch_assoc()['role'];
}

// 当前用户加入申请状态
$my_req_status = null;
if ($uid && !$my_role) {
    $rr = $conn->query("SELECT status FROM club_join_requests WHERE club_id=$club_id AND user_id=$uid");
    if ($rr && $rr->num_rows) $my_req_status = $rr->fetch_assoc()['status'];
}

// 成员列表
$members = [];
$memr = $conn->query("SELECT cm.role, cm.joined_at, u.id, u.username, u.avatar, u.school
    FROM club_members cm JOIN users u ON u.id = cm.user_id
    WHERE cm.club_id = $club_id
    ORDER BY FIELD(cm.role,'president','vice_president','member'), cm.joined_at ASC");
if ($memr) while ($r = $memr->fetch_assoc()) $members[] = $r;

// 待审核入团申请（社长/副社长可见）
$join_reqs = [];
if ($uid && in_array($my_role, ['president','vice_president'])) {
    $jrq = $conn->query("SELECT jr.*, u.username, u.avatar, u.school
        FROM club_join_requests jr JOIN users u ON u.id = jr.user_id
        WHERE jr.club_id = $club_id AND jr.status = 'pending'
        ORDER BY jr.created_at ASC");
    if ($jrq) while ($r = $jrq->fetch_assoc()) $join_reqs[] = $r;
}

$is_president = ($my_role === 'president');
$is_vp        = ($my_role === 'vice_president');
$is_member    = !is_null($my_role);
$user_school  = $_SESSION['school'] ?? '';
$school_match = ($user_school !== '' && $user_school === $club['school']);

function club_role_label($role) {
    return ['president'=>'社长','vice_president'=>'副社长','member'=>'成员'][$role] ?? '成员';
}
function club_role_color($role) {
    return ['president'=>'#dc2626','vice_president'=>'#7c3aed','member'=>'#6b7280'][$role] ?? '#6b7280';
}

$page_title = $club['name'];
include '../includes/header.php';
?>

<style>
.club-member-row { display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border); }
.club-member-row:last-child { border-bottom:none; }
.club-role-badge { font-size:10px;font-weight:700;padding:2px 7px;border-radius:4px;color:#fff; }
.club-action-btn { font-size:12px;padding:3px 10px;border-radius:6px;border:1.5px solid var(--border);background:transparent;color:var(--txt-2);cursor:pointer;transition:border-color .15s,color .15s; }
.club-action-btn:hover { border-color:var(--primary);color:var(--primary); }
.club-action-btn.danger:hover { border-color:#ef4444;color:#ef4444; }
.req-card { display:flex;align-items:center;gap:10px;padding:10px;background:var(--bg-2);border-radius:8px;margin-bottom:8px; }
</style>

<!-- 社团头部 -->
<div class="card mb-20" style="padding:24px">
  <div style="display:flex;gap:18px;align-items:flex-start;flex-wrap:wrap">
    <div style="width:64px;height:64px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;font-size:26px;color:#fff;font-weight:700;flex-shrink:0">
      <?= mb_substr($club['name'], 0, 1) ?>
    </div>
    <div style="flex:1;min-width:0">
      <h2 style="margin:0 0 4px"><?= h($club['name']) ?></h2>
      <div style="font-size:13px;color:var(--txt-2);margin-bottom:8px">
        🏫 <?= h($club['school']) ?> &nbsp;·&nbsp;
        👥 <?= $club['member_count'] ?> 人 &nbsp;·&nbsp;
        👑 社长：<?= h($club['president_name']) ?> &nbsp;·&nbsp;
        📅 <?= date('Y-m-d', strtotime($club['created_at'])) ?> 成立
      </div>
      <?php if ($club['description']): ?>
        <div style="font-size:14px;color:var(--txt);line-height:1.6"><?= h($club['description']) ?></div>
      <?php endif; ?>
    </div>

    <!-- 操作按钮 -->
    <div style="flex-shrink:0;display:flex;flex-direction:column;gap:8px;align-items:flex-end">
      <?php if (!$uid): ?>
        <a href="login.php" class="btn btn-outline btn-sm">登录后加入</a>

      <?php elseif ($is_president): ?>
        <span style="font-size:13px;color:#dc2626;font-weight:600">👑 你是社长</span>
        <button class="btn btn-outline btn-sm" onclick="openTransfer()">移交社长</button>

      <?php elseif ($is_vp): ?>
        <span style="font-size:13px;color:#7c3aed;font-weight:600">⭐ 你是副社长</span>
        <form action="../actions/club_action.php" method="post" onsubmit="return confirm('确认退出该社团？')">
          <input type="hidden" name="action"  value="leave">
          <input type="hidden" name="club_id" value="<?= $club_id ?>">
          <button type="submit" class="btn btn-outline btn-sm" style="color:#ef4444;border-color:#ef4444">退出社团</button>
        </form>

      <?php elseif ($my_role === 'member'): ?>
        <span style="font-size:13px;color:var(--txt-2)">✅ 已加入</span>
        <form action="../actions/club_action.php" method="post" onsubmit="return confirm('确认退出该社团？')">
          <input type="hidden" name="action"  value="leave">
          <input type="hidden" name="club_id" value="<?= $club_id ?>">
          <button type="submit" class="btn btn-outline btn-sm" style="color:#ef4444;border-color:#ef4444">退出社团</button>
        </form>

      <?php elseif ($my_req_status === 'pending'): ?>
        <span style="font-size:13px;color:var(--txt-2)">⏳ 申请待审核</span>
        <form action="../actions/club_action.php" method="post">
          <input type="hidden" name="action"  value="cancel_join">
          <input type="hidden" name="club_id" value="<?= $club_id ?>">
          <button type="submit" class="btn btn-outline btn-sm">撤回申请</button>
        </form>

      <?php elseif (!$school_match): ?>
        <div style="font-size:13px;color:var(--txt-3);text-align:right">
          🚫 此社团仅限<br><?= h($club['school']) ?>学生加入
        </div>

      <?php else: ?>
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('join-panel').style.display='block';this.style.display='none'">申请加入</button>
        <div id="join-panel" style="display:none">
          <form action="../actions/club_action.php" method="post">
            <input type="hidden" name="action"  value="request_join">
            <input type="hidden" name="club_id" value="<?= $club_id ?>">
            <textarea name="message" maxlength="200" rows="2"
                      style="width:200px;padding:6px 10px;font-size:13px;border:1.5px solid var(--border);border-radius:6px;resize:none;background:var(--bg);color:var(--txt)"
                      placeholder="申请理由（选填）…"></textarea>
            <div style="display:flex;gap:6px;margin-top:6px">
              <button type="submit" class="btn btn-primary btn-sm">提交申请</button>
              <button type="button" class="btn btn-outline btn-sm"
                      onclick="this.closest('#join-panel').style.display='none';document.querySelector('[onclick*=join-panel]').style.display=''">取消</button>
            </div>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="layout-2col">
<div class="col-main">

<!-- 待审核入团申请 -->
<?php if (!empty($join_reqs)): ?>
<div class="card mb-16">
  <div class="card-header">📋 待审核申请 <span style="background:#ef4444;color:#fff;font-size:11px;padding:1px 7px;border-radius:10px;margin-left:6px"><?= count($join_reqs) ?></span></div>
  <div class="card-body">
    <?php foreach ($join_reqs as $jr): ?>
    <div class="req-card">
      <img src="<?= avatar_url($jr['avatar'],'../') ?>" onerror="this.src='../assets/default_avatar.svg'"
           style="width:36px;height:36px;border-radius:50%;object-fit:cover">
      <div style="flex:1;min-width:0">
        <div style="font-size:13px;font-weight:600"><?= h($jr['username']) ?>
          <span style="font-size:11px;color:var(--txt-3);font-weight:400"><?= h($jr['school']) ?></span>
        </div>
        <?php if ($jr['message']): ?>
          <div style="font-size:12px;color:var(--txt-2);margin-top:2px"><?= h($jr['message']) ?></div>
        <?php endif; ?>
      </div>
      <div style="display:flex;gap:6px;flex-shrink:0">
        <form action="../actions/club_action.php" method="post">
          <input type="hidden" name="action"  value="approve_join">
          <input type="hidden" name="club_id" value="<?= $club_id ?>">
          <input type="hidden" name="req_id"  value="<?= $jr['id'] ?>">
          <button type="submit" class="club-action-btn" style="border-color:#10b981;color:#10b981">✓ 批准</button>
        </form>
        <button class="club-action-btn danger"
                onclick="openRejectJoin(<?= $jr['id'] ?>,'<?= h($jr['username']) ?>')">✕ 拒绝</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- 成员列表 -->
<div class="card">
  <div class="card-header">👥 成员（<?= $club['member_count'] ?>）</div>
  <div class="card-body" style="padding:0 16px">
    <?php foreach ($members as $m): ?>
    <div class="club-member-row">
      <img src="<?= avatar_url($m['avatar'],'../') ?>" onerror="this.src='../assets/default_avatar.svg'"
           style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0">
      <div style="flex:1;min-width:0">
        <div style="font-size:14px;font-weight:500;display:flex;align-items:center;gap:6px">
          <?= h($m['username']) ?>
          <span class="club-role-badge" style="background:<?= club_role_color($m['role']) ?>">
            <?= club_role_label($m['role']) ?>
          </span>
        </div>
        <div style="font-size:11px;color:var(--txt-3)">加入于 <?= date('Y-m-d', strtotime($m['joined_at'])) ?></div>
      </div>

      <?php if ($is_president && $m['id'] !== $uid): ?>
      <div style="display:flex;gap:5px;flex-shrink:0">
        <?php if ($m['role'] === 'member'): ?>
          <form action="../actions/club_action.php" method="post">
            <input type="hidden" name="action"  value="set_role">
            <input type="hidden" name="club_id" value="<?= $club_id ?>">
            <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
            <input type="hidden" name="role"    value="vice_president">
            <button type="submit" class="club-action-btn">设为副社长</button>
          </form>
        <?php elseif ($m['role'] === 'vice_president'): ?>
          <form action="../actions/club_action.php" method="post">
            <input type="hidden" name="action"  value="set_role">
            <input type="hidden" name="club_id" value="<?= $club_id ?>">
            <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
            <input type="hidden" name="role"    value="member">
            <button type="submit" class="club-action-btn">取消副社长</button>
          </form>
        <?php endif; ?>
        <button class="club-action-btn danger"
                onclick="openKick(<?= $m['id'] ?>,'<?= h($m['username']) ?>')">踢出</button>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

</div><!-- col-main -->

<div class="col-side">
  <div class="card">
    <div class="card-header">ℹ️ 社团信息</div>
    <div class="card-body" style="font-size:13px;color:var(--txt-2);line-height:2">
      <div>🏫 附属学校：<?= h($club['school']) ?></div>
      <div>👑 社长：<?= h($club['president_name']) ?></div>
      <div>👥 成员数：<?= $club['member_count'] ?></div>
      <div>📅 成立：<?= date('Y年m月d日', strtotime($club['created_at'])) ?></div>
    </div>
  </div>
</div>

</div><!-- layout-2col -->

<!-- 踢出弹窗 -->
<div id="kick-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;display:none;align-items:center;justify-content:center">
  <div class="card" style="width:400px;max-width:90vw;padding:0">
    <div class="card-header">踢出成员</div>
    <div class="card-body">
      <p style="margin:0 0 12px;font-size:14px">踢出 <strong id="kick-name"></strong>，请填写原因：</p>
      <form action="../actions/club_action.php" method="post">
        <input type="hidden" name="action"  value="kick">
        <input type="hidden" name="club_id" value="<?= $club_id ?>">
        <input type="hidden" name="user_id" id="kick-uid" value="">
        <textarea name="reason" required maxlength="200" rows="3"
                  style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:8px;resize:none;background:var(--bg);color:var(--txt);box-sizing:border-box"
                  placeholder="必填：踢出原因…"></textarea>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
          <button type="button" class="btn btn-outline" onclick="closeKick()">取消</button>
          <button type="submit" class="btn btn-sm" style="background:#ef4444;color:#fff;border:none">确认踢出</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- 拒绝入团弹窗 -->
<div id="reject-join-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
  <div class="card" style="width:380px;max-width:90vw;padding:0">
    <div class="card-header">拒绝申请</div>
    <div class="card-body">
      <p style="margin:0 0 12px;font-size:14px">拒绝 <strong id="reject-join-name"></strong> 的入团申请：</p>
      <form action="../actions/club_action.php" method="post">
        <input type="hidden" name="action"  value="reject_join">
        <input type="hidden" name="club_id" value="<?= $club_id ?>">
        <input type="hidden" name="req_id"  id="reject-join-rid" value="">
        <textarea name="reject_reason" maxlength="200" rows="2"
                  style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:8px;resize:none;background:var(--bg);color:var(--txt);box-sizing:border-box"
                  placeholder="拒绝原因（选填）…"></textarea>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
          <button type="button" class="btn btn-outline" onclick="closeRejectJoin()">取消</button>
          <button type="submit" class="btn btn-sm" style="background:#ef4444;color:#fff;border:none">确认拒绝</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- 移交社长弹窗 -->
<?php if ($is_president && count($members) > 1): ?>
<div id="transfer-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
  <div class="card" style="width:400px;max-width:90vw;padding:0">
    <div class="card-header">移交社长</div>
    <div class="card-body">
      <p style="margin:0 0 12px;font-size:14px;color:var(--txt-2)">移交后您将变为普通成员。</p>
      <form action="../actions/club_action.php" method="post">
        <input type="hidden" name="action"  value="transfer">
        <input type="hidden" name="club_id" value="<?= $club_id ?>">
        <div class="form-group">
          <label>选择新社长</label>
          <select name="new_president_id" required style="width:100%;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;background:var(--bg);color:var(--txt)">
            <option value="">— 请选择 —</option>
            <?php foreach ($members as $m): if ($m['id'] === $uid) continue; ?>
              <option value="<?= $m['id'] ?>"><?= h($m['username']) ?> (<?= club_role_label($m['role']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
          <button type="button" class="btn btn-outline" onclick="closeTransfer()">取消</button>
          <button type="submit" class="btn btn-primary" onclick="return confirm('确认移交社长？此操作不可撤销。')">确认移交</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
function openKick(uid, name) {
    document.getElementById('kick-uid').value = uid;
    document.getElementById('kick-name').textContent = name;
    var m = document.getElementById('kick-modal');
    m.style.display = 'flex';
}
function closeKick() { document.getElementById('kick-modal').style.display = 'none'; }

function openRejectJoin(rid, name) {
    document.getElementById('reject-join-rid').value  = rid;
    document.getElementById('reject-join-name').textContent = name;
    var m = document.getElementById('reject-join-modal');
    m.style.display = 'flex';
}
function closeRejectJoin() { document.getElementById('reject-join-modal').style.display = 'none'; }

function openTransfer() {
    var m = document.getElementById('transfer-modal');
    if (m) m.style.display = 'flex';
}
function closeTransfer() {
    var m = document.getElementById('transfer-modal');
    if (m) m.style.display = 'none';
}

// 点击遮罩关闭
['kick-modal','reject-join-modal','transfer-modal'].forEach(function(id) {
    var m = document.getElementById(id);
    if (!m) return;
    m.addEventListener('click', function(e) {
        if (e.target === m) m.style.display = 'none';
    });
});
</script>

<?php include '../includes/footer.php'; ?>
