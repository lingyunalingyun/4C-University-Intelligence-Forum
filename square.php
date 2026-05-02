<?php
/*
 * square.php — 广场页面
 * 功能：展示全部已发布帖子，支持分区筛选、排序（最新/热度）、分页（每页20条）。
 * 读库：posts / users / sections
 * 权限：无需登录
 */
require_once 'config.php';
require_once 'includes/helpers.php';
$page_title = '广场';
if (session_status() === PHP_SESSION_NONE) session_start();

// 分页 & 过滤参数
$per_page  = 20;
$cur_page  = max(1, intval($_GET['page'] ?? 1));
$sort      = $_GET['sort'] ?? 'new';  // new | hot
$tab       = in_array($_GET['tab'] ?? '', ['featured', 'unsolved', 'solved']) ? $_GET['tab'] : 'all';
$offset    = ($cur_page - 1) * $per_page;

// 四大分区
$main_sections = [];
$ms_res = $conn->query("SELECT id, name, icon, color, slug, description FROM sections WHERE parent_id = 0 ORDER BY sort_order");
if ($ms_res) while ($r = $ms_res->fetch_assoc()) $main_sections[] = $r;

// 热帖榜（侧边栏）
$hot_posts = [];
$hot_res = $conn->query("
    SELECT p.id, p.title, p.views, p.like_count
    FROM posts p
    WHERE p.status = 'published' AND p.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY (p.views * 0.3 + p.like_count * 2 + p.comment_count) DESC LIMIT 8");
if ($hot_res) while ($r = $hot_res->fetch_assoc()) $hot_posts[] = $r;

// 活跃板块（侧边栏）
$active_sections = [];
$as_res = $conn->query("
    SELECT s.id, s.name, s.icon, s.slug,
        COUNT(p.id) AS cnt
    FROM sections s
    LEFT JOIN sections sub ON sub.parent_id = s.id
    LEFT JOIN posts p ON p.section_id = sub.id AND p.status = 'published'
        AND p.created_at > DATE_SUB(NOW(), INTERVAL 3 DAY)
    WHERE s.parent_id = 0
    GROUP BY s.id ORDER BY cnt DESC LIMIT 4");
if ($as_res) while ($r = $as_res->fetch_assoc()) $active_sections[] = $r;

// 帖子列表
$where = "p.status = 'published'";
if ($tab === 'featured') $where .= " AND p.is_featured = 1";
elseif ($tab === 'unsolved') $where .= " AND p.is_solved = 0";
elseif ($tab === 'solved')   $where .= " AND p.is_solved = 1";

$order_by = $sort === 'hot'
    ? "(p.views * 0.3 + p.like_count * 2 + p.comment_count) DESC"
    : "p.is_pinned DESC, p.created_at DESC";

$total_res   = $conn->query("SELECT COUNT(*) c FROM posts p WHERE $where");
$total       = $total_res ? (int)$total_res->fetch_assoc()['c'] : 0;
$total_pages = max(1, (int)ceil($total / $per_page));

$posts = [];
$post_res = $conn->query("
    SELECT p.*, u.username, u.avatar, s.name AS section_name
    FROM posts p
    JOIN users u ON u.id = p.user_id
    JOIN sections s ON s.id = p.section_id
    WHERE $where
    ORDER BY $order_by
    LIMIT $per_page OFFSET $offset");
if ($post_res) while ($r = $post_res->fetch_assoc()) $posts[] = $r;

include 'includes/header.php';
?>

<div class="eyebrow u1">
  <span class="ey-lbl">Forum · 广场</span>
  <span class="ey-sep">·</span>
  <span class="ey-dt"><?= number_format($total) ?> 篇帖子</span>
</div>

<!-- 四大分区入口 -->
<div class="sec-grid">
<?php foreach ($main_sections as $sec): ?>
  <a href="pages/section.php?slug=<?= h($sec['slug']) ?>" class="sec-card u2">
    <div class="sc-icon"><?= render_icon($sec['icon'], 'lucide', 28) ?></div>
    <div class="sc-name"><?= h($sec['name']) ?></div>
    <div class="sc-desc"><?= h(mb_substr($sec['description'] ?? '', 0, 28)) ?></div>
  </a>
<?php endforeach; ?>
</div>

<div class="layout">
  <div class="col-m">

    <div class="col-hd u3">
      <span class="col-hd-t">全部帖子</span>
      <span class="col-hd-s">
        <a href="?tab=<?= $tab ?>&sort=new" style="color:<?= $sort==='new'?'var(--txt)':'var(--txt-3)' ?>">最新</a>
        &nbsp;·&nbsp;
        <a href="?tab=<?= $tab ?>&sort=hot" style="color:<?= $sort==='hot'?'var(--txt)':'var(--txt-3)' ?>">热度</a>
      </span>
    </div>

    <div class="ftabs u3">
      <a href="?tab=all&sort=<?= $sort ?>"      class="ftab <?= $tab === 'all'      ? 'on' : '' ?>">全部</a>
      <a href="?tab=featured&sort=<?= $sort ?>" class="ftab <?= $tab === 'featured' ? 'on' : '' ?>">精华</a>
      <a href="?tab=unsolved&sort=<?= $sort ?>" class="ftab <?= $tab === 'unsolved' ? 'on' : '' ?>">待解答</a>
      <a href="?tab=solved&sort=<?= $sort ?>"   class="ftab <?= $tab === 'solved'   ? 'on' : '' ?>">已解决</a>
    </div>

    <div class="post-list-new u4">
      <?php if (empty($posts)): ?>
        <div class="empty-state">
          <div class="icon">📭</div>
          <p>暂无帖子，快来<a href="pages/publish.php">发第一帖</a>！</p>
        </div>
      <?php else: ?>
        <?php foreach ($posts as $p): ?>
          <?= render_post_item($p, '') ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pager u6">
      <?php if ($cur_page > 1): ?>
        <a href="?tab=<?= $tab ?>&sort=<?= $sort ?>&page=<?= $cur_page - 1 ?>" class="pg"><i data-lucide="chevron-left" class="lucide"></i></a>
      <?php endif; ?>
      <?php for ($i = max(1, $cur_page - 2); $i <= min($total_pages, $cur_page + 2); $i++): ?>
        <a href="?tab=<?= $tab ?>&sort=<?= $sort ?>&page=<?= $i ?>" class="pg <?= $i === $cur_page ? 'on' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($cur_page < $total_pages): ?>
        <a href="?tab=<?= $tab ?>&sort=<?= $sort ?>&page=<?= $cur_page + 1 ?>" class="pg"><i data-lucide="chevron-right" class="lucide"></i></a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div>

  <aside class="col-s">

    <div class="s-card u4">
      <div class="s-hd"><i data-lucide="trending-up" class="lucide"></i>热门帖子</div>
      <div class="s-body">
        <?php if (empty($hot_posts)): ?>
          <p style="font-size:13px;color:var(--txt-3)">暂无热帖</p>
        <?php else: ?>
        <ul class="hl">
          <?php foreach ($hot_posts as $i => $hp): ?>
          <li>
            <span class="hn <?= $i < 3 ? 'top' : '' ?>"><?= $i + 1 ?></span>
            <div>
              <a href="pages/post.php?id=<?= $hp['id'] ?>" class="ht"><?= h($hp['title']) ?></a>
              <div class="hm"><i data-lucide="eye" class="lucide"></i><?= number_format($hp['views']) ?></div>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
    </div>

    <div class="s-card u5">
      <div class="s-hd"><i data-lucide="bar-chart-2" class="lucide"></i>活跃板块</div>
      <div class="s-body">
        <ul class="sl">
          <?php foreach ($active_sections as $as): ?>
          <li>
            <span class="sn">
              <?= render_icon($as['icon'], 'lucide') ?>
              <a href="pages/section.php?slug=<?= h($as['slug']) ?>" style="color:inherit"><?= h($as['name']) ?></a>
            </span>
            <span class="sc2">今日 <?= $as['cnt'] ?> 帖</span>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

  </aside>
</div>

<?php include 'includes/footer.php'; ?>
