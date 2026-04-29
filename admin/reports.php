<?php
/*
 * admin/reports.php — 举报管理
 * 功能：查看/筛选举报列表（按状态/类型），处理举报（标记已处理/驳回），
 *       一键删除被举报帖子、封禁被举报用户（含时长选择）。
 * 读库：reports / posts / users / comments
 * 写库：reports / posts / users / admin_logs
 * 权限：admin / owner
 */
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!in_array($_SESSION['role'] ?? '', ['admin','owner'])) {
    header('Location: ../pages/login.php'); exit;
}

$admin_id = intval($_SESSION['user_id']);
$msg = $err = '';

// ── POST 处理 ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action']    ?? '';
    $report_id = intval($_POST['report_id'] ?? 0);

    if ($action === 'handle' && $report_id) {
        $conn->query("UPDATE reports SET status='handled',handler_id=$admin_id,handled_at=NOW() WHERE id=$report_id");
        log_admin_action($conn, $admin_id, 'report_handled', 'report', $report_id, '标记已处理');
        $msg = '已标记为已处理';
    } elseif ($action === 'dismiss' && $report_id) {
        $conn->query("UPDATE reports SET status='dismissed',handler_id=$admin_id,handled_at=NOW() WHERE id=$report_id");
        log_admin_action($conn, $admin_id, 'report_dismissed', 'report', $report_id, '已驳回举报');
        $msg = '举报已驳回';
    } elseif ($action === 'delete_post' && $report_id) {
        $rr = $conn->query("SELECT target_id FROM reports WHERE id=$report_id AND type='post'");
        if ($rr && $row = $rr->fetch_assoc()) {
            $post_id = intval($row['target_id']);
            $conn->query("UPDATE posts SET status='deleted' WHERE id=$post_id");
            $conn->query("UPDATE reports SET status='handled',handler_id=$admin_id,handled_at=NOW() WHERE id=$report_id");
            log_admin_action($conn, $admin_id, 'report_delete_post', 'post', $post_id, "因举报#$report_id 删除帖子");
            $msg = '帖子已删除，举报已处理';
        }
    } elseif ($action === 'ban_user' && $report_id) {
        $rr = $conn->query("SELECT target_id FROM reports WHERE id=$report_id AND type='user'");
        if ($rr && $row = $rr->fetch_assoc()) {
            $target_uid = intval($row['target_id']);
            $days   = intval($_POST['ban_days'] ?? 0);
            $reason = trim($_POST['ban_reason'] ?? '违反社区规范');
            if ($days > 0) {
                $until = date('Y-m-d H:i:s', strtotime("+$days days"));
                $stmt  = $conn->prepare("UPDATE users SET is_banned=1,ban_reason=?,ban_until=?,banned_by=? WHERE id=?");
                $stmt->bind_param('ssii', $reason, $until, $admin_id, $target_uid);
            } else {
                $stmt = $conn->prepare("UPDATE users SET is_banned=1,ban_reason=?,ban_until=NULL,banned_by=? WHERE id=?");
                $stmt->bind_param('sii', $reason, $admin_id, $target_uid);
            }
            $stmt->execute(); $stmt->close();
            $conn->query("UPDATE reports SET status='handled',handler_id=$admin_id,handled_at=NOW() WHERE id=$report_id");
            log_admin_action($conn, $admin_id, 'report_ban_user', 'user', $target_uid, "因举报#$report_id 封禁用户，$reason");
            $msg = '用户已封禁，举报已处理';
        }
    }
}

// ── 筛选参数 ──────────────────────────────────────────
$filter_status = $_GET['status'] ?? 'pending';
$filter_type   = $_GET['type']   ?? '';
$page          = max(1, intval($_GET['p'] ?? 1));
$per_page      = 20;
$offset        = ($page - 1) * $per_page;

$where = [];
if (in_array($filter_status, ['pending','handled','dismissed'])) $where[] = "r.status='$filter_status'";
if (in_array($filter_type, ['post','user','comment'])) $where[] = "r.type='$filter_type'";
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total_r = $conn->query("SELECT COUNT(*) as c FROM reports r $where_sql");
$total   = $total_r ? (int)$total_r->fetch_assoc()['c'] : 0;
$pages   = max(1, ceil($total / $per_page));

$reports = [];
$res = $conn->query("SELECT r.*,
    u.username as reporter_name,
    h.username as handler_name
    FROM reports r
    LEFT JOIN users u ON u.id=r.reporter_id
    LEFT JOIN users h ON h.id=r.handler_id
    $where_sql
    ORDER BY r.created_at DESC
    LIMIT $per_page OFFSET $offset");
if ($res) while ($row = $res->fetch_assoc()) $reports[] = $row;

// 待处理数（侧边栏徽章）
$pending_count = (int)$conn->query("SELECT COUNT(*) as c FROM reports WHERE status='pending'")->fetch_assoc()['c'];

$page_title = '举报管理';
include '../includes/header.php';
?>

<?php if ($msg): ?><div class="alert alert-success mb-16"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger  mb-16"><?= h($err) ?></div><?php endif; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px">
  <h2 style="margin:0;font-size:18px">🚩 举报管理
    <?php if ($pending_count): ?><span style="background:var(--danger);color:#fff;font-size:12px;padding:2px 8px;border-radius:12px;margin-left:8px;font-weight:600"><?= $pending_count ?></span><?php endif; ?>
  </h2>

  <!-- 筛选栏 -->
  <form method="get" style="display:flex;gap:8px;flex-wrap:wrap">
    <select name="status" onchange="this.form.submit()" style="padding:6px 10px;border:1px solid var(--border);border-radius:var(--r);font-size:13px;background:var(--bg-2);color:var(--txt)">
      <option value=""     <?= $filter_status==='' ?'selected':'' ?>>全部状态</option>
      <option value="pending"   <?= $filter_status==='pending'   ?'selected':'' ?>>待处理</option>
      <option value="handled"   <?= $filter_status==='handled'   ?'selected':'' ?>>已处理</option>
      <option value="dismissed" <?= $filter_status==='dismissed' ?'selected':'' ?>>已驳回</option>
    </select>
    <select name="type" onchange="this.form.submit()" style="padding:6px 10px;border:1px solid var(--border);border-radius:var(--r);font-size:13px;background:var(--bg-2);color:var(--txt)">
      <option value=""      <?= $filter_type===''      ?'selected':'' ?>>全部类型</option>
      <option value="post"  <?= $filter_type==='post'  ?'selected':'' ?>>帖子</option>
      <option value="user"  <?= $filter_type==='user'  ?'selected':'' ?>>用户</option>
      <option value="comment" <?= $filter_type==='comment' ?'selected':'' ?>>评论</option>
    </select>
  </form>
</div>

<div class="card">
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:var(--bg-2);text-align:left">
        <th style="padding:10px 14px;font-weight:600;color:var(--txt-2)">ID</th>
        <th style="padding:10px 14px;font-weight:600;color:var(--txt-2)">类型</th>
        <th style="padding:10px 14px;font-weight:600;color:var(--txt-2)">被举报对象</th>
        <th style="padding:10px 14px;font-weight:600;color:var(--txt-2)">举报原因</th>
        <th style="padding:10px 14px;font-weight:600;color:var(--txt-2)">举报人</th>
        <th style="padding:10px 14px;font-weight:600;color:var(--txt-2)">时间</th>
        <th style="padding:10px 14px;font-weight:600;color:var(--txt-2)">状态</th>
        <th style="padding:10px 14px;font-weight:600;color:var(--txt-2)">操作</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($reports)): ?>
      <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--txt-3)">暂无举报记录</td></tr>
    <?php else: ?>
      <?php foreach ($reports as $r): ?>
      <?php
        // 取目标摘要
        $target_label = '';
        if ($r['type'] === 'post') {
            $tr = $conn->query("SELECT id,title,user_id FROM posts WHERE id={$r['target_id']}");
            if ($tr && $tp = $tr->fetch_assoc())
                $target_label = '<a href="../pages/post.php?id='.$tp['id'].'" target="_blank" style="color:var(--primary)">'.h(mb_substr($tp['title'],0,25)).'</a>';
            else $target_label = '<span style="color:var(--txt-3)">[帖子已删除]</span>';
        } elseif ($r['type'] === 'user') {
            $tr = $conn->query("SELECT id,username FROM users WHERE id={$r['target_id']}");
            if ($tr && $tp = $tr->fetch_assoc())
                $target_label = '<a href="../pages/profile.php?id='.$tp['id'].'" target="_blank" style="color:var(--primary)">'.h($tp['username']).'</a>';
            else $target_label = '<span style="color:var(--txt-3)">[用户已删除]</span>';
        } else {
            $tr = $conn->query("SELECT id,content FROM comments WHERE id={$r['target_id']}");
            if ($tr && $tp = $tr->fetch_assoc())
                $target_label = h(mb_substr($tp['content'],0,30));
            else $target_label = '<span style="color:var(--txt-3)">[评论已删除]</span>';
        }
        $status_colors = ['pending'=>'#f59e0b','handled'=>'#10b981','dismissed'=>'#6b7280'];
        $status_labels = ['pending'=>'待处理','handled'=>'已处理','dismissed'=>'已驳回'];
      ?>
      <tr style="border-top:1px solid var(--border)">
        <td style="padding:10px 14px;color:var(--txt-3)">#<?= $r['id'] ?></td>
        <td style="padding:10px 14px">
          <span style="padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;
            background:<?= $r['type']==='post' ? 'rgba(37,99,235,.12)' : ($r['type']==='user' ? 'rgba(139,92,246,.12)' : 'rgba(16,185,129,.12)') ?>;
            color:<?= $r['type']==='post' ? '#2563eb' : ($r['type']==='user' ? '#7c3aed' : '#059669') ?>">
            <?= $r['type']==='post' ? '帖子' : ($r['type']==='user' ? '用户' : '评论') ?>
          </span>
        </td>
        <td style="padding:10px 14px"><?= $target_label ?></td>
        <td style="padding:10px 14px">
          <div style="font-weight:600"><?= h($r['reason']) ?></div>
          <?php if ($r['detail']): ?>
            <div style="color:var(--txt-3);font-size:12px;margin-top:2px"><?= h(mb_substr($r['detail'],0,60)) ?></div>
          <?php endif; ?>
        </td>
        <td style="padding:10px 14px"><?= h($r['reporter_name'] ?? '-') ?></td>
        <td style="padding:10px 14px;color:var(--txt-3);white-space:nowrap"><?= date('m-d H:i', strtotime($r['created_at'])) ?></td>
        <td style="padding:10px 14px">
          <span style="color:<?= $status_colors[$r['status']] ?>;font-weight:600">
            <?= $status_labels[$r['status']] ?>
          </span>
          <?php if ($r['handler_name']): ?>
            <div style="font-size:11px;color:var(--txt-3)"><?= h($r['handler_name']) ?></div>
          <?php endif; ?>
        </td>
        <td style="padding:10px 14px">
          <?php if ($r['status'] === 'pending'): ?>
          <div style="display:flex;flex-direction:column;gap:5px;min-width:90px">
            <form method="post" style="margin:0">
              <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
              <button name="action" value="handle"  class="btn btn-outline btn-sm" style="width:100%;font-size:11px">✔ 已处理</button>
            </form>
            <form method="post" style="margin:0">
              <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
              <button name="action" value="dismiss" class="btn btn-outline btn-sm" style="width:100%;font-size:11px;color:var(--txt-3)">✕ 驳回</button>
            </form>
            <?php if ($r['type'] === 'post'): ?>
            <form method="post" style="margin:0" onsubmit="return confirm('确认删除该帖子？')">
              <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
              <button name="action" value="delete_post" class="btn btn-sm" style="width:100%;font-size:11px;background:var(--danger);color:#fff;border:none">🗑 删帖</button>
            </form>
            <?php elseif ($r['type'] === 'user'): ?>
            <button class="btn btn-sm" style="width:100%;font-size:11px;background:var(--danger);color:#fff;border:none"
                    onclick="openBanModal(<?= $r['id'] ?>)">🔒 封禁</button>
            <?php endif; ?>
          </div>
          <?php else: ?>
            <span style="font-size:12px;color:var(--txt-3)">—</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- 分页 -->
<?php if ($pages > 1): ?>
<div style="display:flex;gap:6px;justify-content:center;margin-top:16px;flex-wrap:wrap">
  <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a href="?status=<?= h($filter_status) ?>&type=<?= h($filter_type) ?>&p=<?= $i ?>"
       style="padding:5px 12px;border-radius:var(--r);border:1px solid var(--border);font-size:13px;
              <?= $i===$page ? 'background:var(--primary);color:#fff;border-color:var(--primary)' : 'color:var(--txt)' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<!-- 封禁弹窗 -->
<div id="ban-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9500;align-items:center;justify-content:center;">
  <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--r-lg);padding:24px;width:360px;max-width:94vw;box-shadow:var(--shadow-md)">
    <div style="font-size:15px;font-weight:700;margin-bottom:16px;color:var(--danger)">🔒 封禁用户</div>
    <form id="ban-form" method="post">
      <input type="hidden" name="action" value="ban_user">
      <input type="hidden" name="report_id" id="ban-report-id">
      <div class="form-group">
        <label style="font-size:13px;color:var(--txt-2);display:block;margin-bottom:5px">封禁时长</label>
        <select name="ban_days" style="width:100%;padding:7px 10px;border:1px solid var(--border);border-radius:var(--r);font-size:13px;background:var(--bg-2);color:var(--txt)">
          <option value="1">1 天</option>
          <option value="3">3 天</option>
          <option value="7" selected>7 天</option>
          <option value="30">30 天</option>
          <option value="0">永久</option>
        </select>
      </div>
      <div class="form-group">
        <label style="font-size:13px;color:var(--txt-2);display:block;margin-bottom:5px">封禁原因</label>
        <input type="text" name="ban_reason" value="违反社区规范"
               style="width:100%;padding:7px 10px;border:1px solid var(--border);border-radius:var(--r);font-size:13px;box-sizing:border-box;background:var(--bg-2);color:var(--txt)">
      </div>
      <div style="display:flex;gap:10px;margin-top:16px">
        <button type="submit" class="btn btn-sm" style="flex:1;background:var(--danger);color:#fff;border:none;padding:9px;border-radius:var(--r);font-weight:700;cursor:pointer">确认封禁</button>
        <button type="button" onclick="closeBanModal()" style="flex:1;padding:9px;border-radius:var(--r);border:1px solid var(--border);background:transparent;color:var(--txt-2);cursor:pointer;font-size:13px">取消</button>
      </div>
    </form>
  </div>
</div>

<script>
function openBanModal(reportId) {
    document.getElementById('ban-report-id').value = reportId;
    document.getElementById('ban-modal').style.display = 'flex';
}
function closeBanModal() {
    document.getElementById('ban-modal').style.display = 'none';
}
document.getElementById('ban-modal').addEventListener('click', function(e) {
    if (e.target === this) closeBanModal();
});
</script>

<?php include '../includes/footer.php'; ?>
