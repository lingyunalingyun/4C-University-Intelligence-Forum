<?php
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
    FROM club_applications a JOIN users u ON u.id = a.user_id
    WHERE a.status = 'pending' ORDER BY a.created_at ASC");
if ($pr) while ($r = $pr->fetch_assoc()) $pending[] = $r;

// 待审核改名
$pending_names = [];
$pnr = $conn->query("SELECT nc.*, c.name AS current_name, u.username
    FROM club_name_changes nc
    JOIN clubs c ON c.id = nc.club_id
    JOIN users u ON u.id = nc.user_id
    WHERE nc.status = 'pending'
    ORDER BY nc.created_at ASC");
if ($pnr) while ($r = $pnr->fetch_assoc()) $pending_names[] = $r;

// 全部社团
$clubs = [];
if ($tab === 'clubs') {
    $cr = $conn->query("SELECT c.*, u.username AS president_name
        FROM clubs c JOIN users u ON u.id = c.president_id
        ORDER BY c.created_at DESC");
    if ($cr) while ($r = $cr->fetch_assoc()) $clubs[] = $r;
}

// 历史申请
$history = [];
if ($tab === 'history') {
    $hr = $conn->query("SELECT a.*, u.username, rv.username AS reviewer
        FROM club_applications a
        JOIN users u ON u.id = a.user_id
        LEFT JOIN users rv ON rv.id = a.reviewed_by
        WHERE a.status != 'pending'
        ORDER BY a.reviewed_at DESC LIMIT 50");
    if ($hr) while ($r = $hr->fetch_assoc()) $history[] = $r;
}

$page_title  = '社团管理';
$in_admin    = true;
include '../includes/header.php';
?>

<style>
.app-card { background:var(--card-bg);border:1.5px solid var(--border);border-radius:10px;padding:18px;margin-bottom:14px; }
.app-card-header { display:flex;align-items:center;gap:10px;margin-bottom:10px; }
.app-field { margin-bottom:6px;font-size:13px;color:var(--txt-2) }
.app-field strong { color:var(--txt) }
</style>

<div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
  <a href="index.php" style="color:var(--txt-2);font-size:13px">← 管理后台</a>
  <h2 style="margin:0">🏛️ 社团管理</h2>
</div>

<?php if ($msg): ?><div class="alert alert-success mb-16"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger  mb-16"><?= h($err) ?></div><?php endif; ?>

<!-- 标签切换 -->
<div style="display:flex;gap:6px;margin-bottom:20px">
  <a href="?tab=pending"  class="sub-section-tag <?= $tab==='pending' ?'active':'' ?>">
    待审核 <?php if (count($pending)): ?><span style="background:#ef4444;color:#fff;font-size:10px;padding:1px 6px;border-radius:8px;margin-left:4px"><?= count($pending) ?></span><?php endif; ?>
  </a>
  <a href="?tab=names"   class="sub-section-tag <?= $tab==='names'  ?'active':'' ?>">
    改名申请 <?php if (count($pending_names)): ?><span style="background:#f59e0b;color:#fff;font-size:10px;padding:1px 6px;border-radius:8px;margin-left:4px"><?= count($pending_names) ?></span><?php endif; ?>
  </a>
  <a href="?tab=clubs"   class="sub-section-tag <?= $tab==='clubs'  ?'active':'' ?>">全部社团</a>
  <a href="?tab=history" class="sub-section-tag <?= $tab==='history'?'active':'' ?>">审核记录</a>
</div>

<!-- 待审核申请 -->
<?php if ($tab === 'pending'): ?>
  <?php if (empty($pending)): ?>
    <div class="empty-state"><div class="icon">✅</div><p>暂无待审核申请</p></div>
  <?php else: ?>
    <?php foreach ($pending as $a): ?>
    <div class="app-card">
      <div class="app-card-header">
        <div style="flex:1">
          <div style="font-size:16px;font-weight:700"><?= h($a['name']) ?></div>
          <div style="font-size:12px;color:var(--txt-2)">
            申请人：<?= h($a['username']) ?>（<?= h($a['user_school']) ?>）&nbsp;·&nbsp;
            <?= date('Y-m-d H:i', strtotime($a['created_at'])) ?>
          </div>
        </div>
      </div>
      <div class="app-field"><strong>附属学校：</strong><?= h($a['school']) ?></div>
      <div class="app-field"><strong>社团简介：</strong><?= h($a['description']) ?></div>
      <div class="app-field"><strong>创建目的：</strong><?= h($a['purpose']) ?></div>
      <div style="display:flex;gap:8px;margin-top:12px">
        <form action="../actions/club_action.php" method="post">
          <input type="hidden" name="action" value="admin_approve">
          <input type="hidden" name="app_id" value="<?= $a['id'] ?>">
          <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('批准「<?= h($a['name']) ?>」社团申请？')">✓ 批准</button>
        </form>
        <button class="btn btn-sm" style="background:#ef4444;color:#fff;border:none"
                onclick="openReject(<?= $a['id'] ?>,'<?= h($a['name']) ?>')">✕ 拒绝</button>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

<!-- 改名申请 -->
<?php elseif ($tab === 'names'): ?>
  <?php if (empty($pending_names)): ?>
    <div class="empty-state"><div class="icon">✅</div><p>暂无待审核改名申请</p></div>
  <?php else: ?>
    <?php foreach ($pending_names as $nc): ?>
    <div class="app-card">
      <div class="app-card-header">
        <div style="flex:1">
          <div style="font-size:15px;font-weight:700">
            「<?= h($nc['current_name']) ?>」→「<?= h($nc['new_name']) ?>」
          </div>
          <div style="font-size:12px;color:var(--txt-2)">
            申请人：<?= h($nc['username']) ?> &nbsp;·&nbsp;
            <?= date('Y-m-d H:i', strtotime($nc['created_at'])) ?>
          </div>
        </div>
      </div>
      <div class="app-field"><strong>社团ID：</strong><?= $nc['club_id'] ?></div>
      <div style="display:flex;gap:8px;margin-top:12px">
        <form action="../actions/club_action.php" method="post">
          <input type="hidden" name="action"        value="admin_approve_name">
          <input type="hidden" name="name_change_id" value="<?= $nc['id'] ?>">
          <button type="submit" class="btn btn-primary btn-sm"
                  onclick="return confirm('批准将「<?= h($nc['current_name']) ?>」改名为「<?= h($nc['new_name']) ?>」？')">✓ 批准</button>
        </form>
        <button class="btn btn-sm" style="background:#ef4444;color:#fff;border:none"
                onclick="openRejectName(<?= $nc['id'] ?>,'<?= h($nc['new_name']) ?>')">✕ 拒绝</button>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

<!-- 全部社团 -->
<?php elseif ($tab === 'clubs'): ?>
  <?php if (empty($clubs)): ?>
    <div class="empty-state"><div class="icon">🏛️</div><p>暂无社团</p></div>
  <?php else: ?>
  <div class="card" style="padding:0">
    <table class="data-table">
      <thead><tr><th>社团名</th><th>学校</th><th>社长</th><th>成员</th><th>状态</th><th>创建时间</th><th>操作</th></tr></thead>
      <tbody>
        <?php foreach ($clubs as $c): ?>
        <tr>
          <td><a href="../pages/club.php?id=<?= $c['id'] ?>"><?= h($c['name']) ?></a></td>
          <td><?= h($c['school']) ?></td>
          <td><?= h($c['president_name']) ?></td>
          <td><?= $c['member_count'] ?></td>
          <td><span style="font-size:12px;color:<?= $c['status']==='active'?'#10b981':'#6b7280' ?>">
            <?= $c['status']==='active'?'正常':'停用' ?>
          </span></td>
          <td style="font-size:12px;color:var(--txt-3)"><?= date('Y-m-d', strtotime($c['created_at'])) ?></td>
          <td>
            <form action="../actions/club_action.php" method="post" style="display:inline">
              <input type="hidden" name="action"  value="admin_toggle">
              <input type="hidden" name="club_id" value="<?= $c['id'] ?>">
              <button type="submit" class="btn btn-outline btn-sm"
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

<!-- 审核记录 -->
<?php elseif ($tab === 'history'): ?>
  <?php if (empty($history)): ?>
    <div class="empty-state"><div class="icon">📋</div><p>暂无记录</p></div>
  <?php else: ?>
  <div class="card" style="padding:0">
    <table class="data-table">
      <thead><tr><th>社团名</th><th>学校</th><th>申请人</th><th>状态</th><th>审核人</th><th>审核时间</th></tr></thead>
      <tbody>
        <?php foreach ($history as $h_item): ?>
        <tr>
          <td><?= h($h_item['name']) ?></td>
          <td><?= h($h_item['school']) ?></td>
          <td><?= h($h_item['username']) ?></td>
          <td><span style="font-size:12px;color:<?= $h_item['status']==='approved'?'#10b981':'#ef4444' ?>">
            <?= $h_item['status']==='approved'?'✓ 已批准':'✕ 已拒绝' ?>
          </span>
          <?php if ($h_item['status']==='rejected' && $h_item['reject_reason']): ?>
            <br><span style="font-size:11px;color:var(--txt-3)"><?= h($h_item['reject_reason']) ?></span>
          <?php endif; ?>
          </td>
          <td><?= h($h_item['reviewer'] ?? '—') ?></td>
          <td style="font-size:12px;color:var(--txt-3)"><?= $h_item['reviewed_at'] ? date('Y-m-d', strtotime($h_item['reviewed_at'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
<?php endif; ?>

<!-- 拒绝弹窗 -->
<div id="reject-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
  <div class="card" style="width:400px;max-width:90vw;padding:0">
    <div class="card-header">拒绝申请</div>
    <div class="card-body">
      <p style="margin:0 0 12px;font-size:14px">拒绝社团「<strong id="reject-name"></strong>」的创建申请：</p>
      <form action="../actions/club_action.php" method="post">
        <input type="hidden" name="action" value="admin_reject">
        <input type="hidden" name="app_id" id="reject-app-id" value="">
        <textarea name="reject_reason" maxlength="200" rows="3"
                  style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:8px;resize:none;background:var(--bg);color:var(--txt);box-sizing:border-box"
                  placeholder="拒绝原因（选填）…"></textarea>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
          <button type="button" class="btn btn-outline" onclick="closeReject()">取消</button>
          <button type="submit" class="btn btn-sm" style="background:#ef4444;color:#fff;border:none">确认拒绝</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- 拒绝改名弹窗 -->
<div id="reject-name-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
  <div class="card" style="width:400px;max-width:90vw;padding:0">
    <div class="card-header">拒绝改名申请</div>
    <div class="card-body">
      <p style="margin:0 0 12px;font-size:14px">拒绝将社团改名为「<strong id="reject-new-name"></strong>」：</p>
      <form action="../actions/club_action.php" method="post">
        <input type="hidden" name="action"          value="admin_reject_name">
        <input type="hidden" name="name_change_id"  id="reject-name-change-id" value="">
        <textarea name="reject_reason" maxlength="200" rows="3"
                  style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:8px;resize:none;background:var(--bg);color:var(--txt);box-sizing:border-box"
                  placeholder="拒绝原因（选填）…"></textarea>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
          <button type="button" class="btn btn-outline" onclick="closeRejectName()">取消</button>
          <button type="submit" class="btn btn-sm" style="background:#ef4444;color:#fff;border:none">确认拒绝</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openReject(id, name) {
    document.getElementById('reject-app-id').value = id;
    document.getElementById('reject-name').textContent = name;
    var m = document.getElementById('reject-modal');
    m.style.display = 'flex';
}
function closeReject() { document.getElementById('reject-modal').style.display = 'none'; }
document.getElementById('reject-modal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
function openRejectName(id, newName) {
    document.getElementById('reject-name-change-id').value = id;
    document.getElementById('reject-new-name').textContent = newName;
    document.getElementById('reject-name-modal').style.display = 'flex';
}
function closeRejectName() { document.getElementById('reject-name-modal').style.display = 'none'; }
document.getElementById('reject-name-modal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

<?php include '../includes/footer.php'; ?>
