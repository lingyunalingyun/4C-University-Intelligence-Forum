<?php
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$uid  = intval($_SESSION['user_id'] ?? 0);

$q    = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? 'all'; // all / post / user
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 20;

$posts       = [];
$users       = [];
$total_posts = 0;
$total_users = 0;
$ai_keywords = [];
$ai_hint     = '';

if ($q !== '') {
    $safe = $conn->real_escape_string($q);

    // AI 扩词（仅 API 已配置时）
    if (!empty(DEEPSEEK_API_KEY)) {
        $ai_keywords = ai_expand_query($q, $conn, $uid);
        if (!empty($ai_keywords)) {
            $ai_hint = implode('、', $ai_keywords);
        }
    }

    // 合并原始词 + AI 扩词构建搜索词集
    $all_terms = array_unique(array_merge([$q], $ai_keywords));

    // ── 搜索用户（type=all 或 user）──
    if (in_array($type, ['all', 'user'])) {
        $u_conds = [];
        foreach ($all_terms as $term) {
            $st = $conn->real_escape_string($term);
            $u_conds[] = "username LIKE '%$st%' OR school LIKE '%$st%'";
        }
        // SCID 精确匹配
        $scid_safe  = $conn->real_escape_string($q);
        $u_where    = "(scid='$scid_safe' OR (" . implode(' OR ', $u_conds) . "))";
        $limit_u    = ($type === 'user') ? $per : 3; // all模式最多展示3个用户
        $offset_u   = ($type === 'user') ? ($page - 1) * $per : 0;
        if ($type === 'user') {
            $cnt_r = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE $u_where");
            $total_users = $cnt_r ? (int)$cnt_r->fetch_assoc()['cnt'] : 0;
        }
        $ur = $conn->query("SELECT id,username,avatar,school,exp,role,scid,bio
            FROM users WHERE $u_where
            ORDER BY (scid='$scid_safe') DESC, exp DESC
            LIMIT $limit_u OFFSET $offset_u");
        if ($ur) while ($r = $ur->fetch_assoc()) $users[] = $r;
    }

    // ── 搜索帖子（type=all 或 post）──
    if (in_array($type, ['all', 'post'])) {
        $p_conds = [];
        foreach ($all_terms as $term) {
            $st = $conn->real_escape_string($term);
            $p_conds[] = "(p.title LIKE '%$st%' OR p.content LIKE '%$st%' OR p.tags LIKE '%$st%')";
        }
        $p_where = implode(' OR ', $p_conds);
        $cnt_r   = $conn->query("SELECT COUNT(*) as cnt FROM posts p WHERE p.status='published' AND ($p_where)");
        $total_posts = $cnt_r ? (int)$cnt_r->fetch_assoc()['cnt'] : 0;
        $offset  = ($page - 1) * $per;
        $pr = $conn->query("SELECT p.*,u.username,u.avatar,s.name as section_name
            FROM posts p JOIN users u ON u.id=p.user_id JOIN sections s ON s.id=p.section_id
            WHERE p.status='published' AND ($p_where)
            ORDER BY
                (p.title LIKE '%$safe%') DESC,
                (p.like_count*2 + p.views*0.1) DESC,
                p.created_at DESC
            LIMIT $per OFFSET $offset");
        if ($pr) while ($r = $pr->fetch_assoc()) $posts[] = $r;
    }
}

$total_pages = ($type === 'user')
    ? max(1, ceil($total_users / $per))
    : max(1, ceil($total_posts / $per));

$page_title = $q ? "搜索：$q" : '搜索';
include '../includes/header.php';
?>

<div class="flex-center gap-8 mb-16" style="font-size:13px;color:var(--txt-2)">
  <a href="../index.php">首页</a> &rsaquo; <span>搜索</span>
</div>

<!-- 搜索框 -->
<div class="card mb-16" style="padding:18px 20px">
  <form method="get" action="search.php">
    <div style="display:flex;gap:10px;align-items:center">
      <input type="text" name="q" value="<?= h($q) ?>" placeholder="搜索帖子、用户、学校..."
             style="flex:1;padding:10px 16px;border:1px solid var(--border);border-radius:var(--r);font-size:14px;outline:none;background:var(--bg-2)">
      <button type="submit" class="btn btn-primary" style="padding:10px 22px">🔍 搜索</button>
    </div>
    <div style="display:flex;gap:6px;margin-top:10px">
      <?php foreach (['all'=>'全部','post'=>'只看帖子','user'=>'只看用户'] as $v=>$label): ?>
        <a href="?q=<?= urlencode($q) ?>&type=<?= $v ?>"
           class="btn btn-sm <?= $type===$v ? 'btn-primary' : 'btn-outline' ?>"><?= $label ?></a>
      <?php endforeach; ?>
    </div>
  </form>
</div>

<?php if ($q !== ''): ?>

<!-- AI 扩词提示 -->
<?php if ($ai_hint): ?>
<div style="display:flex;align-items:center;gap:8px;padding:10px 14px;background:var(--bg-2);border:1px solid var(--border);border-radius:var(--r);margin-bottom:14px;font-size:13px">
  <span style="color:var(--primary);font-size:16px">✨</span>
  <span style="color:var(--txt-2)">AI 优化搜索：识别到关键词</span>
  <span style="color:var(--primary);font-weight:600"><?= h($ai_hint) ?></span>
</div>
<?php endif; ?>

<!-- 用户结果 -->
<?php if (!empty($users)): ?>
<div class="card mb-16">
  <div class="card-header" style="display:flex;align-items:center;gap:8px">
    <span>👤 相关用户</span>
    <?php if ($type === 'all' && count($users) >= 3): ?>
      <a href="?q=<?= urlencode($q) ?>&type=user" style="margin-left:auto;font-size:13px;color:var(--primary)">查看全部 →</a>
    <?php endif; ?>
  </div>
  <div class="card-body" style="padding:0">
    <?php foreach ($users as $u): ?>
    <div class="flex-center gap-12" style="padding:12px 16px;border-bottom:1px solid var(--border)">
      <img src="<?= avatar_url($u['avatar'], '../') ?>"
           style="width:44px;height:44px;border-radius:50%;object-fit:cover;flex-shrink:0"
           onerror="this.src='../assets/default_avatar.svg'">
      <div style="flex:1;min-width:0">
        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
          <a href="profile.php?id=<?= $u['id'] ?>" style="font-weight:600;color:var(--txt)"><?= h($u['username']) ?></a>
          <?= level_badge($u['exp']) ?> <?= role_badge($u['role']) ?>
        </div>
        <?php if ($u['school']): ?><div style="font-size:12px;color:var(--txt-2);margin-top:2px"><?= h($u['school']) ?></div><?php endif; ?>
        <?php if ($u['bio']): ?><div style="font-size:12px;color:var(--txt-3);margin-top:2px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis"><?= h(mb_substr($u['bio'],0,60)) ?></div><?php endif; ?>
        <?php if ($u['scid']): ?><div style="font-size:11px;color:var(--txt-3);font-family:monospace;margin-top:2px">SCID: <?= h($u['scid']) ?></div><?php endif; ?>
      </div>
      <a href="profile.php?id=<?= $u['id'] ?>" class="btn btn-outline btn-sm" style="flex-shrink:0">查看主页</a>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- 帖子结果 -->
<?php if (in_array($type, ['all', 'post'])): ?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
  <span style="font-size:13px;color:var(--txt-2)">
    <?php if ($type === 'all'): ?>
      找到 <strong><?= $total_posts ?></strong> 篇帖子
    <?php else: ?>
      找到 <strong><?= $total_posts ?></strong> 个结果，关键词：<strong><?= h($q) ?></strong>
    <?php endif; ?>
  </span>
</div>

<div class="post-list">
  <?php if (empty($posts)): ?>
    <div class="empty-state"><div class="icon">🔍</div><p>没有找到相关帖子</p></div>
  <?php else: ?>
    <?php foreach ($posts as $p): echo render_post_item($p, '../', $q); endforeach; ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- 仅用户模式的统计 -->
<?php if ($type === 'user' && empty($users)): ?>
<div class="empty-state"><div class="icon">👤</div><p>没有找到相关用户</p></div>
<?php endif; ?>

<!-- 分页 -->
<?php if ($total_pages > 1): ?>
<div class="pagination" style="margin-top:20px">
  <?php for ($i = 1; $i <= $total_pages; $i++): ?>
    <a href="?q=<?= urlencode($q) ?>&type=<?= h($type) ?>&page=<?= $i ?>"
       class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<?php include '../includes/footer.php'; ?>
