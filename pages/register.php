<?php
/*
 * pages/register.php — 用户注册页
 * 功能：展示注册表单（含学校选择器），POST 提交到 actions/auth.php（action=register）。
 * 权限：无需登录，已登录自动跳首页
 */
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit; }
$page_title = '注册';
$error = $_GET['error'] ?? '';
include '../includes/header.php';
?>

<div class="form-page">
  <div class="form-card">
    <div class="text-center" style="margin-bottom:24px">
      <div style="font-size:40px"><i data-lucide="sparkles" class="lucide"></i></div>
      <h1 class="form-title">创建账号</h1>
      <p class="form-sub">加入高校智慧，开始交流</p>
    </div>

    <?php
    $errors = [
      'exists_email'  => '该邮箱已被注册',
      'exists_user'   => '该用户名已被使用',
      'password_mismatch' => '两次密码不一致',
      'short_password'=> '密码至少6位',
      'invalid_email' => '请输入有效的邮箱地址',
    ];
    if (!empty($error) && isset($errors[$error])): ?>
      <div class="form-error"><?= $errors[$error] ?></div>
    <?php endif; ?>

    <form action="../actions/auth.php" method="post">
      <input type="hidden" name="action" value="register">

      <div class="form-group">
        <label>用户名</label>
        <input type="text" name="username" required placeholder="2-20个字符" minlength="2" maxlength="20">
        <div class="form-hint">用于展示，可包含中英文、数字、下划线</div>
      </div>
      <div class="form-group">
        <label>邮箱</label>
        <input type="email" name="email" required placeholder="your@email.com" autocomplete="email">
      </div>
      <div class="form-group">
        <label>学校（选填）</label>
        <div class="school-picker" id="sp">
          <input type="text" id="sp-search" autocomplete="off" placeholder="输入关键词搜索学校…">
          <input type="hidden" name="school" id="sp-value">
          <div class="school-dropdown" id="sp-drop"></div>
        </div>
        <div class="form-hint">选择后，首页默认显示本校内容</div>
      </div>
      <div class="form-group">
        <label>密码</label>
        <input type="password" name="password" required placeholder="至少6位" minlength="6" autocomplete="new-password">
      </div>
      <div class="form-group">
        <label>确认密码</label>
        <input type="password" name="password2" required placeholder="再输一遍" minlength="6">
      </div>

      <button type="submit" class="btn btn-primary btn-block" style="margin-top:8px">注册</button>
    </form>

    <div class="form-footer">
      已有账号？ <a href="login.php">立即登录</a>
    </div>
  </div>
</div>

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
