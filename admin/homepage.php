<?php
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../pages/login.php'); exit; }
if (!in_array($_SESSION['role'] ?? '', ['admin','owner'])) { header('Location: ../index.php'); exit; }

// 加载当前6个槽位的配置
$slots = array_fill(1, 6, null);
$sr = $conn->query("SELECT hs.position,
    p.id, p.title, p.content, u.username, s.name as section_name
    FROM homepage_slots hs
    JOIN posts p ON p.id = hs.post_id AND p.status = 'published'
    JOIN users u ON u.id = p.user_id
    JOIN sections s ON s.id = p.section_id
    WHERE hs.position BETWEEN 1 AND 6");
if ($sr) while ($r = $sr->fetch_assoc()) $slots[$r['position']] = $r;

$page_title = '主页内容管理';
$in_admin   = true;
include '../includes/header.php';
?>

<style>
.slot-grid { display: grid; grid-template-columns: 1fr; gap: 14px; }
.slot-card { background: var(--card-bg); border: 1.5px solid var(--border); border-radius: 10px; overflow: hidden; }
.slot-head { display: flex; align-items: center; gap: 10px; padding: 11px 16px; background: var(--bg-2); border-bottom: 1px solid var(--border); }
.slot-num { width: 28px; height: 28px; border-radius: 50%; background: var(--primary); color: #fff; font-size: 13px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.slot-num.hero { background: #f59e0b; }
.slot-body { padding: 14px 16px; }

.slot-preview { display: flex; align-items: center; gap: 12px; padding: 10px 12px; background: var(--bg-2); border-radius: 8px; margin-bottom: 12px; }
.slot-thumb { width: 80px; height: 45px; border-radius: 6px; overflow: hidden; background: var(--border); flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 20px; }
.slot-thumb img { width: 100%; height: 100%; object-fit: cover; }
.slot-info { flex: 1; min-width: 0; }
.slot-info-title { font-size: 14px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.slot-info-meta { font-size: 12px; color: var(--txt-2); margin-top: 2px; }
.slot-empty-label { color: var(--txt-3); font-size: 13px; font-style: italic; }

.slot-search-wrap { position: relative; margin-bottom: 10px; }
.slot-search-input { width: 100%; padding: 8px 12px; font-size: 14px; border: 1.5px solid var(--border); border-radius: 8px; background: var(--bg); color: var(--txt); box-sizing: border-box; }
.slot-search-input:focus { outline: none; border-color: var(--primary); }
.slot-drop { position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: var(--card-bg); border: 1.5px solid var(--border); border-radius: 8px; z-index: 200; max-height: 200px; overflow-y: auto; box-shadow: 0 4px 16px rgba(0,0,0,.12); display: none; }
.slot-drop.open { display: block; }
.slot-drop-item { padding: 8px 12px; cursor: pointer; font-size: 13px; border-bottom: 1px solid var(--border); }
.slot-drop-item:last-child { border-bottom: none; }
.slot-drop-item:hover { background: var(--bg-2); }
.slot-drop-item .di-title { font-weight: 500; }
.slot-drop-item .di-meta { font-size: 11px; color: var(--txt-2); margin-top: 1px; }
.slot-actions { display: flex; gap: 8px; flex-wrap: wrap; }

.layout-hint { background: var(--bg-2); border: 1px solid var(--border); border-radius: 8px; padding: 11px 16px; margin-bottom: 20px; font-size: 13px; color: var(--txt-2); line-height: 1.7; }
.layout-hint strong { color: var(--txt); }

/* 布局预览图 */
.layout-preview { display: flex; flex-direction: column; gap: 4px; padding: 10px; background: var(--bg-2); border-radius: 8px; margin-bottom: 20px; max-width: 360px; }
.lp-row { display: flex; gap: 4px; height: 30px; }
.lp-block { border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: #fff; }
</style>

<div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
  <a href="index.php" style="color:var(--txt-2);font-size:13px">← 管理后台</a>
  <h2 style="margin:0">🖼️ 主页内容管理</h2>
  <a href="../index.php" target="_blank" class="btn btn-outline btn-sm" style="margin-left:auto">预览首页 ↗</a>
</div>

<div class="layout-hint">
  <strong>布局：</strong>
  位置 <strong>1</strong> — 主图（全宽 16:9，最大展示区）；
  位置 <strong>2–3</strong> — 双栏；
  位置 <strong>4–6</strong> — 三栏。
  帖子封面从正文第一张图片自动提取，无图时显示渐变占位。
  空槽位不展示。
</div>

<!-- 布局预览图 -->
<div class="layout-preview">
  <div class="lp-row"><div class="lp-block" style="flex:1;background:var(--primary)">① 主图</div></div>
  <div class="lp-row">
    <div class="lp-block" style="flex:1;background:#6366f1">② 左</div>
    <div class="lp-block" style="flex:1;background:#6366f1">③ 右</div>
  </div>
  <div class="lp-row">
    <div class="lp-block" style="flex:1;background:#10b981">④</div>
    <div class="lp-block" style="flex:1;background:#10b981">⑤</div>
    <div class="lp-block" style="flex:1;background:#10b981">⑥</div>
  </div>
</div>

<div class="slot-grid">
<?php
$label_map = [
    1 => '主图（全宽）',
    2 => '双栏 — 左',
    3 => '双栏 — 右',
    4 => '三栏 — 一',
    5 => '三栏 — 二',
    6 => '三栏 — 三',
];
for ($pos = 1; $pos <= 6; $pos++):
    $s = $slots[$pos];
    $cover = $s ? extract_cover_image($s['content']) : '';
?>
<div class="slot-card">
  <div class="slot-head">
    <div class="slot-num <?= $pos===1?'hero':'' ?>"><?= $pos ?></div>
    <div>
      <div style="font-weight:600;font-size:14px">位置 <?= $pos ?> — <?= $label_map[$pos] ?></div>
      <?php if ($pos===1): ?>
        <div style="font-size:11px;color:var(--txt-2)">最大展示区，建议选图文并茂的帖子</div>
      <?php endif; ?>
    </div>
  </div>
  <div class="slot-body">

    <!-- 当前帖子预览 -->
    <div class="slot-preview" <?= !$s ? 'style="border:1.5px dashed var(--border);background:transparent"' : '' ?>>
      <?php if ($s): ?>
        <div class="slot-thumb">
          <?php if ($cover): ?>
            <img src="<?= h($cover) ?>" alt="">
          <?php else: ?>🎓<?php endif; ?>
        </div>
        <div class="slot-info">
          <div class="slot-info-title"><?= h($s['title']) ?></div>
          <div class="slot-info-meta">
            <?= h($s['section_name']) ?> · <?= h($s['username']) ?>
            &nbsp;·&nbsp; <a href="../pages/post.php?id=<?= $s['id'] ?>" target="_blank">查看 ↗</a>
          </div>
        </div>
      <?php else: ?>
        <div class="slot-empty-label">此位置未配置，不会展示</div>
      <?php endif; ?>
    </div>

    <!-- 搜索选帖 -->
    <div class="slot-search-wrap" id="wrap-<?= $pos ?>">
      <input type="text"
             class="slot-search-input"
             id="search-<?= $pos ?>"
             placeholder="输入关键词搜索帖子标题..."
             autocomplete="off"
             oninput="doSearch(<?= $pos ?>,this.value)"
             onfocus="doSearch(<?= $pos ?>,this.value)">
      <div class="slot-drop" id="drop-<?= $pos ?>"></div>
    </div>

    <div class="slot-actions">
      <button class="btn btn-primary btn-sm" onclick="doSave(<?= $pos ?>)">✓ 保存</button>
      <?php if ($s): ?>
        <button class="btn btn-sm" onclick="doClear(<?= $pos ?>)"
                style="background:transparent;border:1.5px solid #ef4444;color:#ef4444">✕ 清除</button>
      <?php endif; ?>
    </div>

    <input type="hidden" id="sel-<?= $pos ?>" value="">
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
    if (!q) { drop.innerHTML = ''; drop.classList.remove('open'); return; }
    timers[pos] = setTimeout(function() {
        fetch('../api/search_posts.php?q=' + encodeURIComponent(q))
            .then(function(r){ return r.json(); })
            .then(function(data) {
                if (!data.length) {
                    drop.innerHTML = '<div class="slot-drop-item" style="color:var(--txt-2);cursor:default">未找到相关帖子</div>';
                } else {
                    drop.innerHTML = data.map(function(p) {
                        var t = p.title.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
                        return '<div class="slot-drop-item" onclick="doSelect(' + pos + ',' + p.id + ',\'' + t.replace(/'/g,"\\'") + '\')">' +
                            '<div class="di-title">' + t + '</div>' +
                            '<div class="di-meta">' + p.section_name + ' · ' + p.username + '</div>' +
                            '</div>';
                    }).join('');
                }
                drop.classList.add('open');
            })
            .catch(function(){ drop.classList.remove('open'); });
    }, 250);
}

function doSelect(pos, id, title) {
    selected[pos] = id;
    document.getElementById('sel-' + pos).value = id;
    document.getElementById('search-' + pos).value = title;
    document.getElementById('drop-' + pos).classList.remove('open');
}

function doSave(pos) {
    var id = selected[pos] || 0;
    if (!id) { alert('请先搜索并点击选择一个帖子'); return; }
    fetch('../actions/homepage_slot_save.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=set&position=' + pos + '&post_id=' + id
    }).then(function(r){ return r.json(); }).then(function(d) {
        if (d.ok) location.reload();
        else alert(d.error || '保存失败');
    });
}

function doClear(pos) {
    if (!confirm('清除位置 ' + pos + ' 的内容？')) return;
    fetch('../actions/homepage_slot_save.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=clear&position=' + pos
    }).then(function(r){ return r.json(); }).then(function(d) {
        if (d.ok) location.reload();
        else alert(d.error || '操作失败');
    });
}

document.addEventListener('click', function(e) {
    document.querySelectorAll('.slot-drop').forEach(function(d) {
        var inp = document.getElementById(d.id.replace('drop-','search-'));
        if (inp && !inp.contains(e.target) && !d.contains(e.target)) {
            d.classList.remove('open');
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
