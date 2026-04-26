<?php
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php?redirect=club_apply.php'); exit; }

$uid = intval($_SESSION['user_id']);
$u_res = $conn->query("SELECT school FROM users WHERE id=$uid");
$user_school = $u_res ? ($u_res->fetch_assoc()['school'] ?? '') : '';

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';
$page_title = '申请创建社团';
include '../includes/header.php';
?>

<div class="flex-center gap-8 mb-16" style="font-size:13px;color:var(--txt-2)">
  <a href="../index.php">首页</a> &rsaquo; <a href="clubs.php">社团</a> &rsaquo; <span>申请创建</span>
</div>

<div style="max-width:600px;margin:0 auto">
  <?php if ($msg): ?><div class="alert alert-success mb-16"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger  mb-16"><?= h($err) ?></div><?php endif; ?>

  <div class="card">
    <div class="card-header">🏛️ 申请创建社团</div>
    <div class="card-body">
      <div style="background:var(--bg-2);border:1px solid var(--border);border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:var(--txt-2)">
        申请提交后由管理员审核，通过后社团自动创建，您成为社长。
      </div>

      <?php if (!$user_school): ?>
      <div class="alert alert-danger mb-16">
        您尚未设置所在学校，请先前往 <a href="settings.php">账号设置</a> 填写学校后再申请创建社团。
      </div>
      <?php else: ?>
      <form action="../actions/club_action.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="apply_create">

        <div class="form-group">
          <label>社团图片 <span style="color:#ef4444">*</span></label>
          <div style="display:flex;align-items:center;gap:16px">
            <img id="avatar-preview" src="../assets/default_avatar.svg"
                 style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:2px solid var(--border)">
            <div>
              <input type="file" name="avatar" id="avatar-input" accept="image/*" required
                     style="display:none" onchange="previewImg(this)">
              <button type="button" class="btn btn-outline btn-sm"
                      onclick="document.getElementById('avatar-input').click()">选择图片</button>
              <div style="font-size:12px;color:var(--txt-3);margin-top:4px">JPG/PNG/WebP，最大 3MB，必填</div>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label>社团名称 <span style="color:#ef4444">*</span></label>
          <input type="text" name="name" required maxlength="50" placeholder="输入社团名称">
        </div>

        <div class="form-group">
          <label>附属学校</label>
          <div style="padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;background:var(--bg-2);color:var(--txt);font-size:14px">
            🏫 <?= h($user_school) ?>
          </div>
          <div style="font-size:12px;color:var(--txt-3);margin-top:4px">
            自动读取您的账号学校。社团与学校绑定，仅该校学生可加入。
          </div>
        </div>

        <div class="form-group">
          <label>社团简介 <span style="color:#ef4444">*</span></label>
          <textarea name="description" required maxlength="500" rows="3"
                    placeholder="介绍社团的主要活动和特色…"></textarea>
        </div>

        <div class="form-group">
          <label>创建目的 <span style="color:#ef4444">*</span></label>
          <textarea name="purpose" required maxlength="500" rows="3"
                    placeholder="说明创建这个社团的原因和目标…"></textarea>
        </div>

        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px">
          <a href="clubs.php" class="btn btn-outline">取消</a>
          <button type="submit" class="btn btn-primary">提交申请</button>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function previewImg(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatar-preview').src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
<?php include '../includes/footer.php'; ?>
