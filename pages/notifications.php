<?php
/*
 * pages/notifications.php — 消息通知页面
 * 功能：展示当前用户的所有通知（点赞/评论/关注/系统），标记已读，分页。
 * 读库：notifications / users / posts
 * 权限：需登录
 */
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php?redirect=notifications.php'); exit; }

$uid = intval($_SESSION['user_id']);

// 标记全部已读
if (isset($_GET['mark_all'])) {
    $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$uid");
    header('Location: notifications.php'); exit;
}

$notifs = [];
$nr = $conn->query("SELECT n.*,u.username,u.avatar
    FROM notifications n JOIN users u ON u.id=n.from_user_id
    WHERE n.user_id=$uid ORDER BY n.created_at DESC LIMIT 60");
if ($nr) while ($r = $nr->fetch_assoc()) $notifs[] = $r;

// 标为已读
$conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$uid");
$_SESSION['unread_notif'] = 0;

$page_title = '通知中心';
include '../includes/header.php';
?>

<div class="flex-center gap-8 mb-16" style="font-size:13px;color:var(--txt-2)">
  <a href="../index.php">首页</a> &rsaquo; <span>通知</span>
</div>

<div class="card">
  <div class="card-header" style="display:flex;align-items:center;justify-content:space-between">
    <span>🔔 通知中心</span>
    <?php if (!empty($notifs)): ?>
      <a href="notifications.php?mark_all=1" class="btn btn-outline btn-sm">全部标为已读</a>
    <?php endif; ?>
  </div>
  <div class="card-body" style="padding:0">
    <?php if (empty($notifs)): ?>
      <div class="empty-state" style="padding:40px"><div class="icon">🔔</div><p>暂无通知</p></div>
    <?php else: ?>
      <?php foreach ($notifs as $n):
        $type_labels = ['like'=>'赞了你的帖子','comment'=>'评论了你的帖子','reply'=>'回复了你的评论','follow'=>'关注了你'];
        $label = $type_labels[$n['type']] ?? '与你互动';
        $link  = '';
        if ($n['post_id'])    $link = 'post.php?id='.$n['post_id'].($n['comment_id']?'#c'.$n['comment_id']:'');
        elseif ($n['type']==='follow') $link = 'profile.php?id='.$n['from_user_id'];
      ?>
      <div class="notif-item <?= $n['is_read']?'':'notif-unread' ?>">
        <img src="<?= avatar_url($n['avatar'], '../') ?>" alt="">
        <div style="flex:1">
          <span style="font-weight:600"><?= h($n['username']) ?></span>
          <span style="color:var(--txt-2)"> <?= $label ?></span>
          <div style="font-size:12px;color:var(--txt-3);margin-top:2px"><?= time_ago($n['created_at']) ?></div>
        </div>
        <?php if ($link): ?>
          <a href="<?= h($link) ?>" class="btn btn-outline btn-sm">查看</a>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
