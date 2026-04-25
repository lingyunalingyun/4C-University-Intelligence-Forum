<?php
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$range = $_GET['range'] ?? 'week'; // week / month / all
$page  = max(1, (int)($_GET['page'] ?? 1));
$per   = 20;

$where_time = [
    'week'  => "AND p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    'month' => "AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
    'all'   => '',
][$range] ?? "AND p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";

$total_r = $conn->query("SELECT COUNT(*) as cnt FROM posts p WHERE p.status='published' $where_time");
$total   = (int)($total_r ? $total_r->fetch_assoc()['cnt'] : 0);
$total_pages = max(1, ceil($total / $per));
$offset  = ($page-1)*$per;

$posts = [];
$pr = $conn->query("SELECT p.*,u.username,u.avatar,s.name as section_name
    FROM posts p JOIN users u ON u.id=p.user_id JOIN sections s ON s.id=p.section_id
    WHERE p.status='published' $where_time
    ORDER BY (p.views*0.3 + p.like_count*2 + p.comment_count*1.5) DESC
    LIMIT $per OFFSET $offset");
if ($pr) while ($r = $pr->fetch_assoc()) $posts[] = $r;

$page_title = '热帖榜';
include '../includes/header.php';
?>

<div class="flex-center gap-8 mb-16" style="font-size:13px;color:var(--txt-2)">
  <a href="../index.php">首页</a> &rsaquo; <span>热帖榜</span>
</div>

<div class="card mb-16" style="padding:16px 20px">
  <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
    <div style="font-size:18px;font-weight:700">🔥 热帖榜</div>
    <div class="flex-center gap-8">
      <?php foreach (['week'=>'本周','month'=>'本月','all'=>'全部时间'] as $k=>$v): ?>
        <a href="hot.php?range=<?= $k ?>" class="sub-section-tag <?= $range===$k?'active':'' ?>"><?= $v ?></a>
      <?php endforeach; ?>
    </div>
    <span style="margin-left:auto;font-size:13px;color:var(--txt-3)">共 <?= $total ?> 帖</span>
  </div>
</div>

<div class="post-list">
  <?php if (empty($posts)): ?>
    <div class="empty-state"><div class="icon">📭</div><p>暂无热帖</p></div>
  <?php else: ?>
    <?php foreach ($posts as $idx => $p): ?>
    <div class="post-item" style="position:relative">
      <div style="position:absolute;left:-8px;top:50%;transform:translateY(-50%);
                  width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;
                  font-weight:700;font-size:13px;
                  background:<?= $idx+($page-1)*$per < 3 ? 'var(--primary)' : 'var(--bg-2)' ?>;
                  color:<?= $idx+($page-1)*$per < 3 ? '#fff' : 'var(--txt-2)' ?>">
        <?= $idx+1+($page-1)*$per ?>
      </div>
      <div class="post-meta-left" style="margin-left:28px">
        <a href="post.php?id=<?= $p['id'] ?>" class="post-title-link">
          <?= h($p['title']) ?>
          <?= $p['is_solved'] ? ' <span style="font-size:11px;background:#dcfce7;color:#166534;padding:2px 6px;border-radius:4px">✅ 已解决</span>' : '' ?>
        </a>
        <?php if (!empty($p['summary'])): ?>
          <div class="post-summary"><?= h($p['summary']) ?></div>
        <?php endif; ?>
        <div class="post-footer">
          <span class="author">
            <img src="../uploads/avatars/<?= h($p['avatar']) ?>"
                 onerror="this.src='../assets/default_avatar.svg'" alt="">
            <?= h($p['username']) ?>
          </span>
          <span><?= time_ago($p['created_at']) ?></span>
          <span style="font-size:12px;color:var(--txt-3)"><?= h($p['section_name']) ?></span>
          <div class="post-stats">
            <span>👁 <?= $p['views'] ?></span>
            <span>👍 <?= $p['like_count'] ?></span>
            <span>💬 <?= $p['comment_count'] ?></span>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php if ($total_pages > 1): ?>
<div class="pagination">
  <?php for ($i=1; $i<=$total_pages; $i++): ?>
    <a href="?range=<?= h($range) ?>&page=<?= $i ?>"
       class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
