<?php
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../pages/login.php'); exit; }
if (!in_array($_SESSION['role'] ?? '', ['admin','owner'])) { header('Location: ../index.php'); exit; }

$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'add') {
        $parent_id  = intval($_POST['parent_id'] ?? 0);
        $slug       = trim($_POST['slug']        ?? '');
        $name       = trim($_POST['name']        ?? '');
        $icon       = trim($_POST['icon']        ?? '');
        $color      = trim($_POST['color']       ?? '#6b7280');
        $desc       = trim($_POST['description'] ?? '');
        $sort_order = intval($_POST['sort_order']?? 99);

        if (!$slug || !$name) { $err = '板块名称和slug不能为空'; goto render; }

        // slug 唯一
        $r = $conn->query("SELECT id FROM sections WHERE slug='".$conn->real_escape_string($slug)."'");
        if ($r && $r->num_rows > 0) { $err = 'slug 已存在'; goto render; }

        $stmt = $conn->prepare("INSERT INTO sections (parent_id,slug,name,icon,color,description,sort_order) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param('isssssi', $parent_id, $slug, $name, $icon, $color, $desc, $sort_order);
        $stmt->execute(); $stmt->close();
        $msg = "板块 [$name] 已添加";

    } elseif ($act === 'edit') {
        $id    = intval($_POST['sec_id'] ?? 0);
        $name  = trim($_POST['name']    ?? '');
        $icon  = trim($_POST['icon']    ?? '');
        $color = trim($_POST['color']   ?? '#6b7280');
        $desc  = trim($_POST['description'] ?? '');
        $sort  = intval($_POST['sort_order'] ?? 99);
        if (!$id || !$name) { $err = '无效'; goto render; }
        $cn = $conn->real_escape_string($name);
        $ci = $conn->real_escape_string($icon);
        $cc = $conn->real_escape_string($color);
        $cd = $conn->real_escape_string($desc);
        $conn->query("UPDATE sections SET name='$cn',icon='$ci',color='$cc',description='$cd',sort_order=$sort WHERE id=$id");
        $msg = "板块已更新";

    } elseif ($act === 'delete') {
        $id = intval($_POST['sec_id'] ?? 0);
        $cnt = (int)$conn->query("SELECT COUNT(*) as c FROM posts WHERE section_id=$id")->fetch_assoc()['c'];
        if ($cnt > 0) { $err = "该板块下有 $cnt 篇帖子，不能删除"; goto render; }
        $conn->query("DELETE FROM sections WHERE id=$id");
        $msg = "板块 #$id 已删除";
    }
}

render:
// 获取所有分区（带统计）
$sections = [];
$sr = $conn->query("SELECT s.*,p.name as parent_name,(SELECT COUNT(*) FROM posts WHERE section_id=s.id) as post_count
    FROM sections s LEFT JOIN sections p ON p.id=s.parent_id
    ORDER BY COALESCE(s.parent_id,s.id), s.parent_id, s.sort_order");
if ($sr) while ($r = $sr->fetch_assoc()) $sections[] = $r;

$main_secs = array_filter($sections, fn($s) => $s['parent_id'] == 0);

$page_title = '板块管理';
$in_admin = true;
include '../includes/header.php';
?>

<div style="display:flex;align-items:center;gap:8px;margin-bottom:20px">
  <a href="index.php" style="color:var(--txt-2);font-size:13px">← 后台首页</a>
  <h2 style="margin:0">🗂️ 板块管理</h2>
  <button class="btn btn-primary btn-sm" style="margin-left:auto" onclick="showAdd()">+ 新建板块</button>
</div>

<?php if ($msg): ?><div class="alert alert-success mb-16"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger  mb-16"><?= h($err) ?></div><?php endif; ?>

<!-- 板块列表 -->
<?php foreach ($sections as $s): ?>
<div class="card mb-8" style="<?= $s['parent_id'] ? 'margin-left:24px;border-left:3px solid var(--border)' : 'border-top:3px solid '.h($s['color']) ?>">
  <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;flex-wrap:wrap">
    <span style="font-size:20px"><?= h($s['icon']) ?></span>
    <div style="flex:1">
      <div style="font-weight:600"><?= h($s['name']) ?> <span style="font-size:12px;color:var(--txt-3)">(<?= h($s['slug']) ?>)</span></div>
      <?php if ($s['description']): ?><div style="font-size:12px;color:var(--txt-2)"><?= h($s['description']) ?></div><?php endif; ?>
      <div style="font-size:12px;color:var(--txt-3)">帖子：<?= $s['post_count'] ?> · 排序：<?= $s['sort_order'] ?></div>
    </div>
    <div style="display:flex;gap:6px">
      <button class="btn btn-outline btn-sm" onclick="showEdit(<?= $s['id'] ?>,'<?= h(addslashes($s['name'])) ?>','<?= h(addslashes($s['icon'])) ?>','<?= h($s['color']) ?>','<?= h(addslashes($s['description'])) ?>',<?= $s['sort_order'] ?>)">编辑</button>
      <?php if ($s['post_count'] == 0): ?>
      <form method="post" style="display:inline" onsubmit="return confirm('确认删除板块？')">
        <input type="hidden" name="act" value="delete">
        <input type="hidden" name="sec_id" value="<?= $s['id'] ?>">
        <button type="submit" class="btn btn-danger btn-sm">删除</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>

<!-- 新增弹窗 -->
<div id="add-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center">
  <div class="card" style="width:420px;max-width:92vw;max-height:90vh;overflow-y:auto">
    <div class="card-header">新建板块</div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="act" value="add">
        <div class="form-group">
          <label>父级板块（0=一级分区）</label>
          <select name="parent_id">
            <option value="0">— 一级分区 —</option>
            <?php foreach ($main_secs as $ms): ?>
              <option value="<?= $ms['id'] ?>"><?= h($ms['icon']) ?> <?= h($ms['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Slug（英文唯一标识）</label><input type="text" name="slug" required placeholder="如：python"></div>
        <div class="form-group"><label>名称</label><input type="text" name="name" required></div>
        <div class="form-group"><label>图标（Emoji）</label><input type="text" name="icon" placeholder="📂"></div>
        <div class="form-group"><label>颜色</label><input type="color" name="color" value="#6b7280"></div>
        <div class="form-group"><label>简介</label><input type="text" name="description" maxlength="100"></div>
        <div class="form-group"><label>排序</label><input type="number" name="sort_order" value="99"></div>
        <div style="display:flex;gap:8px">
          <button type="submit" class="btn btn-primary btn-sm">添加</button>
          <button type="button" class="btn btn-outline btn-sm" onclick="closeModals()">取消</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- 编辑弹窗 -->
<div id="edit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center">
  <div class="card" style="width:420px;max-width:92vw;max-height:90vh;overflow-y:auto">
    <div class="card-header">编辑板块</div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="act" value="edit">
        <input type="hidden" name="sec_id" id="edit-id">
        <div class="form-group"><label>名称</label><input type="text" name="name" id="edit-name" required></div>
        <div class="form-group"><label>图标（Emoji）</label><input type="text" name="icon" id="edit-icon"></div>
        <div class="form-group"><label>颜色</label><input type="color" name="color" id="edit-color"></div>
        <div class="form-group"><label>简介</label><input type="text" name="description" id="edit-desc" maxlength="100"></div>
        <div class="form-group"><label>排序</label><input type="number" name="sort_order" id="edit-sort"></div>
        <div style="display:flex;gap:8px">
          <button type="submit" class="btn btn-primary btn-sm">保存</button>
          <button type="button" class="btn btn-outline btn-sm" onclick="closeModals()">取消</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function showAdd() { document.getElementById('add-modal').style.display='flex'; }
function showEdit(id,name,icon,color,desc,sort) {
  document.getElementById('edit-id').value    = id;
  document.getElementById('edit-name').value  = name;
  document.getElementById('edit-icon').value  = icon;
  document.getElementById('edit-color').value = color;
  document.getElementById('edit-desc').value  = desc;
  document.getElementById('edit-sort').value  = sort;
  document.getElementById('edit-modal').style.display = 'flex';
}
function closeModals() {
  document.getElementById('add-modal').style.display  = 'none';
  document.getElementById('edit-modal').style.display = 'none';
}
['add-modal','edit-modal'].forEach(id => {
  document.getElementById(id).addEventListener('click', function(e){ if(e.target===this) closeModals(); });
});
</script>

<?php include '../includes/footer.php'; ?>
