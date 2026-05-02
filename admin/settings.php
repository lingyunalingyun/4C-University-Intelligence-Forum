<?php
/*
 * admin/settings.php — AI 配置与统计后台
 * 功能：查看/更新 DeepSeek API Key（DB动态配置），测试连通性，
 *       展示 AI 各功能调用统计（今日/累计/分类型），最近调用日志。
 * 写库：site_settings / ai_logs（只读统计）
 * 权限：需 admin/owner 登录
 */
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../pages/login.php'); exit; }
if (!in_array($_SESSION['role'] ?? '', ['admin','owner'])) { header('Location: ../index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_key  = trim($_POST['deepseek_key'] ?? '');
    $safe_key = $conn->real_escape_string($new_key);
    $conn->query("INSERT INTO site_settings (`key`,`value`) VALUES ('deepseek_api_key','$safe_key')
        ON DUPLICATE KEY UPDATE `value`='$safe_key'");
    log_admin_action($conn, $_SESSION['user_id'], 'update_setting', 'deepseek_api_key', 0, $new_key ? '已设置密钥' : '已清空密钥');
    header('Location: settings.php?saved=1'); exit;
}

$saved = isset($_GET['saved']);

// 当前 key
$db_key = '';
$kr = $conn->query("SELECT `value` FROM site_settings WHERE `key`='deepseek_api_key' LIMIT 1");
if ($kr && ($krow = $kr->fetch_assoc())) $db_key = $krow['value'];
$key_source  = $db_key ? 'db' : (getenv('DEEPSEEK_API_KEY') ? 'env' : 'none');
$key_display = $db_key
    ? mb_substr($db_key,0,6).'****'.mb_substr($db_key,-4)
    : (getenv('DEEPSEEK_API_KEY') ? '来自环境变量（只读）' : '未配置');

// AI 统计
$ai_stats = ['total'=>0,'chat'=>0,'summary'=>0,'tags'=>0,'search'=>0];
$sr = $conn->query("SELECT type,COUNT(*) as cnt FROM ai_logs GROUP BY type");
if ($sr) while ($r = $sr->fetch_assoc()) {
    $ai_stats['total'] += (int)$r['cnt'];
    if (isset($ai_stats[$r['type']])) $ai_stats[$r['type']] = (int)$r['cnt'];
}
$today_cnt = 0;
$tr = $conn->query("SELECT COUNT(*) as c FROM ai_logs WHERE DATE(created_at)=CURDATE()");
if ($tr) $today_cnt = (int)$tr->fetch_assoc()['c'];

// 最近日志
$recent_logs = [];
$lr = $conn->query("SELECT al.type,al.prompt,al.created_at,u.username
    FROM ai_logs al LEFT JOIN users u ON u.id=al.user_id
    ORDER BY al.created_at DESC LIMIT 12");
if ($lr) while ($r = $lr->fetch_assoc()) $recent_logs[] = $r;

$page_title = 'AI 设置';
$in_admin   = true;
include '../includes/header.php';
?>

<div class="admin-page-hd">
  <div><h2><i data-lucide="bot" class="lucide"></i> AI 设置</h2><div class="sub">DeepSeek API 配置与使用统计</div></div>
</div>

<?php if ($saved): ?>
<div class="alert-success"><i data-lucide="check-circle" class="lucide"></i> 设置已保存，API Key 已更新。</div>
<?php endif; ?>

<div class="layout-2col" style="gap:20px">

  <!-- ── 左：API Key 配置 ── -->
  <div>

    <!-- Key 状态 -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><i data-lucide="key" class="lucide"></i> DeepSeek API Key</div>
      <div class="card-body">
        <!-- 当前状态条 -->
        <div style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:var(--bg-2);border:1px solid var(--border);border-radius:var(--r);margin-bottom:18px">
          <?php if ($key_source === 'none'): ?>
            <span style="width:10px;height:10px;border-radius:50%;background:#ef4444;flex-shrink:0"></span>
            <span style="font-size:13px;color:var(--txt-2)">未配置 — AI 功能全部禁用</span>
          <?php elseif ($key_source === 'env'): ?>
            <span style="width:10px;height:10px;border-radius:50%;background:#f59e0b;flex-shrink:0"></span>
            <span style="font-size:13px;color:var(--txt-2)">来自环境变量（只读）</span>
          <?php else: ?>
            <span style="width:10px;height:10px;border-radius:50%;background:#22c55e;flex-shrink:0"></span>
            <span style="font-size:13px;font-family:monospace;font-weight:600"><?= h($key_display) ?></span>
            <span class="spill spill-green" style="margin-left:auto">运行中</span>
          <?php endif; ?>
        </div>

        <?php if ($key_source !== 'env'): ?>
        <form method="post" id="key-form">
          <div class="form-group">
            <label>设置新 Key</label>
            <div style="position:relative">
              <input type="password" name="deepseek_key" id="key-input"
                     placeholder="sk-xxxxxxxxxxxxxxxx"
                     style="width:100%;padding:9px 44px 9px 12px;border:1px solid var(--border);border-radius:var(--r);font-size:13px;font-family:monospace;box-sizing:border-box;background:var(--bg-2);color:var(--txt);outline:none">
              <button type="button" onclick="toggleShow()"
                      style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:14px;color:var(--txt-3)"><i data-lucide="eye" class="lucide"></i></button>
            </div>
            <div style="font-size:12px;color:var(--txt-3);margin-top:5px">留空并保存将清除已配置的 Key</div>
          </div>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button type="submit" class="btn btn-primary btn-sm">💾 保存</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="testKey()"><i data-lucide="link-2" class="lucide"></i> 测试连接</button>
          </div>
        </form>
        <div id="test-result" style="display:none;margin-top:14px;padding:10px 14px;border-radius:var(--r);font-size:13px"></div>
        <?php else: ?>
          <div style="font-size:13px;color:var(--txt-2)">API Key 来自服务器环境变量，请在服务器配置文件中修改。</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- AI 功能列表 -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><i data-lucide="clipboard-list" class="lucide"></i> 功能状态</div>
      <div style="padding:0">
        <?php
        $features = [
          ['<i data-lucide="file-text" class="lucide"></i>','摘要生成',  '发帖时自动生成50字摘要',     true],
          ['<i data-lucide="tag" class="lucide"></i>','标签提取',  '发帖时自动识别3-5个关键标签', true],
          ['🔍','AI搜索扩词','搜索时语义扩展关键词',        true],
          ['<i data-lucide="bot" class="lucide"></i>','AI助手',    '多轮对话，解答学习求职问题',  true],
          ['<i data-lucide="star" class="lucide"></i>','个性化推荐', '基于兴趣标签，纯算法无需API',false],
        ];
        foreach ($features as $idx => $f):
          list($icon,$name,$desc,$need_key) = $f;
          $active = !$need_key || $key_source !== 'none';
        ?>
        <div style="display:flex;align-items:center;gap:12px;padding:11px 20px;<?= $idx < count($features)-1 ? 'border-bottom:1px solid var(--border)' : '' ?>">
          <span style="font-size:18px;flex-shrink:0"><?= $icon ?></span>
          <div style="flex:1">
            <div style="font-size:13px;font-weight:600"><?= $name ?></div>
            <div style="font-size:12px;color:var(--txt-2)"><?= $desc ?></div>
          </div>
          <?php if ($active): ?>
            <span class="spill spill-green"><span class="spill-dot"></span>运行中</span>
          <?php else: ?>
            <span class="spill spill-red">未启用</span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- 获取 Key 引导 -->
    <div class="card">
      <div class="card-header">💡 如何获取 API Key</div>
      <div class="card-body" style="font-size:13px;color:var(--txt-2);line-height:1.9">
        <ol style="margin:0;padding-left:18px">
          <li>访问 <strong>platform.deepseek.com</strong> 注册账号</li>
          <li>进入「API Keys」页面，点击「Create new secret key」</li>
          <li>复制 Key（<code>sk-</code> 开头）粘贴到上方输入框</li>
          <li>点击「测试连接」确认可用后保存</li>
        </ol>
        <div style="margin-top:10px;padding:8px 12px;background:var(--bg-2);border-radius:var(--r);color:var(--txt-3);font-size:12px">
          <i data-lucide="alert-triangle" class="lucide"></i> Key 保存在数据库中，请确保数据库访问安全。
        </div>
      </div>
    </div>
  </div>

  <!-- ── 右：使用统计 ── -->
  <div>

    <!-- 统计数字 -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><i data-lucide="bar-chart-2" class="lucide"></i> 调用统计</div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px">
          <?php
          $stat_cards = [
            ['今日调用','🌅',$today_cnt,'#2563eb','#dbeafe'],
            ['累计调用','📈',$ai_stats['total'],'#10b981','#d1fae5'],
            ['AI助手','<i data-lucide="bot" class="lucide"></i>',$ai_stats['chat'],'#8b5cf6','#ede9fe'],
            ['摘要生成','<i data-lucide="file-text" class="lucide"></i>',$ai_stats['summary'],'#f59e0b','#fef3c7'],
            ['标签提取','<i data-lucide="tag" class="lucide"></i>',$ai_stats['tags'],'#ec4899','#fce7f3'],
            ['搜索扩词','🔍',$ai_stats['search'],'#0ea5e9','#e0f2fe'],
          ];
          foreach ($stat_cards as $sc): list($label,$icon,$val,$color,$bg) = $sc;
          ?>
          <div style="text-align:center;padding:14px 8px;background:<?= $bg ?>;border-radius:var(--r)">
            <div style="font-size:18px"><?= $icon ?></div>
            <div style="font-size:22px;font-weight:700;color:<?= $color ?>;margin:4px 0"><?= number_format($val) ?></div>
            <div style="font-size:11px;color:var(--txt-2)"><?= $label ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- 最近调用日志 -->
    <div class="card">
      <div class="card-header">🕐 最近调用记录</div>
      <?php if (empty($recent_logs)): ?>
        <div style="padding:30px;text-align:center;color:var(--txt-3);font-size:13px">暂无记录</div>
      <?php else: ?>
      <div style="padding:0">
        <?php
        $type_colors = [
          'chat'=>'#8b5cf6','summary'=>'#f59e0b','tags'=>'#ec4899',
          'search'=>'#0ea5e9','recommend'=>'#10b981',
        ];
        foreach ($recent_logs as $idx => $l):
          $tc = $type_colors[$l['type']] ?? '#6b7280';
        ?>
        <div style="display:flex;gap:8px;padding:9px 18px;<?= $idx < count($recent_logs)-1 ? 'border-bottom:1px solid var(--border)' : '' ?>;align-items:flex-start">
          <span style="font-size:11px;font-weight:700;color:<?= $tc ?>;flex-shrink:0;padding-top:1px">[<?= h($l['type']) ?>]</span>
          <span style="font-size:12px;color:var(--txt-2);flex:1;overflow:hidden;white-space:nowrap;text-overflow:ellipsis"><?= h(mb_substr($l['prompt'],0,50)) ?></span>
          <span style="font-size:11px;color:var(--txt-3);flex-shrink:0"><?= date('m-d H:i',strtotime($l['created_at'])) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<script>
function toggleShow() {
  var inp = document.getElementById('key-input');
  inp.type = inp.type === 'password' ? 'text' : 'password';
}
function testKey() {
  var key = document.getElementById('key-input').value.trim();
  var el  = document.getElementById('test-result');
  if (!key) { showResult('error','请先输入 API Key'); return; }
  el.style.display='block';
  el.style.cssText='display:block;margin-top:14px;padding:10px 14px;border-radius:6px;font-size:13px;background:var(--bg-2);border:1px solid var(--border);color:var(--txt-2)';
  el.textContent = '⏳ 正在测试连接…';
  fetch('../actions/ai_test.php',{
    method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'key='+encodeURIComponent(key)
  }).then(function(r){return r.json();}).then(function(data){
    if (data.ok) showResult('ok','<i data-lucide="check-circle" class="lucide"></i> 连接成功！模型回复：'+data.reply);
    else         showResult('error','<i data-lucide="x-circle" class="lucide"></i> 失败：'+(data.error||'未知错误'));
  }).catch(function(){ showResult('error','<i data-lucide="x-circle" class="lucide"></i> 请求异常，请检查网络'); });
}
function showResult(type, msg) {
  var el = document.getElementById('test-result');
  el.style.display='block';
  el.style.background = type==='ok'?'#dcfce7':'#fee2e2';
  el.style.border     = '1px solid '+(type==='ok'?'#bbf7d0':'#fecaca');
  el.style.color      = type==='ok'?'#166534':'#991b1b';
  el.style.padding    = '10px 14px';
  el.style.borderRadius = '6px';
  el.style.fontSize   = '13px';
  el.style.marginTop  = '14px';
  el.textContent = msg;
}
</script>

<?php include '../includes/footer.php'; ?>
