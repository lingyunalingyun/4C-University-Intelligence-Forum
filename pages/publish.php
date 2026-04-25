<?php
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
$sr = $conn->query("SELECT s.*,p.name as parent_name,p.slug as parent_slug FROM sections s LEFT JOIN sections p ON p.id=s.parent_id ORDER BY p.sort_order,s.sort_order");
$main_secs = []; $sub_secs = [];
if ($sr) while ($r = $sr->fetch_assoc()) {
    if ($r['parent_id'] == 0) $main_secs[$r['id']] = $r;
    else $sub_secs[$r['parent_id']][] = $r;
}

$pre_section = $_GET['section'] ?? '';
$pre_sub     = $_GET['sub']     ?? '';

include '../includes/header.php';
?>

<div class="publish-wrap">
  <div class="card">
    <div class="card-header"><?= $edit_id ? '✏️ 编辑帖子' : '✏️ 发布帖子' ?></div>
    <div class="card-body">

      <?php if (!empty($_GET['error'])): ?>
        <div class="form-error"><?= $_GET['error']==='empty'?'标题和内容不能为空':h($_GET['error']) ?></div>
      <?php endif; ?>

      <form action="../actions/post_save.php" method="post" id="publish-form">
        <?php if ($edit_id): ?><input type="hidden" name="edit_id" value="<?= $edit_id ?>"><?php endif; ?>

        <!-- 选择板块 -->
        <div class="form-group">
          <label>选择板块 <span style="color:var(--danger)">*</span></label>
          <div id="section-picker">
            <?php foreach ($main_secs as $ms): ?>
            <div class="mb-16">
              <div style="font-size:13px;font-weight:600;color:var(--txt-2);margin-bottom:8px">
                <?= h($ms['icon']) ?> <?= h($ms['name']) ?>
              </div>
              <div class="section-picker">
                <?php foreach ($sub_secs[$ms['id']] ?? [] as $ss): ?>
                  <label class="section-opt <?= ($edit_post && $edit_post['section_id']==$ss['id']) || ($pre_sub===$ss['slug']) ?'selected':'' ?>">
                    <input type="radio" name="section_id" value="<?= $ss['id'] ?>" style="display:none"
                      <?= ($edit_post && $edit_post['section_id']==$ss['id']) || ($pre_sub===$ss['slug']) ?'checked':'' ?>>
                    <?= h($ss['icon']??'') ?> <?= h($ss['name']) ?>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-group">
          <label>标题 <span style="color:var(--danger)">*</span></label>
          <input type="text" name="title" id="post-title" required maxlength="200"
                 placeholder="请输入清晰的标题..."
                 value="<?= $edit_post ? h($edit_post['title']) : '' ?>">
        </div>

        <div class="form-group">
          <label>内容 <span style="color:var(--danger)">*</span></label>
          <textarea name="content" id="post-content" required rows="16"
                    placeholder="详细描述你的问题或想法...&#10;&#10;支持基本 Markdown：**粗体** _斜体_ `代码` ```代码块```"><?= $edit_post ? h($edit_post['content']) : '' ?></textarea>
        </div>

        <div class="form-group">
          <label>标签（逗号分隔，最多5个）</label>
          <input type="text" name="tags" id="post-tags"
                 placeholder="如：Python,求助,算法"
                 value="<?= $edit_post ? h($edit_post['tags']) : '' ?>">
          <div class="form-hint">
            <button type="button" class="btn btn-outline btn-sm" onclick="aiTags()">🤖 AI生成标签</button>
          </div>
        </div>

        <div style="display:flex;gap:12px;align-items:center">
          <button type="submit" class="btn btn-primary"><?= $edit_id ? '保存修改' : '发布帖子' ?></button>
          <button type="button" class="btn btn-outline" onclick="aiSummaryPreview()">🤖 预览AI摘要</button>
          <a href="javascript:history.back()" class="btn btn-outline">取消</a>
        </div>
      </form>

      <!-- AI摘要预览 -->
      <div id="ai-summary-preview" style="display:none;margin-top:20px" class="ai-summary-box">
        <span id="ai-summary-text">生成中...</span>
      </div>

    </div>
  </div>
</div>

<script>
// 板块选择高亮
document.querySelectorAll('.section-opt').forEach(opt => {
  opt.addEventListener('click', function() {
    document.querySelectorAll('.section-opt').forEach(o => o.classList.remove('selected'));
    this.classList.add('selected');
  });
});

async function aiTags() {
  const title   = document.getElementById('post-title').value.trim();
  const content = document.getElementById('post-content').value.trim();
  if (!title && !content) { alert('请先填写标题和内容'); return; }
  const btn = event.target;
  btn.disabled = true; btn.textContent = '生成中...';
  try {
    const res = await fetch('../actions/ai_summary.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: 'action=tags&title='+encodeURIComponent(title)+'&content='+encodeURIComponent(content)
    });
    const data = await res.json();
    if (data.result) document.getElementById('post-tags').value = data.result;
  } catch(e) {}
  btn.disabled = false; btn.textContent = '🤖 AI生成标签';
}

async function aiSummaryPreview() {
  const title   = document.getElementById('post-title').value.trim();
  const content = document.getElementById('post-content').value.trim();
  if (!title && !content) { alert('请先填写标题和内容'); return; }
  const box = document.getElementById('ai-summary-preview');
  const txt = document.getElementById('ai-summary-text');
  box.style.display = 'block'; txt.textContent = '生成中...';
  try {
    const res = await fetch('../actions/ai_summary.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: 'action=summary&title='+encodeURIComponent(title)+'&content='+encodeURIComponent(content)
    });
    const data = await res.json();
    txt.textContent = data.result || '生成失败，请稍后重试';
  } catch(e) { txt.textContent = '请求失败'; }
}
</script>

<?php include '../includes/footer.php'; ?>
