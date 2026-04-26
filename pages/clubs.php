<?php
require_once '../config.php';
require_once '../includes/helpers.php';

$page_title = '社团广场';
$search  = trim($_GET['q']      ?? '');
$school  = trim($_GET['school'] ?? '');

$where = "c.status = 'active'";
if ($search) $where .= " AND c.name LIKE '%" . $conn->real_escape_string($search) . "%'";
if ($school) $where .= " AND c.school = '" . $conn->real_escape_string($school) . "'";

$clubs = [];
$cr = $conn->query("SELECT c.*, u.username AS president_name
    FROM clubs c JOIN users u ON u.id = c.president_id
    WHERE $where ORDER BY c.created_at DESC");
if ($cr) while ($r = $cr->fetch_assoc()) $clubs[] = $r;

include '../includes/header.php';
?>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap">
  <h2 style="margin:0">🏛️ 社团广场</h2>
  <?php if ($is_logged_in): ?>
    <a href="club_apply.php" class="btn btn-primary btn-sm" style="margin-left:auto">+ 申请创建社团</a>
  <?php endif; ?>
</div>

<form method="get" style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
  <input type="text" name="q"      value="<?= h($search) ?>" placeholder="搜索社团名称…" style="flex:1;min-width:140px;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;background:var(--bg);color:var(--txt)">
  <input type="text" name="school" value="<?= h($school) ?>" placeholder="按学校筛选…"   style="width:180px;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;background:var(--bg);color:var(--txt)">
  <button type="submit" class="btn btn-outline">搜索</button>
  <?php if ($search || $school): ?><a href="clubs.php" class="btn btn-outline">清除</a><?php endif; ?>
</form>

<?php if (empty($clubs)): ?>
<div class="empty-state">
  <div class="icon">🏛️</div>
  <p><?= ($search || $school) ? '未找到符合条件的社团' : '暂无社团，快来申请创建吧！' ?></p>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
  <?php foreach ($clubs as $c): ?>
  <a href="club.php?id=<?= $c['id'] ?>" style="text-decoration:none">
    <div class="card" style="padding:0;overflow:hidden;transition:transform .15s,box-shadow .15s" onmouseenter="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,.1)'" onmouseleave="this.style.transform='';this.style.boxShadow=''">
      <div style="padding:18px 20px 14px;display:flex;gap:14px;align-items:center">
        <?php if (!empty($c['avatar'])): ?>
          <img src="../uploads/clubs/<?= h($c['avatar']) ?>"
               style="width:48px;height:48px;border-radius:50%;object-fit:cover;border:1.5px solid var(--border);flex-shrink:0">
        <?php else: ?>
          <div style="width:48px;height:48px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff;font-weight:700;flex-shrink:0">
            <?= mb_substr($c['name'], 0, 1) ?>
          </div>
        <?php endif; ?>
        <div style="flex:1;min-width:0">
          <div style="font-size:16px;font-weight:700;color:var(--txt);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= h($c['name']) ?></div>
          <div style="font-size:12px;color:var(--txt-2);margin-top:2px">🏫 <?= h($c['school']) ?></div>
        </div>
      </div>
      <div style="padding:10px 20px 14px;border-top:1px solid var(--border)">
        <div style="font-size:13px;color:var(--txt-2);margin-bottom:10px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;min-height:36px">
          <?= $c['description'] ? h(mb_substr($c['description'], 0, 80)) : '暂无简介' ?>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--txt-3)">
          <span>👑 <?= h($c['president_name']) ?></span>
          <span>👥 <?= $c['member_count'] ?> 人</span>
        </div>
      </div>
    </div>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
