<?php
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$uid    = intval($_SESSION['user_id']);
$cid    = intval($_GET['id'] ?? 0);
if (!$cid) { header('Location: my_clubs.php'); exit; }

$res = $conn->query("SELECT * FROM clubs WHERE id=$cid");
$club = $res ? $res->fetch_assoc() : null;
if (!$club || $club['president_id'] != $uid) {
    header('Location: my_clubs.php'); exit;
}

// 待审核改名申请
$pending_name = null;
$pnr = $conn->query("SELECT * FROM club_name_changes WHERE club_id=$cid AND status='pending' ORDER BY created_at DESC LIMIT 1");
if ($pnr) $pending_name = $pnr->fetch_assoc();

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';
$page_title = '管理社团 · ' . $club['name'];
include '../includes/header.php';
?>

<div class="flex-center gap-8 mb-16" style="font-size:13px;color:var(--txt-2)">
  <a href="../index.php">首页</a> &rsaquo;
  <a href="clubs.php">社团</a> &rsaquo;
  <a href="my_clubs.php">我的社团</a> &rsaquo;
  <span>管理</span>
</div>

<div style="max-width:600px;margin:0 auto">
  <?php if ($msg): ?><div class="alert alert-success mb-16"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger  mb-16"><?= h($err) ?></div><?php endif; ?>

  <div class="card">
    <div class="card-header">🏛️ 管理社团 — <?= h($club['name']) ?></div>
    <div class="card-body">
      <form action="../actions/club_action.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="action"  value="club_edit">
        <input type="hidden" name="club_id" value="<?= $cid ?>">

        <!-- 社团背景图 -->
        <div class="form-group">
          <label>社团背景图 <span style="font-size:11px;color:var(--txt-3);font-weight:400">（显示在社团列表卡片，建议 16:9 比例）</span></label>
          <div style="margin-bottom:10px">
            <?php if (!empty($club['banner'])): ?>
              <div id="banner-preview-wrap" style="position:relative;width:100%;aspect-ratio:16/9;border-radius:10px;overflow:hidden;background:var(--bg-2)">
                <img id="banner-preview" src="../uploads/clubs/<?= h($club['banner']) ?>"
                     style="width:100%;height:100%;object-fit:cover">
              </div>
            <?php else: ?>
              <div id="banner-preview-wrap" style="position:relative;width:100%;aspect-ratio:16/9;border-radius:10px;overflow:hidden;background:linear-gradient(135deg,var(--primary),var(--primary-dk));display:flex;align-items:center;justify-content:center">
                <img id="banner-preview" src="" style="width:100%;height:100%;object-fit:cover;display:none;position:absolute;inset:0">
                <span id="banner-placeholder" style="font-size:13px;color:rgba(255,255,255,.7)">未上传背景图，将使用渐变色</span>
              </div>
            <?php endif; ?>
          </div>
          <div style="display:flex;align-items:center;gap:10px">
            <input type="file" name="banner" id="banner-input" accept="image/*"
                   style="display:none" onchange="previewBanner(this)">
            <button type="button" class="btn btn-outline btn-sm"
                    onclick="document.getElementById('banner-input').click()">上传背景图</button>
            <span style="font-size:12px;color:var(--txt-3)">JPG/PNG/WebP，最大 5MB，未上传则以社团头像填充</span>
          </div>
        </div>

        <!-- 社团头像 -->
        <div class="form-group">
          <label>社团图片</label>
          <div style="display:flex;align-items:center;gap:16px">
            <?php if (!empty($club['avatar'])): ?>
              <img id="avatar-preview" src="../uploads/clubs/<?= h($club['avatar']) ?>"
                   style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:2px solid var(--border)">
            <?php else: ?>
              <div id="avatar-preview-default"
                   style="width:80px;height:80px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:700;color:#fff">
                <?= mb_substr($club['name'], 0, 1) ?>
              </div>
              <img id="avatar-preview" src="" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:2px solid var(--border);display:none">
            <?php endif; ?>
            <div>
              <input type="file" name="avatar" id="avatar-input" accept="image/*"
                     style="display:none" onchange="previewImg(this)">
              <button type="button" class="btn btn-outline btn-sm"
                      onclick="document.getElementById('avatar-input').click()">更换图片</button>
              <div style="font-size:12px;color:var(--txt-3);margin-top:4px">JPG/PNG/WebP，最大 3MB</div>
            </div>
          </div>
        </div>

        <!-- 社团名称 -->
        <div class="form-group">
          <label>社团名称</label>
          <?php if ($pending_name): ?>
            <div style="background:var(--bg-2);border:1.5px solid var(--border);border-radius:8px;padding:8px 12px;font-size:14px;color:var(--txt)">
              <?= h($club['name']) ?>
            </div>
            <div style="margin-top:6px;font-size:12px;color:#f59e0b">
              ⏳ 已提交改名申请「<?= h($pending_name['new_name']) ?>」，待管理员审核，审核期间不能再次申请。
            </div>
          <?php else: ?>
            <input type="text" name="name" value="<?= h($club['name']) ?>" maxlength="50" required>
            <div style="font-size:12px;color:var(--txt-3);margin-top:4px">
              修改名称需管理员审核后生效，期间原名称不变。
            </div>
          <?php endif; ?>
        </div>

        <!-- 社团简介 -->
        <div class="form-group">
          <label>社团简介</label>
          <textarea name="description" maxlength="500" rows="4"
                    placeholder="介绍社团的主要活动和特色…"><?= h($club['description'] ?? '') ?></textarea>
          <div style="font-size:12px;color:var(--txt-3);margin-top:4px">简介修改立即生效。</div>
        </div>

        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px">
          <a href="my_clubs.php" class="btn btn-outline">返回</a>
          <a href="club.php?id=<?= $cid ?>" class="btn btn-outline">查看社团</a>
          <button type="submit" class="btn btn-primary">保存更改</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function previewImg(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var preview = document.getElementById('avatar-preview');
            var def = document.getElementById('avatar-preview-default');
            preview.src = e.target.result;
            preview.style.display = '';
            if (def) def.style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
function previewBanner(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = document.getElementById('banner-preview');
            var ph  = document.getElementById('banner-placeholder');
            img.src = e.target.result;
            img.style.display = '';
            img.style.position = 'absolute';
            img.style.inset = '0';
            if (ph) ph.style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
<?php include '../includes/footer.php'; ?>
