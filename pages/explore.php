<?php
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$uid  = intval($_SESSION['user_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 20;

// 个性化推荐（有兴趣数据时）
$posts = [];
$is_personalized = false;

if ($uid) {
    $ir = $conn->query("SELECT tags FROM user_interests WHERE user_id=$uid ORDER BY weight DESC LIMIT 10");
    $interest_tags = [];
    if ($ir) while ($row = $ir->fetch_assoc()) {
        foreach (array_filter(array_map('trim', explode(',', $row['tags']))) as $t)
            $interest_tags[] = $t;
    }
    if (!empty($interest_tags)) {
        $is_personalized = true;
        $pattern = implode('|', array_map(function($t) use ($conn) {
            return $conn->real_escape_string($t);
        }, array_slice(array_unique($interest_tags), 0, 8)));

        $total_r = $conn->query("SELECT COUNT(*) as cnt FROM posts WHERE status='published' AND tags REGEXP '$pattern'");
        $total   = (int)($total_r ? $total_r->fetch_assoc()['cnt'] : 0);

        $offset  = ($page-1)*$per;
        $pr = $conn->query("SELECT p.*,u.username,u.avatar,s.name as section_name
            FROM posts p JOIN users u ON u.id=p.user_id JOIN sections s ON s.id=p.section_id
            WHERE p.status='published' AND p.tags REGEXP '$pattern'
            ORDER BY p.created_at DESC LIMIT $per OFFSET $offset");
        if ($pr) while ($r = $pr->fetch_assoc()) $posts[] = $r;
    }
}

// fallback：精选或最热
if (empty($posts)) {
    $total_r = $conn->query("SELECT COUNT(*) as cnt FROM posts WHERE status='published'");
    $total   = (int)($total_r ? $total_r->fetch_assoc()['cnt'] : 0);
    $offset  = ($page-1)*$per;
    $pr = $conn->query("SELECT p.*,u.username,u.avatar,s.name as section_name
        FROM posts p JOIN users u ON u.id=p.user_id JOIN sections s ON s.id=p.section_id
        WHERE p.status='published'
        ORDER BY (p.views*0.3+p.like_count*2+p.comment_count) DESC LIMIT $per OFFSET $offset");
    if ($pr) while ($r = $pr->fetch_assoc()) $posts[] = $r;
}

$total_pages = max(1, ceil($total / $per));
$page_title  = '发现';
include '../includes/header.php';
?>

<div class="flex-center gap-8 mb-16" style="font-size:13px;color:var(--txt-2)">
  <a href="../index.php">首页</a> &rsaquo; <span>发现</span>
</div>

<div class="card mb-16" style="padding:16px 20px;display:flex;align-items:center;gap:12px">
  <span style="font-size:24px">🔭</span>
  <div>
    <div style="font-weight:700;font-size:16px">发现好帖</div>
    <div style="font-size:13px;color:var(--txt-2)">
      <?= $is_personalized ? '根据你的兴趣个性化推荐' : '全站热门内容' ?>
    </div>
  </div>
</div>

<div class="layout-2col">
  <div class="col-main">
    <div class="post-list">
      <?php if (empty($posts)): ?>
        <div class="empty-state"><div class="icon">📭</div><p>暂无内容</p></div>
      <?php else: ?>
        <?php foreach ($posts as $p): echo render_post_item($p, '../'); endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php for ($i=1; $i<=$total_pages; $i++): ?>
        <a href="?page=<?= $i ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="col-side">
    <!-- 分区导航 -->
    <div class="card">
      <div class="card-header">🗂️ 全部分区</div>
      <div class="card-body">
        <?php
        $sr = $conn->query("SELECT s.*,p.slug as parent_slug FROM sections s LEFT JOIN sections p ON p.id=s.parent_id ORDER BY p.sort_order,s.sort_order");
        $cur_parent = '';
        if ($sr) while ($sec = $sr->fetch_assoc()):
            if ($sec['parent_id'] == 0):
                $cur_parent = $sec['slug'];
        ?>
          <div style="font-weight:600;font-size:13px;margin:8px 0 4px;color:var(--txt-2)"><?= h($sec['icon']) ?> <?= h($sec['name']) ?></div>
        <?php else: ?>
          <a href="section.php?slug=<?= h($sec['parent_slug']) ?>&sub=<?= h($sec['slug']) ?>"
             style="display:block;font-size:13px;padding:3px 8px;color:var(--txt);border-radius:4px"
             onmouseover="this.style.background='var(--bg-2)'" onmouseout="this.style.background=''">
            <?= h($sec['icon']??'') ?> <?= h($sec['name']) ?>
          </a>
        <?php endif; endwhile; ?>
      </div>
    </div>
  </div>
</div>

<?php
function render_post_item($p, $base) {
    $tags_arr = array_filter(array_map('trim', explode(',', $p['tags'])));
    ob_start(); ?>
    <div class="post-item">
      <div class="post-meta-left">
        <a href="<?= $base ?>pages/post.php?id=<?= $p['id'] ?>" class="post-title-link">
          <?= h($p['title']) ?>
          <?= $p['is_solved'] ? ' <span style="font-size:11px;background:#dcfce7;color:#166534;padding:2px 6px;border-radius:4px">✅ 已解决</span>' : '' ?>
        </a>
        <?php if (!empty($p['summary'])): ?>
          <div class="post-summary"><?= h($p['summary']) ?></div>
        <?php endif; ?>
        <div class="post-tags">
          <?php foreach (array_slice($tags_arr,0,3) as $tag): ?>
            <span class="tag"><?= h($tag) ?></span>
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
