<?php
require_once 'config.php';
require_once 'includes/helpers.php';
$page_title = '首页';

// 热帖榜（按浏览+点赞加权，最近7天）
$hot_posts = [];
$hot_res = $conn->query("SELECT p.id,p.title,p.like_count,p.views,p.comment_count,
    u.username, s.name as section_name
    FROM posts p
    JOIN users u ON u.id=p.user_id
    JOIN sections s ON s.id=p.section_id
    WHERE p.status='published' AND p.created_at > DATE_SUB(NOW(),INTERVAL 7 DAY)
    ORDER BY (p.views * 0.3 + p.like_count * 2 + p.comment_count) DESC
    LIMIT 10");
if ($hot_res) while ($r = $hot_res->fetch_assoc()) $hot_posts[] = $r;

// 活跃板块（发帖最多的一级分区）
$active_sections = [];
$as_res = $conn->query("SELECT s.id,s.name,s.icon,s.color,COUNT(p.id) as cnt
    FROM sections s
    LEFT JOIN sections sub ON sub.parent_id=s.id
    LEFT JOIN posts p ON p.section_id=sub.id AND p.status='published' AND p.created_at > DATE_SUB(NOW(),INTERVAL 3 DAY)
    WHERE s.parent_id=0
    GROUP BY s.id ORDER BY cnt DESC LIMIT 4");
if ($as_res) while ($r = $as_res->fetch_assoc()) $active_sections[] = $r;

// 为你推荐（已登录：按兴趣标签匹配；未登录：最新精选）
$recommended = [];
if ($is_logged_in ?? false) {
    $uid = intval($_SESSION['user_id']);
    $tags_res = $conn->query("SELECT tag FROM user_interests WHERE user_id=$uid ORDER BY weight DESC LIMIT 10");
    $tags = [];
    if ($tags_res) while ($r = $tags_res->fetch_assoc()) $tags[] = $r['tag'];

    if (!empty($tags)) {
        $tag_cond = implode(',', array_fill(0, count($tags), '?'));
        $types    = str_repeat('s', count($tags));
        $stmt     = $conn->prepare("SELECT p.*,u.username,u.avatar,s.name as section_name
            FROM posts p JOIN users u ON u.id=p.user_id JOIN sections s ON s.id=p.section_id
            WHERE p.status='published' AND p.tags REGEXP CONCAT('(', ?, ')')
            ORDER BY p.created_at DESC LIMIT 10");
        $tag_pattern = implode('|', array_map(function($t){ return preg_quote($t); }, $tags));
        $stmt->bind_param('s', $tag_pattern);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $recommended[] = $r;
        $stmt->close();
    }
}
if (empty($recommended)) {
    $rec_res = $conn->query("SELECT p.*,u.username,u.avatar,s.name as section_name
        FROM posts p JOIN users u ON u.id=p.user_id JOIN sections s ON s.id=p.section_id
        WHERE p.status='published' AND p.is_featured=1
        ORDER BY p.created_at DESC LIMIT 10");
    if ($rec_res) while ($r = $rec_res->fetch_assoc()) $recommended[] = $r;
}

// 最新帖子（支持本校/全站切换）
$user_school = $_SESSION['school'] ?? '';
$scope       = $_GET['scope'] ?? ($user_school ? 'school' : 'all');
$latest = [];
$school_cond = ($scope === 'school' && $user_school !== '')
    ? "AND u.school = '" . $conn->real_escape_string($user_school) . "'"
    : '';
$lat_res = $conn->query("SELECT p.*,u.username,u.avatar,s.name as section_name
    FROM posts p JOIN users u ON u.id=p.user_id JOIN sections s ON s.id=p.section_id
    WHERE p.status='published' $school_cond
    ORDER BY p.created_at DESC LIMIT 20");
if ($lat_res) while ($r = $lat_res->fetch_assoc()) $latest[] = $r;

// 一级分区
$main_sections = [];
$ms_res = $conn->query("SELECT * FROM sections WHERE parent_id=0 ORDER BY sort_order");
if ($ms_res) while ($r = $ms_res->fetch_assoc()) $main_sections[] = $r;

include 'includes/header.php';
?>

<!-- ── 四大分区入口 ── -->
<div class="section-grid">
<?php foreach ($main_sections as $sec): ?>
  <a href="pages/section.php?slug=<?= h($sec['slug']) ?>" class="section-card" style="border-top-color:<?= h($sec['color']) ?>">
    <div class="section-icon"><?= h($sec['icon']) ?></div>
    <div class="section-name"><?= h($sec['name']) ?></div>
    <div class="section-desc"><?= h(mb_substr($sec['description'], 0, 30)) ?></div>
  </a>
<?php endforeach; ?>
</div>

<div class="layout-2col">
  <!-- ── 主内容 ── -->
  <div class="col-main">

    <?php if (!empty($recommended)): ?>
    <div class="card mb-24">
      <div class="card-header">⭐ <?= ($is_logged_in ?? false) ? '为你推荐' : '精选内容' ?></div>
      <div class="post-list" style="padding:12px 8px 8px">
        <?php foreach ($recommended as $p): ?>
          <?= render_post_item($p, '') ?>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header">
        🕐 最新帖子
        <?php if (!empty($user_school)): ?>
        <div class="scope-tabs" style="margin-left:auto">
          <a href="?scope=school" class="scope-tab <?= $scope==='school'?'active':'' ?>">🏫 <?= h($user_school) ?></a>
          <a href="?scope=all"    class="scope-tab <?= $scope==='all'   ?'active':'' ?>">全站</a>
        </div>
        <?php endif; ?>
      </div>
      <div class="post-list" style="padding:12px 8px 8px">
        <?php if (empty($latest)): ?>
          <div class="empty-state"><div class="icon">📭</div>
            <p><?= ($scope==='school'&&$user_school) ? '本校还没有帖子，<a href="?scope=all">查看全站</a>或者<a href="pages/publish.php">来发第一帖</a>！' : '还没有帖子，快来发第一帖！' ?></p>
          </div>
        <?php else: ?>
          <?php foreach ($latest as $p): ?>
            <?= render_post_item($p, '') ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </div>

  <!-- ── 侧边栏 ── -->
  <div class="col-side">
    <!-- 热帖榜 -->
    <div class="card mb-16">
      <div class="card-header">🔥 热帖榜</div>
      <div class="card-body">
        <?php if (empty($hot_posts)): ?>
          <p class="text-muted" style="font-size:13px">暂无热帖</p>
        <?php else: ?>
        <ul class="hot-list">
          <?php foreach ($hot_posts as $i => $hp): ?>
          <li>
            <span class="hot-rank <?= $i < 3 ? 'top-3' : '' ?>"><?= $i+1 ?></span>
            <a href="pages/post.php?id=<?= $hp['id'] ?>" class="hot-title"><?= h($hp['title']) ?></a>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
    </div>

    <!-- 活跃板块 -->
    <div class="card">
      <div class="card-header">📌 活跃板块</div>
      <div class="card-body">
        <ul class="active-sections">
          <?php foreach ($active_sections as $as): ?>
          <li>
            <a href="pages/section.php?slug=<?= h($as['slug']) ?>" class="active-sect-name">
              <?= h($as['icon']) ?> <?= h($as['name']) ?>
            </a>
            <span class="active-sect-count"><?= $as['cnt'] ?> 帖</span>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php
function render_post_item($p, $base) {
    $tags_arr = array_filter(array_map('trim', explode(',', $p['tags'])));
    ob_start(); ?>
    <div class="post-item <?= $p['is_pinned'] ? 'post-pinned' : ($p['is_featured'] ? 'post-featured' : '') ?>">
      <div class="post-item-top">
        <div class="post-meta-left">
          <a href="<?= $base ?>pages/post.php?id=<?= $p['id'] ?>" class="post-title-link">
            <?= $p['is_pinned'] ? '<span style="color:var(--warning)">📌 </span>' : '' ?>
            <?= h($p['title']) ?>
            <?= $p['is_solved'] ? '<span class="post-solved"></span>' : '' ?>
          </a>
          <?php if (!empty($p['summary'])): ?>
            <div class="post-summary"><?= h($p['summary']) ?></div>
          <?php endif; ?>
          <div class="post-tags">
            <span class="tag tag-section"><?= h($p['section_name']) ?></span>
            <?php foreach (array_slice($tags_arr, 0, 3) as $tag): ?>
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
            <div class="post-stats">
              <span>👁 <?= $p['views'] ?></span>
              <span>👍 <?= $p['like_count'] ?></span>
              <span>💬 <?= $p['comment_count'] ?></span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php return ob_get_clean();
}

include 'includes/footer.php';
