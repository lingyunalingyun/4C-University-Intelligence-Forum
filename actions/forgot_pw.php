<?php
/*
 * actions/forgot_pw.php — 找回密码 / 重置密码 POST 处理
 * 功能：发送重置邮件（防枚举：无论邮箱是否存在均显示"已发送"），
 *       token 1小时有效，验证 token 后更新密码哈希。
 * 写库：users（reset_token / reset_token_expires / password）
 * 权限：无需登录
 */
require_once '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$step = $_POST['step'] ?? '1';

if ($step === '1') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: ../pages/forgot_password.php?err=邮箱格式不正确'); exit;
    }

    $stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
    $stmt->bind_param('s', $email); $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // 无论是否存在都提示成功（防止账号枚举）
    if ($user) {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', time() + 1800); // 30分钟有效

        $conn->query("DELETE FROM password_resets WHERE email='".addslashes($email)."'");
        $stmt = $conn->prepare("INSERT INTO password_resets (email,code,expires_at) VALUES (?,?,?)");
        $stmt->bind_param('sss', $email, $code, $expires); $stmt->execute(); $stmt->close();

        // 发送邮件（简单实现，实际需配置 mail 或 SMTP）
        $subject = '【'.SITE_NAME.'】找回密码验证码';
        $body    = "你的找回密码验证码是：$code\n有效期30分钟，请勿泄露。";
        @mail($email, $subject, $body, 'From: '.MAIL_FROM);
    }

    $_SESSION['reset_email'] = $email;
    header('Location: ../pages/forgot_password.php?step=2&msg=验证码已发送（如未收到请检查垃圾邮件）'); exit;

} elseif ($step === '2') {
    $email   = $_SESSION['reset_email'] ?? '';
    $code    = trim($_POST['code'] ?? '');
    $new_pw  = $_POST['new_password']     ?? '';
    $cfm_pw  = $_POST['confirm_password'] ?? '';

    if (!$email || !$code || !$new_pw) {
        header('Location: ../pages/forgot_password.php?step=2&err=请填写完整'); exit;
    }
    if ($new_pw !== $cfm_pw) {
        header('Location: ../pages/forgot_password.php?step=2&err=两次密码不一致'); exit;
    }
    if (strlen($new_pw) < 6) {
        header('Location: ../pages/forgot_password.php?step=2&err=密码至少6位'); exit;
    }

    $safe_email = $conn->real_escape_string($email);
    $safe_code  = $conn->real_escape_string($code);
    $r = $conn->query("SELECT * FROM password_resets WHERE email='$safe_email' AND code='$safe_code' AND expires_at > NOW()");
    if (!$r || $r->num_rows === 0) {
        header('Location: ../pages/forgot_password.php?step=2&err=验证码错误或已过期'); exit;
    }

    $hash = password_hash($new_pw, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password=? WHERE email=?");
    $stmt->bind_param('ss', $hash, $email); $stmt->execute(); $stmt->close();

    $conn->query("DELETE FROM password_resets WHERE email='$safe_email'");
    unset($_SESSION['reset_email']);

    header('Location: ../pages/login.php?msg=密码已重置，请重新登录'); exit;
} else {
    header('Location: ../pages/forgot_password.php'); exit;
}
