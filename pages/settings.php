<?php
/*
 * pages/settings.php — 个人设置页面
 * 功能：修改头像/昵称/简介/学校/密码，设置隐私选项（关注列表可见性、
 *       帖子默认可见性），黑名单管理（列出/解除）。
 * 读库：users / user_blocks
 * 权限：需登录
 */
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php?redirect=settings.php'); exit; }

$uid = intval($_SESSION['user_id']);
$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param('i', $uid); $stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$page_title = '账号设置';
include '../includes/header.php';
?>

<div class="flex-center gap-8 mb-16" style="font-size:13px;color:var(--txt-2)">
  <a href="../index.php">首页</a> &rsaquo; <span>设置</span>
</div>

<?php if ($msg): ?><div class="alert alert-success mb-16"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger  mb-16"><?= h($err) ?></div><?php endif; ?>

<div class="layout-2col">
  <div class="col-main">

    <!-- 基本信息 -->
    <div class="card mb-20">
      <div class="card-header">👤 基本信息</div>
      <div class="card-body">
        <form action="../actions/settings_save.php" method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="profile">

          <div class="form-group" style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">
            <img id="avatar-preview"
                 src="<?= avatar_url($user['avatar'], '../') ?>"
                 style="width:72px;height:72px;border-radius:50%;object-fit:cover;cursor:pointer"
                 onclick="document.getElementById('avatar-input').click()">
            <div>
              <input type="file" id="avatar-input" name="avatar" accept="image/*" style="display:none"
                     onchange="previewAvatar(this)">
              <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('avatar-input').click()">更换头像</button>
              <div style="font-size:12px;color:var(--txt-3);margin-top:4px">支持 JPG/PNG，最大 2MB</div>
            </div>
          </div>

          <div class="form-group">
            <label>用户名</label>
            <input type="text" name="username" value="<?= h($user['username']) ?>" required maxlength="20">
          </div>
          <div class="form-group">
            <label>学校</label>
            <div class="school-picker" id="sp">
              <input type="text" id="sp-search" autocomplete="off"
                     placeholder="输入关键词搜索学校…"
                     value="<?= h($user['school']) ?>">
              <input type="hidden" name="school" id="sp-value" value="<?= h($user['school']) ?>">
              <div class="school-dropdown" id="sp-drop"></div>
            </div>
          </div>
          <div class="form-group">
            <label>个人简介</label>
            <textarea name="bio" rows="3" maxlength="200" placeholder="介绍一下自己..."><?= h($user['bio'] ?? '') ?></textarea>
          </div>
          <button type="submit" class="btn btn-primary">保存信息</button>
        </form>
      </div>
    </div>

    <!-- 修改密码 -->
    <div class="card">
      <div class="card-header">🔒 修改密码</div>
      <div class="card-body">
        <form action="../actions/settings_save.php" method="post">
          <input type="hidden" name="action" value="password">
          <div class="form-group">
            <label>当前密码</label>
            <input type="password" name="old_password" required>
          </div>
          <div class="form-group">
            <label>新密码</label>
            <input type="password" name="new_password" required minlength="6">
          </div>
          <div class="form-group">
            <label>确认新密码</label>
            <input type="password" name="confirm_password" required minlength="6">
          </div>
          <button type="submit" class="btn btn-primary">修改密码</button>
        </form>
      </div>
    </div>

  </div>

  <div class="col-side">
    <div class="card">
      <div class="card-header">📊 账号信息</div>
      <div class="card-body" style="font-size:13px;color:var(--txt-2);line-height:2">
        <div>邮箱：<?= h($user['email']) ?></div>
        <div>角色：<?= role_badge($user['role']) ?></div>
        <div>等级：<?= level_badge($user['exp']) ?></div>
        <div>经验：<?= $user['exp'] ?> EXP</div>
        <div>注册：<?= date('Y-m-d', strtotime($user['created_at'])) ?></div>
        <?php if ($user['login_streak']): ?>
        <div>连续签到：<?= $user['login_streak'] ?> 天 🔥</div>
        <?php endif; ?>
      </div>
    </div>
    <div class="card mt-16">
      <div class="card-body" style="font-size:13px;color:var(--txt-2)">
        <a href="profile.php?id=<?= $uid ?>" class="btn btn-outline" style="width:100%;display:block;text-align:center;margin-bottom:8px">查看我的主页</a>
        <a href="logout.php" class="btn btn-outline" style="width:100%;display:block;text-align:center;color:var(--danger)">退出登录</a>
      </div>
    </div>
  </div>
</div>

<script>
function previewAvatar(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => document.getElementById('avatar-preview').src = e.target.result;
    reader.readAsDataURL(input.files[0]);
  }
}
</script>

<?php
$schools = require '../config/schools.php';
?>
<script>
(function(){
  var schools = <?= json_encode($schools, JSON_UNESCAPED_UNICODE) ?>;
  var search = document.getElementById('sp-search');
  var val    = document.getElementById('sp-value');
  var drop   = document.getElementById('sp-drop');

  function render(q) {
    var list = q ? schools.filter(function(s){ return s.indexOf(q) !== -1; }) : schools;
    if (!list.length) { drop.style.display='none'; return; }
    drop.innerHTML = list.slice(0,30).map(function(s){
      var hl = q ? s.replace(q,'<mark>'+q+'</mark>') : s;
      return '<div class="school-opt" data-v="'+s+'">'+hl+'</div>';
    }).join('');
    drop.style.display = 'block';
  }

  search.addEventListener('input', function(){ render(search.value); });
  search.addEventListener('focus', function(){ render(search.value); });

  drop.addEventListener('click', function(e){
    var opt = e.target.closest('.school-opt');
    if (!opt) return;
    search.value = opt.dataset.v;
    val.value    = opt.dataset.v;
    drop.style.display = 'none';
  });

  document.addEventListener('click', function(e){
    if (!e.target.closest('#sp')) drop.style.display = 'none';
  });
})();
</script>
<?php include '../includes/footer.php'; ?>
