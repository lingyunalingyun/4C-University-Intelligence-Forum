<?php
/*
 * pages/my_clubs.php — 我的社团页面
 * 功能：展示当前用户所在社团，以及待审批的申请状态，
 *       提供社团管理入口（社长/副社长）。
 * 读库：clubs / club_members
 * 权限：需登录
 */
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php?redirect=my_clubs.php'); exit; }

$uid = intval($_SESSION['user_id']);

// 我的社团（按角色分组）
$my_clubs = [];
$res = $conn->query("SELECT c.*, cm.role AS my_role
    FROM club_members cm
    JOIN clubs c ON c.id = cm.club_id
    WHERE cm.user_id = $uid
    ORDER BY cm.role='president' DESC, cm.role='vice_president' DESC, c.name ASC");
if ($res) while ($r = $res->fetch_assoc()) $my_clubs[] = $r;

// 待审核的创建申请
$pending_apps = [];
$ar = $conn->query("SELECT * FROM club_applications WHERE user_id=$uid AND status='pending' ORDER BY created_at DESC");
if ($ar) while ($r = $ar->fetch_assoc()) $pending_apps[] = $r;

$page_title = '我的社团';
include '../includes/header.php';
?>

<div class="flex-center gap-8 mb-16" style="font-size:13px;color:var(--txt-2)">
  <a href="../index.php">首页</a> &rsaquo; <a href="clubs.php">社团</a> &rsaquo; <span>我的社团</span>
</div>

<div style="max-width:800px;margin:0 auto">

<?php if (!empty($pending_apps)): ?>
<div class="card mb-20">
  <div class="card-header">⏳ 待审核申请</div>
  <div class="card-body" style="padding:0">
    <table class="data-table">
      <thead><tr><th>社团名称</th><th>附属学校</th><th>申请时间</th><th>状态</th></tr></thead>
      <tbody>
        <?php foreach ($pending_apps as $a): ?>
        <tr>
          <td><strong><?= h($a['name']) ?></strong></td>
          <td><?= h($a['school']) ?></td>
          <td style="font-size:12px;color:var(--txt-3)"><?= date('Y-m-d H:i', strtotime($a['created_at'])) ?></td>
          <td><span style="font-size:12px;color:#f59e0b">审核中…</span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if (empty($my_clubs)): ?>
<div class="empty-state">
  <div class="icon"><i data-lucide="building-2" class="lucide"></i></div>
  <p>您还未加入任何社团</p>
  <a href="clubs.php" class="btn btn-primary btn-sm">浏览社团</a>
</div>
<?php else: ?>

<?php
$role_label = ['president' => '社长', 'vice_president' => '副社长', 'member' => '成员'];
$role_color = ['president' => '#f59e0b', 'vice_president' => '#8b5cf6', 'member' => '#6b7280'];
foreach ($my_clubs as $c):
    $role = $c['my_role'];
    $avatar_src = !empty($c['avatar'])
        ? '../uploads/clubs/' . h($c['avatar'])
        : null;
?>
<div class="card mb-16">
  <div class="card-body" style="display:flex;align-items:center;gap:16px">
    <!-- 头像 -->
    <?php if ($avatar_src): ?>
      <img src="<?= $avatar_src ?>" style="width:64px;height:64px;border-radius:50%;object-fit:cover;border:2px solid var(--border);flex-shrink:0">
    <?php else: ?>
      <div style="width:64px;height:64px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:700;color:#fff;flex-shrink:0">
        <?= mb_substr($c['name'], 0, 1) ?>
      </div>
    <?php endif; ?>

    <div style="flex:1;min-width:0">
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <a href="club.php?id=<?= $c['id'] ?>" style="font-size:17px;font-weight:700;color:var(--txt)"><?= h($c['name']) ?></a>
        <span style="font-size:11px;font-weight:600;padding:2px 8px;border-radius:10px;background:<?= $role_color[$role] ?>;color:#fff">
          <?= $role_label[$role] ?>
        </span>
        <?php if ($c['status'] !== 'active'): ?>
          <span style="font-size:11px;color:#ef4444">[已停用]</span>
        <?php endif; ?>
      </div>
      <div style="font-size:13px;color:var(--txt-2);margin-top:2px"><i data-lucide="building" class="lucide"></i> <?= h($c['school']) ?> &nbsp;·&nbsp; <i data-lucide="users" class="lucide"></i> <?= $c['member_count'] ?> 人</div>
      <div style="font-size:13px;color:var(--txt-3);margin-top:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
        <?= h(mb_substr($c['description'] ?? '', 0, 60)) ?>
      </div>
    </div>

    <div style="display:flex;gap:8px;flex-shrink:0">
      <a href="club.php?id=<?= $c['id'] ?>" class="btn btn-outline btn-sm">查看</a>
      <?php if ($role === 'president'): ?>
        <a href="club_edit.php?id=<?= $c['id'] ?>" class="btn btn-primary btn-sm">管理</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<div style="margin-top:20px;text-align:center">
  <a href="club_apply.php" class="btn btn-outline btn-sm">+ 申请创建新社团</a>
</div>

</div>
<?php include '../includes/footer.php'; ?>
