<?php
/*
 * actions/settings_save.php — 个人设置保存 POST 处理
 * 功能：保存昵称/简介/学校/头像/密码等个人资料，以及隐私设置。
 * 写库：users
 * 权限：需登录（只能修改自己的设置）
 */
require_once '../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../pages/login.php'); exit; }

$uid    = intval($_SESSION['user_id']);
$action = $_POST['action'] ?? '';

if ($action === 'profile') {
    $username = trim($_POST['username'] ?? '');
    $school   = trim($_POST['school']   ?? '');
    $bio      = trim($_POST['bio']      ?? '');

    if (!$username || mb_strlen($username) > 20) {
        header('Location: ../pages/settings.php?err=用户名无效'); exit;
    }

    // 用户名唯一性检查
    $stmt = $conn->prepare("SELECT id FROM users WHERE username=? AND id!=?");
    $stmt->bind_param('si', $username, $uid); $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        header('Location: ../pages/settings.php?err=用户名已被使用'); exit;
    }
    $stmt->close();

    // 头像上传
    $avatar = '';
    if (!empty($_FILES['avatar']['tmp_name'])) {
        $file  = $_FILES['avatar'];
        $ext   = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allow = ['jpg','jpeg','png','gif','webp'];
        if (!in_array($ext, $allow)) { header('Location: ../pages/settings.php?err=头像格式不支持'); exit; }
        if ($file['size'] > 2*1024*1024) { header('Location: ../pages/settings.php?err=头像不能超过2MB'); exit; }
        $avatar = uniqid('avatar_', true) . '.' . $ext;
        move_uploaded_file($file['tmp_name'], '../uploads/avatars/' . $avatar);
    }

    $cu = $conn->real_escape_string($username);
    $cs = $conn->real_escape_string($school);
    $cb = $conn->real_escape_string(mb_substr($bio, 0, 200));

    if ($avatar) {
        $ca = $conn->real_escape_string($avatar);
        $conn->query("UPDATE users SET username='$cu',school='$cs',bio='$cb',avatar='$ca' WHERE id=$uid");
        $_SESSION['avatar'] = $avatar;
    } else {
        $conn->query("UPDATE users SET username='$cu',school='$cs',bio='$cb' WHERE id=$uid");
    }
    $_SESSION['username'] = $username;

    header('Location: ../pages/settings.php?msg=信息已更新'); exit;

} elseif ($action === 'password') {
    $old = $_POST['old_password']     ?? '';
    $new = $_POST['new_password']     ?? '';
    $cfm = $_POST['confirm_password'] ?? '';

    if ($new !== $cfm) { header('Location: ../pages/settings.php?err=两次密码不一致'); exit; }
    if (strlen($new) < 6) { header('Location: ../pages/settings.php?err=密码至少6位'); exit; }

    $stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
    $stmt->bind_param('i', $uid); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || !password_verify($old, $row['password'])) {
        header('Location: ../pages/settings.php?err=当前密码错误'); exit;
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
    $stmt->bind_param('si', $hash, $uid); $stmt->execute(); $stmt->close();

    header('Location: ../pages/settings.php?msg=密码已修改'); exit;
} else {
    header('Location: ../pages/settings.php'); exit;
}
