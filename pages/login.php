<?php
/*
 * pages/login.php — 用户登录页
 * 功能：展示登录表单，POST 提交到 actions/auth.php（action=login）。
 * 权限：无需登录，已登录自动跳首页
 */
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit; }
$page_title = '登录';
$error = $_GET['error'] ?? '';
$redirect = $_GET['redirect'] ?? '';
include '../includes/header.php';
?>

<div class="form-page">
  <div class="form-card">
    <div class="text-center" style="margin-bottom:24px">
      <div style="font-size:40px"><i data-lucide="graduation-cap" class="lucide"></i></div>
      <h1 class="form-title">欢迎回来</h1>
      <p class="form-sub">登录你的高校智慧账号</p>
    </div>

    <?php if ($error === 'banned'): ?>
      <div class="form-error">⛔ 你的账号已被封禁，请联系管理员</div>
    <?php elseif ($error === 'wrong'): ?>
      <div class="form-error">邮箱或密码错误，请重试</div>
    <?php elseif ($error === 'unverified'): ?>
      <div class="form-error">请先验证你的邮箱</div>
    <?php endif; ?>

    <form action="../actions/auth.php" method="post">
      <input type="hidden" name="action" value="login">
      <input type="hidden" name="redirect" value="<?= h($redirect) ?>">

      <div class="form-group">
        <label>邮箱</label>
        <input type="email" name="email" required placeholder="your@email.com" autocomplete="email">
      </div>
      <div class="form-group">
        <label>密码</label>
        <input type="password" name="password" required placeholder="••••••••" autocomplete="current-password">
      </div>

      <button type="submit" class="btn btn-primary btn-block" style="margin-top:8px">登录</button>
    </form>

    <div style="text-align:right;margin-top:10px">
      <a href="forgot_password.php" style="font-size:13px;color:var(--txt-2)">忘记密码？</a>
    </div>

    <div class="form-footer">
      还没有账号？ <a href="register.php">立即注册</a>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
