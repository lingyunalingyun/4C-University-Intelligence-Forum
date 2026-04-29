<?php
/*
 * actions/auth.php — 用户登录 / 注册 POST 处理
 * 功能：验证账密、写 Session、登录奖励EXP；注册时生成SCID、写users表。
 * 写库：users（登录更新last_login / 注册插入新行）
 * 权限：无需登录（登录/注册动作本身）
 */
require_once '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$action = $_POST['action'] ?? '';

if ($action === 'login') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $redirect = $_POST['redirect'] ?? '';

    $stmt = $conn->prepare("SELECT id,username,password,role,avatar,school,is_banned,email_verified FROM users WHERE email=?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($password, $user['password'])) {
        header('Location: ../pages/login.php?error=wrong'); exit;
    }
    if (EMAIL_VERIFY_REQUIRED && !$user['email_verified']) {
        header('Location: ../pages/login.php?error=unverified'); exit;
    }
    if ($user['is_banned']) {
        header('Location: ../pages/login.php?error=banned'); exit;
    }

    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role']     = $user['role'];
    $_SESSION['avatar']   = $user['avatar'];
    $_SESSION['school']   = $user['school'];

    // 登录经验 & 连续登录
    $today = date('Y-m-d');
    $row = $conn->query("SELECT login_streak,last_login_date FROM users WHERE id={$user['id']}")->fetch_assoc();
    $streak   = (int)$row['login_streak'];
    $last_day = $row['last_login_date'];
    if ($last_day !== $today) {
        $streak = ($last_day === date('Y-m-d', strtotime('-1 day'))) ? $streak + 1 : 1;
        $exp_gain = min($streak * 10, 100);
        $conn->query("UPDATE users SET login_streak=$streak, last_login_date='$today', exp=exp+$exp_gain WHERE id={$user['id']}");
    }

    $to = $redirect ?: '../index.php';
    header("Location: $to"); exit;
}

if ($action === 'register') {
    $username  = trim($_POST['username']  ?? '');
    $email     = trim($_POST['email']     ?? '');
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';
    $school    = trim($_POST['school']    ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: ../pages/register.php?error=invalid_email'); exit;
    }
    if (strlen($password) < 6) {
        header('Location: ../pages/register.php?error=short_password'); exit;
    }
    if ($password !== $password2) {
        header('Location: ../pages/register.php?error=password_mismatch'); exit;
    }

    $chk = $conn->prepare("SELECT id FROM users WHERE email=?");
    $chk->bind_param('s', $email); $chk->execute();
    if ($chk->get_result()->fetch_assoc()) { header('Location: ../pages/register.php?error=exists_email'); exit; }
    $chk->close();

    $chk2 = $conn->prepare("SELECT id FROM users WHERE username=?");
    $chk2->bind_param('s', $username); $chk2->execute();
    if ($chk2->get_result()->fetch_assoc()) { header('Location: ../pages/register.php?error=exists_user'); exit; }
    $chk2->close();

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $verified = EMAIL_VERIFY_REQUIRED ? 0 : 1;

    // 生成唯一 SCID
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $scid = '';
        for ($i = 0; $i < 8; $i++) $scid .= $chars[random_int(0, strlen($chars)-1)];
        $chk = $conn->query("SELECT id FROM users WHERE scid='$scid'");
    } while ($chk && $chk->num_rows > 0);

    $stmt = $conn->prepare("INSERT INTO users (scid,username,email,password,school,email_verified) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param('sssssi', $scid, $username, $email, $hashed, $school, $verified);
    $stmt->execute();
    $new_id = $stmt->insert_id;
    $stmt->close();

    $_SESSION['user_id']  = $new_id;
    $_SESSION['username'] = $username;
    $_SESSION['role']     = 'user';
    $_SESSION['avatar']   = '';
    $_SESSION['school']   = $school;

    header('Location: ../index.php'); exit;
}

if ($action === 'logout') {
    session_destroy();
    header('Location: ../index.php'); exit;
}

header('Location: ../index.php'); exit;
