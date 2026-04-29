<?php
/*
 * pages/about.php — 关于我们
 * 功能：介绍论坛背景、功能特色、技术架构。
 * 权限：无需登录
 */
require_once '../config.php';
require_once '../includes/helpers.php';
$page_title = '关于我们';
include '../includes/header.php';
?>

<div class="flex-center gap-8 mb-16" style="font-size:13px;color:var(--txt-2)">
  <a href="../index.php">首页</a> &rsaquo; <span>关于我们</span>
</div>

<!-- Hero -->
<div class="card mb-20" style="padding:40px 32px;text-align:center;background:linear-gradient(135deg,var(--primary),#7c3aed);color:#fff;border:none">
  <div style="font-size:48px;margin-bottom:12px">🎓</div>
  <h1 style="margin:0 0 10px;font-size:26px;font-weight:800"><?= SITE_NAME ?></h1>
  <p style="margin:0;font-size:15px;opacity:.9;max-width:520px;margin:0 auto;line-height:1.8">
    为高校学生打造的智慧交流社区，连接校园、赋能成长
  </p>
</div>

<div class="layout-2col">
<div class="col-main">

  <!-- 项目简介 -->
  <div class="card mb-20">
    <div class="card-header">📖 项目简介</div>
    <div class="card-body" style="line-height:2;color:var(--txt-2)">
      <p>高校智慧交流论坛是一个专为在校大学生设计的综合性学习与交流平台，致力于打通学生之间的信息壁垒，促进知识共享与校园文化建设。</p>
      <p>平台围绕「学术交流、校园生活、职业发展、技术问答」四大核心板块展开，覆盖大学生日常学习与生活的各类场景，让有问题的人找到答案，让有经验的人产生价值。</p>
      <p>我们深度整合 AI 技术，引入 DeepSeek 大语言模型提供智能摘要、个性化推荐、AI 助手及内容质量检测等功能，让每一次交流都更高效、更有温度。</p>
    </div>
  </div>

  <!-- 功能特色 -->
  <div class="card mb-20">
    <div class="card-header">✨ 功能特色</div>
    <div class="card-body">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px">
        <?php
        $features = [
            ['🏛️','四大分区','学术/校园/职业/技术，16个子板块精细分类'],
            ['🤖','AI 赋能','摘要生成、个性推荐、AI助手、内容质检'],
            ['🏠','社团系统','申请建团、招募成员、动态发布、角色管理'],
            ['💬','私信系统','私聊与群组、消息撤回、未读徽章、实时轮询'],
            ['🚩','举报机制','多类型举报、管理员审核、一键封禁/删帖'],
            ['🎧','客服中心','AI客服优先、无法解决可转人工工单'],
            ['🌙','深色主题','Light/Dark 一键切换，localStorage 持久化'],
            ['🏫','本校模式','按学校筛选帖子，促进同校学生交流'],
        ];
        foreach ($features as $f): ?>
        <div style="padding:16px;background:var(--bg-2);border-radius:var(--r);border:1px solid var(--border)">
          <div style="font-size:24px;margin-bottom:8px"><?= $f[0] ?></div>
          <div style="font-weight:700;margin-bottom:4px"><?= $f[1] ?></div>
          <div style="font-size:13px;color:var(--txt-2)"><?= $f[2] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- 技术架构 -->
  <div class="card">
    <div class="card-header">⚙️ 技术架构</div>
    <div class="card-body">
      <div style="display:flex;flex-wrap:wrap;gap:10px">
        <?php
        $techs = [
            ['PHP 8','后端语言'],['MySQL 8','关系型数据库'],['DeepSeek API','AI 大语言模型'],
            ['AJAX / Fetch','前端异步通信'],['Session','用户鉴权'],['CSS Variables','主题系统'],
        ];
        foreach ($techs as $t): ?>
        <div style="padding:8px 16px;background:var(--primary-lt);border-radius:20px;font-size:13px;color:var(--primary)">
          <strong><?= $t[0] ?></strong> <span style="opacity:.7">· <?= $t[1] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</div>
<div class="col-side">

  <div class="card mb-16">
    <div class="card-header">📊 平台数据</div>
    <div class="card-body">
      <?php
      $post_cnt = (int)$conn->query("SELECT COUNT(*) as c FROM posts WHERE status='published'")->fetch_assoc()['c'];
      $user_cnt = (int)$conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
      $comm_cnt = (int)$conn->query("SELECT COUNT(*) as c FROM comments")->fetch_assoc()['c'];
      $club_cnt = (int)$conn->query("SELECT COUNT(*) as c FROM clubs WHERE status='active'")->fetch_assoc()['c'];
      $stats = [['📝',$post_cnt,'帖子总数'],['👥',$user_cnt,'注册用户'],['💬',$comm_cnt,'评论总数'],['🏠',$club_cnt,'活跃社团']];
      foreach ($stats as $s): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border)">
        <span><?= $s[0] ?> <?= $s[2] ?></span>
        <span style="font-weight:700;color:var(--primary)"><?= number_format($s[1]) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card mb-16">
    <div class="card-header">🏆 参赛信息</div>
    <div class="card-body" style="font-size:13px;color:var(--txt-2);line-height:2">
      <div>赛事：第19届中国大学生</div>
      <div>　　　计算机设计大赛（4C）</div>
      <div>类别：Web 应用与开发</div>
      <div>年度：2026</div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">🔗 快速入口</div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
      <a href="../index.php" class="btn btn-primary" style="text-align:center">🏠 进入首页</a>
      <a href="support.php"  class="btn btn-outline" style="text-align:center">🎧 联系客服</a>
    </div>
  </div>

</div>
</div>

<?php include '../includes/footer.php'; ?>
