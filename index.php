<?php
/*
 * index.php — 网站首页
 * 功能：展示 Hero Banner（支持可更换背景大图+视差效果）、编辑推荐帖、
 *       算法个性化推荐（余弦相似度兴趣模型）、各大分区最新帖子列表。
 * 读库：posts / users / sections / homepage_slots / user_interests / site_settings
 * 权限：无需登录，登录后显示个性化推荐
 */
require_once 'config.php';
require_once 'includes/helpers.php';
$page_title = '首页';
if (session_status() === PHP_SESSION_NONE) session_start();
$uid = intval($_SESSION['user_id'] ?? 0);

// 编辑推荐（管理员精选槽位）
$editor_picks = [];
$sr = $conn->query("SELECT hs.position,
    p.id, p.title, p.content, p.created_at, p.views, p.like_count, p.comment_count,
    u.username, u.avatar,
    s.name as section_name, s.color as section_color, s.icon as section_icon
    FROM homepage_slots hs
    JOIN posts p ON p.id=hs.post_id AND p.status='published'
    JOIN users u ON u.id=p.user_id
    JOIN sections s ON s.id=p.section_id
    ORDER BY hs.position ASC LIMIT 6");
if ($sr) while ($r = $sr->fetch_assoc()) $editor_picks[] = $r;

// 算法推荐
$rec_posts = get_recommended_posts($conn, $uid, 6);

// 各分区近期帖子
$sections_data = [];
$sec_r = $conn->query("SELECT id, name, icon, color, slug FROM sections WHERE parent_id=0 ORDER BY sort_order LIMIT 8");
if ($sec_r) while ($sec = $sec_r->fetch_assoc()) {
    $sid = intval($sec['id']);
    $pr = $conn->query("SELECT p.id, p.title, p.created_at, p.views, p.like_count, p.comment_count,
        u.username, s2.name as sub_name, s2.color as sub_color
        FROM posts p
        JOIN users u ON u.id=p.user_id
        JOIN sections s2 ON s2.id=p.section_id
        WHERE s2.parent_id=$sid AND p.status='published'
        ORDER BY p.created_at DESC LIMIT 5");
    $sec['posts'] = [];
    if ($pr) while ($p = $pr->fetch_assoc()) $sec['posts'][] = $p;
    if (!empty($sec['posts'])) $sections_data[] = $sec;
}

// Hero 统计
$total_posts  = (int)($conn->query("SELECT COUNT(*) c FROM posts WHERE status='published'")->fetch_assoc()['c'] ?? 0);
$total_users  = (int)($conn->query("SELECT COUNT(*) c FROM users WHERE is_banned=0")->fetch_assoc()['c'] ?? 0);
$today_posts  = (int)($conn->query("SELECT COUNT(*) c FROM posts WHERE status='published' AND DATE(created_at)=CURDATE()")->fetch_assoc()['c'] ?? 0);

// 从 site_settings 读取 Hero 背景图路径（管理员可在后台上传修改）
$hero_bg = '';
$hbr = $conn->query("SELECT `value` FROM site_settings WHERE `key`='hero_bg' LIMIT 1");
if ($hbr && ($hbr_row = $hbr->fetch_assoc())) $hero_bg = $hbr_row['value'];

include 'includes/header.php';
?>

<style>
/* 防止全宽元素产生横向滚动条 */
body { overflow-x: hidden; }

/* ══ Hero Banner（全宽突破容器）══ */
.home-hero {
    position: relative;
    margin-left:  calc(50% - 50vw);
    margin-right: calc(50% - 50vw);
    width: 100vw;
    background: var(--bg-card);
    padding: 44px 20px;
    margin-bottom: 32px;
    overflow: hidden;
}
.hero-bg-img {
    position: absolute;
    top: 0; left: 0;
    width: 100%; height: 100%;
    object-fit: cover;
    display: none;
    will-change: transform;
    z-index: 0;
    transform: scale(1.14);
    transform-origin: center center;
}
.hero-bg-img.loaded { display: block; }
.home-hero::before {
    content: ''; position: absolute; inset: 0;
    background: rgba(246, 243, 237, 0.62);
    z-index: 1; pointer-events: none;
}
[data-theme="dark"] .home-hero::before {
    background: rgba(15, 15, 20, 0.65);
}
.home-hero::after { display: none; }
.home-hero-inner {
    max-width: 1200px; margin: 0 auto;
    display: flex; align-items: center; gap: 40px;
    position: relative; z-index: 2;
}
.hero-name-card {
    background: rgba(255,255,255,.72);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 28px 32px;
    min-width: 260px; max-width: 360px;
    flex-shrink: 0;
}
[data-theme="dark"] .hero-name-card {
    background: rgba(20, 20, 28, 0.72);
}
.hero-name-card h1 {
    font-size: 22px; font-weight: 800;
    color: var(--txt); margin: 0 0 8px; line-height: 1.3;
}
.hero-name-card p {
    font-size: 13px; color: var(--txt-2);
    margin: 0 0 16px; line-height: 1.7;
}
.hero-btn-row { display: flex; gap: 10px; flex-wrap: wrap; }
.hero-btn {
    padding: 7px 18px; border-radius: 20px;
    font-size: 13px; font-weight: 700;
    text-decoration: none; transition: opacity .15s;
}
.hero-btn:hover { opacity: .85; }
.hero-btn-solid { background: var(--txt); color: var(--bg-card); }
.hero-btn-outline {
    background: transparent;
    color: var(--txt); border: 1px solid var(--border);
}
.hero-stats {
    flex: 1; display: flex; gap: 32px;
    justify-content: flex-end; align-items: center;
    flex-wrap: wrap;
}
.hero-stat { text-align: center; }
.hero-stat-num { font-size: 30px; font-weight: 800; color: var(--txt); line-height: 1; }
.hero-stat-lbl { font-size: 12px; color: var(--txt-2); margin-top: 5px; }

/* ══ 中部 2-col ══ */
.home-mid {
    display: grid;
    grid-template-columns: 2fr 3fr;
    gap: 22px;
    margin-bottom: 32px;
    align-items: start;
}
.home-block-hd {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 14px; padding-bottom: 10px;
    border-bottom: 2px solid var(--primary);
}
.home-block-hd-left  { display: flex; align-items: center; gap: 8px; }
.home-block-hd-title { font-size: 16px; font-weight: 700; }
.home-block-hd-sub   { font-size: 12px; color: var(--txt-2); }
.home-block-hd-more  { font-size: 12px; color: var(--txt-3); text-decoration: none; }
.home-block-hd-more:hover { color: var(--primary); }

.ep-list { display: flex; flex-direction: column; }
.ep-item {
    display: flex; align-items: flex-start; gap: 11px;
    padding: 11px 0; border-bottom: 1px solid var(--border);
    text-decoration: none; color: var(--txt); transition: none;
}
.ep-item:last-child { border-bottom: none; }
.ep-item:hover .ep-title { color: var(--primary); }
.ep-rank {
    width: 22px; height: 22px; border-radius: 5px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 700;
    background: var(--bg-2); color: var(--txt-3);
}
.ep-rank.top1 { background: #ef4444; color: #fff; }
.ep-rank.top2 { background: #f97316; color: #fff; }
.ep-rank.top3 { background: #f59e0b; color: #fff; }
.ep-thumb {
    width: 62px; height: 46px; border-radius: 6px; flex-shrink: 0;
    overflow: hidden; background: var(--bg-2);
    display: flex; align-items: center; justify-content: center; font-size: 18px;
}
.ep-thumb img { width: 100%; height: 100%; object-fit: cover; }
.ep-info { flex: 1; min-width: 0; }
.ep-tag {
    display: inline-block; font-size: 10px; font-weight: 700;
    padding: 1px 6px; border-radius: 3px; color: #fff; margin-bottom: 3px;
}
.ep-title {
    font-size: 13px; font-weight: 600; line-height: 1.4;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    transition: color .15s;
}
.ep-meta { font-size: 11px; color: var(--txt-3); margin-top: 4px; }

.rec-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}
.rec-card {
    display: block; text-decoration: none; color: var(--txt);
    border-radius: var(--r-lg); overflow: hidden;
    background: var(--bg-card); border: 1px solid var(--border);
    transition: transform .18s, box-shadow .18s;
}
.rec-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
.rec-thumb {
    aspect-ratio: 16/9; position: relative; overflow: hidden;
    background: var(--bg-2);
    display: flex; align-items: center; justify-content: center; font-size: 28px; opacity: .5;
}
.rec-thumb img {
    position: absolute; inset: 0; width: 100%; height: 100%;
    object-fit: cover; opacity: 1;
}
.rec-body { padding: 10px 12px 12px; }
.rec-tag {
    display: inline-block; font-size: 10px; font-weight: 700;
    padding: 1px 6px; border-radius: 3px; color: #fff; margin-bottom: 5px;
}
.rec-title {
    font-size: 13px; font-weight: 600; line-height: 1.4; margin-bottom: 5px;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.rec-meta { font-size: 11px; color: var(--txt-3); display: flex; gap: 8px; }

/* ══ 各分区展示 ══ */
.home-sec-block { margin-bottom: 28px; }
.home-sec-hd {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 12px; padding-bottom: 10px;
    border-bottom: 2px solid var(--border);
}
.home-sec-hd-title {
    display: flex; align-items: center; gap: 8px;
    font-size: 15px; font-weight: 700;
}
.home-sec-hd-more { font-size: 12px; color: var(--txt-3); text-decoration: none; }
.home-sec-hd-more:hover { color: var(--primary); }
.home-sec-posts {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 10px;
}
.sec-post-card {
    display: block; text-decoration: none; color: var(--txt);
    border-radius: var(--r); padding: 11px 12px;
    background: var(--bg-card); border: 1px solid var(--border);
    transition: box-shadow .15s, transform .15s;
}
.sec-post-card:hover { box-shadow: var(--shadow-md); transform: translateY(-1px); }
.sec-post-card:hover .sec-post-title { color: var(--primary); }
.sec-sub-label {
    display: inline-block; font-size: 10px; font-weight: 700;
    padding: 1px 6px; border-radius: 3px; color: #fff; margin-bottom: 5px;
}
.sec-post-title {
    font-size: 13px; font-weight: 600; line-height: 1.45; margin-bottom: 6px;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    transition: color .15s;
}
.sec-post-meta { font-size: 11px; color: var(--txt-3); display: flex; gap: 8px; flex-wrap: wrap; }

.home-empty-state {
    text-align: center; padding: 40px 0; color: var(--txt-3);
}
.home-empty-state .icon { font-size: 36px; margin-bottom: 8px; }

@media (max-width: 1024px) {
    .home-sec-posts { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 768px) {
    .home-mid { grid-template-columns: 1fr; }
    .hero-stats { justify-content: center; gap: 24px; }
    .home-sec-posts { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 520px) {
    .rec-grid { grid-template-columns: 1fr; }
}
/* ── Editor Picks Carousel ── */
.ep-carousel { position: relative; border-radius: var(--r-lg); overflow: hidden; background: var(--bg-2); }
.ep-slides { display: flex; will-change: transform; transition: transform .4s cubic-bezier(.4,0,.2,1); }
.ep-slide { min-width: 100%; text-decoration: none; display: block; }
.ep-slide-img {
    aspect-ratio: 16/9; position: relative;
    background: var(--bg-2) center/cover no-repeat;
    display: flex; align-items: flex-end;
}
.ep-slide-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(to top, rgba(0,0,0,.78) 0%, rgba(0,0,0,.08) 55%, transparent 100%);
}
.ep-slide-info { position: relative; z-index: 1; padding: 14px 16px; width: 100%; box-sizing: border-box; }
.ep-slide-tag {
    display: inline-block; font-size: 10px; font-weight: 700;
    padding: 1px 7px; border-radius: 3px; color: #fff; margin-bottom: 6px;
}
.ep-slide-title { color: #fff; font-size: 15px; font-weight: 700; line-height: 1.4; margin-bottom: 5px; }
.ep-slide-meta { color: rgba(255,255,255,.6); font-size: 11px; display: flex; gap: 10px; align-items: center; }
.ep-btn {
    position: absolute; top: calc(50% - 16px); transform: translateY(-50%);
    width: 30px; height: 30px; border-radius: 50%;
    background: rgba(0,0,0,.38); backdrop-filter: blur(4px);
    border: none; color: #fff; cursor: pointer; z-index: 10;
    display: flex; align-items: center; justify-content: center;
    transition: background .2s; padding: 0;
}
.ep-btn:hover { background: rgba(0,0,0,.62); }
.ep-prev { left: 8px; }
.ep-next { right: 8px; }
.ep-dots { position: absolute; bottom: 9px; right: 12px; display: flex; gap: 5px; z-index: 10; }
.ep-dot { width: 6px; height: 6px; border-radius: 3px; background: rgba(255,255,255,.32); cursor: pointer; transition: all .25s; border: none; padding: 0; }
.ep-dot.on { background: #fff; width: 16px; }
</style>

<!-- ══ Hero Banner ══ -->
<div class="home-hero" id="home-hero">
  <?php if ($hero_bg): ?>
  <img class="hero-bg-img" id="hero-bg-img"
       src="<?= h($hero_bg) ?>" alt=""
       onerror="this.style.display='none'"
       onload="this.classList.add('loaded')">
  <?php endif; ?>
  <div class="home-hero-inner">

    <div class="hero-name-card">
      <h1><i data-lucide="graduation-cap" class="lucide"></i> <?= h(SITE_NAME) ?></h1>
      <p>高校学生的智慧交流平台<br>分享知识 · 互助成长 · 探索未来</p>
      <div class="hero-btn-row">
        <?php if (!$uid): ?>
          <a href="pages/register.php" class="hero-btn hero-btn-solid">立即注册</a>
          <a href="pages/login.php"    class="hero-btn hero-btn-outline">登录</a>
        <?php else: ?>
          <a href="pages/publish.php"  class="hero-btn hero-btn-solid"><i data-lucide="pencil" class="lucide"></i> 发帖</a>
          <a href="square.php"         class="hero-btn hero-btn-outline">浏览广场</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="hero-stats">
      <div class="hero-stat">
        <div class="hero-stat-num"><?= number_format($total_posts) ?></div>
        <div class="hero-stat-lbl"><i data-lucide="file-text" class="lucide"></i> 帖子总数</div>
      </div>
      <div class="hero-stat">
        <div class="hero-stat-num"><?= number_format($total_users) ?></div>
        <div class="hero-stat-lbl"><i data-lucide="users" class="lucide"></i> 注册用户</div>
      </div>
      <div class="hero-stat">
        <div class="hero-stat-num"><?= number_format($today_posts) ?></div>
        <div class="hero-stat-lbl"><i data-lucide="sunrise" class="lucide"></i> 今日新帖</div>
      </div>
    </div>

  </div>
</div>

<!-- ══ 中部：编辑推荐 + 算法推荐 ══ -->
<div class="home-mid">

  <!-- 编辑推荐（左） -->
  <div>
    <div class="home-block-hd">
      <div class="home-block-hd-left">
        <span style="font-size:18px"><i data-lucide="sparkles" class="lucide"></i></span>
        <span class="home-block-hd-title">编辑推荐</span>
      </div>
      <?php if (!empty($_SESSION['role']) && in_array($_SESSION['role'],['admin','owner'])): ?>
        <a href="admin/homepage.php" class="home-block-hd-more">管理配置 →</a>
      <?php else: ?>
        <a href="square.php" class="home-block-hd-more">更多 →</a>
      <?php endif; ?>
    </div>

    <?php if (!empty($editor_picks)): ?>
    <div class="ep-carousel" id="epCarousel">
      <div class="ep-slides" id="epSlides">
        <?php foreach ($editor_picks as $i => $ep):
          $thumb = extract_cover_image($ep['content']);
          $bg = $thumb
            ? "background-image:url('" . h($thumb) . "')"
            : "background:linear-gradient(135deg," . h($ep['section_color']) . "55," . h($ep['section_color']) . "22)";
        ?>
        <a href="pages/post.php?id=<?= $ep['id'] ?>" class="ep-slide">
          <div class="ep-slide-img" style="<?= $bg ?>">
            <?php if (!$thumb): ?>
              <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;opacity:.22">
                <?= render_icon($ep['section_icon'] ?? '', 'lucide', 52) ?>
              </div>
            <?php endif; ?>
            <div class="ep-slide-overlay"></div>
            <div class="ep-slide-info">
              <span class="ep-slide-tag" style="background:<?= h($ep['section_color']) ?>"><?= h($ep['section_name']) ?></span>
              <div class="ep-slide-title"><?= h($ep['title']) ?></div>
              <div class="ep-slide-meta">
                <span><?= h($ep['username']) ?></span>
                <span><i data-lucide="thumbs-up" class="lucide"></i> <?= $ep['like_count'] ?></span>
                <span><i data-lucide="eye" class="lucide"></i> <?= $ep['views'] ?></span>
              </div>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
      <?php if (count($editor_picks) > 1): ?>
      <button class="ep-btn ep-prev" id="epPrev"><i data-lucide="chevron-left" class="lucide"></i></button>
      <button class="ep-btn ep-next" id="epNext"><i data-lucide="chevron-right" class="lucide"></i></button>
      <div class="ep-dots" id="epDots">
        <?php foreach ($editor_picks as $i => $_): ?>
          <button class="ep-dot <?= $i===0?'on':'' ?>"></button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="home-empty-state">
      <div class="icon"><i data-lucide="clipboard-list" class="lucide"></i></div>
      <p style="font-size:13px;margin:0">管理员尚未配置推荐内容</p>
    </div>
    <?php endif; ?>
  </div>

  <!-- 算法推荐（右） -->
  <div>
    <div class="home-block-hd">
      <div class="home-block-hd-left">
        <span style="font-size:18px"><i data-lucide="heart" class="lucide"></i></span>
        <span class="home-block-hd-title">猜你喜欢</span>
        <span class="home-block-hd-sub"><?= $uid ? '基于你的兴趣' : '热度排行' ?></span>
      </div>
      <a href="square.php" class="home-block-hd-more">查看全部 →</a>
    </div>

    <?php if (!empty($rec_posts)): ?>
    <div class="rec-grid">
      <?php foreach ($rec_posts as $p):
        $thumb = extract_cover_image($p['content']); ?>
      <a href="pages/post.php?id=<?= $p['id'] ?>" class="rec-card">
        <div class="rec-thumb"
             style="background:linear-gradient(135deg,<?= h($p['section_color']) ?>22,<?= h($p['section_color']) ?>08)">
          <?php if ($thumb): ?><img src="<?= h($thumb) ?>" alt=""><?php else: ?><i data-lucide="file" class="lucide"></i><?php endif; ?>
        </div>
        <div class="rec-body">
          <span class="rec-tag" style="background:<?= h($p['section_color']) ?>"><?= h($p['section_name']) ?></span>
          <div class="rec-title"><?= h($p['title']) ?></div>
          <div class="rec-meta">
            <span><?= h($p['username']) ?></span>
            <span><i data-lucide="thumbs-up" class="lucide"></i> <?= $p['like_count'] ?></span>
            <span><i data-lucide="message-circle" class="lucide"></i> <?= $p['comment_count'] ?></span>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="home-empty-state">
      <div class="icon"><i data-lucide="inbox" class="lucide"></i></div>
      <p style="font-size:13px;margin:0">暂无推荐内容</p>
    </div>
    <?php endif; ?>
  </div>

</div>

<!-- ══ 各分区帖子展示 ══ -->
<?php foreach ($sections_data as $sec): ?>
<div class="home-sec-block">
  <div class="home-sec-hd">
    <div class="home-sec-hd-title">
      <span><?= render_icon($sec['icon'] ?? '', 'lucide', 20) ?></span>
      <span><?= h($sec['name']) ?></span>
    </div>
    <a href="pages/section.php?slug=<?= h($sec['slug'] ?? '') ?>" class="home-sec-hd-more">查看更多 →</a>
  </div>
  <div class="home-sec-posts">
    <?php foreach ($sec['posts'] as $p): ?>
    <a href="pages/post.php?id=<?= $p['id'] ?>" class="sec-post-card">
      <span class="sec-sub-label" style="background:<?= h($p['sub_color'] ?? $sec['color']) ?>"><?= h($p['sub_name']) ?></span>
      <div class="sec-post-title"><?= h($p['title']) ?></div>
      <div class="sec-post-meta">
        <span><?= h($p['username']) ?></span>
        <span><i data-lucide="eye" class="lucide"></i> <?= $p['views'] ?></span>
        <span><i data-lucide="thumbs-up" class="lucide"></i> <?= $p['like_count'] ?></span>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

<?php if (empty($sections_data)): ?>
<div class="home-empty-state" style="padding:60px 0">
  <div class="icon"><i data-lucide="hammer" class="lucide"></i></div>
  <p style="font-size:14px;margin:0 0 14px;color:var(--txt-2)">暂无帖子，快来发第一帖</p>
  <a href="pages/publish.php" class="btn btn-primary">立即发帖</a>
</div>
<?php endif; ?>

<script>
/* Hero 背景图视差效果 */
(function() {
    var hero = document.getElementById('home-hero');
    var img  = document.getElementById('hero-bg-img');
    if (!hero || !img) return;
    var targetX = 0, curX = 0, curY = 0, rafId = null;
    function lerp(a, b, t) { return a + (b - a) * t; }
    function tick() {
        curX = lerp(curX, targetX, 0.07);
        img.style.transform = 'scale(1.14) translateX(' + curX.toFixed(2) + 'px) translateY(' + curY.toFixed(2) + 'px)';
        if (Math.abs(curX - targetX) > 0.05) rafId = requestAnimationFrame(tick);
        else rafId = null;
    }
    function startTick() { if (!rafId) rafId = requestAnimationFrame(tick); }
    hero.addEventListener('mousemove', function(e) {
        var rect = hero.getBoundingClientRect();
        targetX = ((e.clientX - rect.left - rect.width / 2) / rect.width) * -24;
        startTick();
    });
    hero.addEventListener('mouseleave', function() { targetX = 0; startTick(); });
    window.addEventListener('scroll', function() {
        curY = window.scrollY * 0.35;
        img.style.transform = 'scale(1.14) translateX(' + curX.toFixed(2) + 'px) translateY(' + curY.toFixed(2) + 'px)';
    }, { passive: true });
})();
</script>

<script>
/* Editor Picks Carousel */
(function() {
    var slides = document.getElementById('epSlides');
    if (!slides || slides.children.length <= 1) return;
    var total = slides.children.length;
    var cur = 0;
    var dots = document.querySelectorAll('#epDots .ep-dot');
    function go(n) {
        cur = (n + total) % total;
        slides.style.transform = 'translateX(-' + (cur * 100) + '%)';
        dots.forEach(function(d, i) { d.classList.toggle('on', i === cur); });
    }
    document.getElementById('epPrev').addEventListener('click', function(e) { e.preventDefault(); go(cur - 1); });
    document.getElementById('epNext').addEventListener('click', function(e) { e.preventDefault(); go(cur + 1); });
    dots.forEach(function(d, i) { d.addEventListener('click', function() { go(i); }); });
    var timer = setInterval(function() { go(cur + 1); }, 5000);
    var car = document.getElementById('epCarousel');
    car.addEventListener('mouseenter', function() { clearInterval(timer); });
    car.addEventListener('mouseleave', function() { timer = setInterval(function() { go(cur + 1); }, 5000); });
})();
</script>

<?php include 'includes/footer.php'; ?>
