<?php
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php?redirect=ai_assistant.php'); exit; }

$uid = intval($_SESSION['user_id']);
$page_title = 'AI助手';
include '../includes/header.php';
?>

<div class="flex-center gap-8 mb-16" style="font-size:13px;color:var(--txt-2)">
  <a href="../index.php">首页</a> &rsaquo; <span>AI助手</span>
</div>

<div class="card" style="max-width:800px;margin:0 auto">
  <div class="card-header" style="display:flex;align-items:center;gap:10px">
    <span style="font-size:20px">🤖</span>
    <div>
      <div style="font-weight:700">校园AI助手</div>
      <div style="font-size:12px;color:var(--txt-2)">由 DeepSeek 驱动 · 可回答学习、求职、校园生活相关问题</div>
    </div>
    <button class="btn btn-outline btn-sm" style="margin-left:auto" onclick="clearChat()">清空对话</button>
  </div>

  <div class="chat-wrap" id="chat-box">
    <div class="chat-msg chat-ai">
      <div class="chat-bubble">
        你好！我是校园AI助手，可以帮你解答学习疑问、提供求职建议、分析论文思路等。有什么需要帮助的吗？😊
      </div>
    </div>
  </div>

  <div style="padding:16px;border-top:1px solid var(--border)">
    <!-- 快捷提示 -->
    <div id="quick-hints" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
      <?php foreach (['帮我分析这段代码的问题','如何准备秋招简历','推荐考研备考方法','我的论文摘要怎么写'] as $hint): ?>
        <button class="btn btn-outline btn-sm" onclick="useHint(this.textContent)"><?= h($hint) ?></button>
      <?php endforeach; ?>
    </div>
    <div style="display:flex;gap:10px;align-items:flex-end">
      <textarea id="user-input" rows="2" placeholder="输入你的问题..."
        style="flex:1;padding:10px 14px;border:1px solid var(--border);border-radius:var(--r);font-size:14px;font-family:inherit;resize:none;outline:none;background:var(--bg-2)"
        onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMsg();}"></textarea>
      <button id="send-btn" class="btn btn-primary" onclick="sendMsg()">发送</button>
    </div>
    <div style="font-size:12px;color:var(--txt-3);margin-top:6px">Enter 发送，Shift+Enter 换行</div>
  </div>
</div>

<script>
const chatBox  = document.getElementById('chat-box');
const inputEl  = document.getElementById('user-input');
const sendBtn  = document.getElementById('send-btn');
let isLoading  = false;
let history    = [];

function appendMsg(role, text) {
  const div = document.createElement('div');
  div.className = 'chat-msg ' + (role==='user' ? 'chat-user' : 'chat-ai');
  const bubble = document.createElement('div');
  bubble.className = 'chat-bubble';
  bubble.textContent = text;
  div.appendChild(bubble);
  chatBox.appendChild(div);
  chatBox.scrollTop = chatBox.scrollHeight;
  return bubble;
}

function useHint(text) {
  inputEl.value = text;
  inputEl.focus();
}

async function sendMsg() {
  if (isLoading) return;
  const text = inputEl.value.trim();
  if (!text) return;
  inputEl.value = '';
  isLoading = true;
  sendBtn.disabled = true;
  sendBtn.textContent = '...';

  appendMsg('user', text);
  history.push({role:'user', content:text});

  const aiBubble = appendMsg('ai', '');
  aiBubble.textContent = '思考中...';

  try {
    const res = await fetch('../actions/ai_chat.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: 'message='+encodeURIComponent(text)+'&history='+encodeURIComponent(JSON.stringify(history.slice(-8)))
    });
    const data = await res.json();
    const reply = data.reply || '抱歉，我暂时无法回答这个问题。';
    aiBubble.textContent = reply;
    history.push({role:'assistant', content:reply});
  } catch(e) {
    aiBubble.textContent = '请求失败，请检查网络或稍后重试。';
  }

  isLoading = false;
  sendBtn.disabled = false;
  sendBtn.textContent = '发送';
  chatBox.scrollTop = chatBox.scrollHeight;
}

function clearChat() {
  chatBox.innerHTML = '';
  history = [];
  const div = document.createElement('div');
  div.className = 'chat-msg chat-ai';
  div.innerHTML = '<div class="chat-bubble">对话已清空，有什么新问题吗？</div>';
  chatBox.appendChild(div);
}
</script>

<?php include '../includes/footer.php'; ?>
