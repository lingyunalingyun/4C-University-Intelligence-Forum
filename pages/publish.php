<?php
/*
 * pages/publish.php — 发帖 / 编辑帖子页面
 * 功能：Quill 富文本编辑器，支持分区选择、标签输入、图片上传，
 *       编辑模式下预填现有内容，提交到 actions/post_save.php。
 * 读库：sections / posts（编辑模式）
 * 权限：需登录，被封禁用户拦截
 */
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php?redirect=publish.php'); exit; }
if (!empty($_SESSION['is_banned'])) { header('Location: ../index.php?msg=banned'); exit; }

$uid = intval($_SESSION['user_id']);

// 编辑模式
$edit_id   = intval($_GET['edit'] ?? 0);
$edit_post = null;
if ($edit_id) {
    $stmt = $conn->prepare("SELECT * FROM posts WHERE id=? AND (user_id=? OR ? IN ('admin','owner'))");
    $role = $_SESSION['role'] ?? 'user';
    $stmt->bind_param('iis', $edit_id, $uid, $role); $stmt->execute();
    $edit_post = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$edit_post) { header('Location: ../index.php'); exit; }
}

$page_title = $edit_id ? '编辑帖子' : '发帖';

// 获取所有分区
$sr = $conn->query("SELECT s.*,p.name as parent_name,p.icon as parent_icon FROM sections s LEFT JOIN sections p ON p.id=s.parent_id ORDER BY p.sort_order,s.sort_order");
$main_secs = []; $sub_secs = [];
if ($sr) while ($r = $sr->fetch_assoc()) {
    if ($r['parent_id'] == 0) $main_secs[$r['id']] = $r;
    else $sub_secs[$r['parent_id']][] = $r;
}

$pre_sub = $_GET['sub'] ?? '';

include '../includes/header.php';
?>

<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">

<div class="publish-2col">

  <!-- ── 左：主表单 ── -->
  <div>
    <div class="card">
      <div class="card-header"><?= $edit_id ? '<i data-lucide="pencil" class="lucide"></i> 编辑帖子' : '<i data-lucide="pencil" class="lucide"></i> 发布帖子' ?></div>
      <div class="card-body">

        <?php if (!empty($_GET['error'])): ?>
          <div class="form-error"><?= $_GET['error']==='empty' ? '请填写标题、内容并选择板块' : h($_GET['error']) ?></div>
        <?php endif; ?>

        <form action="../actions/post_save.php" method="post" id="publish-form">
          <?php if ($edit_id): ?><input type="hidden" name="edit_id" value="<?= $edit_id ?>"><?php endif; ?>
          <input type="hidden" name="section_id" id="section-id-hidden" value="<?= $edit_post ? $edit_post['section_id'] : '' ?>">
          <input type="hidden" name="content"    id="post-content">

          <!-- 标题 -->
          <div class="form-group">
            <label>标题 <span style="color:var(--danger)">*</span></label>
            <input type="text" name="title" id="post-title" required maxlength="200"
                   placeholder="请输入清晰的标题..."
                   value="<?= $edit_post ? h($edit_post['title']) : '' ?>">
          </div>

          <!-- 富文本编辑器 -->
          <div class="form-group">
            <label>内容 <span style="color:var(--danger)">*</span></label>
            <div id="editor"></div>
          </div>

          <!-- 标签 -->
          <div class="form-group">
            <label>标签（逗号分隔，最多5个）</label>
            <input type="text" name="tags" id="post-tags"
                   placeholder="如：Python,求助,算法"
                   value="<?= $edit_post ? h($edit_post['tags']) : '' ?>">
            <div class="form-hint" style="margin-top:6px">
              <button type="button" class="btn btn-outline btn-sm" onclick="aiTags()"><i data-lucide="bot" class="lucide"></i> AI生成标签</button>
            </div>
          </div>

          <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
            <button type="submit" class="btn btn-primary"><?= $edit_id ? '💾 保存修改' : '🚀 发布帖子' ?></button>
            <button type="button" class="btn btn-outline" onclick="aiSummaryPreview()"><i data-lucide="bot" class="lucide"></i> 预览AI摘要</button>
            <a href="javascript:history.back()" class="btn btn-outline">取消</a>
          </div>
        </form>

        <!-- AI 摘要预览 -->
        <div id="ai-summary-preview" style="display:none;margin-top:20px" class="ai-summary-box">
          <span id="ai-summary-text">生成中...</span>
        </div>

      </div>
    </div>
  </div>

  <!-- ── 右：板块选择 ── -->
  <div style="position:sticky;top:calc(var(--nav-h) + 20px)">
    <div class="card">
      <div class="card-header">📌 选择板块 <span style="color:var(--danger);font-size:13px">*</span></div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:14px">

        <?php foreach ($main_secs as $ms):
          if (empty($sub_secs[$ms['id']])) continue; ?>
        <div>
          <div style="font-size:11px;font-weight:700;color:var(--txt-3);text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px">
            <?= h($ms['icon']??'') ?> <?= h($ms['name']) ?>
          </div>
          <select class="section-select" data-pid="<?= $ms['id'] ?>" onchange="onSectionSelect(this)"
                  style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:var(--r);font-size:13px;background:var(--bg-2);color:var(--txt);outline:none;cursor:pointer;transition:border-color .15s">
            <option value="">— 选择子版块 —</option>
            <?php foreach ($sub_secs[$ms['id']] as $ss):
              $sel = ($edit_post && $edit_post['section_id']==$ss['id']) || ($pre_sub===$ss['slug']); ?>
            <option value="<?= $ss['id'] ?>" <?= $sel?'selected':'' ?>><?= h($ss['icon']??'') ?> <?= h($ss['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endforeach; ?>

        <!-- 当前选中提示 -->
        <div id="section-feedback" style="font-size:12px;text-align:center;padding:4px 0">
          <?php if ($edit_post): ?>
            <span style="color:var(--success)">✓ 已选当前板块</span>
          <?php else: ?>
            <span style="color:var(--txt-3)">请在上方选择一个板块</span>
          <?php endif; ?>
        </div>

      </div>
    </div>
  </div>

</div>

<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
// ── 富文本编辑器 ──────────────────────────────────────
var quill = new Quill('#editor', {
  theme: 'snow',
  placeholder: '详细描述你的问题或想法...',
  modules: {
    toolbar: {
      container: [
        [{ header: [1, 2, 3, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        ['blockquote', 'code-block'],
        [{ list: 'ordered' }, { list: 'bullet' }],
        ['link', 'image'],
        ['clean']
      ],
      handlers: { image: imageUploadHandler }
    }
  }
});

<?php if ($edit_post && !empty($edit_post['content'])): ?>
quill.root.innerHTML = <?= json_encode($edit_post['content']) ?>;
<?php endif; ?>

function imageUploadHandler() {
  var input = document.createElement('input');
  input.type = 'file';
  input.accept = 'image/jpeg,image/png,image/gif,image/webp';
  input.click();
  input.addEventListener('change', function() {
    var file = input.files[0];
    if (!file) return;
    var form = new FormData();
    form.append('image', file);
    document.body.style.cursor = 'wait';
    fetch('../api/upload_image.php', { method: 'POST', body: form })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        document.body.style.cursor = '';
        if (d.ok) {
          var range = quill.getSelection(true);
          var idx = range ? range.index : quill.getLength();
          quill.insertEmbed(idx, 'image', location.origin + d.url);
          quill.setSelection(idx + 1);
        } else {
          alert('图片上传失败：' + d.error);
        }
      })
      .catch(function() {
        document.body.style.cursor = '';
        alert('上传失败，请检查网络');
      });
  });
}

// ── 板块选择 ─────────────────────────────────────────
function onSectionSelect(sel) {
  var val = sel.value;
  var pid = sel.dataset.pid;
  document.querySelectorAll('.section-select').forEach(function(s) {
    if (s.dataset.pid !== pid) s.value = '';
  });
  document.getElementById('section-id-hidden').value = val;
  var fb = document.getElementById('section-feedback');
  if (val) {
    var name = sel.options[sel.selectedIndex].text;
    fb.innerHTML = '<span style="color:var(--success)">✓ 已选：' + name.replace(/</g,'&lt;') + '</span>';
  } else {
    fb.innerHTML = '<span style="color:var(--txt-3)">请在上方选择一个板块</span>';
  }
}

// ── 表单提交 ─────────────────────────────────────────
document.getElementById('publish-form').addEventListener('submit', function(e) {
  if (!quill.getText().trim()) {
    e.preventDefault();
    alert('请填写帖子内容');
    quill.focus();
    return;
  }
  if (!document.getElementById('section-id-hidden').value) {
    e.preventDefault();
    alert('请选择一个板块');
    return;
  }
  document.getElementById('post-content').value = quill.root.innerHTML;
});

// ── AI 功能 ──────────────────────────────────────────
async function aiTags() {
  var title   = document.getElementById('post-title').value.trim();
  var content = quill.getText().trim();
  if (!title && !content) { alert('请先填写标题和内容'); return; }
  var btn = event.target;
  btn.disabled = true; btn.textContent = '生成中...';
  try {
    var res = await fetch('../actions/ai_summary.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=tags&title=' + encodeURIComponent(title) + '&content=' + encodeURIComponent(content)
    });
    var data = await res.json();
    if (data.result) document.getElementById('post-tags').value = data.result;
  } catch(e) {}
  btn.disabled = false; btn.textContent = '<i data-lucide="bot" class="lucide"></i> AI生成标签';
}

async function aiSummaryPreview() {
  var title   = document.getElementById('post-title').value.trim();
  var content = quill.getText().trim();
  if (!title && !content) { alert('请先填写标题和内容'); return; }
  var box = document.getElementById('ai-summary-preview');
  var txt = document.getElementById('ai-summary-text');
  box.style.display = 'block'; txt.textContent = '生成中...';
  try {
    var res = await fetch('../actions/ai_summary.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=summary&title=' + encodeURIComponent(title) + '&content=' + encodeURIComponent(content)
    });
    var data = await res.json();
    txt.textContent = data.result || '生成失败，请稍后重试';
  } catch(e) { txt.textContent = '请求失败'; }
}
</script>

<?php include '../includes/footer.php'; ?>
