<?php
/*
 * pages/privacy.php — 隐私政策
 * 功能：说明平台数据收集范围、使用方式、用户权利及第三方服务说明。
 * 权限：无需登录
 */
require_once '../config.php';
require_once '../includes/helpers.php';
$page_title = '隐私政策';
include '../includes/header.php';
?>

<div class="flex-center gap-8 mb-16" style="font-size:13px;color:var(--txt-2)">
  <a href="../index.php">首页</a> &rsaquo; <span>隐私政策</span>
</div>

<div class="layout-2col">
<div class="col-main">

  <div class="card mb-16" style="padding:16px 20px;background:var(--bg-2);border:1px solid var(--border)">
    <div style="font-size:13px;color:var(--txt-2);line-height:1.8">
      🔒 我们高度重视您的隐私。本政策说明 <strong><?= SITE_NAME ?></strong>
      如何收集、使用和保护您的个人信息。使用本平台即表示您同意本政策。
    </div>
  </div>

  <?php
  $sections = [
    ['📥', '一、我们收集哪些信息', [
      ['注册信息', '用户名、邮箱地址、加密后的密码（明文密码不会被存储或传输）。'],
      ['个人资料', '您主动填写的学校、个人简介、头像图片等信息。'],
      ['内容数据', '您发布的帖子、评论、私信内容（用于提供正常服务）。'],
      ['行为数据', '登录时间、签到记录、帖子浏览量（用于经验积分计算和个性化推荐）。'],
      ['设备信息', '主题偏好（深/浅色）存储于您的浏览器 localStorage，不上传服务器。'],
    ], true],
    ['🔧', '二、信息的使用方式', [
      '提供、维护和改进论坛的核心功能（发帖、评论、私信、社团等）。',
      '计算用户经验值、等级，生成 SCID 唯一身份标识。',
      '通过 AI 算法分析用户兴趣标签，提供个性化内容推荐。',
      '发送站内通知（如新评论、被关注、客服回复等）。',
      '管理员依据规则对违规内容和账号进行处理。',
    ]],
    ['🤖', '三、AI 功能与第三方服务', [
      '本平台使用 DeepSeek API 提供 AI 摘要、内容推荐和 AI 助手功能。',
      '您与 AI 助手或客服的对话内容（脱敏后）可能传输至 DeepSeek 服务端处理。',
      '请勿在 AI 对话中输入真实姓名、身份证号、银行卡号等敏感信息。',
      'DeepSeek 的数据处理遵循其自身隐私政策，与本平台独立。',
    ]],
    ['🔐', '四、数据安全', [
      '用户密码使用 bcrypt 算法加密存储，我们无法获知您的明文密码。',
      '邮件验证码和密码重置令牌设有时效限制，超时自动失效。',
      '管理员操作均有日志记录（admin_logs），可追溯审查。',
      '我们不会将您的个人信息出售给任何第三方。',
    ]],
    ['👤', '五、您的权利', [
      '访问权：您可以在「个人主页」查看您公开的所有数据。',
      '修改权：在「账号设置」中随时修改昵称、头像、简介、密码等。',
      '删除权：您可以删除自己发布的帖子和评论。',
      '申诉权：如账号被封禁，可通过客服工单申诉说明情况。',
      '退出权：您可以随时退出登录，浏览器 Session 将被清除。',
    ]],
    ['🍪', '六、Cookie 与本地存储', [
      '本平台使用 PHP Session（存于服务端）维持登录状态，无广告 Cookie。',
      '浏览器 localStorage 仅存储深/浅色主题偏好，不含任何个人信息。',
      '您可随时通过浏览器设置清除 Session 和 localStorage 数据。',
    ]],
    ['📝', '七、政策更新', [
      '本政策可能随功能迭代而更新，更新后将在平台内公告。',
      '继续使用本平台视为您接受更新后的隐私政策。',
      '如有重大变更，我们将通过站内通知方式告知您。',
    ]],
  ];
  foreach ($sections as $sec):
    $is_kv = !empty($sec[3]);
  ?>
  <div class="card mb-16">
    <div class="card-header"><?= $sec[0] ?> <?= $sec[1] ?></div>
    <div class="card-body">
      <?php if ($is_kv): ?>
      <table style="width:100%;border-collapse:collapse;font-size:13px">
        <?php foreach ($sec[2] as $row): ?>
        <tr style="border-bottom:1px solid var(--border)">
          <td style="padding:9px 12px;color:var(--txt);font-weight:600;width:100px;white-space:nowrap"><?= $row[0] ?></td>
          <td style="padding:9px 12px;color:var(--txt-2);line-height:1.7"><?= $row[1] ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php else: ?>
      <ul style="margin:0;padding-left:20px;line-height:2.2;color:var(--txt-2)">
        <?php foreach ($sec[2] as $item): ?>
          <li><?= $item ?></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>

</div>
<div class="col-side">

  <div class="card mb-16">
    <div class="card-header">🗂 目录</div>
    <div class="card-body" style="font-size:13px;line-height:2.5">
      <?php foreach (['收集的信息','使用方式','AI与第三方','数据安全','您的权利','Cookie','政策更新'] as $i => $t): ?>
        <div style="color:var(--txt-2)"><?= $i+1 ?>. <?= $t ?></div>
      <?php endforeach; ?>
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
    <div class="card-body" style="font-size:13px;color:var(--txt-2);margin-bottom:10px">
      对隐私政策有疑问？请联系我们。
    </div>
    <div class="card-body" style="padding-top:0">
      <a href="support.php" class="btn btn-primary" style="display:block;text-align:center">🎧 联系客服</a>
    </div>
  </div>

</div>
</div>

<?php include '../includes/footer.php'; ?>
