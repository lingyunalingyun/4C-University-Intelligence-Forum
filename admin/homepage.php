<?php
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../pages/login.php'); exit; }
if (!in_array($_SESSION['role'] ?? '', ['admin','owner'])) { header('Location: ../index.php'); exit; }

// 加载6个槽位
$slots = array_fill(1, 6, null);
$sr = $conn->query("SELECT hs.position, p.id, p.title, p.content, u.username, s.name as section_name
    FROM homepage_slots hs
    JOIN posts p ON p.id=hs.post_id AND p.status='published'
    JOIN users u ON u.id=p.user_id
    JOIN sections s ON s.id=p.section_id
    WHERE hs.position BETWEEN 1 AND 6");
if ($sr) while ($r = $sr->fetch_assoc()) $slots[$r['position']] = $r;

$page_title = '主页精选';
$in_admin   = true;
include '../includes/header.php';
?>
<style>
.slot-card { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--r-lg); overflow:hidden; box-shadow:var(--shadow); }
.slot-head { display:flex;align-items:center;gap:10px;padding:12px 18px;background:var(--bg-2);border-bottom:1px solid var(--border); }
.slot-num  { width:26px;height:26px;border-radius:50%;background:var(--primary);color:#fff;font-size:12px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.slot-num.hero { background:#f59e0b; }
.slot-body { padding:16px 18px; }
.slot-preview { display:flex;align-items:center;gap:12px;padding:10px 12px;background:var(--bg-2);border-radius:var(--r);margin-bottom:14px; }
.slot-thumb { width:72px;height:40px;border-radius:6px;overflow:hidden;background:var(--border);flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:18px; }
.slot-thumb img { width:100%;height:100%;object-fit:cover; }
.slot-drop { position:absolute;top:calc(100% + 4px);left:0;right:0;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--r);z-index:200;max-height:200px;overflow-y:auto;box-shadow:0 4px 16px rgba(0,0,0,.12);display:none; }
.slot-drop.open { display:block; }
.slot-drop-item { padding:8px 12px;cursor:pointer;font-size:13px;border-bottom:1px solid var(--border); }
.slot-drop-item:last-child { border-bottom:none; }
.slot-drop-item:hover { background:var(--bg-2); }
</style>

<div class="admin-page-hd">
  <div>
    <h2>🖼️ 主页精选</h2>
    <div class="sub">配置首页轮播展示的帖子内容</div>
  </div>
  <div class="actions">
    <a href="../index.php" target="_blank" class="btn btn-outline btn-sm">预览首页 ↗</a>
  </div>
</div>

<!-- 布局说明 -->
<div class="card" style="margin-bottom:20px">
  <div class="card-body" style="padding:14px 20px">
    <div style="display:flex;align-items:flex-start;gap:24px;flex-wrap:wrap">
      <div style="flex:1;min-width:200px;font-size:13px;color:var(--txt-2);line-height:1.9">
        <strong style="color:var(--txt)">布局说明</strong><br>
        位置 <strong>①</strong> — 主图（大图全宽展示，建议图文并茂的帖子）<br>
        位置 <strong>②③④⑤⑥⑦⑧</strong> — 右侧 2×4 卡片矩阵<br>
        帖子封面从正文第一张图片自动提取，无图时显示渐变色块。空槽位不展示。
      </div>
      <!-- 微型布局预览 -->
      <div style="flex-shrink:0">
        <div style="font-size:11px;color:var(--txt-3);margin-bottom:6px">布局预览</div>
        <div style="display:flex;gap:3px;height:64px">
          <div style="width:80px;background:var(--primary);border-radius:4px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:700">① 主图</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;grid-template-rows:1fr 1fr;gap:3px;flex:1">
            <?php for ($n=2;$n<=9;$n++): ?>
            <div style="background:var(--bg-2);border:1px solid var(--border);border-radius:3px;display:flex;align-items:center;justify-content:center;font-size:10px;color:var(--txt-3)">
              <?= ['②','③','④','⑤','⑥','⑦','⑧','⑨'][$n-2] ?>
            </div>
            <?php endfor; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- 槽位配置 (6个) -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
<?php
$label_map = [
  1=>'主图（大图展示区）', 2=>'卡片 ②', 3=>'卡片 ③',
  4=>'卡片 ④',           5=>'卡片 ⑤', 6=>'卡片 ⑥',
];
for ($pos = 1; $pos <= 6; $pos++):
  $s     = $slots[$pos];
  $cover = $s ? extract_cover_image($s['content']) : '';
?>
<div class="slot-card <?= $pos===1?'':'' ?>" <?= $pos===1?'style="grid-column:1/-1"':'' ?>>
  <div class="slot-head">
    <div class="slot-num <?= $pos===1?'hero':'' ?>"><?= $pos ?></div>
    <div>
      <div style="font-weight:600;font-size:13px"><?= $label_map[$pos] ?></div>
      <?php if ($pos===1): ?><div style="font-size:11px;color:var(--txt-2)">此位置最突出，建议选图文丰富的优质帖子</div><?php endif; ?>
    </div>
    <?php if ($s): ?>
      <span class="spill spill-green" style="margin-left:auto"><span class="spill-dot"></span>已配置</span>
    <?php else: ?>
      <span class="spill spill-gray" style="margin-left:auto">空槽位</span>
    <?php endif; ?>
  </div>
  <div class="slot-body">

    <!-- 当前帖子预览 -->
    <div class="slot-preview" <?= !$s?'style="border:1.5px dashed var(--border);background:transparent"':'' ?>>
      <?php if ($s): ?>
        <div class="slot-thumb">
          <?php if ($cover): ?><img src="<?= h($cover) ?>" alt=""><?php else: ?>🎓<?php endif; ?>
        </div>
        <div style="flex:1;min-width:0">
          <div style="font-size:13px;font-weight:600;overflow:hidden;white-space:nowrap;text-overflow:ellipsis"><?= h($s['title']) ?></div>
          <div style="font-size:11px;color:var(--txt-2);margin-top:2px"><?= h($s['section_name']) ?> · <?= h($s['username']) ?>
            &nbsp;·&nbsp;<a href="../pages/post.php?id=<?= $s['id'] ?>" target="_blank" style="color:var(--primary)">查看 ↗</a>
          </div>
        </div>
      <?php else: ?>
        <span style="font-size:13px;color:var(--txt-3);font-style:italic">此位置未配置，不会展示</span>
      <?php endif; ?>
    </div>

    <!-- 搜索选帖 -->
    <div style="position:relative;margin-bottom:12px" id="wrap-<?= $pos ?>">
      <input type="text" id="search-<?= $pos ?>" autocomplete="off"
             placeholder="输入关键词搜索帖子…"
             oninput="doSearch(<?= $pos ?>,this.value)" onfocus="doSearch(<?= $pos ?>,this.value)"
             style="width:100%;padding:8px 12px;font-size:13px;border:1px solid var(--border);border-radius:var(--r);background:var(--bg-2);color:var(--txt);box-sizing:border-box;outline:none">
      <div class="slot-drop" id="drop-<?= $pos ?>"></div>
    </div>
    <input type="hidden" id="sel-<?= $pos ?>" value="">

    <div style="display:flex;gap:8px">
      <button class="btn btn-primary btn-sm" onclick="doSave(<?= $pos ?>)">✓ 保存</button>
      <?php if ($s): ?>
        <button class="btn btn-outline btn-sm" onclick="doClear(<?= $pos ?>)"
                style="color:#ef4444;border-color:#ef4444">✕ 清除</button>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endfor; ?>
</div>

<script>
var timers = {}, selected = {};
function doSearch(pos, q) {
  clearTimeout(timers[pos]);
  var drop = document.getElementById('drop-' + pos);
  q = q.trim();
  if (!q) { drop.innerHTML=''; drop.classList.remove('open'); return; }
  timers[pos] = setTimeout(function() {
    fetch('../api/search_posts.php?q=' + encodeURIComponent(q))
      .then(function(r){ return r.json(); })
      .then(function(data) {
        if (!data.length) {
          drop.innerHTML = '<div class="slot-drop-item" style="color:var(--txt-2);cursor:default">未找到相关帖子</div>';
        } else {
          drop.innerHTML = data.map(function(p) {
            var t = p.title.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            return '<div class="slot-drop-item" onclick="doSelect('+pos+','+p.id+',\''+t.replace(/'/g,"\\'")+'\')">'+
              '<div style="font-weight:500">'+t+'</div>'+
              '<div style="font-size:11px;color:var(--txt-2)">'+p.section_name+' · '+p.username+'</div></div>';
          }).join('');
        }
        drop.classList.add('open');
      }).catch(function(){ drop.classList.remove('open'); });
  }, 250);
}
function doSelect(pos, id, title) {
  selected[pos] = id;
  document.getElementById('sel-'+pos).value = id;
  document.getElementById('search-'+pos).value = title;
  document.getElementById('drop-'+pos).classList.remove('open');
}
function doSave(pos) {
  var id = selected[pos] || 0;
  if (!id) { alert('请先搜索并选择一个帖子'); return; }
  fetch('../actions/homepage_slot_save.php', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'action=set&position='+pos+'&post_id='+id
  }).then(function(r){ return r.json(); }).then(function(d){ d.ok ? location.reload() : alert(d.error||'保存失败'); });
}
function doClear(pos) {
  if (!confirm('清除位置 '+pos+' 的内容？')) return;
  fetch('../actions/homepage_slot_save.php', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'action=clear&position='+pos
  }).then(function(r){ return r.json(); }).then(function(d){ d.ok ? location.reload() : alert(d.error||'失败'); });
}
document.addEventListener('click', function(e) {
  document.querySelectorAll('.slot-drop').forEach(function(d) {
    var inp = document.getElementById(d.id.replace('drop-','search-'));
    if (inp && !inp.contains(e.target) && !d.contains(e.target)) d.classList.remove('open');
  });
});
</script>

<?php include '../includes/footer.php'; ?>
