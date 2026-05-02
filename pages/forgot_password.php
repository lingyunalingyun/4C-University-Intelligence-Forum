<?php
/*
 * pages/forgot_password.php — 忘记密码 / 重置密码页面
 * 功能：发送重置链接到邮箱（防枚举：响应统一），token 1小时有效，
 *       重置密码表单验证新密码并更新。
 * 权限：无需登录
 */
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$step = $_GET['step'] ?? '1'; // 1=输入邮箱 2=输入验证码+新密码
$msg  = $_GET['msg'] ?? '';
$err  = $_GET['err'] ?? '';

$page_title = '找回密码';
include '../includes/header.php';
?>

<div style="max-width:460px;margin:40px auto">
  <div class="card">
    <div class="card-header" style="text-align:center"><i data-lucide="key" class="lucide"></i> 找回密码</div>
    <div class="card-body">

      <?php if ($msg): ?><div class="alert alert-success mb-16"><?= h($msg) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="alert alert-danger  mb-16"><?= h($err) ?></div><?php endif; ?>

      <?php if ($step === '1'): ?>
      <p style="font-size:14px;color:var(--txt-2);margin-bottom:20px">输入注册邮箱，我们将发送验证码。</p>
      <form action="../actions/forgot_pw.php" method="post">
        <input type="hidden" name="step" value="1">
        <div class="form-group">
          <label>注册邮箱</label>
          <input type="email" name="email" required placeholder="your@email.com">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%">发送验证码</button>
      </form>

      <?php else: ?>
      <p style="font-size:14px;color:var(--txt-2);margin-bottom:20px">验证码已发送到你的邮箱，请查收。</p>
      <form action="../actions/forgot_pw.php" method="post">
        <input type="hidden" name="step" value="2">
        <div class="form-group">
          <label>验证码（6位）</label>
          <input type="text" name="code" required maxlength="6" placeholder="123456">
        </div>
        <div class="form-group">
          <label>新密码</label>
          <input type="password" name="new_password" required minlength="6">
        </div>
        <div class="form-group">
          <label>确认新密码</label>
          <input type="password" name="confirm_password" required minlength="6">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%">重置密码</button>
      </form>
      <?php endif; ?>

      <div style="text-align:center;margin-top:16px;font-size:13px">
        <a href="login.php">返回登录</a>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
