<?php
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../pages/login.php'); exit; }
if (!in_array($_SESSION['role'] ?? '', ['admin','owner'])) { header('Location: ../index.php'); exit; }

$self_role = $_SESSION['role'];
$msg = $err = '';

// 操作处理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act    = $_POST['act']     ?? '';
    $target = intval($_POST['uid'] ?? 0);

    if (!$target) { $err = '无效用户'; goto render; }

    // owner 不可被 admin 操作
    $tr = $conn->query("SELECT role FROM users WHERE id=$target");
    $trow = $tr ? $tr->fetch_assoc() : null;
    if ($trow && $trow['role'] === 'owner' && $self_role !== 'owner') {
        $err = '无权操作 owner'; goto render;
    }

    if ($act === 'ban') {
        $days   = max(1, intval($_POST['days'] ?? 7));
        $reason = $conn->real_escape_string(trim($_POST['reason'] ?? '违规'));
        $until  = date('Y-m-d H:i:s', time() + $days*86400);
        $conn->query("UPDATE users SET is_banned=1,ban_reason='$reason',ban_until='$until' WHERE id=$target");
        $msg = "用户 #$target 已封禁 $days 天";
    } elseif ($act === 'unban') {
        $conn->query("UPDATE users SET is_banned=0,ban_reason=NULL,ban_until=NULL WHERE id=$target");
        $msg = "用户 #$target 已解封";
    } elseif ($act === 'set_role' && $self_role === 'owner') {
        $role = $_POST['role'] ?? 'user';
        if (!in_array($role, ['user','admin','owner'])) { $err='无效角色'; goto render; }
        $conn->query("UPDATE users SET role='$role' WHERE id=$target");
        $msg = "用户 #$target 角色已更新";
    } elseif ($act === 'delete' && $self_role === 'owner') {
        $conn->query("DELETE FROM users WHERE id=$target AND role!='owner'");
        $msg = "用户 #$target 已删除";
    }
}

render:
$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 20;

$where = $search ? "WHERE username LIKE '%".$conn->real_escape_string($search)."%' OR email LIKE '%".$conn->real_escape_string($search)."%'" : '';
$total = (int)$conn->query("SELECT COUNT(*) as c FROM users $where")->fetch_assoc()['c'];
$total_pages = max(1, ceil($total/$per));
$offset = ($page-1)*$per;

$users = [];
$ur = $conn->query("SELECT * FROM users $where ORDER BY created_at DESC LIMIT $per OFFSET $offset");
if ($ur) while ($r = $ur->fetch_assoc()) $users[] = $r;

$page_title = '用户管理';
$in_admin = true;
include '../includes/header.php';
?>

<div style="display:flex;align-items:center;gap:8px;margin-bottom:20px">
  <a href="index.php" style="color:var(--txt-2);font-size:13px">← 后台首页</a>
  <h2 style="margin:0">👥 用户管理</h2>
</div>

<?php if ($msg): ?><div class="alert alert-success mb-16"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger  mb-16"><?= h($err) ?></div><?php endif; ?>

<!-- 搜索 -->
<form method="get" class="flex-center gap-8 mb-16">
  <input type="text" name="search" value="<?= h($search) ?>" placeholder="搜索用户名或邮箱..."
         style="flex:1;max-width:400px;padding:8px 14px;border:1px solid var(--border);border-radius:var(--r);font-size:14px;outline:none">
  <button type="submit" class="btn btn-primary btn-sm">搜索</button>
  <?php if ($search): ?><a href="users.php" class="btn btn-outline btn-sm">清除</a><?php endif; ?>
  <span style="margin-left:auto;font-size:13px;color:var(--txt-3)">共 <?= $total ?> 人</span>
</form>

<div class="card">
  <div class="card-body" style="padding:0;overflow-x:auto">
    <table class="data-table">
      <thead>
        <tr>
          <th>ID</th><th>用户名</th><th>邮箱</th><th>学校</th>
          <th>角色</th><th>等级</th><th>状态</th><th>注册时间</th><th>操作</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td><?= $u['id'] ?></td>
          <td><a href="../pages/profile.php?id=<?= $u['id'] ?>"><?= h($u['username']) ?></a></td>
          <td style="font-size:12px;color:var(--txt-2)"><?= h($u['email']) ?></td>
          <td><?= h($u['school']?:'—') ?></td>
          <td><?= role_badge($u['role']) ?></td>
          <td><?= level_badge($u['exp']) ?></td>
          <td><?= $u['is_banned'] ? '<span style="color:var(--danger)">已封禁</span>' : '<span style="color:#16a34a">正常</span>' ?></td>
          <td style="font-size:12px;color:var(--txt-3)"><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
          <td>
            <?php if ($u['id'] != intval($_SESSION['user_id'])): ?>
            <button class="btn btn-outline btn-sm" onclick="showUserAction(<?= $u['id'] ?>,'<?= h(addslashes($u['username'])) ?>',<?= $u['is_banned']?1:0 ?>,'<?= h($u['role']) ?>')">操作</button>
            <?php else: ?><span style="font-size:12px;color:var(--txt-3)">自己</span><?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($total_pages > 1): ?>
<div class="pagination">
  <?php for ($i=1; $i<=$total_pages; $i++): ?>
    <a href="?search=<?= urlencode($search) ?>&page=<?= $i ?>"
       class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<!-- 操作弹窗 -->
<div id="user-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center">
  <div class="card" style="width:380px;max-width:90vw">
    <div class="card-header" id="modal-title">操作用户</div>
    <div class="card-body">
      <form method="post" id="modal-form">
        <input type="hidden" name="uid" id="modal-uid">

        <div id="ban-section">
          <div class="form-group">
            <label>封禁天数</label>
            <input type="number" name="days" value="7" min="1" max="3650">
          </div>
          <div class="form-group">
            <label>封禁原因</label>
            <input type="text" name="reason" value="违规" maxlength="100">
          </div>
          <button type="submit" name="act" value="ban" class="btn btn-danger btn-sm">确认封禁</button>
        </div>

        <div id="unban-section" style="display:none">
          <p style="color:var(--txt-2);font-size:14px">确定解除封禁？</p>
          <button type="submit" name="act" value="unban" class="btn btn-primary btn-sm">确认解封</button>
        </div>

        <?php if ($self_role === 'owner'): ?>
        <hr style="margin:16px 0;border-color:var(--border)">
        <div class="form-group">
          <label>设置角色（owner 可用）</label>
          <select name="role" id="modal-role">
            <option value="user">user</option>
            <option value="admin">admin</option>
            <option value="owner">owner</option>
          </select>
        </div>
        <div style="display:flex;gap:8px">
          <button type="submit" name="act" value="set_role" class="btn btn-outline btn-sm">更新角色</button>
          <button type="submit" name="act" value="delete" class="btn btn-danger btn-sm"
                  onclick="return confirm('永久删除用户？不可恢复！')">删除用户</button>
        </div>
        <?php endif; ?>

        <div style="margin-top:12px">
          <button type="button" class="btn btn-outline btn-sm" onclick="closeModal()">取消</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function showUserAction(uid, name, isBanned, role) {
  document.getElementById('modal-uid').value   = uid;
  document.getElementById('modal-title').textContent = '操作用户：' + name;
  document.getElementById('ban-section').style.display   = isBanned ? 'none' : 'block';
  document.getElementById('unban-section').style.display = isBanned ? 'block' : 'none';
  const rs = document.getElementById('modal-role');
  if (rs) rs.value = role;
  const m = document.getElementById('user-modal');
  m.style.display = 'flex';
}
function closeModal() {
  document.getElementById('user-modal').style.display = 'none';
}
document.getElementById('user-modal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
</script>

<?php include '../includes/footer.php'; ?>
