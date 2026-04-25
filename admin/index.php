<?php
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../pages/login.php'); exit; }
if (!in_array($_SESSION['role'] ?? '', ['admin','owner'])) { header('Location: ../index.php'); exit; }

// 统计数据
$stats = [];
foreach ([
    'users'    => "SELECT COUNT(*) as c FROM users",
    'posts'    => "SELECT COUNT(*) as c FROM posts WHERE status='published'",
    'comments' => "SELECT COUNT(*) as c FROM comments",
    'today_users' => "SELECT COUNT(*) as c FROM users WHERE DATE(created_at)=CURDATE()",
    'today_posts' => "SELECT COUNT(*) as c FROM posts WHERE DATE(created_at)=CURDATE()",
    'banned_users'=> "SELECT COUNT(*) as c FROM users WHERE is_banned=1",
] as $key => $sql) {
    $r = $conn->query($sql);
    $stats[$key] = $r ? (int)$r->fetch_assoc()['c'] : 0;
}

// 最新注册用户
$new_users = [];
$ur = $conn->query("SELECT id,username,email,school,role,created_at FROM users ORDER BY created_at DESC LIMIT 8");
if ($ur) while ($r = $ur->fetch_assoc()) $new_users[] = $r;

// 最新帖子
$new_posts = [];
$pr = $conn->query("SELECT p.id,p.title,p.created_at,u.username FROM posts p JOIN users u ON u.id=p.user_id WHERE p.status='published' ORDER BY p.created_at DESC LIMIT 8");
if ($pr) while ($r = $pr->fetch_assoc()) $new_posts[] = $r;

$page_title = '管理后台';
$in_admin = true;
include '../includes/header.php';
?>

<div style="display:flex;gap:8px;align-items:center;margin-bottom:20px">
  <h2 style="margin:0">⚙️ 管理后台</h2>
  <span style="font-size:13px;color:var(--txt-2)">— 数据总览</span>
  <div style="margin-left:auto;display:flex;gap:8px">
    <a href="users.php"    class="btn btn-outline btn-sm">👥 用户管理</a>
    <a href="posts.php"    class="btn btn-outline btn-sm">📝 帖子管理</a>
    <a href="sections.php" class="btn btn-outline btn-sm">🗂️ 板块管理</a>
  </div>
</div>

<!-- 数据卡片 -->
<div class="admin-grid mb-24">
  <?php
  $cards = [
    ['icon'=>'👥','label'=>'注册用户','value'=>$stats['users'],'sub'=>"今日 +{$stats['today_users']}"],
    ['icon'=>'📝','label'=>'帖子总数','value'=>$stats['posts'],'sub'=>"今日 +{$stats['today_posts']}"],
    ['icon'=>'💬','label'=>'评论总数','value'=>$stats['comments'],'sub'=>''],
    ['icon'=>'🚫','label'=>'封禁用户','value'=>$stats['banned_users'],'sub'=>''],
  ];
  foreach ($cards as $c): ?>
  <div class="card" style="text-align:center;padding:20px">
    <div style="font-size:28px"><?= $c['icon'] ?></div>
    <div style="font-size:28px;font-weight:700;margin:4px 0"><?= $c['value'] ?></div>
    <div style="font-size:13px;color:var(--txt-2)"><?= $c['label'] ?></div>
    <?php if ($c['sub']): ?><div style="font-size:12px;color:var(--primary);margin-top:2px"><?= $c['sub'] ?></div><?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<div class="layout-2col">
  <!-- 最新用户 -->
  <div class="card">
    <div class="card-header">👥 最新注册</div>
    <div class="card-body" style="padding:0">
      <table class="data-table">
        <thead><tr><th>用户名</th><th>学校</th><th>角色</th><th>注册时间</th><th>操作</th></tr></thead>
        <tbody>
          <?php foreach ($new_users as $u): ?>
          <tr>
            <td><a href="../pages/profile.php?id=<?= $u['id'] ?>"><?= h($u['username']) ?></a></td>
            <td><?= h($u['school']?:'—') ?></td>
            <td><?= role_badge($u['role']) ?></td>
            <td style="font-size:12px;color:var(--txt-3)"><?= date('m-d H:i', strtotime($u['created_at'])) ?></td>
            <td><a href="users.php?search=<?= urlencode($u['username']) ?>" class="btn btn-outline btn-sm">管理</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div style="padding:8px 16px"><a href="users.php" style="font-size:13px">查看全部用户 →</a></div>
    </div>
  </div>

  <!-- 最新帖子 -->
  <div class="card">
    <div class="card-header">📝 最新帖子</div>
    <div class="card-body" style="padding:0">
      <table class="data-table">
        <thead><tr><th>标题</th><th>作者</th><th>时间</th><th>操作</th></tr></thead>
        <tbody>
          <?php foreach ($new_posts as $p): ?>
          <tr>
            <td><a href="../pages/post.php?id=<?= $p['id'] ?>"><?= h(mb_substr($p['title'],0,20)) ?></a></td>
            <td><?= h($p['username']) ?></td>
            <td style="font-size:12px;color:var(--txt-3)"><?= date('m-d H:i', strtotime($p['created_at'])) ?></td>
            <td><a href="posts.php?search=<?= urlencode($p['title']) ?>" class="btn btn-outline btn-sm">管理</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div style="padding:8px 16px"><a href="posts.php" style="font-size:13px">查看全部帖子 →</a></div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
