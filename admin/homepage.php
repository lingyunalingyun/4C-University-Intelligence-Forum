<?php
/*
 * admin/homepage.php — 首页内容管理后台
 * 功能：配置首页精选6个帖子槽位（搜索选帖/清除），
 *       上传/删除首页 Hero 背景大图（存 site_settings/uploads/hero/）。
 * 写库：homepage_slots / site_settings / uploads/hero/
 * 权限：需 admin/owner 登录
 */
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../pages/login.php'); exit; }
if (!in_array($_SESSION['role'] ?? '', ['admin','owner'])) { header('Location: ../index.php'); exit; }

// 读取当前 Hero 背景图路径
$hero_bg_current = '';
$hbr = $conn->query("SELECT `value` FROM site_settings WHERE `key`='hero_bg' LIMIT 1");
if ($hbr && ($hbr_row = $hbr->fetch_assoc())) $hero_bg_current = $hbr_row['value'];

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
    <h2><i data-lucide="image" class="lucide"></i> 主页精选</h2>
    <div class="sub">配置首页轮播展示的帖子内容</div>
  </div>
  <div class="actions">
    <a href="../index.php" target="_blank" class="btn btn-outline btn-sm">预览首页 ↗</a>
  </div>
</div>

<!-- ══ Hero 背景图管理 ══ -->
<div class="card" style="margin-bottom:20px">
  <div class="card-header"><i data-lucide="image" class="lucide"></i> 首页背景大图</div>
  <div class="card-body">
    <div style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap">

      <!-- 当前图片预览 -->
      <div style="flex-shrink:0">
        <div style="font-size:12px;color:var(--txt-2);margin-bottom:8px">当前背景图</div>
        <?php if ($hero_bg_current): ?>
          <div style="position:relative;width:240px;height:120px;border-radius:var(--r);overflow:hidden;border:1px solid var(--border)">
            <img src="../<?= h($hero_bg_current) ?>" alt="当前背景图"
                 style="width:100%;height:100%;object-fit:cover">
            <div style="position:absolute;inset:0;background:linear-gradient(120deg,rgba(37,99,235,.7),rgba(99,102,241,.6));display:flex;align-items:center;justify-content:center">
              <span style="color:#fff;font-size:11px;opacity:.8">预览效果</span>
            </div>
          </div>
          <div style="font-size:11px;color:var(--txt-3);margin-top:5px;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($hero_bg_current) ?></div>
        <?php else: ?>
          <div style="width:240px;height:120px;border-radius:var(--r);border:2px dashed var(--border);display:flex;align-items:center;justify-content:center;color:var(--txt-3);font-size:13px">
            未设置背景图<br><span style="font-size:11px">（使用纯色兜底）</span>
          </div>
        <?php endif; ?>
      </div>

      <!-- 上传/删除操作 -->
      <div style="flex:1;min-width:200px">
        <div style="font-size:12px;color:var(--txt-2);margin-bottom:8px">上传新背景图</div>
        <div style="font-size:12px;color:var(--txt-3);margin-bottom:10px;line-height:1.7">
          建议尺寸：<strong>1920×600</strong> 或更宽，JPG/PNG/WebP，<strong>≤5MB</strong>。<br>
          图片会被渐变遮罩叠加，建议使用风景、校园等高质量照片。
        </div>
        <form id="hero-bg-form" onsubmit="uploadHeroBg(event)" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
          <input type="file" id="hero-bg-file" name="hero_bg" accept="image/jpeg,image/png,image/webp"
                 style="font-size:13px;border:1px solid var(--border);border-radius:var(--r);padding:6px 10px;background:var(--bg-2);color:var(--txt);flex:1;min-width:160px">
          <button type="submit" class="btn btn-primary btn-sm"><i data-lucide="upload" class="lucide"></i> 上传</button>
          <?php if ($hero_bg_current): ?>
          <button type="button" class="btn btn-outline btn-sm" onclick="deleteHeroBg()"
                  style="color:#ef4444;border-color:#ef4444">✕ 删除</button>
          <?php endif; ?>
        </form>
        <div id="hero-bg-msg" style="margin-top:10px;font-size:13px;display:none"></div>
      </div>

    </div>
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
          <?php if ($cover): ?><img src="<?= h($cover) ?>" alt=""><?php else: ?><i data-lucide="graduation-cap" class="lucide"></i><?php endif; ?>
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
/* Hero 背景图上传 */
function uploadHeroBg(e) {
  e.preventDefault();
  var file = document.getElementById('hero-bg-file').files[0];
  if (!file) { showHeroMsg('请先选择图片文件', 'error'); return; }
  if (file.size > 5 * 1024 * 1024) { showHeroMsg('文件大小超过 5MB 限制', 'error'); return; }
  var fd = new FormData();
  fd.append('action', 'upload');
  fd.append('hero_bg', file);
  showHeroMsg('上传中…', 'info');
  fetch('../actions/hero_bg_upload.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (d.ok) { showHeroMsg('<i data-lucide="check-circle" class="lucide"></i> 上传成功，刷新页面预览', 'ok'); setTimeout(function(){ location.reload(); }, 1200); }
      else       { showHeroMsg('<i data-lucide="x-circle" class="lucide"></i> ' + (d.error || '上传失败'), 'error'); }
    }).catch(function() { showHeroMsg('<i data-lucide="x-circle" class="lucide"></i> 请求异常', 'error'); });
}
function deleteHeroBg() {
  if (!confirm('确认删除首页背景图？删除后恢复为纯色背景。')) return;
  fetch('../actions/hero_bg_upload.php', {
    method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=delete'
  }).then(function(r) { return r.json(); })
    .then(function(d) {
      if (d.ok) location.reload();
      else showHeroMsg('<i data-lucide="x-circle" class="lucide"></i> ' + (d.error || '删除失败'), 'error');
    });
}
function showHeroMsg(msg, type) {
  var el = document.getElementById('hero-bg-msg');
  el.style.display = 'block';
  el.style.color = type === 'ok' ? '#16a34a' : type === 'error' ? '#dc2626' : 'var(--txt-2)';
  el.textContent = msg;
}

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
