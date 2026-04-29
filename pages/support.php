<?php
/*
 * pages/support.php — 客服中心
 * 功能：先由 AI 回答用户问题，AI 无法解决时用户可提交人工工单，
 *       并可查看自己所有工单的状态和回复。
 * 读库：support_tickets / support_replies
 * 权限：需登录
 */
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php?redirect=support.php'); exit; }

$uid = intval($_SESSION['user_id']);

$tickets = [];
$tr = $conn->query("SELECT t.*,
    (SELECT COUNT(*) FROM support_replies WHERE ticket_id=t.id) as reply_count,
    (SELECT is_admin FROM support_replies WHERE ticket_id=t.id ORDER BY created_at DESC LIMIT 1) as last_is_admin
    FROM support_tickets t
    WHERE t.user_id=$uid ORDER BY t.updated_at DESC LIMIT 20");
if ($tr) while ($r = $tr->fetch_assoc()) $tickets[] = $r;

$page_title = '客服中心';
include '../includes/header.php';
?>

<style>
.sc-msg-box { min-height:200px;max-height:380px;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px; }
.sc-bubble { max-width:78%;padding:9px 14px;border-radius:14px;font-size:14px;line-height:1.6;word-break:break-word; }
.sc-bubble.ai  { background:var(--bg-2);color:var(--txt);border-bottom-left-radius:3px;align-self:flex-start; }
.sc-bubble.usr { background:var(--primary);color:#fff;border-bottom-right-radius:3px;align-self:flex-end; }
.sc-bubble.typing { color:var(--txt-3);font-style:italic; }
.quick-chip { font-size:12px;padding:4px 10px;border:1px solid var(--border);border-radius:20px;
              background:transparent;color:var(--txt-2);cursor:pointer;transition:.15s; }
.quick-chip:hover { border-color:var(--primary);color:var(--primary); }
</style>

<div class="flex-center gap-8 mb-16" style="font-size:13px;color:var(--txt-2)">
  <a href="../index.php">首页</a> &rsaquo; <span>客服中心</span>
</div>

<div class="layout-2col">
<div class="col-main">

<!-- ── AI 客服 ── -->
<div class="card mb-20">
  <div class="card-header" style="display:flex;align-items:center;gap:8px">
    <span>🤖 AI 客服</span>
    <span style="font-size:12px;color:var(--txt-3);font-weight:400">— 遇到问题先问 AI，解决不了再转人工</span>
  </div>

  <div id="sc-msg-box" class="sc-msg-box">
    <div class="sc-bubble ai">你好！我是论坛 AI 客服 🎓 有什么可以帮到你？</div>
  </div>

  <div style="padding:12px 16px;border-top:1px solid var(--border)">
    <!-- 快捷问题 -->
    <div id="quick-chips" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px">
      <?php foreach (['如何发帖？','忘记密码怎么办？','如何举报违规？','社团怎么用？','积分/等级说明'] as $q): ?>
        <button class="quick-chip" onclick="quickAsk(<?= json_encode($q) ?>)"><?= h($q) ?></button>
      <?php endforeach; ?>
    </div>

    <!-- 输入框 -->
    <div style="display:flex;gap:8px">
      <input id="sc-input" type="text" placeholder="输入你的问题…" maxlength="300"
             style="flex:1;padding:8px 12px;border:1px solid var(--border);border-radius:var(--r);
                    font-size:14px;outline:none;background:var(--bg-2);color:var(--txt)"
             onkeydown="if(event.key==='Enter'&&!event.isComposing)sendAI()">
      <button onclick="sendAI()" id="sc-send-btn" class="btn btn-primary btn-sm">发送</button>
    </div>

    <!-- 转人工按钮（首次AI回复后显示） -->
    <div id="human-btn-wrap" style="display:none;margin-top:12px;text-align:center">
      <button onclick="showTicketForm()"
              style="padding:8px 22px;border-radius:var(--r);border:1.5px solid var(--danger);
                     background:transparent;color:var(--danger);font-size:13px;font-weight:600;cursor:pointer">
        👨‍💼 AI 无法解决？转人工客服
      </button>
    </div>
  </div>
</div>

<!-- ── 提交工单表单（隐藏，点击转人工后显示） ── -->
<div id="ticket-form-card" class="card mb-20" style="display:none">
  <div class="card-header">📋 提交人工工单</div>
  <div class="card-body">
    <div id="ticket-msg" style="font-size:13px;min-height:18px;margin-bottom:8px"></div>
    <div class="form-group">
      <label>问题分类</label>
      <select id="tk-cat" style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:var(--r);font-size:13px;background:var(--bg-2);color:var(--txt)">
        <option>账号问题</option><option>功能异常</option><option>内容投诉</option><option>功能建议</option><option>其他</option>
      </select>
    </div>
    <div class="form-group">
      <label>工单主题</label>
      <input type="text" id="tk-subject" maxlength="100" placeholder="简短描述你的问题…"
             style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:var(--r);font-size:13px;box-sizing:border-box;background:var(--bg-2);color:var(--txt)">
    </div>
    <div class="form-group">
      <label>详细描述</label>
      <textarea id="tk-content" rows="4" maxlength="1000" placeholder="请详细描述问题，以便客服更好地帮助你…"
                style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:var(--r);
                       font-size:13px;resize:vertical;box-sizing:border-box;font-family:inherit;background:var(--bg-2);color:var(--txt)"></textarea>
    </div>
    <div style="display:flex;gap:10px">
      <button onclick="submitTicket()" class="btn btn-primary">提交工单</button>
      <button onclick="hideTicketForm()" class="btn btn-outline">取消</button>
    </div>
  </div>
</div>

<!-- ── 我的工单 ── -->
<div class="card">
  <div class="card-header">📋 我的工单</div>
  <?php if (empty($tickets)): ?>
    <div class="card-body">
      <div class="empty-state" style="padding:28px 0">
        <div class="icon">📋</div>
        <p>暂无工单，有问题先试试 AI 客服吧</p>
      </div>
    </div>
  <?php else: ?>
  <div>
    <?php
    $status_label = ['open'=>'待处理','replied'=>'已回复','closed'=>'已关闭'];
    $status_color = ['open'=>'#f59e0b','replied'=>'#10b981','closed'=>'#6b7280'];
    foreach ($tickets as $tk):
      $sc = $tk['status'];
      // 用户未读：最后一条是管理员回复且状态=replied
      $has_unread = ($sc === 'replied' && $tk['last_is_admin']);
    ?>
    <div style="padding:14px 16px;border-bottom:1px solid var(--border)">
      <div style="display:flex;align-items:flex-start;gap:10px;flex-wrap:wrap">
        <div style="flex:1;min-width:0">
          <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:4px">
            <?php if ($has_unread): ?>
              <span style="width:7px;height:7px;border-radius:50%;background:var(--danger);flex-shrink:0"></span>
            <?php endif; ?>
            <span style="font-weight:600;font-size:14px"><?= h($tk['subject']) ?></span>
          </div>
          <div style="font-size:12px;color:var(--txt-3)">
            <?= h($tk['category']) ?> &nbsp;·&nbsp; <?= date('m-d H:i', strtotime($tk['created_at'])) ?>
            &nbsp;·&nbsp; <?= $tk['reply_count'] ?> 条回复
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
          <span id="status-user-<?= $tk['id'] ?>" style="font-size:12px;font-weight:600;color:<?= $status_color[$sc] ?>"><?= $status_label[$sc] ?></span>
          <button onclick="toggleTicket(<?= $tk['id'] ?>, false)" class="btn btn-outline btn-sm">查看</button>
          <?php if ($sc !== 'closed'): ?>
            <button onclick="closeTicket(<?= $tk['id'] ?>)" class="btn btn-outline btn-sm" style="color:var(--txt-3)">关闭</button>
          <?php endif; ?>
        </div>
      </div>
      <div id="tk-<?= $tk['id'] ?>" style="display:none;margin-top:12px;padding-top:12px;border-top:1px solid var(--border)"></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

</div><!-- .col-main -->

<!-- ── 侧边栏 ── -->
<div class="col-side">
  <div class="card mb-16">
    <div class="card-header">❓ 常见问题</div>
    <div class="card-body" style="font-size:13px;line-height:2.3">
      <?php foreach ([
        '如何修改头像或昵称？',
        '账号被封禁了怎么办？',
        '如何删除自己的帖子？',
        '如何加入/创建社团？',
        '积分和等级是怎么计算的？',
        '怎么找回忘记的密码？',
      ] as $faq): ?>
        <a href="#" onclick="quickAsk(<?= json_encode($faq) ?>);return false"
           style="display:block;color:var(--primary)"><?= h($faq) ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header">⏱ 响应时间</div>
    <div class="card-body" style="font-size:13px;color:var(--txt-2);line-height:2">
      <div>🤖 AI 客服：即时响应</div>
      <div>👨‍💼 人工客服：工作日 24h 内</div>
      <div>🔴 紧急问题：尽快处理</div>
    </div>
  </div>
</div>

</div><!-- .layout-2col -->

<script>
var aiHistory    = [];
var aiHasReplied = false;

function appendMsg(role, text) {
    var box = document.getElementById('sc-msg-box');
    var div = document.createElement('div');
    div.className = 'sc-bubble ' + (role === 'user' ? 'usr' : 'ai');
    div.textContent = text;
    box.appendChild(div);
    box.scrollTop = box.scrollHeight;
    return div;
}

function sendAI() {
    var input = document.getElementById('sc-input');
    var msg   = input.value.trim();
    if (!msg) return;
    input.value = '';
    document.getElementById('quick-chips').style.display = 'none';

    appendMsg('user', msg);
    aiHistory.push({role:'user', content:msg});

    var typing = appendMsg('ai', '正在思考…');
    typing.classList.add('typing');
    document.getElementById('sc-send-btn').disabled = true;

    var fd = new FormData();
    fd.append('message', msg);
    fd.append('history', JSON.stringify(aiHistory.slice(-8)));

    fetch('../actions/support_ai_chat.php', {method:'POST', body:fd})
        .then(function(r){ return r.json(); })
        .then(function(d) {
            var reply = d.reply || '暂时无法回复，请点击「转人工客服」。';
            typing.textContent = reply;
            typing.classList.remove('typing');
            aiHistory.push({role:'assistant', content:reply});
            document.getElementById('sc-send-btn').disabled = false;
            if (!aiHasReplied) {
                aiHasReplied = true;
                document.getElementById('human-btn-wrap').style.display = 'block';
            }
        }).catch(function() {
            typing.textContent = '网络错误，请点击「转人工客服」联系我们。';
            typing.classList.remove('typing');
            document.getElementById('sc-send-btn').disabled = false;
            document.getElementById('human-btn-wrap').style.display = 'block';
        });
}

function quickAsk(q) {
    document.getElementById('sc-input').value = q;
    window.scrollTo({top:0, behavior:'smooth'});
    sendAI();
}

function showTicketForm() {
    var card = document.getElementById('ticket-form-card');
    card.style.display = 'block';
    card.scrollIntoView({behavior:'smooth', block:'start'});
    // 从 AI 对话中提取最后一条用户提问作为工单主题
    for (var i = aiHistory.length - 1; i >= 0; i--) {
        if (aiHistory[i].role === 'user') {
            document.getElementById('tk-subject').value = aiHistory[i].content.substring(0, 80);
            break;
        }
    }
}

function hideTicketForm() {
    document.getElementById('ticket-form-card').style.display = 'none';
}

function submitTicket() {
    var subject  = document.getElementById('tk-subject').value.trim();
    var content  = document.getElementById('tk-content').value.trim();
    var category = document.getElementById('tk-cat').value;
    var msgEl    = document.getElementById('ticket-msg');
    if (!subject || !content) {
        msgEl.textContent  = '请填写工单主题和详细描述';
        msgEl.style.color  = 'var(--danger)';
        return;
    }
    var fd = new FormData();
    fd.append('action',     'create');
    fd.append('category',   category);
    fd.append('subject',    subject);
    fd.append('content',    content);
    fd.append('ai_context', JSON.stringify(aiHistory));
    fetch('../actions/support_action.php', {method:'POST', body:fd})
        .then(function(r){ return r.json(); })
        .then(function(d) {
            msgEl.textContent = d.msg;
            msgEl.style.color = d.ok ? 'var(--success)' : 'var(--danger)';
            if (d.ok) setTimeout(function(){ location.reload(); }, 1500);
        });
}

function closeTicket(id) {
    if (!confirm('确认关闭此工单？关闭后将无法再追加回复。')) return;
    var fd = new FormData();
    fd.append('action',    'close');
    fd.append('ticket_id', id);
    fetch('../actions/support_action.php', {method:'POST', body:fd})
        .then(function(r){ return r.json(); })
        .then(function(d){ if (d.ok) location.reload(); });
}

function toggleTicket(id, isAdminView) {
    var el = document.getElementById('tk-' + id);
    if (el.style.display !== 'none' && el.dataset.loaded) {
        el.style.display = 'none';
        return;
    }
    el.style.display = 'block';
    if (el.dataset.loaded) return;
    el.innerHTML = '<div style="color:var(--txt-3);font-size:13px">加载中…</div>';

    fetch('../actions/support_load_replies.php?ticket_id=' + id)
        .then(function(r){ return r.json(); })
        .then(function(d) {
            if (!d.ok) {
                el.innerHTML = '<div style="color:var(--danger);font-size:13px">加载失败</div>';
                return;
            }
            var html = '';

            // 原始工单内容
            html += '<div style="padding:8px 12px;background:var(--bg-2);border-radius:var(--r);font-size:13px;margin-bottom:10px;white-space:pre-wrap">';
            html += escH(d.content) + '</div>';

            // 回复气泡
            if (d.replies.length === 0) {
                html += '<div style="color:var(--txt-3);font-size:13px;padding:4px 0 8px">暂无回复，客服处理中…</div>';
            }
            d.replies.forEach(function(r) {
                var admin = r.is_admin == 1;
                html += '<div style="display:flex;gap:10px;margin-bottom:8px;justify-content:' + (admin ? 'flex-start' : 'flex-end') + '">';
                html += '<div style="max-width:82%;padding:8px 12px;border-radius:10px;font-size:13px;'
                      + 'background:' + (admin ? 'var(--bg-2)' : 'var(--primary)') + ';'
                      + 'color:'      + (admin ? 'var(--txt)' : '#fff') + '">';
                html += '<div style="font-size:11px;margin-bottom:3px;color:' + (admin ? 'var(--txt-3)' : 'rgba(255,255,255,.7)') + '">';
                html += (admin ? '🛡 客服' : '👤 我') + ' · ' + escH(r.created_at.substring(5,16));
                html += '</div><div style="white-space:pre-wrap">' + escH(r.content) + '</div></div></div>';
            });

            // 回复输入（工单未关闭时显示）
            if (d.status !== 'closed') {
                html += '<div style="margin-top:8px">';
                html += '<textarea id="reply-' + id + '" rows="2" placeholder="追加说明…"'
                      + ' style="width:100%;padding:7px 10px;border:1px solid var(--border);border-radius:var(--r);'
                      + 'font-size:13px;resize:none;box-sizing:border-box;font-family:inherit;background:var(--bg-2);color:var(--txt)"></textarea>';
                html += '<button onclick="replyTicket(' + id + ')" class="btn btn-primary btn-sm" style="margin-top:6px">发送</button>';
                html += '</div>';
            }

            el.dataset.loaded = '1';
            el.innerHTML = html;
        }).catch(function() {
            el.innerHTML = '<div style="color:var(--danger);font-size:13px">加载失败，请刷新重试</div>';
        });
}

function replyTicket(id) {
    var ta = document.getElementById('reply-' + id);
    if (!ta || !ta.value.trim()) return;
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
                el.style.display = 'none';
                toggleTicket(id, false);
            }
        });
}

function escH(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>

<?php include '../includes/footer.php'; ?>
