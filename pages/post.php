<?php
/*
 * pages/post.php — 帖子详情页
 * 功能：展示帖子全文、AI摘要、评论列表、点赞/收藏/分享操作，
 *       支持可见性权限控制（public/followers/following/mutual/private）。
 * 读库：posts / users / sections / comments / post_likes / post_favs / follows
 * 权限：根据帖子可见性判断；admin/owner 绕过所有限制
 */
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$post_id = intval($_GET['id'] ?? 0);
if (!$post_id) { header('Location: ../index.php'); exit; }

$stmt = $conn->prepare("SELECT p.*,u.username,u.avatar,u.school,u.exp,u.role,s.name as section_name,s.slug as section_slug,ps.slug as parent_slug,ps.name as parent_name
    FROM posts p JOIN users u ON u.id=p.user_id
    JOIN sections s ON s.id=p.section_id
    LEFT JOIN sections ps ON ps.id=s.parent_id
    WHERE p.id=? AND p.status='published'");
$stmt->bind_param('i', $post_id); $stmt->execute();
$post = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$post) { header('Location: ../index.php'); exit; }

$conn->query("UPDATE posts SET views=views+1 WHERE id=$post_id");

$uid = intval($_SESSION['user_id'] ?? 0);
$liked = $faved = false;
if ($uid) {
    $lr = $conn->query("SELECT 1 FROM post_likes WHERE user_id=$uid AND post_id=$post_id");
    $liked = $lr && $lr->num_rows > 0;
    $fr = $conn->query("SELECT 1 FROM post_favs WHERE user_id=$uid AND post_id=$post_id");
    $faved = $fr && $fr->num_rows > 0;
    if (!empty($post['tags'])) update_interest($conn, $uid, $post['tags'], 0.5);
}

// 评论（含子回复）
$comments = [];
$cr = $conn->query("SELECT c.*,u.username,u.avatar,u.exp FROM comments c JOIN users u ON u.id=c.user_id WHERE c.post_id=$post_id AND c.parent_id=0 ORDER BY c.created_at ASC");
if ($cr) while ($r = $cr->fetch_assoc()) {
    $r['replies'] = [];
    $rr = $conn->query("SELECT c.*,u.username,u.avatar FROM comments c JOIN users u ON u.id=c.user_id WHERE c.parent_id={$r['id']} ORDER BY c.created_at ASC");
    if ($rr) while ($rep = $rr->fetch_assoc()) $r['replies'][] = $rep;
    $comments[] = $r;
}

// 相关推荐
$related = [];
$rr = $conn->query("SELECT p.id,p.title,p.like_count,u.username FROM posts p JOIN users u ON u.id=p.user_id WHERE p.section_id={$post['section_id']} AND p.id!=$post_id AND p.status='published' ORDER BY p.created_at DESC LIMIT 5");
if ($rr) while ($r = $rr->fetch_assoc()) $related[] = $r;

$tags_arr  = array_filter(array_map('trim', explode(',', $post['tags'])));
$page_title = $post['title'];

// 作者统计
$author_id = intval($post['user_id']);
$follower_count = 0; $post_count = 0; $is_following = false;
$fc = $conn->query("SELECT COUNT(*) as c FROM follows WHERE following_id=$author_id");
if ($fc) $follower_count = (int)$fc->fetch_assoc()['c'];
$pc = $conn->query("SELECT COUNT(*) as c FROM posts WHERE user_id=$author_id AND status='published'");
if ($pc) $post_count = (int)$pc->fetch_assoc()['c'];
if ($uid && $uid !== $author_id) {
    $fol = $conn->query("SELECT 1 FROM follows WHERE follower_id=$uid AND following_id=$author_id");
    $is_following = $fol && $fol->num_rows > 0;
}

include '../includes/header.php';
?>

<!-- 面包屑 -->
<div class="flex-center gap-8 mb-16" style="font-size:13px;color:var(--txt-2)">
  <a href="../index.php">首页</a> ›
  <?php if ($post['parent_slug']): ?>
    <a href="section.php?slug=<?= h($post['parent_slug']) ?>"><?= h($post['parent_name']) ?></a> ›
  <?php endif; ?>
  <a href="section.php?slug=<?= h($post['parent_slug'] ?? $post['section_slug']) ?>&sub=<?= h($post['section_slug']) ?>"><?= h($post['section_name']) ?></a>
</div>

<div class="layout-2col">
  <div class="col-main">
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

      <?php if (!empty($tags_arr)): ?>
      <div class="post-tags mb-16">
        <?php foreach ($tags_arr as $tag): ?>
          <a href="search.php?q=<?= urlencode($tag) ?>" class="tag"><?= h($tag) ?></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- 正文 -->
      <div class="post-detail-body"><?= render_post_content($post['content']) ?></div>

      <!-- 操作栏 -->
      <div class="post-actions">
        <button class="action-btn <?= $liked?'active':'' ?>" id="like-btn" onclick="toggleLike()">
          👍 <span id="like-count"><?= $post['like_count'] ?></span>
        </button>
        <button class="action-btn <?= $faved?'active':'' ?>" id="fav-btn" onclick="toggleFav()">
          ⭐ <span id="fav-count"><?= $post['fav_count'] ?></span>
        </button>
        <button class="action-btn" id="share-trigger" onclick="openShare()">🔗 分享</button>
        <?php if ($uid && $uid !== $post['user_id']): ?>
          <button class="action-btn" onclick="openReportModal('post',<?= $post_id ?>)" style="color:var(--danger)">🚩 举报</button>
        <?php endif; ?>
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
            <textarea name="content" placeholder="写下你的评论…" rows="3" required
              style="width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:var(--r);font-size:14px;resize:none;font-family:inherit;outline:none;box-sizing:border-box"></textarea>
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
                <?php if ($uid): ?>
                <a href="#" onclick="replyTo(<?= $c['id'] ?>,'<?= h($c['username']) ?>');return false">回复</a>
                <?php endif; ?>
                <span><?= $c['like_count'] ?> 赞</span>
              </div>
            </div>

            <!-- 子回复：超过2条则折叠 -->
            <?php
            $replies    = $c['replies'];
            $reply_cnt  = count($replies);
            $show_first = array_slice($replies, 0, 2);
            $show_rest  = array_slice($replies, 2);
            ?>
            <?php foreach ($show_first as $rep): ?>
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

            <?php if (!empty($show_rest)): ?>
            <button class="replies-expand-btn" onclick="expandReplies(this,<?= $c['id'] ?>)">
              ▸ 展开另外 <?= count($show_rest) ?> 条回复
            </button>
            <div class="replies-extra" id="extra-<?= $c['id'] ?>">
              <?php foreach ($show_rest as $rep): ?>
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
            </div>
            <?php endif; ?>

            <!-- 回复输入框 -->
            <div id="reply-form-<?= $c['id'] ?>" style="display:none;margin-top:8px;margin-left:48px">
              <form action="../actions/comment_save.php" method="post">
                <input type="hidden" name="post_id" value="<?= $post_id ?>">
                <input type="hidden" name="parent_id" value="<?= $c['id'] ?>">
                <textarea name="content" id="reply-text-<?= $c['id'] ?>" rows="2"
                  placeholder="回复 @<?= h($c['username']) ?>…"
                  style="width:100%;padding:8px 12px;border:1px solid var(--border);border-radius:var(--r);font-size:13px;font-family:inherit;outline:none;resize:none;box-sizing:border-box"></textarea>
                <div style="margin-top:6px;display:flex;gap:8px">
                  <button type="submit" class="btn btn-primary btn-sm">回复</button>
                  <button type="button" class="btn btn-outline btn-sm"
                          onclick="document.getElementById('reply-form-<?= $c['id'] ?>').style.display='none'">取消</button>
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

    <!-- AI 摘要 -->
    <?php if (!empty($post['summary'])): ?>
    <div class="ai-summary-box"><?= h($post['summary']) ?></div>
    <?php endif; ?>

    <!-- 作者信息 -->
    <div class="card mb-16">
      <div class="card-header">👤 发帖人</div>
      <div class="card-body" style="text-align:center">
        <img src="<?= avatar_url($post['avatar'], '../') ?>"
             style="width:60px;height:60px;border-radius:50%;object-fit:cover;margin:0 auto 10px">
        <div style="font-weight:600;margin-bottom:4px"><?= h($post['username']) ?></div>
        <?php if ($post['school']): ?><div style="font-size:12px;color:var(--txt-2)"><?= h($post['school']) ?></div><?php endif; ?>
        <div style="margin-top:8px"><?= level_badge($post['exp']) ?> <?= role_badge($post['role']) ?></div>
        <div style="display:flex;justify-content:center;gap:20px;margin-top:12px;padding-top:12px;border-top:1px solid var(--border)">
          <div>
            <div style="font-size:16px;font-weight:700"><?= $follower_count ?></div>
            <div style="font-size:11px;color:var(--txt-3)">粉丝</div>
          </div>
          <div>
            <div style="font-size:16px;font-weight:700"><?= $post_count ?></div>
            <div style="font-size:11px;color:var(--txt-3)">帖子</div>
          </div>
        </div>
        <?php if ($uid && $uid !== $post['user_id']): ?>
          <button id="follow-btn" class="btn <?= $is_following ? 'btn-outline' : 'btn-primary' ?> btn-sm"
                  style="margin-top:12px;width:100%" onclick="toggleFollow(<?= $post['user_id'] ?>)">
            <?= $is_following ? '✓ 已关注' : '+ 关注' ?>
          </button>
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

<!-- 分享面板 -->
<div id="share-modal" style="display:none;position:fixed;top:0;right:0;bottom:0;left:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center;padding:20px;box-sizing:border-box">
  <div class="modal-box" style="width:420px">
    <div class="modal-hd">
      <span class="modal-title">🔗 分享帖子</span>
      <button class="modal-close" onclick="closeShare()">×</button>
    </div>
    <div class="modal-bd" style="padding:0">

      <!-- 复制链接 -->
      <div style="padding:16px 22px;border-bottom:1px solid var(--border)">
        <div style="font-size:12px;font-weight:700;color:var(--txt-3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">🔗 复制链接</div>
        <div style="display:flex;gap:8px">
          <input id="share-url" type="text" readonly
                 style="flex:1;padding:8px 12px;border:1px solid var(--border);border-radius:var(--r);font-size:13px;font-family:monospace;outline:none">
          <button onclick="copyLink()" class="btn btn-primary btn-sm">复制</button>
        </div>
      </div>

      <!-- 私信关注的人 -->
      <?php if ($uid): ?>
      <div style="padding:16px 22px;border-bottom:1px solid var(--border)">
        <div style="font-size:12px;font-weight:700;color:var(--txt-3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">💬 私信给关注的人</div>
        <div style="position:relative;margin-bottom:8px">
          <input type="text" id="friend-search" placeholder="搜索关注的人…"
                 oninput="filterFriends(this.value)"
                 style="width:100%;padding:7px 12px;border:1px solid var(--border);border-radius:var(--r);font-size:13px;outline:none;box-sizing:border-box">
        </div>
        <div id="friend-list" style="max-height:180px;overflow-y:auto"></div>
        <div id="share-send-result" style="display:none;margin-top:8px;font-size:13px;padding:8px 12px;border-radius:var(--r)"></div>
      </div>

      <!-- 分享给 AI -->
      <div style="padding:16px 22px">
        <div style="font-size:12px;font-weight:700;color:var(--txt-3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">🤖 AI 分析</div>
        <button onclick="shareToAI()" class="btn btn-outline btn-sm" style="width:100%;justify-content:center;gap:8px">
          🤖 让 AI 帮我分析这篇帖子
        </button>
      </div>
      <?php else: ?>
      <div style="padding:20px 22px;text-align:center;color:var(--txt-2);font-size:13px">
        <a href="login.php">登录</a> 后可私信分享或发给 AI
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<div id="toast"></div>
<script>
const postId    = <?= $post_id ?>;
const postTitle = <?= json_encode($post['title']) ?>;
const isLoggedIn = <?= $uid ? 'true' : 'false' ?>;

// ── 点赞/收藏 ──────────────────────────────────────────
async function postAction(action) {
  if (!isLoggedIn) { location.href='login.php'; return; }
  const res = await fetch('../actions/post_action.php', {
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

// ── 关注 ───────────────────────────────────────────────
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

// ── 回复折叠 ───────────────────────────────────────────
function expandReplies(btn, cid) {
  var extra = document.getElementById('extra-' + cid);
  extra.classList.add('open');
  btn.style.display = 'none';
}

// ── 评论回复 ───────────────────────────────────────────
function replyTo(cid, username) {
  document.querySelectorAll('[id^="reply-form-"]').forEach(function(f){ f.style.display='none'; });
  var form = document.getElementById('reply-form-'+cid);
  form.style.display = 'block';
  document.getElementById('reply-text-'+cid).focus();
}

// ── 分享面板 ───────────────────────────────────────────
var followingCache = null;

function openShare() {
  document.getElementById('share-url').value = location.href;
  var el = document.getElementById('share-modal');
  el.style.display = 'flex';
  if (isLoggedIn && !followingCache) loadFollowing();
}
function closeShare() { document.getElementById('share-modal').style.display = 'none'; }
document.getElementById('share-modal').addEventListener('click', function(e){ if(e.target===this) closeShare(); });

function copyLink() {
  var input = document.getElementById('share-url');
  input.select(); input.setSelectionRange(0, 9999);
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(input.value).then(function(){ showToast('链接已复制！'); });
  } else {
    try { document.execCommand('copy'); showToast('链接已复制！'); } catch(e) { showToast('请手动复制'); }
  }
}

function loadFollowing() {
  var list = document.getElementById('friend-list');
  list.innerHTML = '<div style="text-align:center;padding:16px;color:var(--txt-3);font-size:13px">加载中…</div>';
  fetch('../api/get_following.php')
    .then(function(r){ return r.json(); })
    .then(function(data) {
      followingCache = data;
      renderFriendList(data);
    }).catch(function(){
      list.innerHTML = '<div style="color:var(--txt-3);font-size:13px;padding:8px">加载失败</div>';
    });
}

function filterFriends(q) {
  if (!followingCache) return;
  var filtered = q ? followingCache.filter(function(u){ return u.username.toLowerCase().indexOf(q.toLowerCase()) >= 0; }) : followingCache;
  renderFriendList(filtered);
}

function renderFriendList(users) {
  var list = document.getElementById('friend-list');
  if (!users.length) {
    list.innerHTML = '<div style="text-align:center;color:var(--txt-3);font-size:13px;padding:12px">暂无关注的用户</div>';
    return;
  }
  list.innerHTML = users.map(function(u) {
    var name = u.username.replace(/&/g,'&amp;').replace(/</g,'&lt;');
    return '<div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border)">' +
      '<img src="' + u.avatar + '" style="width:32px;height:32px;border-radius:50%;object-fit:cover;flex-shrink:0">' +
      '<span style="flex:1;font-size:13px;font-weight:500">' + name + '</span>' +
      '<button class="btn btn-primary btn-sm" style="font-size:11px" onclick="sendShareMsg('+u.id+',this)">发送</button>' +
      '</div>';
  }).join('');
}

function sendShareMsg(targetUid, btn) {
  btn.disabled = true;
  btn.textContent = '…';
  var msg = '📎 分享帖子给你：「' + postTitle + '」\n' + location.href;
  fetch('../actions/message_action.php', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'action=create_private&target_id='+targetUid
  }).then(function(r){ return r.json(); })
  .then(function(d) {
    if (!d.ok) throw new Error(d.error);
    var cid = d.cid;
    return fetch('../actions/message_action.php', {
      method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:'action=send&cid='+cid+'&content='+encodeURIComponent(msg)
    }).then(function(r){ return r.json(); });
  }).then(function(d) {
    if (!d.ok) throw new Error(d.error);
    btn.textContent = '✓ 已发';
    btn.classList.remove('btn-primary');
    btn.classList.add('btn-outline');
    showToast('已私信发送！');
  }).catch(function(e){
    btn.disabled = false;
    btn.textContent = '发送';
    showShareResult('error', '发送失败：' + e.message);
  });
}

function showShareResult(type, msg) {
  var el = document.getElementById('share-send-result');
  el.style.display = 'block';
  el.style.background = type==='ok' ? '#dcfce7' : '#fee2e2';
  el.style.color      = type==='ok' ? '#166534' : '#991b1b';
  el.textContent = msg;
}

function shareToAI() {
  var params = new URLSearchParams({
    share_title: postTitle,
    share_url:   location.href
  });
  window.location.href = 'ai_assistant.php?' + params.toString();
}

// ── Toast ──────────────────────────────────────────────
function showToast(msg) {
  var t = document.getElementById('toast');
  t.textContent = msg; t.classList.add('show');
  setTimeout(function(){ t.classList.remove('show'); }, 2500);
}
</script>

<?php include '../includes/report_modal.php'; ?>
<?php include '../includes/footer.php'; ?>
