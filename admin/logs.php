<?php
/*
 * admin/logs.php — 管理员操作日志后台
 * 功能：分页展示所有管理员操作记录（时间/操作人/动作/目标/详情），
 *       支持按操作人筛选。
 * 读库：admin_logs / users
 * 权限：需 admin/owner 登录
 */
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../pages/login.php'); exit; }
if (!in_array($_SESSION['role'] ?? '', ['admin','owner'])) { header('Location: ../index.php'); exit; }

$page         = max(1, intval($_GET['page'] ?? 1));
$limit        = 30;
$offset       = ($page - 1) * $limit;
$filter_admin = intval($_GET['admin_id'] ?? 0);
$filter_type  = trim($_GET['type'] ?? '');
$filter_date  = trim($_GET['date'] ?? '');

$where = '1=1';
if ($filter_admin > 0) $where .= " AND l.admin_id=$filter_admin";
if ($filter_type !== '') $where .= " AND l.action LIKE '%".$conn->real_escape_string($filter_type)."%'";
if ($filter_date !== '') $where .= " AND DATE(l.created_at)='".$conn->real_escape_string($filter_date)."'";

$total_res = $conn->query("SELECT COUNT(*) as c FROM admin_logs l WHERE $where");
$total     = $total_res ? (int)$total_res->fetch_assoc()['c'] : 0;
$pages     = max(1, ceil($total / $limit));

$logs = [];
$lr = $conn->query("SELECT l.*, u.username AS admin_name
    FROM admin_logs l JOIN users u ON u.id=l.admin_id
    WHERE $where ORDER BY l.created_at DESC LIMIT $limit OFFSET $offset");
if ($lr) while ($r = $lr->fetch_assoc()) $logs[] = $r;

$admins = [];
$ar = $conn->query("SELECT id,username FROM users WHERE role IN ('admin','owner') ORDER BY username");
if ($ar) while ($r = $ar->fetch_assoc()) $admins[] = $r;

$page_title = '操作日志';
$in_admin   = true;
include '../includes/header.php';
?>

<div class="admin-page-hd">
  <div>
    <h2>📋 操作日志</h2>
    <div class="sub">共 <?= number_format($total) ?> 条记录</div>
  </div>
</div>

<form method="get" class="admin-filter-bar">
  <select name="admin_id">
    <option value="0">全部管理员</option>
    <?php foreach ($admins as $a): ?>
      <option value="<?= $a['id'] ?>" <?= $filter_admin==$a['id']?'selected':'' ?>><?= h($a['username']) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="text" name="type" value="<?= h($filter_type) ?>" placeholder="操作类型关键词…" style="width:160px">
  <input type="date" name="date" value="<?= h($filter_date) ?>" style="padding:7px 10px;border:1px solid var(--border);border-radius:var(--r);font-size:13px;background:var(--bg-2);color:var(--txt);outline:none">
  <button type="submit" class="btn btn-primary btn-sm">筛选</button>
  <?php if ($filter_admin || $filter_type || $filter_date): ?>
    <a href="logs.php" class="btn btn-outline btn-sm">重置</a>
  <?php endif; ?>
  <span class="spacer"></span>
  <span style="font-size:13px;color:var(--txt-3)"><?= number_format($total) ?> 条</span>
</form>

<?php if (empty($logs)): ?>
  <div class="empty-state"><div class="icon">📋</div><p>暂无操作记录</p></div>
<?php else: ?>

<div class="card" style="overflow-x:auto">
  <table class="data-table">
    <thead>
      <tr>
        <th style="width:120px">时间</th>
        <th style="width:90px">管理员</th>
        <th style="width:140px">操作</th>
        <th style="width:80px">目标类型</th>
        <th style="width:60px">目标ID</th>
        <th>详情</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($logs as $log): ?>
      <tr>
        <td style="font-size:12px;color:var(--txt-3);white-space:nowrap"><?= date('m-d H:i:s', strtotime($log['created_at'])) ?></td>
        <td>
          <a href="../pages/profile.php?id=<?= $log['admin_id'] ?>" target="_blank" style="font-size:13px;font-weight:500">
            <?= h($log['admin_name']) ?>
          </a>
        </td>
        <td>
          <?php
          $act_colors = [
            'ban'=>'#dc2626','unban'=>'#16a34a','delete'=>'#dc2626',
            'pin'=>'#f59e0b','hide'=>'#6b7280','publish'=>'#16a34a',
            'set_role'=>'#8b5cf6','update_setting'=>'#2563eb',
          ];
          $act = $log['action'];
          $color = '#6b7280';
          foreach ($act_colors as $k => $c) { if (strpos($act, $k) !== false) { $color = $c; break; } }
          ?>
          <span style="font-size:12px;font-weight:700;color:<?= $color ?>"><?= h($act) ?></span>
        </td>
        <td style="font-size:12px;color:var(--txt-2)"><?= h($log['target_type'] ?: '—') ?></td>
        <td style="font-size:12px;color:var(--txt-3)"><?= $log['target_id'] ?: '—' ?></td>
        <td style="font-size:12px;color:var(--txt-2);max-width:300px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis">
          <?= h($log['detail'] ?: '—') ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($pages > 1): ?>
<div class="pagination">
  <?php
  $qb = http_build_query(['admin_id'=>$filter_admin,'type'=>$filter_type,'date'=>$filter_date]);
  for ($i = 1; $i <= $pages; $i++):
  ?>
    <a href="?<?= $qb ?>&page=<?= $i ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
