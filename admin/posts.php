<?php
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../pages/login.php'); exit; }
if (!in_array($_SESSION['role'] ?? '', ['admin','owner'])) { header('Location: ../index.php'); exit; }

$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act     = $_POST['act']     ?? '';
    $post_id = intval($_POST['post_id'] ?? 0);

    if (!$post_id) { $err = '无效帖子'; goto render; }

    if ($act === 'delete') {
        $conn->query("DELETE FROM posts WHERE id=$post_id");
        $conn->query("DELETE FROM comments WHERE post_id=$post_id");
        $msg = "帖子 #$post_id 已删除";
    } elseif ($act === 'pin') {
        $conn->query("UPDATE posts SET is_pinned=1 WHERE id=$post_id");
        $msg = "帖子 #$post_id 已置顶";
    } elseif ($act === 'unpin') {
        $conn->query("UPDATE posts SET is_pinned=0 WHERE id=$post_id");
        $msg = "帖子 #$post_id 已取消置顶";
    } elseif ($act === 'hide') {
        $conn->query("UPDATE posts SET status='hidden' WHERE id=$post_id");
        $msg = "帖子 #$post_id 已隐藏";
    } elseif ($act === 'publish') {
        $conn->query("UPDATE posts SET status='published' WHERE id=$post_id");
        $msg = "帖子 #$post_id 已发布";
    }
}

render:
$search  = trim($_GET['search'] ?? '');
$section = intval($_GET['section'] ?? 0);
$status  = $_GET['status'] ?? 'all';
$page    = max(1, (int)($_GET['page'] ?? 1));
$per     = 20;

$where_parts = [];
if ($search)  $where_parts[] = "(p.title LIKE '%".$conn->real_escape_string($search)."%' OR u.username LIKE '%".$conn->real_escape_string($search)."%')";
if ($section) $where_parts[] = "p.section_id=$section";
if ($status !== 'all') $where_parts[] = "p.status='".$conn->real_escape_string($status)."'";
$where = $where_parts ? 'WHERE '.implode(' AND ', $where_parts) : '';

$total = (int)$conn->query("SELECT COUNT(*) as c FROM posts p JOIN users u ON u.id=p.user_id $where")->fetch_assoc()['c'];
$total_pages = max(1, ceil($total/$per));
$offset = ($page-1)*$per;

$posts = [];
$pr = $conn->query("SELECT p.*,u.username,s.name as section_name
    FROM posts p JOIN users u ON u.id=p.user_id JOIN sections s ON s.id=p.section_id
    $where ORDER BY p.created_at DESC LIMIT $per OFFSET $offset");
if ($pr) while ($r = $pr->fetch_assoc()) $posts[] = $r;

// 分区列表（用于筛选）
$all_sections = [];
$sr = $conn->query("SELECT id,name FROM sections WHERE parent_id!=0 ORDER BY name");
if ($sr) while ($r = $sr->fetch_assoc()) $all_sections[] = $r;

$page_title = '帖子管理';
$in_admin = true;
include '../includes/header.php';
?>

<div style="display:flex;align-items:center;gap:8px;margin-bottom:20px">
  <a href="index.php" style="color:var(--txt-2);font-size:13px">← 后台首页</a>
  <h2 style="margin:0">📝 帖子管理</h2>
</div>

<?php if ($msg): ?><div class="alert alert-success mb-16"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger  mb-16"><?= h($err) ?></div><?php endif; ?>

<!-- 筛选 -->
<form method="get" class="flex-center gap-8 mb-16" style="flex-wrap:wrap">
  <input type="text" name="search" value="<?= h($search) ?>" placeholder="搜索标题或作者..."
         style="flex:1;min-width:200px;max-width:300px;padding:8px 14px;border:1px solid var(--border);border-radius:var(--r);font-size:14px;outline:none">
  <select name="section" style="padding:8px;border:1px solid var(--border);border-radius:var(--r);font-size:14px;outline:none">
    <option value="0">全部板块</option>
    <?php foreach ($all_sections as $s): ?>
      <option value="<?= $s['id'] ?>" <?= $section===$s['id']?'selected':'' ?>><?= h($s['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="status" style="padding:8px;border:1px solid var(--border);border-radius:var(--r);font-size:14px;outline:none">
    <option value="all"      <?= $status==='all'      ?'selected':'' ?>>全部状态</option>
    <option value="published"<?= $status==='published'?'selected':'' ?>>已发布</option>
    <option value="hidden"   <?= $status==='hidden'   ?'selected':'' ?>>已隐藏</option>
  </select>
  <button type="submit" class="btn btn-primary btn-sm">筛选</button>
  <span style="margin-left:auto;font-size:13px;color:var(--txt-3)">共 <?= $total ?> 帖</span>
</form>

<div class="card">
  <div class="card-body" style="padding:0;overflow-x:auto">
    <table class="data-table">
      <thead>
        <tr><th>ID</th><th>标题</th><th>作者</th><th>板块</th><th>状态</th><th>数据</th><th>时间</th><th>操作</th></tr>
      </thead>
      <tbody>
        <?php foreach ($posts as $p): ?>
        <tr>
          <td><?= $p['id'] ?></td>
          <td>
            <a href="../pages/post.php?id=<?= $p['id'] ?>" target="_blank" style="font-size:13px">
              <?= $p['is_pinned'] ? '📌 ' : '' ?><?= h(mb_substr($p['title'],0,25)) ?>
            </a>
          </td>
          <td><?= h($p['username']) ?></td>
          <td style="font-size:12px;color:var(--txt-2)"><?= h($p['section_name']) ?></td>
          <td>
            <?php if ($p['status']==='published'): ?>
              <span style="color:#16a34a;font-size:12px">发布</span>
            <?php else: ?>
              <span style="color:var(--danger);font-size:12px"><?= h($p['status']) ?></span>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:var(--txt-3)">👁<?= $p['views'] ?> 👍<?= $p['like_count'] ?> 💬<?= $p['comment_count'] ?></td>
          <td style="font-size:12px;color:var(--txt-3)"><?= date('m-d H:i', strtotime($p['created_at'])) ?></td>
          <td>
            <div style="display:flex;gap:4px;flex-wrap:wrap">
              <?php if ($p['is_pinned']): ?>
                <form method="post" style="display:inline"><input type="hidden" name="post_id" value="<?= $p['id'] ?>"><button name="act" value="unpin" class="btn btn-outline btn-sm">取消置顶</button></form>
              <?php else: ?>
                <form method="post" style="display:inline"><input type="hidden" name="post_id" value="<?= $p['id'] ?>"><button name="act" value="pin" class="btn btn-outline btn-sm">置顶</button></form>
              <?php endif; ?>
              <?php if ($p['status']==='published'): ?>
                <form method="post" style="display:inline"><input type="hidden" name="post_id" value="<?= $p['id'] ?>"><button name="act" value="hide" class="btn btn-outline btn-sm">隐藏</button></form>
              <?php else: ?>
                <form method="post" style="display:inline"><input type="hidden" name="post_id" value="<?= $p['id'] ?>"><button name="act" value="publish" class="btn btn-outline btn-sm">发布</button></form>
              <?php endif; ?>
              <form method="post" style="display:inline" onsubmit="return confirm('确认删除？')"><input type="hidden" name="post_id" value="<?= $p['id'] ?>"><button name="act" value="delete" class="btn btn-danger btn-sm">删除</button></form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($total_pages > 1): ?>
<div class="pagination">
  <?php for ($i=1; $i<=$total_pages; $i++): ?>
    <a href="?search=<?= urlencode($search) ?>&section=<?= $section ?>&status=<?= h($status) ?>&page=<?= $i ?>"
       class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
