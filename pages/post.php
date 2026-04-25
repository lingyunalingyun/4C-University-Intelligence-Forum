<?php
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$post_id = intval($_GET['id'] ?? 0);
if (!$post_id) { header('Location: ../index.php'); exit; }

// 获取帖子
$stmt = $conn->prepare("SELECT p.*,u.username,u.avatar,u.school,u.exp,u.role,s.name as section_name,s.slug as section_slug,ps.slug as parent_slug,ps.name as parent_name
    FROM posts p JOIN users u ON u.id=p.user_id
    JOIN sections s ON s.id=p.section_id
    LEFT JOIN sections ps ON ps.id=s.parent_id
    WHERE p.id=? AND p.status='published'");
$stmt->bind_param('i', $post_id); $stmt->execute();
$post = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$post) { header('Location: ../index.php'); exit; }

// 浏览量 +1
$conn->query("UPDATE posts SET views=views+1 WHERE id=$post_id");

$uid = intval($_SESSION['user_id'] ?? 0);

// 当前用户点赞/收藏状态
$liked = $faved = false;
if ($uid) {
    $lr = $conn->query("SELECT 1 FROM post_likes WHERE user_id=$uid AND post_id=$post_id");
    $liked = $lr && $lr->num_rows > 0;
    $fr = $conn->query("SELECT 1 FROM post_favs  WHERE user_id=$uid AND post_id=$post_id");
    $faved = $fr && $fr->num_rows > 0;
    // 更新兴趣
    if (!empty($post['tags'])) update_interest($conn, $uid, $post['tags'], 0.5);
}

// 评论
$comments = [];
$cr = $conn->query("SELECT c.*,u.username,u.avatar,u.exp FROM comments c JOIN users u ON u.id=c.user_id WHERE c.post_id=$post_id AND c.parent_id=0 ORDER BY c.created_at ASC");
if ($cr) while ($r = $cr->fetch_assoc()) {
    // 子回复
    $r['replies'] = [];
    $rr = $conn->query("SELECT c.*,u.username,u.avatar FROM comments c JOIN users u ON u.id=c.user_id WHERE c.parent_id={$r['id']} ORDER BY c.created_at ASC");
    if ($rr) while ($rep = $rr->fetch_assoc()) $r['replies'][] = $rep;
    $comments[] = $r;
}

// 相关推荐（同板块最新5帖）
$related = [];
$rr = $conn->query("SELECT p.id,p.title,p.like_count,u.username FROM posts p JOIN users u ON u.id=p.user_id WHERE p.section_id={$post['section_id']} AND p.id!=$post_id AND p.status='published' ORDER BY p.created_at DESC LIMIT 5");
if ($rr) while ($r = $rr->fetch_assoc()) $related[] = $r;

$tags_arr = array_filter(array_map('trim', explode(',', $post['tags'])));
$page_title = $post['title'];
include '../includes/header.php';
?>

<!-- 面包屑 -->
<div class="flex-center gap-8 mb-16" style="font-size:13px;color:var(--txt-2)">
  <a href="../index.php">首页</a> &rsaquo;
  <?php if ($post['parent_slug']): ?>
    <a href="section.php?slug=<?= h($post['parent_slug']) ?>"><?= h($post['parent_name']) ?></a> &rsaquo;
  <?php endif; ?>
  <a href="section.php?slug=<?= h($post['parent_slug'] ?? $post['section_slug']) ?>&sub=<?= h($post['section_slug']) ?>"><?= h($post['section_name']) ?></a>
</div>

<div class="layout-2col">
  <div class="col-main">
    <!-- 帖子内容 -->
    <div class="post-detail">
      <h1 class="post-detail-title"><?= h($post['title']) ?></h1>
      <div class="post-detail-meta">
        <img src="<?= avatar_url($post['avatar'], '../') ?>" alt="">
        <a href="profile.php?id=<?= $post['user_id'] ?>" style="color:var(--txt);font-weight:600"><?= h($post['username']) ?></a>
        <?= role_badge($post['role']) ?>
        <?= level_badge($post['exp']) ?>
        <?php if ($post['school']): ?><span><?= h($post['school']) ?></span><?php endif; ?>
        <span><?= date('Y-m-d H:i', strtotime($post['created_at'])) ?></span>
        <span>👁 <?= $post['views'] ?></span>
        <?php if ($post['is_solved']): ?><span style="background:#dcfce7;color:#166634;padding:2px 8px;border-radius:4px;font-size:12px">✅ 已解决</span><?php endif; ?>
      </div>

      <!-- 标签 -->
      <?php if (!empty($tags_arr)): ?>
      <div class="post-tags mb-16">
        <?php foreach ($tags_arr as $tag): ?>
          <a href="search.php?q=<?= urlencode($tag) ?>" class="tag"><?= h($tag) ?></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- AI摘要 -->
      <?php if (!empty($post['summary'])): ?>
      <div class="ai-summary-box"><?= h($post['summary']) ?></div>
      <?php endif; ?>

      <!-- 正文 -->
      <div class="post-detail-body">
        <?php echo nl2br($post['content']); ?>
      </div>

      <!-- 操作栏 -->
      <div class="post-actions">
        <button class="action-btn <?= $liked?'active':'' ?>" id="like-btn" onclick="toggleLike()">
          👍 <span id="like-count"><?= $post['like_count'] ?></span>
        </button>
        <button class="action-btn <?= $faved?'active':'' ?>" id="fav-btn" onclick="toggleFav()">
          ⭐ <span id="fav-count"><?= $post['fav_count'] ?></span>
        </button>
        <button class="action-btn" onclick="sharePost()">🔗 分享</button>
        <div id="share-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:999;align-items:center;justify-content:center">
          <div style="background:var(--bg-card);border-radius:12px;padding:24px;width:90%;max-width:480px;box-shadow:0 8px 32px rgba(0,0,0,.2)">
            <div style="font-weight:600;margin-bottom:12px">🔗 分享链接</div>
            <div style="display:flex;gap:8px">
              <input id="share-url" type="text" readonly style="flex:1;padding:8px 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:var(--bg-2);color:var(--txt)">
              <button onclick="copyShareUrl()" class="btn btn-primary btn-sm">复制</button>
            </div>
            <div style="text-align:right;margin-top:12px">
              <button onclick="document.getElementById('share-modal').style.display='none'" class="btn btn-outline btn-sm">关闭</button>
            </div>
          </div>
        </div>
        <?php if ($uid === $post['user_id'] && !$post['is_solved']): ?>
          <button class="action-btn" onclick="markSolved()">✅ 标记已解决</button>
        <?php endif; ?>
        <?php if ($uid === $post['user_id'] || in_array($_SESSION['role']??'', ['admin','owner'])): ?>
          <a href="publish.php?edit=<?= $post_id ?>" class="action-btn">✏️ 编辑</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- 评论区 -->
    <div class="comment-section card mt-24" style="padding:20px">
      <div class="card-header" style="padding:0 0 14px;border-bottom:1px solid var(--border);margin-bottom:16px">
        💬 评论 <span style="color:var(--txt-2);font-size:14px">(<?= count($comments) ?>)</span>
      </div>

      <?php if ($uid): ?>
      <form action="../actions/comment_save.php" method="post" class="comment-form mb-24">
        <input type="hidden" name="post_id" value="<?= $post_id ?>">
        <input type="hidden" name="parent_id" value="0">
        <div class="flex-center gap-12">
          <img src="<?= avatar_url($_SESSION['avatar']??'', '../') ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0">
          <div style="flex:1">
            <textarea name="content" placeholder="写下你的评论..." rows="3" required style="width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:var(--r);font-size:14px;resize:none;font-family:inherit;outline:none;background:var(--bg-2)"></textarea>
            <div style="text-align:right;margin-top:8px">
              <button type="submit" class="btn btn-primary btn-sm">发表评论</button>
            </div>
          </div>
        </div>
      </form>
      <?php else: ?>
        <div style="text-align:center;padding:16px;color:var(--txt-2);font-size:13px">
          <a href="login.php">登录</a> 后参与评论
        </div>
      <?php endif; ?>

      <!-- 评论列表 -->
      <div class="comment-list">
        <?php foreach ($comments as $c): ?>
        <div class="comment-item" id="c<?= $c['id'] ?>">
          <img src="<?= avatar_url($c['avatar'], '../') ?>" alt="">
          <div style="flex:1">
            <div class="comment-body">
              <div class="comment-header">
                <a href="profile.php?id=<?= $c['user_id'] ?>" class="comment-author"><?= h($c['username']) ?></a>
                <?= level_badge($c['exp']) ?>
                <span class="comment-time"><?= time_ago($c['created_at']) ?></span>
              </div>
              <div class="comment-text"><?= nl2br(h($c['content'])) ?></div>
              <div class="comment-actions">
                <a href="#" onclick="replyTo(<?= $c['id'] ?>,'<?= h($c['username']) ?>');return false">回复</a>
                <span><?= $c['like_count'] ?> 赞</span>
              </div>
            </div>

            <!-- 子回复 -->
            <?php foreach ($c['replies'] as $rep): ?>
            <div class="comment-item comment-reply mt-8">
              <img src="<?= avatar_url($rep['avatar'], '../') ?>" alt="">
              <div class="comment-body">
                <div class="comment-header">
                  <a href="profile.php?id=<?= $rep['user_id'] ?>" class="comment-author"><?= h($rep['username']) ?></a>
                  <span class="comment-time"><?= time_ago($rep['created_at']) ?></span>
                </div>
                <div class="comment-text"><?= nl2br(h($rep['content'])) ?></div>
              </div>
            </div>
            <?php endforeach; ?>

            <!-- 回复框（动态插入） -->
            <div id="reply-form-<?= $c['id'] ?>" style="display:none;margin-top:8px;margin-left:48px">
              <form action="../actions/comment_save.php" method="post">
                <input type="hidden" name="post_id" value="<?= $post_id ?>">
                <input type="hidden" name="parent_id" value="<?= $c['id'] ?>">
                <textarea name="content" id="reply-text-<?= $c['id'] ?>" rows="2" placeholder="回复 @<?= h($c['username']) ?>..."
                  style="width:100%;padding:8px 12px;border:1px solid var(--border);border-radius:var(--r);font-size:13px;font-family:inherit;outline:none;resize:none"></textarea>
                <div style="margin-top:6px;display:flex;gap:8px">
                  <button type="submit" class="btn btn-primary btn-sm">回复</button>
                  <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('reply-form-<?= $c['id'] ?>').style.display='none'">取消</button>
                </div>
              </form>
            </div>
          </div>
        </div>
        <?php endforeach; ?>

        <?php if (empty($comments)): ?>
          <div class="empty-state"><div class="icon">💬</div><p>还没有评论，来说第一句话吧</p></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- 侧边栏 -->
  <div class="col-side">
    <!-- 作者信息 -->
    <div class="card mb-16">
      <div class="card-header">👤 发帖人</div>
      <div class="card-body" style="text-align:center">
        <img src="<?= avatar_url($post['avatar'], '../') ?>"
             style="width:60px;height:60px;border-radius:50%;object-fit:cover;margin:0 auto 10px">
        <div style="font-weight:600;margin-bottom:4px"><?= h($post['username']) ?></div>
        <?php if ($post['school']): ?><div style="font-size:12px;color:var(--txt-2)"><?= h($post['school']) ?></div><?php endif; ?>
        <div style="margin-top:8px"><?= level_badge($post['exp']) ?> <?= role_badge($post['role']) ?></div>
        <?php if ($uid && $uid !== $post['user_id']): ?>
          <button id="follow-btn" class="btn btn-outline btn-sm" style="margin-top:12px;width:100%" onclick="toggleFollow(<?= $post['user_id'] ?>)">+ 关注</button>
        <?php endif; ?>
      </div>
    </div>

    <!-- 相关推荐 -->
    <?php if (!empty($related)): ?>
    <div class="card">
      <div class="card-header">📎 相关帖子</div>
      <div class="card-body">
        <?php foreach ($related as $r): ?>
        <div style="padding:6px 0;border-bottom:1px solid var(--border)">
          <a href="post.php?id=<?= $r['id'] ?>" style="font-size:13px;color:var(--txt);display:block"><?= h(mb_substr($r['title'],0,30)) ?></a>
          <span style="font-size:12px;color:var(--txt-3)">👍 <?= $r['like_count'] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<div id="toast"></div>
<script>
const postId = <?= $post_id ?>;
const isLoggedIn = <?= $uid ? 'true' : 'false' ?>;

async function postAction(action) {
  if (!isLoggedIn) { location.href='login.php'; return; }
  const res  = await fetch('../actions/post_action.php', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: 'action='+action+'&post_id='+postId
  });
  return await res.json();
}

async function toggleLike() {
  const d = await postAction('like');
  if (!d) return;
  document.getElementById('like-count').textContent = d.count;
  document.getElementById('like-btn').classList.toggle('active', d.liked);
}
async function toggleFav() {
  const d = await postAction('fav');
  if (!d) return;
  document.getElementById('fav-count').textContent = d.count;
  document.getElementById('fav-btn').classList.toggle('active', d.faved);
}
async function markSolved() {
  if (!confirm('确认标记为已解决？')) return;
  await postAction('solve');
  location.reload();
}
function sharePost() {
  var modal = document.getElementById('share-modal');
  document.getElementById('share-url').value = location.href;
  modal.style.display = 'flex';
  modal.onclick = function(e){ if(e.target===modal) modal.style.display='none'; };
}
function copyShareUrl() {
  var input = document.getElementById('share-url');
  input.select(); input.setSelectionRange(0, 9999);
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(input.value).then(function(){ showToast('链接已复制！'); });
  } else {
    try { document.execCommand('copy'); showToast('链接已复制！'); }
    catch(e) { showToast('请手动选中复制'); }
  }
}
async function toggleFollow(uid) {
  if (!isLoggedIn) { location.href='login.php'; return; }
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
function replyTo(cid, username) {
  document.querySelectorAll('[id^="reply-form-"]').forEach(f => f.style.display='none');
  const form = document.getElementById('reply-form-'+cid);
  form.style.display = 'block';
  document.getElementById('reply-text-'+cid).focus();
}
function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg; t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2500);
}
</script>

<?php include '../includes/footer.php'; ?>
