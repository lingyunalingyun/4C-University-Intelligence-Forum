<?php
/*
 * admin/clubs.php — 社团管理后台
 * 功能：审批社团申请（通过/拒绝）、审批改名申请、强制解散社团，
 *       查看所有社团状态和成员数。
 * 写库：clubs / club_members / admin_logs
 * 权限：需 admin/owner 登录
 */
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../pages/login.php'); exit; }
if (!in_array($_SESSION['role'] ?? '', ['admin','owner'])) { header('Location: ../index.php'); exit; }

$tab = $_GET['tab'] ?? 'pending';
$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

// 待审核申请
$pending = [];
$pr = $conn->query("SELECT a.*, u.username, u.school as user_school
    FROM club_applications a JOIN users u ON u.id=a.user_id
    WHERE a.status='pending' ORDER BY a.created_at ASC");
if ($pr) while ($r = $pr->fetch_assoc()) $pending[] = $r;

// 待审核改名
$pending_names = [];
$pnr = $conn->query("SELECT nc.*, c.name AS current_name, u.username
    FROM club_name_changes nc JOIN clubs c ON c.id=nc.club_id JOIN users u ON u.id=nc.user_id
    WHERE nc.status='pending' ORDER BY nc.created_at ASC");
if ($pnr) while ($r = $pnr->fetch_assoc()) $pending_names[] = $r;

// 全部社团
$clubs = [];
if ($tab === 'clubs') {
    $cr = $conn->query("SELECT c.*, u.username AS president_name
        FROM clubs c JOIN users u ON u.id=c.president_id ORDER BY c.created_at DESC");
    if ($cr) while ($r = $cr->fetch_assoc()) $clubs[] = $r;
}

// 审核历史
$history = [];
if ($tab === 'history') {
    $hr = $conn->query("SELECT a.*, u.username, rv.username AS reviewer
        FROM club_applications a JOIN users u ON u.id=a.user_id
        LEFT JOIN users rv ON rv.id=a.reviewed_by
        WHERE a.status!='pending' ORDER BY a.reviewed_at DESC LIMIT 50");
    if ($hr) while ($r = $hr->fetch_assoc()) $history[] = $r;
}

$page_title = '社团管理';
$in_admin   = true;
include '../includes/header.php';
?>

<div class="admin-page-hd">
  <div>
    <h2><i data-lucide="building-2" class="lucide"></i> 社团管理</h2>
    <div class="sub">待审核
      <strong style="color:<?= count($pending)?'#ef4444':'inherit' ?>"><?= count($pending) ?></strong> 个申请
    </div>
  </div>
</div>

<?php if ($msg): ?><div class="alert-success"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert-danger"><?= h($err) ?></div><?php endif; ?>

<!-- Tab 栏 -->
<div style="display:flex;gap:6px;margin-bottom:20px;flex-wrap:wrap">
  <a href="?tab=pending" class="sub-section-tag <?= $tab==='pending'?'active':'' ?>">
    待审核
    <?php if (count($pending)): ?>
      <span style="display:inline-block;background:#ef4444;color:#fff;font-size:10px;padding:1px 6px;border-radius:10px;margin-left:4px;font-weight:700"><?= count($pending) ?></span>
    <?php endif; ?>
  </a>
  <a href="?tab=names" class="sub-section-tag <?= $tab==='names'?'active':'' ?>">
    改名申请
    <?php if (count($pending_names)): ?>
      <span style="display:inline-block;background:#f59e0b;color:#fff;font-size:10px;padding:1px 6px;border-radius:10px;margin-left:4px;font-weight:700"><?= count($pending_names) ?></span>
    <?php endif; ?>
  </a>
  <a href="?tab=clubs"   class="sub-section-tag <?= $tab==='clubs'  ?'active':'' ?>">全部社团</a>
  <a href="?tab=history" class="sub-section-tag <?= $tab==='history'?'active':'' ?>">审核记录</a>
</div>

<!-- ── 待审核申请 ── -->
<?php if ($tab === 'pending'): ?>
  <?php if (empty($pending)): ?>
    <div class="empty-state"><div class="icon"><i data-lucide="check-circle" class="lucide"></i></div><p>暂无待审核的社团申请</p></div>
  <?php else: ?>
    <?php foreach ($pending as $a): ?>
    <div class="card" style="margin-bottom:14px">
      <div class="card-header" style="justify-content:space-between">
        <div>
          <span style="font-size:15px;font-weight:700"><?= h($a['name']) ?></span>
          <span style="font-size:12px;color:var(--txt-3);margin-left:10px"><?= date('Y-m-d H:i', strtotime($a['created_at'])) ?></span>
        </div>
        <span class="spill spill-yellow">待审核</span>
      </div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px 24px;margin-bottom:14px">
          <div style="font-size:13px"><span style="color:var(--txt-3)">申请人：</span><?= h($a['username']) ?><?= $a['user_school'] ? "（{$a['user_school']}）" : '' ?></div>
          <div style="font-size:13px"><span style="color:var(--txt-3)">附属学校：</span><?= h($a['school']) ?></div>
          <div style="font-size:13px;grid-column:1/-1"><span style="color:var(--txt-3)">社团简介：</span><?= h($a['description']) ?></div>
          <div style="font-size:13px;grid-column:1/-1"><span style="color:var(--txt-3)">创建目的：</span><?= h($a['purpose']) ?></div>
        </div>
        <div style="display:flex;gap:8px">
          <form action="../actions/club_action.php" method="post">
            <input type="hidden" name="action" value="admin_approve">
            <input type="hidden" name="app_id" value="<?= $a['id'] ?>">
            <button type="submit" class="btn btn-primary btn-sm"
                    onclick="return confirm('批准「<?= h($a['name']) ?>」的社团申请？')">✓ 批准</button>
          </form>
          <button class="btn btn-danger btn-sm" onclick="openReject(<?= $a['id'] ?>,'<?= h(addslashes($a['name'])) ?>')">✕ 拒绝</button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

<!-- ── 改名申请 ── -->
<?php elseif ($tab === 'names'): ?>
  <?php if (empty($pending_names)): ?>
    <div class="empty-state"><div class="icon"><i data-lucide="check-circle" class="lucide"></i></div><p>暂无待审核的改名申请</p></div>
  <?php else: ?>
    <?php foreach ($pending_names as $nc): ?>
    <div class="card" style="margin-bottom:14px">
      <div class="card-header" style="justify-content:space-between">
        <div style="font-size:14px;font-weight:600">
          「<?= h($nc['current_name']) ?>」&nbsp;→&nbsp;「<?= h($nc['new_name']) ?>」
        </div>
        <span class="spill spill-yellow">待审核</span>
      </div>
      <div class="card-body">
        <div style="font-size:13px;color:var(--txt-2);margin-bottom:14px">
          申请人：<strong><?= h($nc['username']) ?></strong> &nbsp;·&nbsp; 社团ID：#<?= $nc['club_id'] ?>
          &nbsp;·&nbsp; <?= date('Y-m-d H:i', strtotime($nc['created_at'])) ?>
        </div>
        <div style="display:flex;gap:8px">
          <form action="../actions/club_action.php" method="post">
            <input type="hidden" name="action" value="admin_approve_name">
            <input type="hidden" name="name_change_id" value="<?= $nc['id'] ?>">
            <button type="submit" class="btn btn-primary btn-sm"
                    onclick="return confirm('批准将「<?= h($nc['current_name']) ?>」改名为「<?= h($nc['new_name']) ?>」？')">✓ 批准</button>
          </form>
          <button class="btn btn-danger btn-sm" onclick="openRejectName(<?= $nc['id'] ?>,'<?= h(addslashes($nc['new_name'])) ?>')">✕ 拒绝</button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

<!-- ── 全部社团 ── -->
<?php elseif ($tab === 'clubs'): ?>
  <?php if (empty($clubs)): ?>
    <div class="empty-state"><div class="icon"><i data-lucide="building-2" class="lucide"></i></div><p>暂无社团</p></div>
  <?php else: ?>
  <div class="card" style="overflow-x:auto">
    <table class="data-table">
      <thead>
        <tr><th>社团名</th><th>附属学校</th><th>社长</th><th style="width:70px">成员</th><th style="width:80px">状态</th><th style="width:90px">创建时间</th><th style="width:70px">操作</th></tr>
      </thead>
      <tbody>
        <?php foreach ($clubs as $c): ?>
        <tr>
          <td><a href="../pages/club.php?id=<?= $c['id'] ?>" target="_blank" style="font-weight:600"><?= h($c['name']) ?></a></td>
          <td style="font-size:13px"><?= h($c['school']) ?></td>
          <td style="font-size:13px"><?= h($c['president_name']) ?></td>
          <td style="font-size:13px"><?= $c['member_count'] ?></td>
          <td>
            <?php if ($c['status']==='active'): ?>
              <span class="spill spill-green"><span class="spill-dot"></span>正常</span>
            <?php else: ?>
              <span class="spill spill-gray">停用</span>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:var(--txt-3)"><?= date('Y-m-d', strtotime($c['created_at'])) ?></td>
          <td>
            <form action="../actions/club_action.php" method="post">
              <input type="hidden" name="action" value="admin_toggle">
              <input type="hidden" name="club_id" value="<?= $c['id'] ?>">
              <button type="submit" class="btn btn-outline btn-sm" style="font-size:11px"
                      onclick="return confirm('<?= $c['status']==='active'?'停用':'启用' ?>该社团？')">
                <?= $c['status']==='active'?'停用':'启用' ?>
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

<!-- ── 审核记录 ── -->
<?php elseif ($tab === 'history'): ?>
  <?php if (empty($history)): ?>
    <div class="empty-state"><div class="icon"><i data-lucide="clipboard-list" class="lucide"></i></div><p>暂无审核记录</p></div>
  <?php else: ?>
  <div class="card" style="overflow-x:auto">
    <table class="data-table">
      <thead>
        <tr><th>社团名</th><th>学校</th><th>申请人</th><th style="width:100px">结果</th><th>拒绝原因</th><th>审核人</th><th style="width:90px">时间</th></tr>
      </thead>
      <tbody>
        <?php foreach ($history as $h_item): ?>
        <tr>
          <td style="font-weight:600"><?= h($h_item['name']) ?></td>
          <td style="font-size:13px"><?= h($h_item['school']) ?></td>
          <td style="font-size:13px"><?= h($h_item['username']) ?></td>
          <td>
            <?php if ($h_item['status']==='approved'): ?>
              <span class="spill spill-green">✓ 批准</span>
            <?php else: ?>
              <span class="spill spill-red">✕ 拒绝</span>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:var(--txt-2)"><?= h($h_item['reject_reason'] ?: '—') ?></td>
          <td style="font-size:13px"><?= h($h_item['reviewer'] ?? '—') ?></td>
          <td style="font-size:12px;color:var(--txt-3)"><?= $h_item['reviewed_at'] ? date('m-d H:i', strtotime($h_item['reviewed_at'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
<?php endif; ?>

<!-- 拒绝创建弹窗 -->
<div class="admin-modal" id="reject-modal">
  <div class="modal-box" style="width:420px">
    <div class="modal-hd">
      <span class="modal-title">拒绝社团申请</span>
      <button class="modal-close" onclick="closeReject()">×</button>
    </div>
    <div class="modal-bd">
      <p style="font-size:14px;color:var(--txt-2);margin:0 0 14px">拒绝「<strong id="reject-name"></strong>」的创建申请：</p>
      <form action="../actions/club_action.php" method="post">
        <input type="hidden" name="action" value="admin_reject">
        <input type="hidden" name="app_id" id="reject-app-id">
        <div class="form-group">
          <label>拒绝原因（选填）</label>
          <textarea name="reject_reason" maxlength="200" rows="3"
                    style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:var(--r);resize:vertical;background:var(--bg);color:var(--txt);box-sizing:border-box;font-size:13px"
                    placeholder="请填写拒绝原因…"></textarea>
        </div>
        <div style="display:flex;gap:8px">
          <button type="submit" class="btn btn-danger">确认拒绝</button>
          <button type="button" class="btn btn-outline" onclick="closeReject()">取消</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- 拒绝改名弹窗 -->
<div class="admin-modal" id="reject-name-modal">
  <div class="modal-box" style="width:420px">
    <div class="modal-hd">
      <span class="modal-title">拒绝改名申请</span>
      <button class="modal-close" onclick="closeRejectName()">×</button>
    </div>
    <div class="modal-bd">
      <p style="font-size:14px;color:var(--txt-2);margin:0 0 14px">拒绝改名为「<strong id="reject-new-name"></strong>」：</p>
      <form action="../actions/club_action.php" method="post">
        <input type="hidden" name="action" value="admin_reject_name">
        <input type="hidden" name="name_change_id" id="reject-name-change-id">
        <div class="form-group">
          <label>拒绝原因（选填）</label>
          <textarea name="reject_reason" maxlength="200" rows="3"
                    style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:var(--r);resize:vertical;background:var(--bg);color:var(--txt);box-sizing:border-box;font-size:13px"
                    placeholder="请填写拒绝原因…"></textarea>
        </div>
        <div style="display:flex;gap:8px">
          <button type="submit" class="btn btn-danger">确认拒绝</button>
          <button type="button" class="btn btn-outline" onclick="closeRejectName()">取消</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openReject(id, name) {
  document.getElementById('reject-app-id').value = id;
  document.getElementById('reject-name').textContent = name;
  document.getElementById('reject-modal').classList.add('open');
}
function closeReject() { document.getElementById('reject-modal').classList.remove('open'); }
document.getElementById('reject-modal').addEventListener('click', function(e){ if(e.target===this) closeReject(); });

function openRejectName(id, newName) {
  document.getElementById('reject-name-change-id').value = id;
  document.getElementById('reject-new-name').textContent = newName;
  document.getElementById('reject-name-modal').classList.add('open');
}
function closeRejectName() { document.getElementById('reject-name-modal').classList.remove('open'); }
document.getElementById('reject-name-modal').addEventListener('click', function(e){ if(e.target===this) closeRejectName(); });
</script>

<?php include '../includes/footer.php'; ?>
