<?php
require_once 'config.php';
require_once 'includes/helpers.php';
$page_title = '首页';

// 读取管理员配置的槽位
$slots = [];
$sr = $conn->query("SELECT hs.position,
    p.id, p.title, p.content, p.created_at, p.views, p.like_count, p.comment_count,
    u.username, u.avatar,
    s.name as section_name, s.color as section_color
    FROM homepage_slots hs
    JOIN posts p ON p.id = hs.post_id AND p.status = 'published'
    JOIN users u ON u.id = p.user_id
    JOIN sections s ON s.id = p.section_id
    ORDER BY hs.position ASC");
if ($sr) while ($r = $sr->fetch_assoc()) $slots[] = $r;

include 'includes/header.php';
?>

<style>
.home-hero {
    display: block;
    position: relative;
    width: 100%;
    aspect-ratio: 16/9;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 20px;
    text-decoration: none;
    transition: transform .2s, box-shadow .2s;
    background: var(--bg-2);
}
.home-hero:hover { transform: scale(1.008); box-shadow: 0 8px 32px rgba(0,0,0,0.18); }

.home-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
}
.home-grid-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
}
@media(max-width: 900px) { .home-grid-3 { grid-template-columns: 1fr 1fr; } }
@media(max-width: 640px) { .home-grid-2, .home-grid-3 { grid-template-columns: 1fr; } }

.home-card {
    display: block;
    position: relative;
    aspect-ratio: 16/9;
    border-radius: 10px;
    overflow: hidden;
    text-decoration: none;
    background: var(--bg-2);
    transition: transform .2s, box-shadow .2s;
}
.home-card:hover { transform: translateY(-3px); box-shadow: 0 6px 24px rgba(0,0,0,0.16); }

.home-card-img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.home-card-placeholder {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 52px;
    opacity: .5;
}
.home-card-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0,0,0,.82) 0%, rgba(0,0,0,.22) 55%, transparent 100%);
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: 16px;
    color: #fff;
}
.home-hero .home-card-overlay { padding: 28px; }

.home-card-section-tag {
    display: inline-block;
    font-size: 11px;
    font-weight: 600;
    background: var(--primary);
    color: #fff;
    padding: 2px 8px;
    border-radius: 4px;
    margin-bottom: 8px;
    align-self: flex-start;
}
.home-card-title {
    font-size: 15px;
    font-weight: 700;
    margin: 0 0 5px;
    line-height: 1.35;
    color: #fff;
    text-shadow: 0 1px 4px rgba(0,0,0,.5);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.home-hero .home-card-title { font-size: 24px; -webkit-line-clamp: 3; }
.home-card-meta { font-size: 12px; color: rgba(255,255,255,.75); }

.home-empty {
    text-align: center;
    padding: 80px 0 60px;
    color: var(--txt-2);
}
.home-empty-icon { font-size: 52px; margin-bottom: 14px; }
.home-more-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 13px;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    color: var(--txt-2);
    text-decoration: none;
    font-size: 14px;
    margin-bottom: 32px;
    transition: border-color .15s, color .15s;
}
.home-more-btn:hover { border-color: var(--primary); color: var(--primary); }
</style>

<?php if (empty($slots)): ?>
<div class="home-empty">
  <div class="home-empty-icon">🏗️</div>
  <p style="margin:0 0 16px">管理员尚未配置首页内容</p>
  <a href="square.php" class="btn btn-primary">前往广场浏览帖子</a>
</div>

<?php else:
  $hero   = $slots[0];
  $row2   = array_slice($slots, 1, 2);
  $row3   = array_slice($slots, 3);
  $hero_img = extract_cover_image($hero['content']);
?>

<!-- 主图（全宽 16:9） -->
<a href="pages/post.php?id=<?= $hero['id'] ?>" class="home-hero home-card">
  <?php if ($hero_img): ?>
    <img src="<?= h($hero_img) ?>" class="home-card-img" alt="">
  <?php else: ?>
    <div class="home-card-placeholder"
         style="background:linear-gradient(135deg,<?= h($hero['section_color']) ?>33,<?= h($hero['section_color']) ?>11)">
      🎓
    </div>
  <?php endif; ?>
  <div class="home-card-overlay">
    <span class="home-card-section-tag"><?= h($hero['section_name']) ?></span>
    <h2 class="home-card-title"><?= h($hero['title']) ?></h2>
    <div class="home-card-meta">
      <?= h($hero['username']) ?> &nbsp;·&nbsp; <?= time_ago($hero['created_at']) ?>
      &nbsp;&nbsp; 👁 <?= $hero['views'] ?> &nbsp; 👍 <?= $hero['like_count'] ?>
    </div>
  </div>
</a>

<?php if (!empty($row2)): ?>
<!-- 双栏 -->
<div class="home-grid-2">
  <?php foreach ($row2 as $s):
    $img = extract_cover_image($s['content']); ?>
  <a href="pages/post.php?id=<?= $s['id'] ?>" class="home-card">
    <?php if ($img): ?>
      <img src="<?= h($img) ?>" class="home-card-img" alt="">
    <?php else: ?>
      <div class="home-card-placeholder"
           style="background:linear-gradient(135deg,<?= h($s['section_color']) ?>33,<?= h($s['section_color']) ?>11)">🎓</div>
    <?php endif; ?>
    <div class="home-card-overlay">
      <span class="home-card-section-tag"><?= h($s['section_name']) ?></span>
      <h3 class="home-card-title"><?= h($s['title']) ?></h3>
      <div class="home-card-meta"><?= h($s['username']) ?> · <?= time_ago($s['created_at']) ?></div>
    </div>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($row3)): ?>
<!-- 三栏（含超出部分） -->
<div class="home-grid-3">
  <?php foreach ($row3 as $s):
    $img = extract_cover_image($s['content']); ?>
  <a href="pages/post.php?id=<?= $s['id'] ?>" class="home-card">
    <?php if ($img): ?>
      <img src="<?= h($img) ?>" class="home-card-img" alt="">
    <?php else: ?>
      <div class="home-card-placeholder"
           style="background:linear-gradient(135deg,<?= h($s['section_color']) ?>33,<?= h($s['section_color']) ?>11)">🎓</div>
    <?php endif; ?>
    <div class="home-card-overlay">
      <span class="home-card-section-tag"><?= h($s['section_name']) ?></span>
      <h3 class="home-card-title"><?= h($s['title']) ?></h3>
      <div class="home-card-meta"><?= h($s['username']) ?> · <?= time_ago($s['created_at']) ?></div>
    </div>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<a href="square.php" class="home-more-btn">前往广场，浏览全部帖子 →</a>

<?php include 'includes/footer.php'; ?>
