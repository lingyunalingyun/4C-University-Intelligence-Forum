<?php
require_once '../config.php';
require_once '../includes/helpers.php';

$slug   = trim($_GET['slug']   ?? '');
$sub    = trim($_GET['sub']    ?? '');
$sort   = $_GET['sort']  ?? 'new';
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 20;

// 无 slug → 展示全部分区概览
if ($slug === '') {
    $page_title = '全部分区';
    include '../includes/header.php';

    $all_sections = [];
    $ar = $conn->query("SELECT * FROM sections WHERE parent_id=0 ORDER BY sort_order");
    if ($ar) while ($r = $ar->fetch_assoc()) {
        $r['subs'] = [];
        $sr2 = $conn->query("SELECT * FROM sections WHERE parent_id={$r['id']} ORDER BY sort_order");
        if ($sr2) while ($s2 = $sr2->fetch_assoc()) $r['subs'][] = $s2;
        $cnt_r = $conn->query("SELECT COUNT(*) as c FROM posts p
            JOIN sections sub ON sub.id=p.section_id AND sub.parent_id={$r['id']}
            WHERE p.status='published'");
        $r['post_count'] = $cnt_r ? (int)$cnt_r->fetch_assoc()['c'] : 0;
        $all_sections[] = $r;
    }
    ?>
    <h2 style="margin:0 0 20px">🗂️ 全部分区</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
      <?php foreach ($all_sections as $sec): ?>
      <div class="card" style="border-top:4px solid <?= h($sec['color']) ?>;padding:0">
        <a href="section.php?slug=<?= h($sec['slug']) ?>" style="display:flex;align-items:center;gap:12px;padding:16px 20px;text-decoration:none">
          <span style="font-size:32px"><?= h($sec['icon']) ?></span>
          <div>
            <div style="font-size:16px;font-weight:700;color:var(--txt)"><?= h($sec['name']) ?></div>
            <div style="font-size:12px;color:var(--txt-2);margin-top:2px"><?= h(mb_substr($sec['description'],0,30)) ?></div>
          </div>
          <div style="margin-left:auto;font-size:12px;color:var(--txt-3)"><?= $sec['post_count'] ?> 帖</div>
        </a>
        <?php if (!empty($sec['subs'])): ?>
        <div style="padding:8px 20px 14px;display:flex;flex-wrap:wrap;gap:6px;border-top:1px solid var(--border)">
          <?php foreach ($sec['subs'] as $s2): ?>
            <a href="section.php?slug=<?= h($sec['slug']) ?>&sub=<?= h($s2['slug']) ?>"
               class="tag" style="font-size:12px"><?= h($s2['icon']) ?> <?= h($s2['name']) ?></a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php
    include '../includes/footer.php';
    exit;
}

// 获取一级分区
$stmt = $conn->prepare("SELECT * FROM sections WHERE slug=? AND parent_id=0");
$stmt->bind_param('s', $slug); $stmt->execute();
$section = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$section) { header('Location: section.php'); exit; }

// 子分区列表
$subs = [];
$sr = $conn->query("SELECT * FROM sections WHERE parent_id={$section['id']} ORDER BY sort_order");
if ($sr) while ($r = $sr->fetch_assoc()) $subs[] = $r;

// 当前子分区
$current_sub = null;
$section_ids = [$section['id']];
if ($sub) {
    foreach ($subs as $s) {
        if ($s['slug'] === $sub) { $current_sub = $s; $section_ids = [$s['id']]; break; }
    }
} else {
    foreach ($subs as $s) $section_ids[] = $s['id'];
}

$ids_str = implode(',', $section_ids);

// 排序
$order_sql = [
    'new'  => 'p.created_at DESC',
    'hot'  => '(p.views*0.3 + p.like_count*2 + p.comment_count) DESC',
    'solved'=> 'p.is_solved DESC, p.created_at DESC',
][$sort] ?? 'p.created_at DESC';

$total_res = $conn->query("SELECT COUNT(*) as cnt FROM posts p WHERE p.section_id IN ($ids_str) AND p.status='published'");
$total = (int)$total_res->fetch_assoc()['cnt'];
$total_pages = max(1, ceil($total / $per));
$offset = ($page - 1) * $per;

$posts = [];
$pr = $conn->query("SELECT p.*,u.username,u.avatar,s.name as section_name
    FROM posts p JOIN users u ON u.id=p.user_id JOIN sections s ON s.id=p.section_id
    WHERE p.section_id IN ($ids_str) AND p.status='published'
    ORDER BY p.is_pinned DESC, $order_sql LIMIT $per OFFSET $offset");
if ($pr) while ($r = $pr->fetch_assoc()) $posts[] = $r;

$page_title = $section['name'];
include '../includes/header.php';
?>

<!-- 面包屑 -->
<div class="flex-center gap-8 mb-16" style="font-size:13px;color:var(--txt-2)">
  <a href="../index.php">首页</a> &rsaquo;
  <a href="section.php?slug=<?= h($slug) ?>"><?= h($section['name']) ?></a>
  <?php if ($current_sub): ?> &rsaquo; <span><?= h($current_sub['name']) ?></span><?php endif; ?>
</div>

<!-- 分区头 -->
<div class="card mb-16" style="padding:20px 24px;border-top:4px solid <?= h($section['color']) ?>">
  <div style="display:flex;align-items:center;gap:12px">
    <span style="font-size:36px"><?= h($section['icon']) ?></span>
    <div>
      <div style="font-size:20px;font-weight:700"><?= h($section['name']) ?></div>
      <div style="font-size:13px;color:var(--txt-2);margin-top:4px"><?= h($section['description']) ?></div>
    </div>
    <?php if ($is_logged_in): ?>
      <a href="publish.php?section=<?= h($slug) ?><?= $sub ? '&sub='.$sub : '' ?>" class="btn btn-primary btn-sm" style="margin-left:auto">✏️ 发帖</a>
    <?php endif; ?>
  </div>
</div>

<!-- 子分区标签 -->
<div class="sub-section-list mb-16">
  <a href="section.php?slug=<?= h($slug) ?>&sort=<?= h($sort) ?>"
     class="sub-section-tag <?= !$sub ? 'active' : '' ?>">全部</a>
  <?php foreach ($subs as $s): ?>
    <a href="section.php?slug=<?= h($slug) ?>&sub=<?= h($s['slug']) ?>&sort=<?= h($sort) ?>"
       class="sub-section-tag <?= $sub === $s['slug'] ? 'active' : '' ?>">
      <?= h($s['icon']) ?> <?= h($s['name']) ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="layout-2col">
  <div class="col-main">
    <!-- 排序 -->
    <div class="flex-center gap-8 mb-16">
      <?php foreach (['new'=>'最新','hot'=>'最热','solved'=>'已解决'] as $k=>$v): ?>
        <a href="?slug=<?= h($slug) ?><?= $sub?'&sub='.h($sub):'' ?>&sort=<?= $k ?>"
           class="sub-section-tag <?= $sort===$k?'active':'' ?>"><?= $v ?></a>
      <?php endforeach; ?>
      <span style="margin-left:auto;font-size:13px;color:var(--txt-3)">共 <?= $total ?> 帖</span>
    </div>

    <!-- 帖子列表 -->
    <div class="post-list">
      <?php if (empty($posts)): ?>
        <div class="empty-state"><div class="icon">📭</div><p>暂无帖子，快来发第一帖！</p></div>
      <?php else: ?>
        <?php foreach ($posts as $p): echo render_post_item($p, '../'); endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- 分页 -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php for ($i=1; $i<=$total_pages; $i++): ?>
        <a href="?slug=<?= h($slug) ?><?= $sub?'&sub='.h($sub):'' ?>&sort=<?= h($sort) ?>&page=<?= $i ?>"
           class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="col-side">
    <div class="card">
      <div class="card-header">📌 分区规则</div>
      <div class="card-body" style="font-size:13px;color:var(--txt-2);line-height:1.8">
        <p>1. 发帖请选择合适的子分区</p>
        <p>2. 标题清晰，描述具体</p>
        <p>3. 禁止广告、违规内容</p>
        <p>4. 互相尊重，文明交流</p>
        <p>5. 问题解决后请标记已解决</p>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
