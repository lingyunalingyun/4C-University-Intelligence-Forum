<?php
/*
 * pages/terms.php — 使用规则
 * 功能：论坛社区规范、用户行为准则、违规处理说明。
 * 权限：无需登录
 */
require_once '../config.php';
require_once '../includes/helpers.php';
$page_title = '使用规则';
include '../includes/header.php';
?>

<div class="flex-center gap-8 mb-16" style="font-size:13px;color:var(--txt-2)">
  <a href="../index.php">首页</a> &rsaquo; <span>使用规则</span>
</div>

<div class="layout-2col">
<div class="col-main">

  <div class="card mb-16" style="padding:20px 24px;background:var(--primary-lt);border:1px solid var(--primary);border-radius:var(--r)">
    <div style="font-size:14px;color:var(--primary);line-height:1.8">
      📌 本规则适用于所有在 <strong><?= SITE_NAME ?></strong> 注册和使用平台的用户。
      注册即表示您已阅读并同意以下全部条款。
    </div>
  </div>

  <?php
  $sections = [
    ['📝', '一、账号规范', [
      '每位用户只能注册一个账号，禁止多账号注册或绕过封禁。',
      '用户名须符合公序良俗，不得含有侮辱性、歧视性或违法词汇。',
      '请妥善保管账号密码，因账号泄露造成的损失由用户本人承担。',
      '禁止将账号出租、出售或转让给他人使用。',
      '账号头像、昵称、简介等个人信息不得含有违法违规内容。',
    ]],
    ['📄', '二、内容规范', [
      '禁止发布违反中华人民共和国相关法律法规的内容。',
      '禁止发布色情、赌博、暴力、恐怖等有害信息。',
      '禁止发布虚假信息、谣言或恶意误导他人的内容。',
      '禁止发布未经授权的商业广告、推广引流等营销内容。',
      '禁止侵犯他人知识产权，转载内容须注明来源。',
      '发帖内容须与所在板块主题相关，禁止无意义刷帖或灌水。',
    ]],
    ['🤝', '三、互动规范', [
      '尊重每一位用户，禁止人身攻击、辱骂、歧视或骚扰他人。',
      '评论须有实质内容，禁止恶意引战或制造对立情绪。',
      '禁止利用私信功能发送骚扰、广告或恶意链接。',
      '发现违规内容请使用举报功能，禁止以私下方式"讨回公道"。',
    ]],
    ['🔒', '四、社团规范', [
      '每位用户同一时间只能加入一个社团。',
      '社团创建须经管理员审核，虚假申请将被驳回并记录。',
      '社长/副社长须以身作则，承担社团内容的管理责任。',
      '社团动态不得用于发布违规内容或进行商业引流。',
    ]],
    ['⚖️', '五、违规处理', [
      '情节轻微：内容删除，系统警告。',
      '情节较重：限时封禁账号（1~30天），限制发帖/评论功能。',
      '情节严重：永久封禁账号，保留追究法律责任的权利。',
      '对处理结果有异议，可通过客服中心提交申诉工单。',
    ]],
    ['📋', '六、免责声明', [
      '本平台为用户提供信息交流服务，不对用户发布内容的真实性、合法性承担责任。',
      '用户因违规行为造成的任何后果由用户本人承担。',
      '本平台有权根据实际情况修改使用规则，修改后将在平台内公告。',
    ]],
  ];
  foreach ($sections as $sec): ?>
  <div class="card mb-16">
    <div class="card-header"><?= $sec[0] ?> <?= $sec[1] ?></div>
    <div class="card-body">
      <ul style="margin:0;padding-left:20px;line-height:2.2;color:var(--txt-2)">
        <?php foreach ($sec[2] as $item): ?>
          <li><?= $item ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <?php endforeach; ?>

</div>
<div class="col-side">

  <div class="card mb-16">
    <div class="card-header">⚡ 快速导航</div>
    <div class="card-body" style="font-size:13px;line-height:2.5">
      <a href="#" style="display:block;color:var(--primary)">📝 账号规范</a>
      <a href="#" style="display:block;color:var(--primary)">📄 内容规范</a>
      <a href="#" style="display:block;color:var(--primary)">🤝 互动规范</a>
      <a href="#" style="display:block;color:var(--primary)">🔒 社团规范</a>
      <a href="#" style="display:block;color:var(--primary)">⚖️ 违规处理</a>
    </div>
  </div>

  <div class="card mb-16">
    <div class="card-header">📅 版本信息</div>
    <div class="card-body" style="font-size:13px;color:var(--txt-2);line-height:2">
      <div>版本：v1.0</div>
      <div>生效日期：2026年1月1日</div>
      <div>最后更新：2026年4月</div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">💬 有疑问？</div>
    <div class="card-body" style="font-size:13px;color:var(--txt-2);margin-bottom:10px">
      如对规则有任何疑问，欢迎通过客服中心联系我们。
    </div>
    <div class="card-body" style="padding-top:0">
      <a href="support.php" class="btn btn-primary" style="display:block;text-align:center">🎧 前往客服中心</a>
    </div>
  </div>

</div>
</div>

<?php include '../includes/footer.php'; ?>
