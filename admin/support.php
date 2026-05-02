<?php
/*
 * admin/support.php — 客服工单管理
 * 功能：查看/筛选全部工单，回复用户，一键关闭工单，查看 AI 对话上下文。
 * 读库：support_tickets / support_replies / users
 * 写库：support_tickets / support_replies / notifications
 * 权限：admin / owner
 */
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!in_array($_SESSION['role'] ?? '', ['admin','owner'])) {
    header('Location: ../pages/login.php'); exit;
}

// ── 筛选参数 ──────────────────────────────────────────
$filter_status = $_GET['status'] ?? 'open';
$filter_cat    = $_GET['cat']    ?? '';
$page          = max(1, intval($_GET['p'] ?? 1));
$per_page      = 20;
$offset        = ($page - 1) * $per_page;

$where = [];
if (in_array($filter_status, ['open','replied','closed'])) $where[] = "t.status='$filter_status'";
$cats = ['账号问题','功能异常','内容投诉','功能建议','其他'];
if (in_array($filter_cat, $cats)) $where[] = "t.category='" . $conn->real_escape_string($filter_cat) . "'";
$wsql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total = (int)$conn->query("SELECT COUNT(*) as c FROM support_tickets t $wsql")->fetch_assoc()['c'];
$pages = max(1, ceil($total / $per_page));

$tickets = [];
$res = $conn->query("SELECT t.*,
    u.username as user_name, u.avatar as user_avatar,
    (SELECT COUNT(*) FROM support_replies WHERE ticket_id=t.id) as reply_count
    FROM support_tickets t
    LEFT JOIN users u ON u.id=t.user_id
    $wsql ORDER BY t.updated_at DESC LIMIT $per_page OFFSET $offset");
if ($res) while ($row = $res->fetch_assoc()) $tickets[] = $row;

// 待处理数
$open_count = (int)$conn->query("SELECT COUNT(*) as c FROM support_tickets WHERE status='open'")->fetch_assoc()['c'];

$page_title = '客服工单';
include '../includes/header.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px">
  <h2 style="margin:0;font-size:18px"><i data-lucide="headphones" class="lucide"></i> 客服工单
    <?php if ($open_count): ?>
      <span style="background:var(--danger);color:#fff;font-size:12px;padding:2px 8px;border-radius:12px;margin-left:8px;font-weight:600"><?= $open_count ?></span>
    <?php endif; ?>
  </h2>

  <form method="get" style="display:flex;gap:8px;flex-wrap:wrap">
    <select name="status" onchange="this.form.submit()" style="padding:6px 10px;border:1px solid var(--border);border-radius:var(--r);font-size:13px;background:var(--bg-2);color:var(--txt)">
      <option value=""        <?= $filter_status===''        ?'selected':'' ?>>全部状态</option>
      <option value="open"    <?= $filter_status==='open'    ?'selected':'' ?>>待处理</option>
      <option value="replied" <?= $filter_status==='replied' ?'selected':'' ?>>已回复</option>
      <option value="closed"  <?= $filter_status==='closed'  ?'selected':'' ?>>已关闭</option>
    </select>
    <select name="cat" onchange="this.form.submit()" style="padding:6px 10px;border:1px solid var(--border);border-radius:var(--r);font-size:13px;background:var(--bg-2);color:var(--txt)">
      <option value="">全部分类</option>
      <?php foreach (['账号问题','功能异常','内容投诉','功能建议','其他'] as $c): ?>
        <option <?= $filter_cat===$c?'selected':'' ?>><?= $c ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($filter_status||$filter_cat): ?>
      <a href="support.php" class="btn btn-outline btn-sm">清除筛选</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
      <tr style="background:var(--bg-2);text-align:left">
        <th style="padding:10px 14px;color:var(--txt-2);font-weight:600">用户</th>
        <th style="padding:10px 14px;color:var(--txt-2);font-weight:600">分类</th>
        <th style="padding:10px 14px;color:var(--txt-2);font-weight:600">主题</th>
        <th style="padding:10px 14px;color:var(--txt-2);font-weight:600">回复</th>
        <th style="padding:10px 14px;color:var(--txt-2);font-weight:600">状态</th>
        <th style="padding:10px 14px;color:var(--txt-2);font-weight:600">时间</th>
        <th style="padding:10px 14px;color:var(--txt-2);font-weight:600">操作</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($tickets)): ?>
      <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--txt-3)">暂无工单</td></tr>
    <?php endif; ?>
    <?php
    $sl = ['open'=>'待处理','replied'=>'已回复','closed'=>'已关闭'];
    $sc = ['open'=>'#f59e0b','replied'=>'#10b981','closed'=>'#6b7280'];
    foreach ($tickets as $tk):
    ?>
    <tr style="border-top:1px solid var(--border)">
      <td style="padding:10px 14px">
        <a href="../pages/profile.php?id=<?= $tk['user_id'] ?>" target="_blank" style="color:var(--primary);font-weight:500"><?= h($tk['user_name'] ?? '-') ?></a>
      </td>
      <td style="padding:10px 14px;color:var(--txt-2)"><?= h($tk['category']) ?></td>
      <td style="padding:10px 14px;max-width:200px">
        <div style="font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($tk['subject']) ?></div>
      </td>
      <td style="padding:10px 14px;color:var(--txt-3);text-align:center"><?= $tk['reply_count'] ?></td>
      <td style="padding:10px 14px">
        <span id="status-<?= $tk['id'] ?>" style="font-weight:600;color:<?= $sc[$tk['status']] ?>"><?= $sl[$tk['status']] ?></span>
      </td>
      <td style="padding:10px 14px;color:var(--txt-3);white-space:nowrap"><?= date('m-d H:i', strtotime($tk['updated_at'])) ?></td>
      <td style="padding:10px 14px">
        <button onclick="toggleTicket(<?= $tk['id'] ?>)" class="btn btn-outline btn-sm">查看/回复</button>
      </td>
    </tr>
    <!-- 展开区 -->
    <tr id="expand-<?= $tk['id'] ?>" style="display:none">
      <td colspan="7" style="padding:0 20px 16px;background:var(--bg-2)">
        <div id="tk-<?= $tk['id'] ?>"></div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- 分页 -->
<?php if ($pages > 1): ?>
<div style="display:flex;gap:6px;justify-content:center;margin-top:16px;flex-wrap:wrap">
  <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a href="?status=<?= h($filter_status) ?>&cat=<?= urlencode($filter_cat) ?>&p=<?= $i ?>"
       style="padding:5px 12px;border-radius:var(--r);border:1px solid var(--border);font-size:13px;
              <?= $i===$page ? 'background:var(--primary);color:#fff;border-color:var(--primary)' : 'color:var(--txt)' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<script>
function toggleTicket(id) {
    var row = document.getElementById('expand-' + id);
    var el  = document.getElementById('tk-' + id);

    if (row.style.display !== 'none' && el.dataset.loaded) {
        row.style.display = 'none';
        return;
    }
    row.style.display = '';
    if (el.dataset.loaded) return;
    el.innerHTML = '<div style="color:var(--txt-3);font-size:13px;padding:12px 0">加载中…</div>';

    fetch('../actions/support_load_replies.php?ticket_id=' + id)
        .then(function(r){ return r.json(); })
        .then(function(d) {
            if (!d.ok) { el.innerHTML = '<div style="color:var(--danger);padding:8px 0">加载失败</div>'; return; }
            var html = '';

            // 用户原始描述
            html += '<div style="margin-top:12px;margin-bottom:10px">';
            html += '<div style="font-size:11px;color:var(--txt-3);margin-bottom:4px">用户原始描述</div>';
            html += '<div style="padding:8px 12px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--r);font-size:13px;white-space:pre-wrap">' + escH(d.content) + '</div>';
            html += '</div>';

            // AI 对话上下文（可折叠）
            if (d.ai_context) {
                try {
                    var ctx = JSON.parse(d.ai_context);
                    if (Array.isArray(ctx) && ctx.length > 0) {
                        html += '<details style="margin-bottom:10px">';
                        html += '<summary style="font-size:12px;color:var(--txt-3);cursor:pointer;padding:4px 0"><i data-lucide="bot" class="lucide"></i> AI 对话记录（' + ctx.length + ' 条）</summary>';
                        html += '<div style="margin-top:6px;padding:8px 12px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--r);font-size:12px;color:var(--txt-2);max-height:200px;overflow-y:auto">';
                        ctx.forEach(function(m) {
                            html += '<div style="margin-bottom:6px"><strong>' + (m.role==='user'?'用户':'AI') + '：</strong>' + escH(m.content) + '</div>';
                        });
                        html += '</div></details>';
                    }
                } catch(e) {}
            }

            // 回复历史
            html += '<div style="margin-bottom:8px">';
            if (!d.replies.length) {
                html += '<div style="color:var(--txt-3);font-size:13px;padding:4px 0">暂无回复</div>';
            }
            d.replies.forEach(function(r) {
                var admin = r.is_admin == 1;
                html += '<div style="padding:7px 0;border-bottom:1px solid var(--border)">';
                html += '<div style="font-size:11px;color:var(--txt-3);margin-bottom:2px">';
                html += (admin ? '🛡 客服（' + escH(r.username) + '）' : '<i data-lucide="user" class="lucide"></i> 用户') + ' · ' + escH(r.created_at.substring(0,16));
                html += '</div>';
                html += '<div style="font-size:13px;white-space:pre-wrap">' + escH(r.content) + '</div>';
                html += '</div>';
            });
            html += '</div>';

            // 回复表单
            if (d.status !== 'closed') {
                html += '<textarea id="admin-reply-' + id + '" rows="3" placeholder="输入回复内容…"'
                      + ' style="width:100%;padding:8px;border:1px solid var(--border);border-radius:var(--r);'
                      + 'font-size:13px;resize:vertical;box-sizing:border-box;font-family:inherit;background:var(--bg-card);color:var(--txt)"></textarea>';
                html += '<div style="display:flex;gap:8px;margin-top:6px">';
                html += '<button onclick="adminReply(' + id + ')" class="btn btn-primary btn-sm">回复用户</button>';
                html += '<button onclick="adminClose(' + id + ')" class="btn btn-outline btn-sm" style="color:var(--danger)">关闭工单</button>';
                html += '</div>';
            } else {
                html += '<div style="font-size:12px;color:var(--txt-3);padding:4px 0">工单已关闭</div>';
            }

            el.dataset.loaded = '1';
            el.innerHTML = html;
        }).catch(function() {
            el.innerHTML = '<div style="color:var(--danger);font-size:13px;padding:8px 0">加载失败，请刷新重试</div>';
        });
}

function adminReply(id) {
    var ta = document.getElementById('admin-reply-' + id);
    if (!ta || !ta.value.trim()) { alert('请输入回复内容'); return; }
    var fd = new FormData();
    fd.append('action',    'reply');
    fd.append('ticket_id', id);
    fd.append('content',   ta.value.trim());
    fetch('../actions/support_action.php', {method:'POST', body:fd})
        .then(function(r){ return r.json(); })
        .then(function(d) {
            if (d.ok) {
                var el = document.getElementById('tk-' + id);
                delete el.dataset.loaded;
                var badge = document.getElementById('status-' + id);
                if (badge) { badge.textContent = '已回复'; badge.style.color = '#10b981'; }
                toggleTicket(id);
            } else {
                alert(d.msg || '回复失败');
            }
        });
}

function adminClose(id) {
    if (!confirm('确认关闭该工单？')) return;
    var fd = new FormData();
    fd.append('action',    'close');
    fd.append('ticket_id', id);
    fetch('../actions/support_action.php', {method:'POST', body:fd})
        .then(function(r){ return r.json(); })
        .then(function(d){ if (d.ok) location.reload(); });
}

function escH(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>

<?php include '../includes/footer.php'; ?>
