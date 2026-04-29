<?php
/*
 * admin/sections.php — 分区管理后台
 * 功能：新增/编辑/删除顶级分区和子分区，设置名称/图标/颜色/排序，
 *       分区下帖子数统计。
 * 写库：sections / admin_logs
 * 权限：需 admin/owner 登录
 */
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../pages/login.php'); exit; }
if (!in_array($_SESSION['role'] ?? '', ['admin','owner'])) { header('Location: ../index.php'); exit; }

$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'add') {
        $parent_id  = intval($_POST['parent_id']  ?? 0);
        $slug       = trim($_POST['slug']         ?? '');
        $name       = trim($_POST['name']         ?? '');
        $icon       = trim($_POST['icon']         ?? '');
        $color      = trim($_POST['color']        ?? '#6b7280');
        $desc       = trim($_POST['description']  ?? '');
        $sort_order = intval($_POST['sort_order'] ?? 99);
        if (!$slug || !$name) { $err = '板块名称和 slug 不能为空'; goto render; }
        $r = $conn->query("SELECT id FROM sections WHERE slug='".$conn->real_escape_string($slug)."'");
        if ($r && $r->num_rows > 0) { $err = 'slug 已存在'; goto render; }
        $stmt = $conn->prepare("INSERT INTO sections (parent_id,slug,name,icon,color,description,sort_order) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param('isssssi', $parent_id, $slug, $name, $icon, $color, $desc, $sort_order);
        $stmt->execute(); $stmt->close();
        $msg = "板块「$name」已添加";

    } elseif ($act === 'edit') {
        $id    = intval($_POST['sec_id']      ?? 0);
        $name  = trim($_POST['name']          ?? '');
        $icon  = trim($_POST['icon']          ?? '');
        $color = trim($_POST['color']         ?? '#6b7280');
        $desc  = trim($_POST['description']   ?? '');
        $sort  = intval($_POST['sort_order']  ?? 99);
        if (!$id || !$name) { $err = '参数无效'; goto render; }
        $conn->query("UPDATE sections SET
            name='".$conn->real_escape_string($name)."',
            icon='".$conn->real_escape_string($icon)."',
            color='".$conn->real_escape_string($color)."',
            description='".$conn->real_escape_string($desc)."',
            sort_order=$sort WHERE id=$id");
        $msg = "板块已更新";

    } elseif ($act === 'delete') {
        $id  = intval($_POST['sec_id'] ?? 0);
        $cnt = (int)$conn->query("SELECT COUNT(*) as c FROM posts WHERE section_id=$id")->fetch_assoc()['c'];
        if ($cnt > 0) { $err = "该板块下有 $cnt 篇帖子，无法删除"; goto render; }
        $conn->query("DELETE FROM sections WHERE id=$id");
        $msg = "板块已删除";
    }
}

render:
$sections = [];
$sr = $conn->query("SELECT s.*,p.name as parent_name,
    (SELECT COUNT(*) FROM posts WHERE section_id=s.id) as post_count
    FROM sections s LEFT JOIN sections p ON p.id=s.parent_id
    ORDER BY COALESCE(s.parent_id,s.id), s.parent_id IS NOT NULL, s.sort_order");
if ($sr) while ($r = $sr->fetch_assoc()) $sections[] = $r;

$main_secs = array_filter($sections, function($s){ return $s['parent_id'] == 0; });

$page_title = '板块管理';
$in_admin   = true;
include '../includes/header.php';
?>

<div class="admin-page-hd">
  <div>
    <h2>🗂️ 板块管理</h2>
    <div class="sub">共 <?= count($sections) ?> 个板块</div>
  </div>
  <div class="actions">
    <button class="btn btn-primary btn-sm" onclick="openAddModal()">＋ 新建板块</button>
  </div>
</div>

<?php if ($msg): ?><div class="alert-success"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert-danger"><?= h($err) ?></div><?php endif; ?>

<!-- 板块树 -->
<?php if (empty($sections)): ?>
<div class="empty-state"><div class="icon">🗂️</div><p>还没有板块，点击右上角新建</p></div>
<?php else: ?>

<?php
// 按 parent_id 分组渲染
$roots    = array_filter($sections, function($s){ return $s['parent_id'] == 0; });
$children = [];
foreach ($sections as $s) {
    if ($s['parent_id'] != 0) $children[$s['parent_id']][] = $s;
}
foreach ($roots as $root):
    $root_color = $root['color'] ?: '#6b7280';
?>
<div class="card" style="margin-bottom:16px;overflow:hidden">
  <!-- 父板块行 -->
  <div style="display:flex;align-items:center;gap:14px;padding:14px 20px;background:var(--bg-2);border-bottom:1px solid var(--border);border-left:4px solid <?= h($root_color) ?>">
    <span style="font-size:22px;flex-shrink:0"><?= h($root['icon']) ?></span>
    <div style="flex:1;min-width:0">
      <div style="font-weight:700;font-size:14px"><?= h($root['name']) ?></div>
      <div style="font-size:12px;color:var(--txt-3)">slug: <?= h($root['slug']) ?> &nbsp;·&nbsp; 排序: <?= $root['sort_order'] ?> &nbsp;·&nbsp; 帖子: <?= $root['post_count'] ?></div>
    </div>
    <div style="display:flex;gap:6px;flex-shrink:0">
      <button class="btn btn-outline btn-sm"
        onclick="openEditModal(<?= $root['id'] ?>,'<?= h(addslashes($root['name'])) ?>','<?= h(addslashes($root['icon'])) ?>','<?= h($root['color']) ?>','<?= h(addslashes($root['description'])) ?>',<?= $root['sort_order'] ?>)">
        编辑
      </button>
      <?php if ($root['post_count'] == 0 && empty($children[$root['id']])): ?>
      <form method="post" style="display:inline" onsubmit="return confirm('确认删除该板块？')">
        <input type="hidden" name="act" value="delete">
        <input type="hidden" name="sec_id" value="<?= $root['id'] ?>">
        <button type="submit" class="btn btn-danger btn-sm">删除</button>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <!-- 子板块 -->
  <?php if (!empty($children[$root['id']])): ?>
    <?php foreach ($children[$root['id']] as $idx => $child): ?>
    <div style="display:flex;align-items:center;gap:12px;padding:11px 20px 11px 36px;border-bottom:<?= ($idx < count($children[$root['id']])-1)?'1px solid var(--border)':'none' ?>;background:var(--bg-card)">
      <span style="font-size:16px;flex-shrink:0"><?= h($child['icon']) ?></span>
      <div style="flex:1;min-width:0">
        <div style="font-weight:600;font-size:13px"><?= h($child['name']) ?>
          <span style="font-size:11px;color:var(--txt-3);font-weight:400;margin-left:6px"><?= h($child['slug']) ?></span>
        </div>
        <?php if ($child['description']): ?>
          <div style="font-size:12px;color:var(--txt-2)"><?= h($child['description']) ?></div>
        <?php endif; ?>
        <div style="font-size:11px;color:var(--txt-3)">排序 <?= $child['sort_order'] ?> &nbsp;·&nbsp; <?= $child['post_count'] ?> 篇帖子</div>
      </div>
      <div style="display:flex;gap:6px;flex-shrink:0">
        <button class="btn btn-outline btn-sm" style="font-size:11px"
          onclick="openEditModal(<?= $child['id'] ?>,'<?= h(addslashes($child['name'])) ?>','<?= h(addslashes($child['icon'])) ?>','<?= h($child['color']) ?>','<?= h(addslashes($child['description'])) ?>',<?= $child['sort_order'] ?>)">
          编辑
        </button>
        <?php if ($child['post_count'] == 0): ?>
        <form method="post" style="display:inline" onsubmit="return confirm('确认删除子板块？')">
          <input type="hidden" name="act" value="delete">
          <input type="hidden" name="sec_id" value="<?= $child['id'] ?>">
          <button type="submit" class="btn btn-danger btn-sm" style="font-size:11px">删除</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div style="padding:10px 36px;font-size:12px;color:var(--txt-3);font-style:italic">暂无子板块</div>
  <?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- 新增弹窗 -->
<div class="admin-modal" id="add-modal">
  <div class="modal-box">
    <div class="modal-hd">
      <span class="modal-title">新建板块</span>
      <button class="modal-close" onclick="closeModals()">×</button>
    </div>
    <div class="modal-bd">
      <form method="post">
        <input type="hidden" name="act" value="add">
        <div class="form-group">
          <label>父级板块</label>
          <select name="parent_id">
            <option value="0">— 一级分区（无父级）—</option>
            <?php foreach ($main_secs as $ms): ?>
              <option value="<?= $ms['id'] ?>"><?= h($ms['icon']) ?> <?= h($ms['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Slug <span style="color:var(--txt-3);font-size:12px">（英文唯一标识）</span></label><input type="text" name="slug" required placeholder="如：python-study"></div>
        <div class="form-group"><label>名称</label><input type="text" name="name" required placeholder="板块显示名称"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group"><label>图标（Emoji）</label><input type="text" name="icon" placeholder="📂"></div>
          <div class="form-group"><label>颜色</label><input type="color" name="color" value="#6b7280"></div>
        </div>
        <div class="form-group"><label>简介</label><input type="text" name="description" maxlength="100" placeholder="一句话介绍（选填）"></div>
        <div class="form-group"><label>排序值 <span style="color:var(--txt-3);font-size:12px">（越小越靠前）</span></label><input type="number" name="sort_order" value="99" min="0"></div>
        <div class="modal-ft" style="padding:0;border:none;justify-content:flex-start;margin-top:4px">
          <button type="submit" class="btn btn-primary">添加板块</button>
          <button type="button" class="btn btn-outline" onclick="closeModals()">取消</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- 编辑弹窗 -->
<div class="admin-modal" id="edit-modal">
  <div class="modal-box">
    <div class="modal-hd">
      <span class="modal-title">编辑板块</span>
      <button class="modal-close" onclick="closeModals()">×</button>
    </div>
    <div class="modal-bd">
      <form method="post">
        <input type="hidden" name="act" value="edit">
        <input type="hidden" name="sec_id" id="edit-id">
        <div class="form-group"><label>名称</label><input type="text" name="name" id="edit-name" required></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group"><label>图标（Emoji）</label><input type="text" name="icon" id="edit-icon"></div>
          <div class="form-group"><label>颜色</label><input type="color" name="color" id="edit-color"></div>
        </div>
        <div class="form-group"><label>简介</label><input type="text" name="description" id="edit-desc" maxlength="100"></div>
        <div class="form-group"><label>排序值</label><input type="number" name="sort_order" id="edit-sort" min="0"></div>
        <div class="modal-ft" style="padding:0;border:none;justify-content:flex-start;margin-top:4px">
          <button type="submit" class="btn btn-primary">保存修改</button>
          <button type="button" class="btn btn-outline" onclick="closeModals()">取消</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openAddModal()  { document.getElementById('add-modal').classList.add('open'); }
function openEditModal(id, name, icon, color, desc, sort) {
  document.getElementById('edit-id').value    = id;
  document.getElementById('edit-name').value  = name;
  document.getElementById('edit-icon').value  = icon;
  document.getElementById('edit-color').value = color || '#6b7280';
  document.getElementById('edit-desc').value  = desc;
  document.getElementById('edit-sort').value  = sort;
  document.getElementById('edit-modal').classList.add('open');
}
function closeModals() {
  document.getElementById('add-modal').classList.remove('open');
  document.getElementById('edit-modal').classList.remove('open');
}
['add-modal','edit-modal'].forEach(function(id) {
  document.getElementById(id).addEventListener('click', function(e){ if (e.target===this) closeModals(); });
});
</script>

<?php include '../includes/footer.php'; ?>
