<?php
/*
 * admin/index.php — 管理后台首页（数据仪表板）
 * 功能：展示全站核心统计（用户/帖子/今日活跃/AI调用），
 *       最近用户注册、最近帖子列表，快捷功能入口。
 * 读库：users / posts / ai_logs / admin_logs
 * 权限：需 admin/owner 登录
 */
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../pages/login.php'); exit; }
if (!in_array($_SESSION['role'] ?? '', ['admin','owner'])) { header('Location: ../index.php'); exit; }

$stats = [];
foreach ([
    'users'        => "SELECT COUNT(*) as c FROM users",
    'posts'        => "SELECT COUNT(*) as c FROM posts WHERE status='published'",
    'comments'     => "SELECT COUNT(*) as c FROM comments",
    'clubs'        => "SELECT COUNT(*) as c FROM clubs WHERE status='active'",
    'today_users'  => "SELECT COUNT(*) as c FROM users WHERE DATE(created_at)=CURDATE()",
    'today_posts'  => "SELECT COUNT(*) as c FROM posts WHERE DATE(created_at)=CURDATE()",
    'banned_users' => "SELECT COUNT(*) as c FROM users WHERE is_banned=1",
    'ai_calls'     => "SELECT COUNT(*) as c FROM ai_logs WHERE DATE(created_at)=CURDATE()",
] as $key => $sql) {
    $r = $conn->query($sql);
    $stats[$key] = $r ? (int)$r->fetch_assoc()['c'] : 0;
}

// 最近7天每日注册
$daily_labels = $daily_data = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $daily_labels[] = date('m/d', strtotime($d));
    $daily_data[$d] = 0;
}
$dr = $conn->query("SELECT DATE(created_at) as d, COUNT(*) as c FROM users WHERE created_at >= DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY DATE(created_at)");
if ($dr) while ($r = $dr->fetch_assoc()) { if (isset($daily_data[$r['d']])) $daily_data[$r['d']] = (int)$r['c']; }
$daily_values = array_values($daily_data);

// 最新用户
$new_users = [];
$ur = $conn->query("SELECT id,username,avatar,school,role,exp,created_at FROM users ORDER BY created_at DESC LIMIT 8");
if ($ur) while ($r = $ur->fetch_assoc()) $new_users[] = $r;

// 最新帖子
$new_posts = [];
$pr = $conn->query("SELECT p.id,p.title,p.created_at,p.like_count,p.views,p.comment_count,u.username
    FROM posts p JOIN users u ON u.id=p.user_id WHERE p.status='published' ORDER BY p.created_at DESC LIMIT 8");
if ($pr) while ($r = $pr->fetch_assoc()) $new_posts[] = $r;

$ai_ok = !empty(DEEPSEEK_API_KEY);

$page_title = '数据总览';
$in_admin   = true;
include '../includes/header.php';
?>

<div class="admin-page-hd">
  <div>
    <h2><i data-lucide="bar-chart-2" class="lucide"></i> 数据总览</h2>
    <div class="sub"><?= date('Y年m月d日') ?> &nbsp;·&nbsp;
      AI <?= $ai_ok
        ? '<span style="color:#16a34a">● 运行中</span>'
        : '<span style="color:#ef4444">● 未配置</span>' ?>
    </div>
  </div>
  <div class="actions">
    <?php if (!$ai_ok): ?>
      <a href="settings.php" class="btn btn-primary btn-sm"><i data-lucide="bot" class="lucide"></i> 配置 AI</a>
    <?php endif; ?>
    <a href="../index.php" target="_blank" class="btn btn-outline btn-sm">访问前台 ↗</a>
  </div>
</div>

<!-- 统计卡片 -->
<div class="admin-stat-grid">
<?php
$cards = [
  ['<i data-lucide="users" class="lucide"></i>','注册用户',$stats['users'],  "今日 +{$stats['today_users']}",  '#2563eb','#dbeafe'],
  ['<i data-lucide="file-text" class="lucide"></i>','发布帖子',$stats['posts'],  "今日 +{$stats['today_posts']}",  '#10b981','#d1fae5'],
  ['<i data-lucide="message-circle" class="lucide"></i>','评论总数',$stats['comments'],'',                              '#8b5cf6','#ede9fe'],
  ['<i data-lucide="building-2" class="lucide"></i>','活跃社团',$stats['clubs'],  '',                              '#f59e0b','#fef3c7'],
  ['<i data-lucide="ban" class="lucide"></i>','封禁用户',$stats['banned_users'],'',                         '#ef4444','#fee2e2'],
  ['<i data-lucide="bot" class="lucide"></i>','今日AI调用',$stats['ai_calls'],'',                           '#0ea5e9','#e0f2fe'],
];
foreach ($cards as $sc): list($icon,$label,$val,$sub,$color,$bg) = $sc;
?>
<div class="admin-stat-card" style="border-top-color:<?= $color ?>">
  <div class="admin-stat-icon" style="background:<?= $bg ?>"><?= $icon ?></div>
  <div>
    <div class="admin-stat-val" style="color:<?= $color ?>"><?= number_format($val) ?></div>
    <div class="admin-stat-lbl"><?= $label ?></div>
    <?php if ($sub): ?><div class="admin-stat-sub" style="color:<?= $color ?>"><?= $sub ?></div><?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- 最新内容 -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">

  <!-- 最新用户 -->
  <div class="card">
    <div class="card-header" style="justify-content:space-between">
      <span><i data-lucide="users" class="lucide"></i> 最新注册</span>
      <a href="users.php" style="font-size:12px;color:var(--txt-3);font-weight:400">查看全部 →</a>
    </div>
    <div style="padding:0">
      <?php foreach ($new_users as $u): ?>
      <div style="display:flex;align-items:center;gap:12px;padding:10px 20px;border-bottom:1px solid var(--border)">
        <img src="<?= avatar_url($u['avatar'],'../') ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0" onerror="this.src='../assets/default_avatar.svg'">
        <div style="flex:1;min-width:0">
          <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
            <a href="../pages/profile.php?id=<?= $u['id'] ?>" style="font-weight:600;font-size:13px;color:var(--txt)"><?= h($u['username']) ?></a>
            <?= role_badge($u['role']) ?> <?= level_badge($u['exp']) ?>
          </div>
          <?php if ($u['school']): ?><div style="font-size:11px;color:var(--txt-3)"><?= h($u['school']) ?></div><?php endif; ?>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0">
          <span style="font-size:11px;color:var(--txt-3)"><?= date('m-d H:i',strtotime($u['created_at'])) ?></span>
          <a href="users.php?search=<?= urlencode($u['username']) ?>" class="btn btn-outline btn-sm" style="font-size:11px;padding:2px 8px">管理</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- 最新帖子 -->
  <div class="card">
    <div class="card-header" style="justify-content:space-between">
      <span><i data-lucide="file-text" class="lucide"></i> 最新帖子</span>
      <a href="posts.php" style="font-size:12px;color:var(--txt-3);font-weight:400">查看全部 →</a>
    </div>
    <div style="padding:0">
      <?php foreach ($new_posts as $p): ?>
      <div style="display:flex;align-items:center;gap:12px;padding:10px 20px;border-bottom:1px solid var(--border)">
        <div style="width:34px;height:34px;border-radius:8px;background:var(--primary-lt);display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0"><i data-lucide="file" class="lucide"></i></div>
        <div style="flex:1;min-width:0">
          <a href="../pages/post.php?id=<?= $p['id'] ?>" target="_blank" style="font-size:13px;font-weight:600;color:var(--txt);display:block;overflow:hidden;white-space:nowrap;text-overflow:ellipsis"><?= h($p['title']) ?></a>
          <div style="font-size:11px;color:var(--txt-3);"><?= h($p['username']) ?> · <i data-lucide="eye" class="lucide"></i><?= $p['views'] ?> <i data-lucide="thumbs-up" class="lucide"></i><?= $p['like_count'] ?> <i data-lucide="message-circle" class="lucide"></i><?= $p['comment_count'] ?></div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0">
          <span style="font-size:11px;color:var(--txt-3)"><?= date('m-d H:i',strtotime($p['created_at'])) ?></span>
          <a href="posts.php?search=<?= urlencode($p['title']) ?>" class="btn btn-outline btn-sm" style="font-size:11px;padding:2px 8px">管理</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<!-- 快捷操作 -->
<div class="card">
  <div class="card-header"><i data-lucide="zap" class="lucide"></i> 快捷入口</div>
  <div class="card-body" style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px">
    <?php
    $shortcuts = [
      ['users.php',    '<i data-lucide="users" class="lucide"></i>','用户管理','#dbeafe','#2563eb'],
      ['posts.php',    '<i data-lucide="file-text" class="lucide"></i>','帖子管理','#d1fae5','#10b981'],
      ['sections.php', '<i data-lucide="folder" class="lucide"></i>','板块管理','#ede9fe','#8b5cf6'],
      ['clubs.php',    '<i data-lucide="building-2" class="lucide"></i>','社团管理','#fce7f3','#ec4899'],
      ['homepage.php', '<i data-lucide="image" class="lucide"></i>','主页精选','#fef3c7','#f59e0b'],
      ['messages.php', '<i data-lucide="message-circle" class="lucide"></i>','消息记录','#e0f2fe','#0ea5e9'],
      ['logs.php',     '<i data-lucide="clipboard-list" class="lucide"></i>','操作日志','#f1f5f9','#64748b'],
      ['settings.php', '<i data-lucide="bot" class="lucide"></i>','AI 设置', '#d1fae5','#10b981'],
    ];
    foreach ($shortcuts as $sc): list($href,$icon,$name,$bg,$color) = $sc;
    ?>
    <a href="<?= $href ?>" style="display:flex;align-items:center;gap:10px;padding:14px 16px;background:<?= $bg ?>;border-radius:var(--r);text-decoration:none;border:1px solid transparent;transition:all .15s"
       onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
      <span style="font-size:22px"><?= $icon ?></span>
      <span style="font-size:13px;font-weight:600;color:<?= $color ?>"><?= $name ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
