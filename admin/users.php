<?php
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../pages/login.php'); exit; }
if (!in_array($_SESSION['role'] ?? '', ['admin','owner'])) { header('Location: ../index.php'); exit; }

$self_role = $_SESSION['role'];
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act    = $_POST['act']    ?? '';
    $target = intval($_POST['uid'] ?? 0);
    if (!$target) { $err = '无效用户'; goto render; }

    $tr = $conn->query("SELECT role FROM users WHERE id=$target");
    $trow = $tr ? $tr->fetch_assoc() : null;
    if ($trow && $trow['role'] === 'owner' && $self_role !== 'owner') {
        $err = '无权操作 owner'; goto render;
    }

    if ($act === 'ban') {
        $days   = max(1, intval($_POST['days'] ?? 7));
        $reason = $conn->real_escape_string(trim($_POST['reason'] ?? '违规'));
        $until  = date('Y-m-d H:i:s', time() + $days * 86400);
        $conn->query("UPDATE users SET is_banned=1,ban_reason='$reason',ban_until='$until' WHERE id=$target");
        $msg = "用户 #$target 已封禁 $days 天";
    } elseif ($act === 'unban') {
        $conn->query("UPDATE users SET is_banned=0,ban_reason=NULL,ban_until=NULL WHERE id=$target");
        $msg = "用户 #$target 已解封";
    } elseif ($act === 'set_role' && $self_role === 'owner') {
        $role = $_POST['role'] ?? 'user';
        if (!in_array($role, ['user','admin','owner'])) { $err = '无效角色'; goto render; }
        $conn->query("UPDATE users SET role='$role' WHERE id=$target");
        $msg = "用户 #$target 角色已更新";
    } elseif ($act === 'delete' && $self_role === 'owner') {
        $conn->query("DELETE FROM users WHERE id=$target AND role!='owner'");
        $msg = "用户 #$target 已删除";
    }
}

render:
$search      = trim($_GET['search'] ?? '');
$filter_role = trim($_GET['urole']  ?? '');
$filter_ban  = trim($_GET['ban']    ?? '');
$page        = max(1, (int)($_GET['page'] ?? 1));
$per         = 20;

$where_parts = [];
if ($search)                  $where_parts[] = "(username LIKE '%".$conn->real_escape_string($search)."%' OR email LIKE '%".$conn->real_escape_string($search)."%')";
if ($filter_role)             $where_parts[] = "role='".$conn->real_escape_string($filter_role)."'";
if ($filter_ban === '1')      $where_parts[] = "is_banned=1";
elseif ($filter_ban === '0')  $where_parts[] = "is_banned=0";
$where = $where_parts ? 'WHERE '.implode(' AND ', $where_parts) : '';

$total       = (int)$conn->query("SELECT COUNT(*) as c FROM users $where")->fetch_assoc()['c'];
$total_pages = max(1, ceil($total / $per));
$offset      = ($page - 1) * $per;

$users = [];
$ur = $conn->query("SELECT * FROM users $where ORDER BY created_at DESC LIMIT $per OFFSET $offset");
if ($ur) while ($r = $ur->fetch_assoc()) $users[] = $r;

$page_title = '用户管理';
$in_admin   = true;
include '../includes/header.php';
?>

<div class="admin-page-hd">
  <div>
    <h2>👥 用户管理</h2>
    <div class="sub">共 <?= number_format($total) ?> 名用户</div>
  </div>
</div>

<?php if ($msg): ?><div class="alert-success"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert-danger"><?= h($err) ?></div><?php endif; ?>

<form method="get" class="admin-filter-bar">
  <input type="text" name="search" value="<?= h($search) ?>" placeholder="搜索用户名或邮箱…" style="flex:1;min-width:180px;max-width:300px">
  <select name="urole">
    <option value="">全部角色</option>
    <option value="user"  <?= $filter_role==='user' ?'selected':'' ?>>普通用户</option>
    <option value="admin" <?= $filter_role==='admin'?'selected':'' ?>>管理员</option>
    <option value="owner" <?= $filter_role==='owner'?'selected':'' ?>>Owner</option>
  </select>
  <select name="ban">
    <option value="">全部状态</option>
    <option value="0" <?= $filter_ban==='0'?'selected':'' ?>>正常</option>
    <option value="1" <?= $filter_ban==='1'?'selected':'' ?>>已封禁</option>
  </select>
  <button type="submit" class="btn btn-primary btn-sm">筛选</button>
  <?php if ($search || $filter_role || $filter_ban !== ''): ?>
  <a href="users.php" class="btn btn-outline btn-sm">重置</a>
  <?php endif; ?>
  <span class="spacer"></span>
  <span style="font-size:13px;color:var(--txt-3)">共 <?= $total ?> 人</span>
</form>

<div class="card" style="overflow-x:auto">
  <table class="data-table">
    <thead>
      <tr>
        <th style="width:48px">ID</th>
        <th>用户</th>
        <th>邮箱</th>
        <th>学校</th>
        <th style="width:80px">角色</th>
        <th style="width:70px">等级</th>
        <th style="width:90px">状态</th>
        <th style="width:90px">注册时间</th>
        <th style="width:65px">操作</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td style="color:var(--txt-3);font-size:12px"><?= $u['id'] ?></td>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <img src="<?= avatar_url($u['avatar'],'../') ?>"
                 style="width:34px;height:34px;border-radius:50%;object-fit:cover;flex-shrink:0"
                 onerror="this.src='../assets/default_avatar.svg'">
            <a href="../pages/profile.php?id=<?= $u['id'] ?>" target="_blank"
               style="font-weight:600;font-size:13px;color:var(--txt)"><?= h($u['username']) ?></a>
          </div>
        </td>
        <td style="font-size:12px;color:var(--txt-2)"><?= h($u['email']) ?></td>
        <td style="font-size:13px"><?= h($u['school'] ?: '—') ?></td>
        <td><?= role_badge($u['role']) ?></td>
        <td><?= level_badge($u['exp']) ?></td>
        <td>
          <?php if ($u['is_banned']): ?>
            <span class="spill spill-red"><span class="spill-dot"></span>封禁中</span>
          <?php else: ?>
            <span class="spill spill-green"><span class="spill-dot"></span>正常</span>
          <?php endif; ?>
        </td>
        <td style="font-size:12px;color:var(--txt-3)"><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
        <td>
          <?php if ($u['id'] != intval($_SESSION['user_id'])): ?>
          <button class="btn btn-outline btn-sm"
            onclick="openUserModal(<?= $u['id'] ?>,'<?= h(addslashes($u['username'])) ?>',<?= $u['is_banned']?1:0 ?>,'<?= h($u['role']) ?>')">
            管理
          </button>
          <?php else: ?>
          <span style="font-size:12px;color:var(--txt-3)">自己</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($users)): ?>
      <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--txt-3)">暂无匹配用户</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($total_pages > 1): ?>
<div class="pagination">
  <?php
  $qb = http_build_query(['search'=>$search,'urole'=>$filter_role,'ban'=>$filter_ban]);
  for ($i = 1; $i <= $total_pages; $i++):
  ?>
    <a href="?<?= $qb ?>&page=<?= $i ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<!-- 管理弹窗 -->
<div class="admin-modal" id="user-modal">
  <div class="modal-box">
    <div class="modal-hd">
      <span class="modal-title" id="modal-user-title">管理用户</span>
      <button class="modal-close" onclick="closeUserModal()">×</button>
    </div>
    <div class="modal-bd">
      <form method="post" id="modal-form">
        <input type="hidden" name="uid" id="modal-uid">

        <!-- 封禁 -->
        <div id="ban-section">
          <div class="form-group">
            <label>封禁天数</label>
            <input type="number" name="days" value="7" min="1" max="3650">
          </div>
          <div class="form-group">
            <label>封禁原因</label>
            <input type="text" name="reason" value="违规" maxlength="100">
          </div>
          <button type="submit" name="act" value="ban" class="btn btn-danger btn-sm">🚫 确认封禁</button>
        </div>

        <!-- 解封 -->
        <div id="unban-section" style="display:none">
          <p style="font-size:14px;color:var(--txt-2);margin:0 0 16px">确定解除该用户的封禁状态？</p>
          <button type="submit" name="act" value="unban" class="btn btn-primary btn-sm">✓ 确认解封</button>
        </div>

        <?php if ($self_role === 'owner'): ?>
        <div class="modal-section-label">Owner 专属操作</div>
        <div class="form-group">
          <label>设置角色</label>
          <select name="role" id="modal-role">
            <option value="user">user — 普通用户</option>
            <option value="admin">admin — 管理员</option>
            <option value="owner">owner — 超管</option>
          </select>
        </div>
        <div style="display:flex;gap:8px">
          <button type="submit" name="act" value="set_role" class="btn btn-outline btn-sm">更新角色</button>
          <button type="submit" name="act" value="delete" class="btn btn-danger btn-sm"
                  onclick="return confirm('永久删除该用户？不可恢复！')">删除用户</button>
        </div>
        <?php endif; ?>
      </form>
    </div>
  </div>
</div>

<script>
function openUserModal(uid, name, isBanned, role) {
  document.getElementById('modal-uid').value = uid;
  document.getElementById('modal-user-title').textContent = '管理用户：' + name + '（#' + uid + '）';
  document.getElementById('ban-section').style.display   = isBanned ? 'none' : 'block';
  document.getElementById('unban-section').style.display = isBanned ? 'block' : 'none';
  var rs = document.getElementById('modal-role');
  if (rs) rs.value = role;
  document.getElementById('user-modal').classList.add('open');
}
function closeUserModal() { document.getElementById('user-modal').classList.remove('open'); }
document.getElementById('user-modal').addEventListener('click', function(e) { if (e.target === this) closeUserModal(); });
</script>

<?php include '../includes/footer.php'; ?>
