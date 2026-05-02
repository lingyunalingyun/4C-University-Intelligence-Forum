<?php
/*
 * pages/ai_assistant.php — AI 智能助手页面
 * 功能：内嵌 DeepSeek 多轮对话界面，支持历史记录本地保存，
 *       提供学习/求职/编程等场景快捷引导语。
 * 权限：需登录，API Key 未配置时显示提示
 */
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php?redirect=ai_assistant.php'); exit; }

$uid        = intval($_SESSION['user_id']);
$user_avatar = avatar_url($_SESSION['avatar'] ?? '', '../');
$page_title = 'AI助手';
$ai_enabled = !empty(DEEPSEEK_API_KEY);
include '../includes/header.php';
?>

<style>
/* ── 覆盖本页 main-wrap，让聊天占满剩余视口 ── */
.main-wrap {
  padding: 0 !important;
  max-width: 100% !important;
  height: calc(100vh - var(--nav-h));
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* ── 聊天页整体容器 ── */
.ai-page {
  display: flex;
  flex-direction: column;
  height: 100%;
  max-width: 860px;
  width: 100%;
  margin: 0 auto;
  background: var(--bg-card);
  border-left: 1px solid var(--border);
  border-right: 1px solid var(--border);
}

/* ── 顶栏 ── */
.ai-topbar {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 20px;
  border-bottom: 1px solid var(--border);
  flex-shrink: 0;
  background: var(--bg-card);
}
.ai-avatar-wrap {
  width: 40px; height: 40px; border-radius: 50%;
  background: linear-gradient(135deg, #2563eb, #7c3aed);
  display: flex; align-items: center; justify-content: center;
  font-size: 20px; flex-shrink: 0;
}
.ai-topbar-name  { font-weight: 700; font-size: 15px; }
.ai-topbar-sub   { font-size: 12px; color: var(--txt-2); margin-top: 1px; }
.ai-status-dot   { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 4px; }

/* ── 消息区 ── */
.ai-messages {
  flex: 1;
  overflow-y: auto;
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 18px;
  background: var(--bg);
  scroll-behavior: smooth;
}
.ai-messages::-webkit-scrollbar { width: 5px; }
.ai-messages::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

/* ── 单条消息 ── */
.ai-msg { display: flex; gap: 10px; max-width: 82%; }
.ai-msg.from-ai   { align-self: flex-start; }
.ai-msg.from-user { align-self: flex-end; flex-direction: row-reverse; }

.ai-msg-avatar {
  width: 34px; height: 34px; border-radius: 50%;
  flex-shrink: 0; object-fit: cover;
  display: flex; align-items: center; justify-content: center;
  font-size: 16px;
}
.ai-msg-avatar img { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; }

.ai-bubble {
  padding: 10px 14px;
  border-radius: 18px;
  font-size: 14px;
  line-height: 1.7;
  word-break: break-word;
}
.from-ai   .ai-bubble {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-top-left-radius: 4px;
  color: var(--txt);
}
.from-user .ai-bubble {
  background: var(--primary);
  color: #fff;
  border-top-right-radius: 4px;
}

/* Markdown 样式 */
.ai-bubble strong { font-weight: 700; }
.ai-bubble em     { font-style: italic; }
.ai-bubble code {
  background: rgba(0,0,0,.06); border-radius: 4px;
  padding: 1px 5px; font-size: 12px; font-family: monospace;
}
.from-user .ai-bubble code { background: rgba(255,255,255,.2); }
.ai-bubble pre {
  background: #1e293b; color: #e2e8f0;
  border-radius: 8px; padding: 12px 14px;
  overflow-x: auto; margin: 8px 0;
  font-size: 12.5px; line-height: 1.6;
}
.ai-bubble pre code { background: none; padding: 0; color: inherit; font-size: inherit; }
.ai-bubble ul, .ai-bubble ol { margin: 4px 0; padding-left: 20px; }
.ai-bubble li { margin: 3px 0; }
.ai-bubble h2,.ai-bubble h3,.ai-bubble h4 { margin: 8px 0 4px; }

/* 思考动画 */
.typing-dot {
  display: inline-flex; gap: 4px; align-items: center; padding: 4px 2px;
}
.typing-dot span {
  width: 7px; height: 7px; border-radius: 50%;
  background: var(--txt-3);
  animation: blink 1.2s infinite;
}
.typing-dot span:nth-child(2) { animation-delay: .2s; }
.typing-dot span:nth-child(3) { animation-delay: .4s; }
@keyframes blink {
  0%,80%,100% { opacity: .25; transform: scale(.9); }
  40%         { opacity: 1;   transform: scale(1.1); }
}

/* ── 时间戳 ── */
.ai-msg-time {
  font-size: 11px; color: var(--txt-3);
  margin-top: 4px; padding: 0 4px;
  align-self: flex-end;
}
.from-user .ai-msg-time { align-self: flex-start; }

/* ── 输入区 ── */
.ai-input-area {
  border-top: 1px solid var(--border);
  background: var(--bg-card);
  flex-shrink: 0;
  padding: 12px 20px 16px;
}
.ai-hints {
  display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 10px;
}
.ai-hint-btn {
  font-size: 12px; padding: 4px 10px;
  border: 1px solid var(--border);
  border-radius: 20px; background: var(--bg-2);
  color: var(--txt-2); cursor: pointer;
  transition: all .15s; white-space: nowrap;
}
.ai-hint-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-lt); }

.ai-input-row {
  display: flex; gap: 10px; align-items: flex-end;
}
.ai-textarea {
  flex: 1; padding: 10px 14px;
  border: 1px solid var(--border); border-radius: 12px;
  font-size: 14px; font-family: inherit;
  resize: none; outline: none;
  background: var(--bg-2); color: var(--txt);
  line-height: 1.5; min-height: 44px; max-height: 140px;
  transition: border-color .15s;
}
.ai-textarea:focus { border-color: var(--primary); }
.ai-send-btn {
  width: 44px; height: 44px; border-radius: 12px;
  background: var(--primary); color: #fff;
  border: none; cursor: pointer; font-size: 18px;
  display: flex; align-items: center; justify-content: center;
  transition: background .15s, transform .1s;
  flex-shrink: 0;
}
.ai-send-btn:hover:not(:disabled) { background: var(--primary-dk); transform: scale(1.05); }
.ai-send-btn:disabled { opacity: .5; cursor: not-allowed; transform: none; }
.ai-input-tip { font-size: 11px; color: var(--txt-3); margin-top: 6px; }

/* 未配置提示 */
.ai-disabled-tip {
  text-align: center; padding: 60px 20px;
  color: var(--txt-2); font-size: 14px;
}
</style>

<div class="ai-page">

  <!-- ── 顶栏 ── -->
  <div class="ai-topbar">
    <div class="ai-avatar-wrap"><i data-lucide="bot" class="lucide"></i></div>
    <div style="flex:1">
      <div class="ai-topbar-name">校园 AI 助手</div>
      <div class="ai-topbar-sub">
        <?php if ($ai_enabled): ?>
          <span class="ai-status-dot" style="background:#22c55e"></span>DeepSeek 驱动 · 在线
        <?php else: ?>
          <span class="ai-status-dot" style="background:#ef4444"></span>未配置 API Key
        <?php endif; ?>
      </div>
    </div>
    <button class="ai-hint-btn" onclick="clearChat()" style="border-radius:8px;padding:6px 12px"><i data-lucide="trash-2" class="lucide"></i> 清空对话</button>
  </div>

  <!-- ── 消息区 ── -->
  <div class="ai-messages" id="chat-box">
    <?php if ($ai_enabled): ?>
    <div class="ai-msg from-ai">
      <div class="ai-msg-avatar" style="background:linear-gradient(135deg,#2563eb,#7c3aed)"><i data-lucide="bot" class="lucide"></i></div>
      <div>
        <div class="ai-bubble">
          你好！我是校园 AI 助手 👋<br><br>
          我可以帮你：<strong>解答学习问题</strong>、<strong>分析论文思路</strong>、<strong>准备求职简历</strong>、<strong>编程 Debug</strong> 等。<br><br>
          有什么需要帮助的？
        </div>
        <div class="ai-msg-time"><?= date('H:i') ?></div>
      </div>
    </div>
    <?php else: ?>
    <div class="ai-disabled-tip">
      <div style="font-size:40px;margin-bottom:12px"><i data-lucide="key" class="lucide"></i></div>
      <div style="font-weight:600;margin-bottom:6px">AI 功能未启用</div>
      <div>请管理员在后台配置 DeepSeek API Key</div>
      <?php if (in_array($_SESSION['role'] ?? '', ['admin','owner'])): ?>
        <a href="../admin/settings.php" class="btn btn-primary" style="margin-top:14px;display:inline-block">前往配置</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── 输入区 ── -->
  <?php if ($ai_enabled): ?>
  <div class="ai-input-area">
    <div class="ai-hints" id="hints-bar">
      <?php foreach ([
        '帮我分析这段代码','如何准备秋招简历','推荐考研备考方法','论文摘要怎么写','解释一下这个概念'
      ] as $hint): ?>
        <button class="ai-hint-btn" onclick="useHint(this.textContent)"><?= h($hint) ?></button>
      <?php endforeach; ?>
    </div>
    <div class="ai-input-row">
      <textarea id="user-input" class="ai-textarea" rows="1"
        placeholder="输入你的问题，Enter 发送…"
        oninput="autoResize(this)"
        onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMsg();}"></textarea>
      <button id="send-btn" class="ai-send-btn" onclick="sendMsg()" title="发送">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <line x1="22" y1="2" x2="11" y2="13"></line>
          <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
        </svg>
      </button>
    </div>
    <div class="ai-input-tip">Enter 发送 &nbsp;·&nbsp; Shift+Enter 换行</div>
  </div>
  <?php endif; ?>

</div>

<script>
const chatBox  = document.getElementById('chat-box');
const inputEl  = document.getElementById('user-input');
const sendBtn  = document.getElementById('send-btn');
const STORE_KEY = 'ai_chat_<?= $uid ?>';
const TTL       = 12 * 60 * 60 * 1000; // 12h

let isLoading = false;
let history   = [];
let msgLog    = []; // 用于持久化显示消息

/* ── 自动伸缩输入框 ── */
function autoResize(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 140) + 'px';
}

/* ── 轻量 Markdown 渲染 ── */
function md(raw) {
  let s = raw.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  s = s.replace(/```[\w]*\n?([\s\S]*?)```/g, '<pre><code>$1</code></pre>');
  s = s.replace(/`([^`\n]+)`/g, '<code>$1</code>');
  s = s.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  s = s.replace(/__(.+?)__/g,     '<strong>$1</strong>');
  s = s.replace(/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/g, '<em>$1</em>');
  s = s.replace(/^### (.+)$/gm, '<h4 style="margin:8px 0 3px">$1</h4>');
  s = s.replace(/^## (.+)$/gm,  '<h3 style="margin:10px 0 4px">$1</h3>');
  s = s.replace(/^# (.+)$/gm,   '<h2 style="margin:10px 0 5px">$1</h2>');
  s = s.replace(/^[-*] (.+)$/gm, '<li>$1</li>');
  s = s.replace(/(<li>[\s\S]+?<\/li>)(?=(<br>)*(?!<li>)|$)/g, '<ul>$1</ul>');
  s = s.replace(/^\d+\. (.+)$/gm, '<li>$1</li>');
  s = s.replace(/\n{2,}/g, '<br><br>');
  s = s.replace(/\n/g, '<br>');
  return s;
}

function nowTime() {
  return new Date().toLocaleTimeString('zh-CN', {hour:'2-digit', minute:'2-digit'});
}

/* ── 追加消息气泡（不写 storage） ── */
function appendBubble(role, content, isHtml, time) {
  const isUser = role === 'user';
  const wrap   = document.createElement('div');
  wrap.className = 'ai-msg ' + (isUser ? 'from-user' : 'from-ai');

  const avatarDiv = document.createElement('div');
  avatarDiv.className = 'ai-msg-avatar';
  if (isUser) {
    const img = document.createElement('img');
    img.src = '<?= $user_avatar ?>';
    img.onerror = () => { img.style.display='none'; avatarDiv.textContent='<i data-lucide="user" class="lucide"></i>'; };
    avatarDiv.appendChild(img);
  } else {
    avatarDiv.style.cssText = 'background:linear-gradient(135deg,#2563eb,#7c3aed);color:#fff;font-size:16px';
    avatarDiv.textContent = '<i data-lucide="bot" class="lucide"></i>';
  }

  const bodyDiv = document.createElement('div');
  const bubble  = document.createElement('div');
  bubble.className = 'ai-bubble';
  if (isHtml) bubble.innerHTML = content;
  else        bubble.textContent = content;

  const timeEl = document.createElement('div');
  timeEl.className = 'ai-msg-time';
  timeEl.textContent = time || nowTime();

  bodyDiv.appendChild(bubble);
  bodyDiv.appendChild(timeEl);

  if (isUser) { wrap.appendChild(bodyDiv); wrap.appendChild(avatarDiv); }
  else        { wrap.appendChild(avatarDiv); wrap.appendChild(bodyDiv); }

  chatBox.appendChild(wrap);
  chatBox.scrollTop = chatBox.scrollHeight;
  return bubble;
}

/* ── localStorage 读写 ── */
function saveSession() {
  try {
    localStorage.setItem(STORE_KEY, JSON.stringify({
      savedAt: Date.now(),
      history,
      msgLog
    }));
  } catch(e) {}
}

function loadSession() {
  try {
    const raw = localStorage.getItem(STORE_KEY);
    if (!raw) return false;
    const data = JSON.parse(raw);
    if (!data.savedAt || Date.now() - data.savedAt > TTL) {
      localStorage.removeItem(STORE_KEY);
      return false;
    }
    history = data.history || [];
    msgLog  = data.msgLog  || [];
    return true;
  } catch(e) { return false; }
}

function clearSession() {
  localStorage.removeItem(STORE_KEY);
  history = [];
  msgLog  = [];
}

/* ── 恢复历史对话 ── */
function restoreSession() {
  if (!loadSession() || msgLog.length === 0) return;

  // 清除欢迎气泡，换成恢复提示
  chatBox.innerHTML = '';
  const ago = (() => {
    try {
      const d = JSON.parse(localStorage.getItem(STORE_KEY) || '{}');
      const min = Math.round((Date.now() - d.savedAt) / 60000);
      return min < 60 ? `${min} 分钟前` : `${Math.round(min/60)} 小时前`;
    } catch(e) { return ''; }
  })();
  const sep = document.createElement('div');
  sep.style.cssText = 'text-align:center;font-size:12px;color:var(--txt-3);padding:4px 0 8px';
  sep.textContent = `已恢复 ${ago} 的对话记录`;
  chatBox.appendChild(sep);

  msgLog.forEach(m => {
    appendBubble(m.role, m.role === 'ai' ? md(m.raw) : m.raw, true, m.time);
  });
}

/* ── 对外 appendMsg（带持久化） ── */
function appendMsg(role, raw, isHtml, time) {
  const t = time || nowTime();
  const bubble = appendBubble(role, isHtml ? raw : raw, isHtml, t);
  return bubble;
}

/* ── 使用快捷提示 ── */
function useHint(text) {
  inputEl.value = text;
  inputEl.focus();
  autoResize(inputEl);
  const bar = document.getElementById('hints-bar');
  if (bar) bar.style.display = 'none';
}

/* ── 发送消息 ── */
async function sendMsg() {
  if (isLoading || !sendBtn) return;
  const text = inputEl.value.trim();
  if (!text) return;

  inputEl.value = '';
  autoResize(inputEl);
  isLoading = true;
  sendBtn.disabled = true;

  const t = nowTime();
  appendBubble('user', text, false, t);
  history.push({role:'user', content:text});
  msgLog.push({role:'user', raw:text, time:t});

  const aiBubble = appendBubble('ai',
    '<div class="typing-dot"><span></span><span></span><span></span></div>', true);

  try {
    const res = await fetch('../actions/ai_chat.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: 'message=' + encodeURIComponent(text)
          + '&history=' + encodeURIComponent(JSON.stringify(history.slice(-8)))
    });
    const data  = await res.json();
    const reply = data.reply || '抱歉，我暂时无法回答这个问题。';
    const rt    = nowTime();
    aiBubble.innerHTML = md(reply);
    // 更新气泡下方时间
    aiBubble.closest('.ai-msg').querySelector('.ai-msg-time').textContent = rt;
    history.push({role:'assistant', content:reply});
    msgLog.push({role:'ai', raw:reply, time:rt});
    saveSession();
  } catch(e) {
    aiBubble.innerHTML = '<span style="color:#ef4444"><i data-lucide="alert-triangle" class="lucide"></i> 请求失败，请检查网络后重试。</span>';
  }

  isLoading = false;
  sendBtn.disabled = false;
  chatBox.scrollTop = chatBox.scrollHeight;
}

/* ── 清空对话 ── */
function clearChat() {
  clearSession();
  chatBox.innerHTML = '';
  appendBubble('ai', '对话已清空，有什么新问题吗？', false);
  const bar = document.getElementById('hints-bar');
  if (bar) bar.style.display = 'flex';
}

/* ── 初始化：尝试恢复历史 ── */
restoreSession();

/* ── 处理从帖子页分享跳转 ── */
(function(){
  var p = new URLSearchParams(location.search);
  var title = p.get('share_title');
  var url   = p.get('share_url');
  if (!title || !url) return;
  setTimeout(function(){
    var inp = document.getElementById('msg-input');
    if (!inp) return;
    inp.value = '帮我分析这篇帖子，给出评价和要点总结：\n「' + title + '」\n' + url;
    inp.style.height = 'auto';
    inp.style.height = Math.min(inp.scrollHeight, 140) + 'px';
    inp.focus();
  }, 150);
})();
</script>

<?php include '../includes/footer.php'; ?>
