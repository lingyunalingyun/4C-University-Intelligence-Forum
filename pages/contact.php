<?php
/*
 * pages/contact.php — 联系我们
 * 功能：展示联系方式，引导用户通过客服工单或举报系统提交问题。
 * 权限：无需登录
 */
require_once '../config.php';
require_once '../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$page_title = '联系我们';
include '../includes/header.php';
?>

<div class="flex-center gap-8 mb-16" style="font-size:13px;color:var(--txt-2)">
  <a href="../index.php">首页</a> &rsaquo; <span>联系我们</span>
</div>

<!-- 优先入口 -->
<div class="card mb-20" style="padding:32px;text-align:center">
  <div style="font-size:40px;margin-bottom:12px"><i data-lucide="headphones" class="lucide"></i></div>
  <h2 style="margin:0 0 8px;font-size:20px">遇到问题？先试试客服中心</h2>
  <p style="color:var(--txt-2);font-size:14px;margin:0 0 20px;max-width:440px;margin-left:auto;margin-right:auto">
    AI 客服 7×24 小时在线，大部分问题即时解决；AI 无法处理时可一键转人工，工作日 24 小时内响应。
  </p>
  <a href="support.php" class="btn btn-primary" style="padding:10px 32px;font-size:15px">立即前往客服中心</a>
</div>

<div class="layout-2col">
<div class="col-main">

  <!-- 适用场景 -->
  <div class="card mb-20">
    <div class="card-header"><i data-lucide="clipboard-list" class="lucide"></i> 各类问题对应渠道</div>
    <div class="card-body">
      <?php
      $channels = [
        ['<i data-lucide="bot" class="lucide"></i>','AI 客服',    '立即响应','论坛使用问题、功能疑问、账号操作指引',  'support.php','前往咨询'],
        ['<i data-lucide="flag" class="lucide"></i>','举报系统',   '24h内',  '违规帖子、违规用户、违规评论',            '../pages/post.php','去举报'],
        ['<i data-lucide="clipboard-list" class="lucide"></i>','人工工单',   '24h内',  '账号封禁申诉、数据错误、功能异常',         'support.php','提交工单'],
        ['<i data-lucide="message-circle" class="lucide"></i>','站内私信',   '即时',   '与其他用户交流，寻求社区帮助',             'messages.php','发私信'],
      ];
      foreach ($channels as $c):
        $logged = !empty($_SESSION['user_id']);
      ?>
      <div style="display:flex;align-items:center;gap:14px;padding:14px 0;border-bottom:1px solid var(--border);flex-wrap:wrap">
        <div style="font-size:28px;flex-shrink:0"><?= $c[0] ?></div>
        <div style="flex:1;min-width:160px">
          <div style="font-weight:700;margin-bottom:3px"><?= $c[1] ?>
            <span style="font-size:12px;font-weight:400;color:var(--txt-3);margin-left:8px">⏱ <?= $c[2] ?></span>
          </div>
          <div style="font-size:13px;color:var(--txt-2)"><?= $c[3] ?></div>
        </div>
        <?php if ($logged || in_array($c[1],['AI 客服','举报系统'])): ?>
          <a href="<?= $c[4] ?>" class="btn btn-outline btn-sm" style="flex-shrink:0"><?= $c[5] ?></a>
        <?php else: ?>
          <a href="login.php" class="btn btn-outline btn-sm" style="flex-shrink:0;color:var(--txt-3)">登录后可用</a>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- 常见问题快答 -->
  <div class="card">
    <div class="card-header">❓ 常见问题快答</div>
    <div class="card-body">
      <?php
      $faqs = [
        ['账号被封禁了怎么办？', '可通过客服中心提交「账号申诉」工单，说明情况，管理员会在24小时内处理。'],
        ['忘记密码怎么找回？', '点击登录页的「忘记密码」，输入注册邮箱，我们将发送验证码到您邮箱，按提示重置即可。'],
        ['如何删除自己的帖子？', '进入帖子详情页，点击「<i data-lucide="pencil" class="lucide"></i> 编辑」，选择删除；或在个人主页的帖子列表操作。'],
        ['发现他人违规内容怎么办？', '点击帖子/用户主页上的「<i data-lucide="flag" class="lucide"></i> 举报」按钮，选择原因提交，管理员会审核处理。'],
        ['账号注销如何操作？', '目前暂不支持自助注销，如有需要请通过客服工单联系管理员处理。'],
      ];
      foreach ($faqs as $i => $faq): ?>
      <details style="padding:12px 0;border-bottom:1px solid var(--border);cursor:pointer">
        <summary style="font-weight:600;font-size:14px;list-style:none;display:flex;align-items:center;gap:8px">
          <span style="color:var(--primary);font-size:13px;font-weight:700">Q</span> <?= $faq[0] ?>
        </summary>
        <div style="margin-top:8px;padding-left:20px;font-size:13px;color:var(--txt-2);line-height:1.8">
          <?= $faq[1] ?>
        </div>
      </details>
      <?php endforeach; ?>
    </div>
  </div>

</div>
<div class="col-side">

  <div class="card mb-16">
    <div class="card-header">📬 其他联系方式</div>
    <div class="card-body" style="font-size:13px;color:var(--txt-2);line-height:2.2">
      <div style="padding:10px 0;border-bottom:1px solid var(--border)">
        📧 <strong>邮箱</strong><br>
        <span style="padding-left:20px">support@campusforum.edu</span>
      </div>
      <div style="padding:10px 0;border-bottom:1px solid var(--border)">
        🕐 <strong>工作时间</strong><br>
        <span style="padding-left:20px">周一至周五 9:00–18:00</span>
      </div>
      <div style="padding:10px 0">
        <i data-lucide="zap" class="lucide"></i> <strong>紧急问题</strong><br>
        <span style="padding-left:20px">请在工单中标注「紧急」，我们将优先处理</span>
      </div>
    </div>
  </div>

  <div class="card mb-16">
    <div class="card-header">💡 反馈建议</div>
    <div class="card-body" style="font-size:13px;color:var(--txt-2);line-height:1.8;margin-bottom:12px">
      有功能建议或体验反馈？欢迎通过客服工单选择「功能建议」分类提交，我们会认真参考。
    </div>
    <div class="card-body" style="padding-top:0">
      <a href="support.php" class="btn btn-outline" style="display:block;text-align:center">💡 提交建议</a>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><i data-lucide="file" class="lucide"></i> 相关页面</div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:6px;font-size:13px">
      <a href="about.php"   style="color:var(--primary)"><i data-lucide="book-open" class="lucide"></i> 关于我们</a>
      <a href="terms.php"   style="color:var(--primary)"><i data-lucide="clipboard-list" class="lucide"></i> 使用规则</a>
      <a href="privacy.php" style="color:var(--primary)">🔒 隐私政策</a>
    </div>
  </div>

</div>
</div>

<?php include '../includes/footer.php'; ?>
