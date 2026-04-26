<?php
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../pages/login.php'); exit; }
if (!in_array($_SESSION['role'] ?? '', ['admin','owner'])) { header('Location: ../index.php'); exit; }

$page  = max(1, intval($_GET['page'] ?? 1));
$limit = 30;
$offset = ($page - 1) * $limit;

$filter_admin = intval($_GET['admin_id'] ?? 0);
$filter_type  = trim($_GET['type'] ?? '');

$where = '1=1';
if ($filter_admin > 0) $where .= " AND l.admin_id = $filter_admin";
if ($filter_type !== '') $where .= " AND l.action LIKE '%" . $conn->real_escape_string($filter_type) . "%'";

$total_res = $conn->query("SELECT COUNT(*) as c FROM admin_logs l WHERE $where");
$total     = $total_res ? (int)$total_res->fetch_assoc()['c'] : 0;
$pages     = max(1, ceil($total / $limit));

$logs = [];
$lr = $conn->query("SELECT l.*, u.username AS admin_name
    FROM admin_logs l
    JOIN users u ON u.id = l.admin_id
    WHERE $where
    ORDER BY l.created_at DESC
    LIMIT $limit OFFSET $offset");
if ($lr) while ($r = $lr->fetch_assoc()) $logs[] = $r;

// 管理员列表（筛选用）
$admins = [];
$ar = $conn->query("SELECT id, username FROM users WHERE role IN ('admin','owner') ORDER BY username");
if ($ar) while ($r = $ar->fetch_assoc()) $admins[] = $r;

$page_title = '操作记录';
$in_admin   = true;
include '../includes/header.php';
?>

<div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
  <a href="index.php" style="color:var(--txt-2);font-size:13px">← 管理后台</a>
  <h2 style="margin:0">📋 操作记录</h2>
  <span style="font-size:13px;color:var(--txt-3)">共 <?= $total ?> 条</span>
</div>

<!-- 筛选 -->
<form method="get" style="display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap">
  <select name="admin_id" style="padding:6px 10px;border:1.5px solid var(--border);border-radius:8px;background:var(--bg);color:var(--txt);font-size:13px">
    <option value="0">全部管理员</option>
    <?php foreach ($admins as $a): ?>
      <option value="<?= $a['id'] ?>" <?= $filter_admin == $a['id'] ? 'selected' : '' ?>><?= h($a['username']) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="text" name="type" value="<?= h($filter_type) ?>" placeholder="操作类型关键词…"
         style="padding:6px 12px;border:1.5px solid var(--border);border-radius:8px;background:var(--bg);color:var(--txt);font-size:13px;width:180px">
  <button type="submit" class="btn btn-outline btn-sm">筛选</button>
  <?php if ($filter_admin || $filter_type): ?>
    <a href="logs.php" class="btn btn-outline btn-sm">清除</a>
  <?php endif; ?>
</form>

<?php if (empty($logs)): ?>
<div class="empty-state"><div class="icon">📋</div><p>暂无操作记录</p></div>
<?php else: ?>
<div class="card" style="padding:0">
  <table class="data-table">
    <thead>
      <tr>
        <th style="width:130px">时间</th>
        <th style="width:100px">管理员</th>
        <th>操作</th>
        <th style="width:80px">目标类型</th>
        <th style="width:60px">目标ID</th>
        <th>详情</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($logs as $log): ?>
      <tr>
        <td style="font-size:12px;color:var(--txt-3);white-space:nowrap">
          <?= date('m-d H:i:s', strtotime($log['created_at'])) ?>
        </td>
        <td>
          <a href="../pages/profile.php?id=<?= $log['admin_id'] ?>" style="font-size:13px">
            <?= h($log['admin_name']) ?>
          </a>
        </td>
        <td style="font-size:13px;font-weight:600"><?= h($log['action']) ?></td>
        <td style="font-size:12px;color:var(--txt-2)"><?= h($log['target_type'] ?: '—') ?></td>
        <td style="font-size:12px;color:var(--txt-3)"><?= $log['target_id'] ?: '—' ?></td>
        <td style="font-size:12px;color:var(--txt-2);max-width:300px;word-break:break-all">
          <?= h($log['detail'] ?: '—') ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- 分页 -->
<?php if ($pages > 1): ?>
<div style="display:flex;justify-content:center;gap:6px;margin-top:20px;flex-wrap:wrap">
  <?php for ($i = 1; $i <= $pages; $i++): ?>
    <?php
    $q = http_build_query(['page' => $i, 'admin_id' => $filter_admin, 'type' => $filter_type]);
    ?>
    <a href="?<?= $q ?>" class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-outline' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
