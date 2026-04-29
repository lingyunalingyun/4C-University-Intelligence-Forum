<?php
/*
 * actions/club_action.php — 社团相关动作 AJAX 接口
 * 功能：申请加入/退出/审批/改名/转让/解散社团，发布/删除社团动态。
 * 写库：clubs / club_members / club_posts / admin_logs
 * 权限：不同子操作有不同权限（成员/社长/管理员）
 */
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../pages/login.php'); exit;
}

$uid    = intval($_SESSION['user_id']);
$action = $_POST['action'] ?? '';

function redirect_back($path, $msg = '', $err = '') {
    $q = $msg ? 'msg='.urlencode($msg) : ($err ? 'err='.urlencode($err) : '');
    header('Location: ' . $path . ($q ? '?'.$q : '')); exit;
}

function get_club($conn, $club_id) {
    $r = $conn->query("SELECT * FROM clubs WHERE id=$club_id");
    return $r ? $r->fetch_assoc() : null;
}

function get_my_role($conn, $club_id, $uid) {
    $r = $conn->query("SELECT role FROM club_members WHERE club_id=$club_id AND user_id=$uid");
    return ($r && $r->num_rows) ? $r->fetch_assoc()['role'] : null;
}

function save_club_avatar($conn) {
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) return null;
    $file = $_FILES['avatar'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) return null;
    if ($file['size'] > 3 * 1024 * 1024) return null;
    $dir = __DIR__ . '/../uploads/clubs/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $fn = 'club_' . uniqid() . '.' . $ext;
    return move_uploaded_file($file['tmp_name'], $dir . $fn) ? $fn : null;
}

function save_club_banner() {
    if (!isset($_FILES['banner']) || $_FILES['banner']['error'] !== UPLOAD_ERR_OK) return null;
    $file = $_FILES['banner'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) return null;
    if ($file['size'] > 5 * 1024 * 1024) return null;
    $dir = __DIR__ . '/../uploads/clubs/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $fn = 'banner_' . uniqid() . '.' . $ext;
    return move_uploaded_file($file['tmp_name'], $dir . $fn) ? $fn : null;
}

// ── 申请创建社团 ──────────────────────────────────────────
if ($action === 'apply_create') {
    $name = trim($_POST['name']        ?? '');
    $desc = trim($_POST['description'] ?? '');
    $purp = trim($_POST['purpose']     ?? '');

    $sch_res = $conn->query("SELECT school FROM users WHERE id=$uid");
    $school  = $sch_res ? ($sch_res->fetch_assoc()['school'] ?? '') : '';

    if (!$name || !$school || !$desc || !$purp) {
        redirect_back('../pages/club_apply.php', '', !$school ? '请先在账号设置中填写学校' : '请填写所有必填项');
    }

    // 必须上传头图
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        redirect_back('../pages/club_apply.php', '', '请上传社团图片');
    }
    $avatar = save_club_avatar($conn);
    if (!$avatar) {
        redirect_back('../pages/club_apply.php', '', '图片格式不支持或超过3MB，请重新上传');
    }

    $chk = $conn->query("SELECT id FROM clubs WHERE name='" . $conn->real_escape_string($name) . "'");
    if ($chk && $chk->num_rows) {
        redirect_back('../pages/club_apply.php', '', '已存在同名社团');
    }
    $pend = $conn->query("SELECT id FROM club_applications WHERE user_id=$uid AND status='pending'");
    if ($pend && $pend->num_rows) {
        redirect_back('../pages/club_apply.php', '', '您有一个申请正在审核中');
    }

    $stmt = $conn->prepare("INSERT INTO club_applications (user_id,name,school,description,purpose,avatar) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param('isssss', $uid, $name, $school, $desc, $purp, $avatar);
    $stmt->execute(); $stmt->close();
    redirect_back('../pages/club_apply.php', '申请已提交，请等待管理员审核');
}

// ── 社长编辑社团 ──────────────────────────────────────────
if ($action === 'club_edit') {
    $club_id = intval($_POST['club_id'] ?? 0);
    $club    = get_club($conn, $club_id);
    if (!$club) redirect_back('../pages/clubs.php', '', '社团不存在');

    $my_role = get_my_role($conn, $club_id, $uid);
    if ($my_role !== 'president') redirect_back('../pages/club.php?id='.$club_id, '', '只有社长才能编辑社团');

    $back = '../pages/club_edit.php?id=' . $club_id;
    $msgs = [];

    // 更换背景图
    if (isset($_FILES['banner']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
        $fn = save_club_banner();
        if ($fn) {
            $fn_esc = $conn->real_escape_string($fn);
            $conn->query("UPDATE clubs SET banner='$fn_esc' WHERE id=$club_id");
            $msgs[] = '背景图已更新';
        }
    }

    // 更换头图
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $fn = save_club_avatar($conn);
        if ($fn) {
            $fn_esc = $conn->real_escape_string($fn);
            $conn->query("UPDATE clubs SET avatar='$fn_esc' WHERE id=$club_id");
            $msgs[] = '图片已更新';
        }
    }

    // 更新简介（直接保存）
    $new_desc = trim($_POST['description'] ?? '');
    if ($new_desc !== $club['description']) {
        $desc_esc = $conn->real_escape_string($new_desc);
        $conn->query("UPDATE clubs SET description='$desc_esc' WHERE id=$club_id");
        $msgs[] = '简介已更新';
    }

    // 改名需要管理员审核
    $new_name = trim($_POST['name'] ?? '');
    if ($new_name && $new_name !== $club['name']) {
        $dup = $conn->query("SELECT id FROM clubs WHERE name='" . $conn->real_escape_string($new_name) . "' AND id!=$club_id");
        if ($dup && $dup->num_rows) redirect_back($back, '', '已存在同名社团');

        $pend_nc = $conn->query("SELECT id FROM club_name_changes WHERE club_id=$club_id AND status='pending'");
        if ($pend_nc && $pend_nc->num_rows) redirect_back($back, '', '已有一个改名申请正在审核中');

        $old_esc = $conn->real_escape_string($club['name']);
        $new_esc = $conn->real_escape_string($new_name);
        $conn->query("INSERT INTO club_name_changes (club_id,user_id,old_name,new_name) VALUES ($club_id,$uid,'$old_esc','$new_esc')");
        $msgs[] = '改名申请已提交，等待管理员审核';
    }

    $msg = $msgs ? implode('；', $msgs) : '无变更';
    redirect_back($back, $msg);
}

// ── 后续操作需要 club_id ──────────────────────────────────
$club_id = intval($_POST['club_id'] ?? 0);
$back    = '../pages/club.php?id=' . $club_id;

// ── 申请加入 ─────────────────────────────────────────────
if ($action === 'request_join') {
    $club = get_club($conn, $club_id);
    if (!$club || $club['status'] !== 'active') redirect_back('../pages/clubs.php', '', '社团不存在');

    $u_res = $conn->query("SELECT school FROM users WHERE id=$uid");
    $u_school = $u_res ? $u_res->fetch_assoc()['school'] : '';
    if ($u_school !== $club['school']) redirect_back($back, '', '您的学校与社团不符，无法加入');

    if (get_my_role($conn, $club_id, $uid)) redirect_back($back, '', '您已是社团成员');

    $already = $conn->query("SELECT c.name FROM club_members cm JOIN clubs c ON c.id=cm.club_id WHERE cm.user_id=$uid LIMIT 1");
    if ($already && $already->num_rows) {
        $aname = $already->fetch_assoc()['name'];
        redirect_back($back, '', "每人只能加入一个社团，您已加入「{$aname}」");
    }

    $msg_esc = $conn->real_escape_string(trim($_POST['message'] ?? ''));
    $conn->query("INSERT INTO club_join_requests (club_id,user_id,message) VALUES ($club_id,$uid,'$msg_esc')
        ON DUPLICATE KEY UPDATE status='pending',message='$msg_esc',reviewed_by=NULL,reject_reason=NULL,reviewed_at=NULL,created_at=NOW()");
    redirect_back($back, '申请已提交，等待社长/副社长审核');
}

// ── 撤回申请 ──────────────────────────────────────────────
if ($action === 'cancel_join') {
    $conn->query("DELETE FROM club_join_requests WHERE club_id=$club_id AND user_id=$uid AND status='pending'");
    redirect_back($back, '已撤回申请');
}

// ── 批准入团 ──────────────────────────────────────────────
if ($action === 'approve_join') {
    $my_role = get_my_role($conn, $club_id, $uid);
    if (!in_array($my_role, ['president','vice_president'])) redirect_back($back, '', '无权限');

    $req_id = intval($_POST['req_id'] ?? 0);
    $req_r  = $conn->query("SELECT * FROM club_join_requests WHERE id=$req_id AND club_id=$club_id AND status='pending'");
    $req    = $req_r ? $req_r->fetch_assoc() : null;
    if (!$req) redirect_back($back, '', '申请不存在');

    $target_uid = intval($req['user_id']);
    $club = get_club($conn, $club_id);
    $u_res = $conn->query("SELECT school FROM users WHERE id=$target_uid");
    $u_school = $u_res ? $u_res->fetch_assoc()['school'] : '';
    if ($u_school !== $club['school']) {
        $conn->query("UPDATE club_join_requests SET status='rejected',reviewed_by=$uid,reject_reason='学校不符',reviewed_at=NOW() WHERE id=$req_id");
        redirect_back($back, '', '该用户学校不符，已自动拒绝');
    }
    $already = $conn->query("SELECT c.name FROM club_members cm JOIN clubs c ON c.id=cm.club_id WHERE cm.user_id=$target_uid LIMIT 1");
    if ($already && $already->num_rows) {
        $aname = $already->fetch_assoc()['name'];
        $conn->query("UPDATE club_join_requests SET status='rejected',reviewed_by=$uid,reject_reason='已加入其他社团',reviewed_at=NOW() WHERE id=$req_id");
        redirect_back($back, '', "该用户已加入「{$aname}」，每人只能加入一个社团，已自动拒绝");
    }

    $conn->query("INSERT IGNORE INTO club_members (club_id,user_id,role) VALUES ($club_id,$target_uid,'member')");
    $conn->query("UPDATE club_join_requests SET status='approved',reviewed_by=$uid,reviewed_at=NOW() WHERE id=$req_id");
    $conn->query("UPDATE clubs SET member_count=member_count+1 WHERE id=$club_id");
    redirect_back($back, '已批准入团');
}

// ── 拒绝入团 ──────────────────────────────────────────────
if ($action === 'reject_join') {
    $my_role = get_my_role($conn, $club_id, $uid);
    if (!in_array($my_role, ['president','vice_president'])) redirect_back($back, '', '无权限');

    $req_id = intval($_POST['req_id'] ?? 0);
    $reason = $conn->real_escape_string(trim($_POST['reject_reason'] ?? ''));
    $conn->query("UPDATE club_join_requests SET status='rejected',reviewed_by=$uid,reject_reason='$reason',reviewed_at=NOW() WHERE id=$req_id AND club_id=$club_id");
    redirect_back($back, '已拒绝申请');
}

// ── 踢出成员 ──────────────────────────────────────────────
if ($action === 'kick') {
    $my_role = get_my_role($conn, $club_id, $uid);
    if ($my_role !== 'president') redirect_back($back, '', '只有社长才能踢出成员');

    $target_uid = intval($_POST['user_id'] ?? 0);
    $reason     = trim($_POST['reason'] ?? '');
    if (!$reason) redirect_back($back, '', '踢出原因不能为空');
    if ($target_uid === $uid) redirect_back($back, '', '不能踢出自己');

    if (!get_my_role($conn, $club_id, $target_uid)) redirect_back($back, '', '该用户不是成员');

    $conn->query("DELETE FROM club_members WHERE club_id=$club_id AND user_id=$target_uid");
    $conn->query("UPDATE clubs SET member_count=GREATEST(member_count-1,1) WHERE id=$club_id");
    $r_esc = $conn->real_escape_string($reason);
    $conn->query("INSERT INTO club_kick_logs (club_id,user_id,kicked_by,reason) VALUES ($club_id,$target_uid,$uid,'$r_esc')");
    redirect_back($back, '已踢出成员');
}

// ── 设置/取消副社长 ───────────────────────────────────────
if ($action === 'set_role') {
    $my_role = get_my_role($conn, $club_id, $uid);
    if ($my_role !== 'president') redirect_back($back, '', '只有社长才能设置副社长');

    $target_uid = intval($_POST['user_id'] ?? 0);
    $new_role   = ($_POST['role'] ?? '') === 'vice_president' ? 'vice_president' : 'member';
    if ($target_uid === $uid) redirect_back($back, '', '不能修改自己的角色');

    $conn->query("UPDATE club_members SET role='$new_role' WHERE club_id=$club_id AND user_id=$target_uid");
    redirect_back($back, $new_role === 'vice_president' ? '已设为副社长' : '已取消副社长');
}

// ── 移交社长 ──────────────────────────────────────────────
if ($action === 'transfer') {
    $my_role = get_my_role($conn, $club_id, $uid);
    if ($my_role !== 'president') redirect_back($back, '', '只有社长才能移交');

    $new_pid = intval($_POST['new_president_id'] ?? 0);
    if (!$new_pid || $new_pid === $uid) redirect_back($back, '', '请选择有效的接任者');
    if (!get_my_role($conn, $club_id, $new_pid)) redirect_back($back, '', '接任者不是成员');

    $conn->query("UPDATE club_members SET role='member'    WHERE club_id=$club_id AND user_id=$uid");
    $conn->query("UPDATE club_members SET role='president' WHERE club_id=$club_id AND user_id=$new_pid");
    $conn->query("UPDATE clubs SET president_id=$new_pid WHERE id=$club_id");
    redirect_back($back, '社长已移交');
}

// ── 退出社团 ──────────────────────────────────────────────
if ($action === 'leave') {
    $my_role = get_my_role($conn, $club_id, $uid);
    if (!$my_role) redirect_back($back, '', '您不是成员');
    if ($my_role === 'president') redirect_back($back, '', '社长不能直接退出，请先移交社长');

    $conn->query("DELETE FROM club_members WHERE club_id=$club_id AND user_id=$uid");
    $conn->query("UPDATE clubs SET member_count=GREATEST(member_count-1,1) WHERE id=$club_id");
    redirect_back('../pages/clubs.php', '已退出社团');
}

// ── 管理员：批准建社申请 ──────────────────────────────────
if ($action === 'admin_approve') {
    if (!in_array($_SESSION['role'] ?? '', ['admin','owner'])) redirect_back('../admin/clubs.php', '', '无权限');

    $app_id = intval($_POST['app_id'] ?? 0);
    $app_r  = $conn->query("SELECT * FROM club_applications WHERE id=$app_id AND status='pending'");
    $app    = $app_r ? $app_r->fetch_assoc() : null;
    if (!$app) redirect_back('../admin/clubs.php', '', '申请不存在');

    $founder  = intval($app['user_id']);
    $n_esc    = $conn->real_escape_string($app['name']);
    $s_esc    = $conn->real_escape_string($app['school']);
    $d_esc    = $conn->real_escape_string($app['description']);
    $av_esc   = $conn->real_escape_string($app['avatar'] ?? '');

    $conn->query("INSERT INTO clubs (name,school,description,president_id,member_count,avatar)
        VALUES ('$n_esc','$s_esc','$d_esc',$founder,1,'$av_esc')");
    $new_club_id = $conn->insert_id;

    $conn->query("INSERT IGNORE INTO club_members (club_id,user_id,role) VALUES ($new_club_id,$founder,'president')");
    $conn->query("UPDATE club_applications SET status='approved',reviewed_by=$uid,reviewed_at=NOW(),club_id=$new_club_id WHERE id=$app_id");

    log_admin_action($conn, $uid, 'club_approve', 'club', $new_club_id, "批准社团「{$app['name']}」创建申请");
    redirect_back('../admin/clubs.php', '已批准，社团已创建');
}

// ── 管理员：拒绝建社申请 ──────────────────────────────────
if ($action === 'admin_reject') {
    if (!in_array($_SESSION['role'] ?? '', ['admin','owner'])) redirect_back('../admin/clubs.php', '', '无权限');

    $app_id = intval($_POST['app_id'] ?? 0);
    $reason = $conn->real_escape_string(trim($_POST['reject_reason'] ?? ''));
    $app_r  = $conn->query("SELECT name FROM club_applications WHERE id=$app_id AND status='pending'");
    $app    = $app_r ? $app_r->fetch_assoc() : null;

    $conn->query("UPDATE club_applications SET status='rejected',reviewed_by=$uid,reject_reason='$reason',reviewed_at=NOW() WHERE id=$app_id AND status='pending'");
    if ($app) log_admin_action($conn, $uid, 'club_reject', 'club_application', $app_id, "拒绝社团「{$app['name']}」申请，原因：$reason");
    redirect_back('../admin/clubs.php', '已拒绝申请');
}

// ── 管理员：停用/启用社团 ────────────────────────────────
if ($action === 'admin_toggle') {
    if (!in_array($_SESSION['role'] ?? '', ['admin','owner'])) redirect_back('../admin/clubs.php', '', '无权限');

    $club = get_club($conn, $club_id);
    $conn->query("UPDATE clubs SET status=IF(status='active','inactive','active') WHERE id=$club_id");
    $new_status = ($club && $club['status'] === 'active') ? 'inactive' : 'active';
    $label = $new_status === 'inactive' ? '停用' : '启用';
    if ($club) log_admin_action($conn, $uid, 'club_'.$label, 'club', $club_id, "{$label}社团「{$club['name']}」");
    redirect_back('../admin/clubs.php?tab=clubs', '操作成功');
}

// ── 管理员：批准改名申请 ──────────────────────────────────
if ($action === 'admin_approve_name') {
    if (!in_array($_SESSION['role'] ?? '', ['admin','owner'])) redirect_back('../admin/clubs.php', '', '无权限');

    $nc_id = intval($_POST['name_change_id'] ?? $_POST['nc_id'] ?? 0);
    $nc_r  = $conn->query("SELECT * FROM club_name_changes WHERE id=$nc_id AND status='pending'");
    $nc    = $nc_r ? $nc_r->fetch_assoc() : null;
    if (!$nc) redirect_back('../admin/clubs.php?tab=names', '', '申请不存在');

    $new_esc = $conn->real_escape_string($nc['new_name']);
    $conn->query("UPDATE clubs SET name='$new_esc' WHERE id={$nc['club_id']}");
    $conn->query("UPDATE club_name_changes SET status='approved',reviewed_by=$uid,reviewed_at=NOW() WHERE id=$nc_id");

    log_admin_action($conn, $uid, 'approve_name_change', 'club', $nc['club_id'],
        "批准社团改名：「{$nc['old_name']}」→「{$nc['new_name']}」");
    redirect_back('../admin/clubs.php?tab=names', '已批准改名');
}

// ── 管理员：拒绝改名申请 ──────────────────────────────────
if ($action === 'admin_reject_name') {
    if (!in_array($_SESSION['role'] ?? '', ['admin','owner'])) redirect_back('../admin/clubs.php', '', '无权限');

    $nc_id  = intval($_POST['name_change_id'] ?? $_POST['nc_id'] ?? 0);
    $reason = $conn->real_escape_string(trim($_POST['reject_reason'] ?? ''));
    $nc_r   = $conn->query("SELECT * FROM club_name_changes WHERE id=$nc_id AND status='pending'");
    $nc     = $nc_r ? $nc_r->fetch_assoc() : null;
    if ($nc) {
        $conn->query("UPDATE club_name_changes SET status='rejected',reviewed_by=$uid,reject_reason='$reason',reviewed_at=NOW() WHERE id=$nc_id");
        log_admin_action($conn, $uid, 'reject_name_change', 'club', $nc['club_id'],
            "拒绝改名：「{$nc['old_name']}」→「{$nc['new_name']}」，原因：$reason");
    }
    redirect_back('../admin/clubs.php?tab=names', '已拒绝改名申请');
}

// ── 以社团名义发布动态 ────────────────────────────────────
if ($action === 'club_post') {
    $club_id = intval($_POST['club_id'] ?? 0);
    $back    = '../pages/club.php?id=' . $club_id;
    $my_role = get_my_role($conn, $club_id, $uid);
    if (!in_array($my_role, ['president','vice_president'])) redirect_back($back, '', '只有社长/副社长才能发布动态');

    $title   = trim($_POST['title']   ?? '');
    $content = trim($_POST['content'] ?? '');
    if (!$title || !$content) redirect_back($back, '', '标题和内容不能为空');

    $t_esc = $conn->real_escape_string($title);
    $c_esc = $conn->real_escape_string($content);
    $conn->query("INSERT INTO club_posts (club_id,user_id,title,content) VALUES ($club_id,$uid,'$t_esc','$c_esc')");
    redirect_back($back, '动态已发布');
}

// ── 删除社团动态 ──────────────────────────────────────────
if ($action === 'club_post_delete') {
    $club_id      = intval($_POST['club_id']      ?? 0);
    $club_post_id = intval($_POST['club_post_id'] ?? 0);
    $back         = '../pages/club.php?id=' . $club_id;
    $my_role      = get_my_role($conn, $club_id, $uid);
    if (!in_array($my_role, ['president','vice_president'])) redirect_back($back, '', '无权限');

    $conn->query("DELETE FROM club_posts WHERE id=$club_post_id AND club_id=$club_id");
    redirect_back($back, '动态已删除');
}

redirect_back('../pages/clubs.php', '', '未知操作');
