<?php
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$q    = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? 'post'; // post / user
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 20;

$posts = $users = [];
$total = 0;

if ($q !== '') {
    $safe = $conn->real_escape_string($q);
    if ($type === 'user') {
        $total_r = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE username LIKE '%$safe%' OR school LIKE '%$safe%'");
        $total   = (int)($total_r ? $total_r->fetch_assoc()['cnt'] : 0);
        $offset  = ($page-1)*$per;
        $ur = $conn->query("SELECT id,username,avatar,school,exp,role FROM users WHERE username LIKE '%$safe%' OR school LIKE '%$safe%' ORDER BY exp DESC LIMIT $per OFFSET $offset");
        if ($ur) while ($r = $ur->fetch_assoc()) $users[] = $r;
    } else {
        $total_r = $conn->query("SELECT COUNT(*) as cnt FROM posts p WHERE p.status='published' AND (p.title LIKE '%$safe%' OR p.content LIKE '%$safe%' OR p.tags LIKE '%$safe%')");
        $total   = (int)($total_r ? $total_r->fetch_assoc()['cnt'] : 0);
        $offset  = ($page-1)*$per;
        $pr = $conn->query("SELECT p.*,u.username,u.avatar,s.name as section_name
            FROM posts p JOIN users u ON u.id=p.user_id JOIN sections s ON s.id=p.section_id
            WHERE p.status='published' AND (p.title LIKE '%$safe%' OR p.content LIKE '%$safe%' OR p.tags LIKE '%$safe%')
            ORDER BY p.created_at DESC LIMIT $per OFFSET $offset");
        if ($pr) while ($r = $pr->fetch_assoc()) $posts[] = $r;
    }
}

$total_pages = max(1, ceil($total / $per));
$page_title  = $q ? "搜索：$q" : '搜索';
include '../includes/header.php';
?>

<div class="flex-center gap-8 mb-16" style="font-size:13px;color:var(--txt-2)">
  <a href="../index.php">首页</a> &rsaquo; <span>搜索</span>
</div>

<!-- 搜索框 -->
<div class="card mb-20" style="padding:20px">
  <form method="get" action="search.php" class="flex-center gap-12">
    <input type="text" name="q" value="<?= h($q) ?>" placeholder="搜索帖子、用户..."
           style="flex:1;padding:10px 16px;border:1px solid var(--border);border-radius:var(--r);font-size:14px;outline:none">
    <select name="type" style="padding:10px;border:1px solid var(--border);border-radius:var(--r);font-size:14px;outline:none;background:var(--bg-2)">
      <option value="post"  <?= $type==='post' ?'selected':'' ?>>帖子</option>
      <option value="user"  <?= $type==='user' ?'selected':'' ?>>用户</option>
    </select>
    <button type="submit" class="btn btn-primary">搜索</button>
  </form>
</div>

<?php if ($q !== ''): ?>
<div style="font-size:13px;color:var(--txt-2);margin-bottom:12px">
  找到 <strong><?= $total ?></strong> 个结果，关键词：<strong><?= h($q) ?></strong>
</div>

<?php if ($type === 'post'): ?>
  <div class="post-list">
    <?php if (empty($posts)): ?>
      <div class="empty-state"><div class="icon">🔍</div><p>没有找到相关帖子</p></div>
    <?php else: ?>
      <?php foreach ($posts as $p): echo render_post_item($p, '../', $q); endforeach; ?>
    <?php endif; ?>
  </div>

<?php else: ?>
  <div class="card">
    <div class="card-body">
      <?php if (empty($users)): ?>
        <div class="empty-state"><div class="icon">👤</div><p>没有找到相关用户</p></div>
      <?php else: ?>
        <?php foreach ($users as $u): ?>
        <div class="flex-center gap-12" style="padding:12px 0;border-bottom:1px solid var(--border)">
          <img src="../uploads/avatars/<?= h($u['avatar']) ?>"
               onerror="this.src='../assets/default_avatar.svg'"
               style="width:44px;height:44px;border-radius:50%;object-fit:cover;flex-shrink:0">
          <div style="flex:1">
            <a href="profile.php?id=<?= $u['id'] ?>" style="font-weight:600;color:var(--txt)"><?= h($u['username']) ?></a>
            <?= level_badge($u['exp']) ?> <?= role_badge($u['role']) ?>
            <?php if ($u['school']): ?><div style="font-size:12px;color:var(--txt-2)"><?= h($u['school']) ?></div><?php endif; ?>
          </div>
          <a href="profile.php?id=<?= $u['id'] ?>" class="btn btn-outline btn-sm">查看主页</a>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php if ($total_pages > 1): ?>
<div class="pagination">
  <?php for ($i=1; $i<=$total_pages; $i++): ?>
    <a href="?q=<?= urlencode($q) ?>&type=<?= h($type) ?>&page=<?= $i ?>"
       class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<?php
function render_post_item($p, $base, $kw='') {
    $tags_arr = array_filter(array_map('trim', explode(',', $p['tags'])));
    ob_start(); ?>
    <div class="post-item">
      <div class="post-meta-left">
        <a href="<?= $base ?>pages/post.php?id=<?= $p['id'] ?>" class="post-title-link">
          <?= h($p['title']) ?>
        </a>
        <?php if (!empty($p['summary'])): ?>
          <div class="post-summary"><?= h($p['summary']) ?></div>
        <?php endif; ?>
        <div class="post-tags">
          <?php foreach (array_slice($tags_arr,0,3) as $tag): ?>
            <a href="search.php?q=<?= urlencode($tag) ?>" class="tag"><?= h($tag) ?></a>
          <?php endforeach; ?>
        </div>
        <div class="post-footer">
          <span class="author">
            <img src="<?= $base ?>uploads/avatars/<?= h($p['avatar']) ?>"
                 onerror="this.src='<?= $base ?>assets/default_avatar.svg'" alt="">
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
    <?php return ob_get_clean();
}

include '../includes/footer.php';
