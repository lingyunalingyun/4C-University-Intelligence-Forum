<?php
/*
 * pages/profile.php — 用户主页
 * 功能：展示用户资料、SCID、等级/经验条、角色徽章，
 *       我的帖子 / 收藏夹 / 点赞夹 Tab，关注/粉丝数（弹窗列表），
 *       关注、私信操作，黑名单（拉黑/被拉黑互相隐藏）。
 * 读库：users / posts / follows / post_favs / post_likes / user_blocks
 * 权限：无需登录（仅自己可见收藏夹/点赞夹）
 */
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$profile_id = intval($_GET['id'] ?? 0);
if (!$profile_id) { header('Location: ../index.php'); exit; }

$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param('i', $profile_id); $stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$profile) { header('Location: ../index.php'); exit; }

$uid = intval($_SESSION['user_id'] ?? 0);
$tab = $_GET['tab'] ?? 'posts';

// 关注状态
$is_following = false;
if ($uid && $uid !== $profile_id) {
    $fr = $conn->query("SELECT 1 FROM follows WHERE follower_id=$uid AND following_id=$profile_id");
    $is_following = $fr && $fr->num_rows > 0;
}

// 统计
$post_count = (int)$conn->query("SELECT COUNT(*) as c FROM posts WHERE user_id=$profile_id AND status='published'")->fetch_assoc()['c'];
$fav_count  = (int)$conn->query("SELECT COUNT(*) as c FROM post_favs WHERE user_id=$profile_id")->fetch_assoc()['c'];
$follower_count  = (int)$conn->query("SELECT COUNT(*) as c FROM follows WHERE following_id=$profile_id")->fetch_assoc()['c'];
$following_count = (int)$conn->query("SELECT COUNT(*) as c FROM follows WHERE follower_id=$profile_id")->fetch_assoc()['c'];

// 内容列表
$items = [];
if ($tab === 'posts') {
    $r = $conn->query("SELECT p.*,u.username,u.avatar,s.name as section_name
        FROM posts p JOIN users u ON u.id=p.user_id JOIN sections s ON s.id=p.section_id
        WHERE p.user_id=$profile_id AND p.status='published'
        ORDER BY p.created_at DESC LIMIT 30");
    if ($r) while ($row = $r->fetch_assoc()) $items[] = $row;
} elseif ($tab === 'favs') {
    $r = $conn->query("SELECT p.*,u.username,u.avatar,s.name as section_name
        FROM post_favs f JOIN posts p ON p.id=f.post_id JOIN users u ON u.id=p.user_id JOIN sections s ON s.id=p.section_id
        WHERE f.user_id=$profile_id AND p.status='published'
        ORDER BY f.created_at DESC LIMIT 30");
    if ($r) while ($row = $r->fetch_assoc()) $items[] = $row;
} elseif ($tab === 'following') {
    $r = $conn->query("SELECT u.id,u.username,u.avatar,u.school,u.exp,u.role
        FROM follows f JOIN users u ON u.id=f.following_id
        WHERE f.follower_id=$profile_id ORDER BY f.created_at DESC LIMIT 50");
    if ($r) while ($row = $r->fetch_assoc()) $items[] = $row;
} elseif ($tab === 'followers') {
    $r = $conn->query("SELECT u.id,u.username,u.avatar,u.school,u.exp,u.role
        FROM follows f JOIN users u ON u.id=f.follower_id
        WHERE f.following_id=$profile_id ORDER BY f.created_at DESC LIMIT 50");
    if ($r) while ($row = $r->fetch_assoc()) $items[] = $row;
}

// 社团信息
$profile_club = null;
$pcr = $conn->query("SELECT c.id, c.name, c.avatar, c.school, cm.role
    FROM club_members cm JOIN clubs c ON c.id=cm.club_id
    WHERE cm.user_id=$profile_id AND c.status='active' LIMIT 1");
if ($pcr) $profile_club = $pcr->fetch_assoc();

$level = get_level($profile['exp']);
$page_title = $profile['username'] . ' 的主页';
include '../includes/header.php';
?>

<div class="flex-center gap-8 mb-16" style="font-size:13px;color:var(--txt-2)">
  <a href="../index.php">首页</a> &rsaquo; <span>用户主页</span>
</div>

<!-- 用户信息卡 -->
<div class="card mb-20" style="padding:24px">
  <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
    <img src="<?= avatar_url($profile['avatar'], '../') ?>"
         style="width:80px;height:80px;border-radius:50%;object-fit:cover;flex-shrink:0">
    <div style="flex:1;min-width:200px">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px">
        <span style="font-size:22px;font-weight:700"><?= h($profile['username']) ?></span>
        <?= role_badge($profile['role']) ?>
        <?= level_badge($profile['exp']) ?>
      </div>
      <?php if ($profile['school']): ?>
        <div style="font-size:13px;color:var(--txt-2);margin-bottom:4px">🏫 <?= h($profile['school']) ?></div>
      <?php endif; ?>
      <?php if ($profile_club): ?>
        <?php
          $club_role_label = ['president'=>'社长','vice_president'=>'副社长','member'=>'成员'];
          $club_role_color = ['president'=>'linear-gradient(135deg,#f59e0b,#d97706)','vice_president'=>'linear-gradient(135deg,#8b5cf6,#6d28d9)','member'=>'linear-gradient(135deg,#3b82f6,#1d4ed8)'];
          $cr = $profile_club['role'];
          $club_av = !empty($profile_club['avatar']) ? '../uploads/clubs/' . h($profile_club['avatar']) : null;
        ?>
        <a href="club.php?id=<?= $profile_club['id'] ?>" style="display:inline-flex;align-items:center;gap:8px;margin-bottom:6px;text-decoration:none;
            background:<?= $club_role_color[$cr] ?>;border-radius:20px;padding:4px 12px 4px 5px;
            box-shadow:0 2px 8px rgba(0,0,0,.15);transition:opacity .2s" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
          <?php if ($club_av): ?>
            <img src="<?= $club_av ?>" style="width:22px;height:22px;border-radius:50%;object-fit:cover;border:1.5px solid rgba(255,255,255,.5)">
          <?php else: ?>
            <div style="width:22px;height:22px;border-radius:50%;background:rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff">
              <?= mb_substr($profile_club['name'],0,1) ?>
            </div>
          <?php endif; ?>
          <span style="font-size:12px;font-weight:600;color:#fff;line-height:1">
            <?= h($profile_club['name']) ?>
          </span>
          <span style="font-size:10px;color:rgba(255,255,255,.8);background:rgba(0,0,0,.15);border-radius:10px;padding:1px 6px;line-height:1.4">
            <?= $club_role_label[$cr] ?>
          </span>
        </a>
      <?php endif; ?>
      <?php if ($profile['bio']): ?>
        <div style="font-size:14px;color:var(--txt-2);margin-bottom:8px"><?= h($profile['bio']) ?></div>
      <?php endif; ?>
      <div style="font-size:12px;color:var(--txt-3)">注册于 <?= date('Y-m-d', strtotime($profile['created_at'])) ?>
        <?php if ($profile['scid']): ?>
          &nbsp;·&nbsp; <span style="font-family:monospace;letter-spacing:1px;color:var(--txt-3)">SCID: <?= h($profile['scid']) ?></span>
        <?php endif; ?>
      </div>
    </div>

    <div style="display:flex;gap:24px;text-align:center;align-items:center">
      <div><div style="font-size:20px;font-weight:700"><?= $post_count ?></div><div style="font-size:12px;color:var(--txt-2)">帖子</div></div>
      <div><div style="font-size:20px;font-weight:700"><?= $follower_count ?></div><div style="font-size:12px;color:var(--txt-2)">粉丝</div></div>
      <div><div style="font-size:20px;font-weight:700"><?= $following_count ?></div><div style="font-size:12px;color:var(--txt-2)">关注</div></div>
      <?php if ($uid && $uid !== $profile_id): ?>
        <button id="follow-btn" class="btn <?= $is_following?'btn-primary':'btn-outline' ?> btn-sm"
                onclick="toggleFollow(<?= $profile_id ?>)">
          <?= $is_following ? '✓ 已关注' : '+ 关注' ?>
        </button>
      <?php elseif ($uid === $profile_id): ?>
        <a href="settings.php" class="btn btn-outline btn-sm">⚙️ 设置</a>
        <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'owner'): ?>
          <a href="../admin/index.php" class="btn btn-outline btn-sm">🛠 后台</a>
        <?php endif; ?>
        <a href="logout.php" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger)"
           onclick="return confirm('确定退出登录？')">退出</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- 经验条 -->
  <?php
    $lv_num   = get_level($profile['exp']);
    $lv_floor = [1=>0, 2=>1000, 3=>5000, 4=>15000, 5=>30000, 6=>50000];
    $lv_names = [1=>'新手', 2=>'学徒', 3=>'达人', 4=>'精英', 5=>'大神', 6=>'传说'];
    $cur_floor = $lv_floor[$lv_num];
    $next_exp  = level_next($profile['exp']);
    $cur_rel   = $profile['exp'] - $cur_floor;
    $needed    = $next_exp - $cur_floor;
    $pct       = $needed > 0 ? min(100, round($cur_rel / $needed * 100)) : 100;
  ?>
  <div style="margin-top:16px">
    <div style="font-size:12px;color:var(--txt-2);margin-bottom:4px">
      <?= $lv_names[$lv_num] ?> · <?= $profile['exp'] ?> EXP
      <?php if ($lv_num < 6): ?> · 距 <?= $lv_names[$lv_num+1] ?> 还需 <?= $needed - $cur_rel ?> EXP<?php endif; ?>
    </div>
    <div style="background:var(--bg-2);border-radius:99px;height:6px;overflow:hidden">
      <div style="width:<?= $pct ?>%;background:var(--primary);height:100%;border-radius:99px;transition:.3s"></div>
    </div>
  </div>
</div>

<!-- 标签页 -->
<div class="tabs mb-16">
  <?php foreach (['posts'=>"帖子 ($post_count)",'favs'=>"收藏 ($fav_count)",'following'=>"关注 ($following_count)",'followers'=>"粉丝 ($follower_count)"] as $k=>$v): ?>
    <a href="profile.php?id=<?= $profile_id ?>&tab=<?= $k ?>"
       class="tab <?= $tab===$k?'active':'' ?>"><?= $v ?></a>
  <?php endforeach; ?>
</div>

<!-- 内容 -->
<?php if ($tab === 'posts' || $tab === 'favs'): ?>
  <div class="post-list">
    <?php if (empty($items)): ?>
      <div class="empty-state"><div class="icon">📭</div><p>暂无内容</p></div>
    <?php else: ?>
      <?php foreach ($items as $p): echo render_post_item($p, '../'); endforeach; ?>
    <?php endif; ?>
  </div>

<?php else: ?>
  <div class="card"><div class="card-body">
    <?php if (empty($items)): ?>
      <div class="empty-state"><div class="icon">👤</div><p>暂无数据</p></div>
    <?php else: foreach ($items as $u): ?>
      <div class="flex-center gap-12" style="padding:10px 0;border-bottom:1px solid var(--border)">
        <img src="<?= avatar_url($u['avatar'], '../') ?>"
             style="width:40px;height:40px;border-radius:50%;object-fit:cover">
        <div style="flex:1">
          <a href="profile.php?id=<?= $u['id'] ?>" style="font-weight:600"><?= h($u['username']) ?></a>
          <?= level_badge($u['exp']) ?>
          <?php if ($u['school']): ?><div style="font-size:12px;color:var(--txt-2)"><?= h($u['school']) ?></div><?php endif; ?>
        </div>
        <a href="profile.php?id=<?= $u['id'] ?>" class="btn btn-outline btn-sm">查看</a>
      </div>
    <?php endforeach; endif; ?>
  </div></div>
<?php endif; ?>

<div id="toast"></div>
<script>
async function toggleFollow(uid) {
  const res = await fetch('../actions/follow_toggle.php', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'target_id='+uid
  });
  const d = await res.json();
  const btn = document.getElementById('follow-btn');
  btn.textContent = d.followed ? '✓ 已关注' : '+ 关注';
  btn.classList.toggle('btn-primary', d.followed);
  btn.classList.toggle('btn-outline', !d.followed);
}
function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg; t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2500);
}
</script>

<?php include '../includes/footer.php'; ?>
