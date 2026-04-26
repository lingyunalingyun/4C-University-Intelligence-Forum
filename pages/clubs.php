<?php
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$tab = $_GET['tab'] ?? 'feed';

// 获取当前用户学校
$uid = intval($_SESSION['user_id'] ?? 0);
$user_school = '';
if ($uid) {
    $ur = $conn->query("SELECT school FROM users WHERE id=$uid");
    if ($ur) $user_school = $ur->fetch_assoc()['school'] ?? '';
}
$school_esc = $conn->real_escape_string($user_school);
$school_where = $user_school ? "c.school='$school_esc'" : "1=1";

// 社团动态
$feed_posts = [];
if ($tab === 'feed') {
    $pr = $conn->query("SELECT cp.*, c.name AS club_name, c.avatar AS club_avatar, c.id AS club_id
        FROM club_posts cp
        JOIN clubs c ON c.id=cp.club_id AND c.status='active'
        JOIN users u ON u.id=cp.user_id
        WHERE $school_where
        ORDER BY cp.created_at DESC LIMIT 60");
    if ($pr) while ($r = $pr->fetch_assoc()) $feed_posts[] = $r;
}

// 社团列表
$clubs = [];
if ($tab === 'clubs') {
    $cr = $conn->query("SELECT c.*, u.username AS president_name
        FROM clubs c JOIN users u ON u.id=c.president_id
        WHERE $school_where AND c.status='active'
        ORDER BY c.created_at DESC");
    if ($cr) while ($r = $cr->fetch_assoc()) $clubs[] = $r;
}

$page_title = '社团';
include '../includes/header.php';
?>

<style>
.club-card-wrap {
    position:relative; border-radius:12px; overflow:hidden;
    aspect-ratio:16/9; display:block; text-decoration:none;
    box-shadow:var(--shadow-md); transition:transform .2s,box-shadow .2s;
}
.club-card-wrap:hover { transform:translateY(-3px); box-shadow:var(--shadow-lg); }
.club-card-bg {
    position:absolute;inset:0;width:100%;height:100%;
    object-fit:cover; transition:transform .3s;
}
.club-card-wrap:hover .club-card-bg { transform:scale(1.04); }
.club-card-overlay {
    position:absolute;inset:0;
    background:linear-gradient(to top, rgba(0,0,0,.75) 0%, rgba(0,0,0,.2) 55%, transparent 100%);
}
.club-card-body {
    position:absolute;bottom:0;left:0;right:0;
    padding:14px 16px;
}
.club-card-name { font-size:17px;font-weight:700;color:#fff;margin-bottom:4px;line-height:1.3; }
.club-card-meta { font-size:12px;color:rgba(255,255,255,.8);display:flex;gap:10px;flex-wrap:wrap; }
.club-card-avatar {
    position:absolute;top:12px;left:14px;
    width:40px;height:40px;border-radius:50%;
    object-fit:cover;border:2px solid rgba(255,255,255,.6);
}
.club-card-avatar-txt {
    position:absolute;top:12px;left:14px;
    width:40px;height:40px;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    font-size:18px;font-weight:700;color:#fff;
    background:rgba(255,255,255,.2);border:2px solid rgba(255,255,255,.5);
    backdrop-filter:blur(4px);
}
.feed-post-card { padding:16px 0; border-bottom:1px solid var(--border); }
.feed-post-card:last-child { border-bottom:none; }
</style>

<!-- 顶部栏 -->
<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap">
  <h2 style="margin:0">🏛️ 社团</h2>
  <?php if ($user_school): ?>
    <span style="font-size:13px;color:var(--txt-2)">· <?= h($user_school) ?></span>
  <?php endif; ?>
  <?php if ($uid): ?>
    <div style="margin-left:auto;display:flex;gap:8px">
      <a href="my_clubs.php"    class="btn btn-outline btn-sm">我的社团</a>
      <a href="club_apply.php"  class="btn btn-primary btn-sm">+ 申请创建</a>
    </div>
  <?php else: ?>
    <a href="login.php" class="btn btn-outline btn-sm" style="margin-left:auto">登录查看本校社团</a>
  <?php endif; ?>
</div>

<!-- Tab 导航 -->
<div style="display:flex;gap:4px;margin-bottom:20px">
  <a href="clubs.php?tab=feed"
     style="padding:7px 20px;border-radius:20px;font-size:13px;font-weight:500;text-decoration:none;
            background:<?= $tab==='feed'?'var(--primary)':'var(--bg-card)' ?>;
            color:<?= $tab==='feed'?'#fff':'var(--txt-2)' ?>;
            border:1.5px solid <?= $tab==='feed'?'var(--primary)':'var(--border)' ?>">
    📢 社团动态
  </a>
  <a href="clubs.php?tab=clubs"
     style="padding:7px 20px;border-radius:20px;font-size:13px;font-weight:500;text-decoration:none;
            background:<?= $tab==='clubs'?'var(--primary)':'var(--bg-card)' ?>;
            color:<?= $tab==='clubs'?'#fff':'var(--txt-2)' ?>;
            border:1.5px solid <?= $tab==='clubs'?'var(--primary)':'var(--border)' ?>">
    🏛️ 社团详细
  </a>
</div>

<!-- ═══ 社团动态 ═══ -->
<?php if ($tab === 'feed'): ?>

<?php if (empty($feed_posts)): ?>
  <div class="empty-state">
    <div class="icon">📢</div>
    <p><?= $user_school ? '本校暂无社团动态' : '暂无社团动态' ?></p>
    <?php if (!$uid): ?><a href="login.php" class="btn btn-primary btn-sm">登录查看本校动态</a><?php endif; ?>
  </div>
<?php else: ?>
  <div class="card"><div class="card-body" style="padding:0 16px">
    <?php foreach ($feed_posts as $cp): ?>
    <div class="feed-post-card">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
        <a href="club.php?id=<?= $cp['club_id'] ?>">
          <?php if (!empty($cp['club_avatar'])): ?>
            <img src="../uploads/clubs/<?= h($cp['club_avatar']) ?>"
                 style="width:38px;height:38px;border-radius:50%;object-fit:cover;border:1.5px solid var(--border)">
          <?php else: ?>
            <div style="width:38px;height:38px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;color:#fff">
              <?= mb_substr($cp['club_name'],0,1) ?>
            </div>
          <?php endif; ?>
        </a>
        <div style="flex:1;min-width:0">
          <a href="club.php?id=<?= $cp['club_id'] ?>" style="font-size:13px;font-weight:600;color:var(--txt)">
            <?= h($cp['club_name']) ?>
          </a>
          <div style="font-size:11px;color:var(--txt-3)"><?= time_ago($cp['created_at']) ?></div>
        </div>
      </div>
      <a href="club.php?id=<?= $cp['club_id'] ?>&tab=posts" style="color:var(--txt)">
        <div style="font-size:15px;font-weight:600;margin-bottom:4px"><?= h($cp['title']) ?></div>
        <div style="font-size:13px;color:var(--txt-2);line-height:1.6;overflow:hidden;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical">
          <?= h($cp['content']) ?>
        </div>
      </a>
    </div>
    <?php endforeach; ?>
  </div></div>
<?php endif; ?>

<!-- ═══ 社团详细（16:9 卡片） ═══ -->
<?php elseif ($tab === 'clubs'): ?>

<?php if (empty($clubs)): ?>
  <div class="empty-state">
    <div class="icon">🏛️</div>
    <p><?= $user_school ? '本校暂无社团' : '暂无社团' ?></p>
    <?php if ($uid): ?><a href="club_apply.php" class="btn btn-primary btn-sm">申请创建</a><?php endif; ?>
  </div>
<?php else: ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px">
    <?php foreach ($clubs as $c):
        // 背景：banner > avatar（拉伸填充） > null（用渐变色占位）
        $bg_url = '';
        if (!empty($c['banner']))       $bg_url = '../uploads/clubs/' . $c['banner'];
        elseif (!empty($c['avatar']))   $bg_url = '../uploads/clubs/' . $c['avatar'];
    ?>
    <a href="club.php?id=<?= $c['id'] ?>" class="club-card-wrap">
      <?php if ($bg_url): ?>
        <img src="<?= h($bg_url) ?>" class="club-card-bg" alt="">
      <?php else: ?>
        <!-- 无图时用渐变色背景 -->
        <div class="club-card-bg" style="background:linear-gradient(135deg,var(--primary) 0%,var(--primary-dk) 100%)"></div>
      <?php endif; ?>

      <div class="club-card-overlay"></div>

      <!-- 小头像 -->
      <?php if (!empty($c['avatar'])): ?>
        <img src="../uploads/clubs/<?= h($c['avatar']) ?>" class="club-card-avatar" alt="">
      <?php else: ?>
        <div class="club-card-avatar-txt"><?= mb_substr($c['name'],0,1) ?></div>
      <?php endif; ?>

      <div class="club-card-body">
        <div class="club-card-name"><?= h($c['name']) ?></div>
        <div class="club-card-meta">
          <span>🏫 <?= h($c['school']) ?></span>
          <span>👥 <?= $c['member_count'] ?> 人</span>
          <span>👑 <?= h($c['president_name']) ?></span>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php endif; ?>

<?php include '../includes/footer.php'; ?>
