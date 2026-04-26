<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$is_logged_in = isset($_SESSION['user_id']);
$current_role = $_SESSION['role'] ?? 'user';
$in_pages     = strpos($_SERVER['PHP_SELF'], '/pages/') !== false;
$in_admin     = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
$base         = ($in_pages || $in_admin) ? '../' : '';

$unread_count = 0;
if ($is_logged_in && isset($conn)) {
    $uid_h = intval($_SESSION['user_id']);

    // 封禁检测（到期自动解封）
    $_SESSION['is_banned'] = 0;
    $ban_res = $conn->query("SELECT is_banned,ban_reason,ban_until,role FROM users WHERE id=$uid_h");
    if ($ban_res && $ban_row = $ban_res->fetch_assoc()) {
        // 同步角色（避免改数据库后需要重新登录）
        $_SESSION['role'] = $ban_row['role'];
        $current_role     = $ban_row['role'];
        if (!empty($ban_row['is_banned'])) {
            $until = $ban_row['ban_until'];
            if ($until !== null && strtotime($until) <= time()) {
                $conn->query("UPDATE users SET is_banned=0,ban_reason=NULL,ban_until=NULL,banned_by=NULL WHERE id=$uid_h");
            } else {
                $_SESSION['is_banned']  = 1;
                $_SESSION['ban_reason'] = $ban_row['ban_reason'] ?: '违反社区规范';
            }
        }
    }

    $n_res = $conn->query("SELECT COUNT(*) as cnt FROM notifications WHERE user_id=$uid_h AND is_read=0");
    if ($n_res) $unread_count = (int)$n_res->fetch_assoc()['cnt'];

    $msg_unread = 0;
    $mur = $conn->query("SELECT COUNT(*) as cnt FROM messages m
        JOIN conversation_members cm ON cm.conversation_id=m.conversation_id AND cm.user_id=$uid_h
        WHERE m.user_id!=$uid_h AND m.is_recalled=0
        AND (cm.last_read_at IS NULL OR m.created_at > cm.last_read_at)");
    if ($mur) $msg_unread = (int)$mur->fetch_assoc()['cnt'];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= isset($page_title) ? h($page_title).' - ' : '' ?><?= SITE_NAME ?></title>
<link rel="stylesheet" href="<?= $base ?>style.css">
<script>
  (function(){
    var t = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', t);
  })();
</script>
<link rel="icon" href="<?= $base ?>assets/logo.svg" type="image/svg+xml">
</head>
<body>

<!-- ── 顶部导航 ── -->
<header class="top-nav">
  <div class="nav-inner">
    <!-- Logo -->
    <a href="<?= $base ?>index.php" class="nav-logo">
      <span class="logo-icon">🎓</span>
      <span class="logo-text"><?= SITE_NAME ?></span>
    </a>

    <!-- 搜索框 -->
    <form class="nav-search" action="<?= $base ?>pages/search.php" method="get">
      <input type="text" name="q" placeholder="搜索帖子、用户、板块..." value="<?= h($_GET['q'] ?? '') ?>">
      <button type="submit">🔍</button>
    </form>

    <!-- 导航链接 -->
    <nav class="nav-links">
      <a href="<?= $base ?>index.php">首页</a>
      <a href="<?= $base ?>square.php">广场</a>
      <a href="<?= $base ?>pages/explore.php">发现</a>
      <a href="<?= $base ?>pages/section.php">分区</a>
      <a href="<?= $base ?>pages/hot.php">热榜</a>
      <a href="<?= $base ?>pages/clubs.php">社团</a>
      <?php if ($is_logged_in): ?>
        <a href="<?= $base ?>pages/my_clubs.php">我的社团</a>
        <a href="<?= $base ?>pages/ai_assistant.php">AI助手</a>
        <a href="<?= $base ?>pages/messages.php" style="position:relative">
          私信<?php if (!empty($msg_unread) && $msg_unread > 0): ?><span class="badge" style="top:-6px;right:-10px"><?= $msg_unread > 99 ? '99+' : $msg_unread ?></span><?php endif; ?>
        </a>
        <?php if ($current_role === 'admin' || $current_role === 'owner'): ?>
          <a href="<?= $base ?>admin/index.php">管理后台</a>
        <?php endif; ?>
      <?php endif; ?>
    </nav>

    <!-- 右侧用户区 -->
    <div class="nav-user">
      <button id="theme-toggle" class="theme-btn" onclick="toggleTheme()" title="切换主题">🌙</button>
      <?php if ($is_logged_in): ?>
        <a href="<?= $base ?>pages/publish.php" class="btn-publish">✏️ 发帖</a>

        <a href="<?= $base ?>pages/notifications.php" class="nav-bell">
          🔔<?php if ($unread_count > 0): ?><span class="badge"><?= $unread_count > 99 ? '99+' : $unread_count ?></span><?php endif; ?>
        </a>

        <a href="<?= $base ?>pages/profile.php?id=<?= intval($_SESSION['user_id']) ?>">
          <?php $nav_avatar = !empty($_SESSION['avatar'])
              ? $base . 'uploads/avatars/' . h($_SESSION['avatar'])
              : $base . 'assets/default_avatar.svg'; ?>
          <img src="<?= $nav_avatar ?>" class="nav-avatar" alt="头像">
        </a>
      <?php else: ?>
        <a href="<?= $base ?>pages/login.php" class="btn-login">登录</a>
        <a href="<?= $base ?>pages/register.php" class="btn-register">注册</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<?php if (!empty($_SESSION['is_banned'])): ?>
<div class="ban-banner">
  ⛔ 您的账号已被封禁：<?= h($_SESSION['ban_reason']) ?>  &nbsp;|&nbsp; 如有疑问请联系管理员
</div>
<?php endif; ?>

<!-- ── 主体内容开始 ── -->
<main class="main-wrap">
