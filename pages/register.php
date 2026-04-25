<?php
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
      <div style="font-size:40px">✨</div>
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
        <input type="text" name="school" placeholder="如：桂林理工大学" maxlength="50">
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

<?php include '../includes/footer.php'; ?>
