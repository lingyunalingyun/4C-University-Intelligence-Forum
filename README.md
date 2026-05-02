# 高校智慧交流论坛

<div align="center">

**成品展示：http://paperchemis.top**

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green)
![Competition](https://img.shields.io/badge/第19届4C大赛-Web应用与开发类-orange)

**面向高校学生的全功能智慧社区论坛平台**

集帖子发布、社团管理、AI 助手、个性化推荐、举报审核、客服工单于一体  
原生 PHP + MySQL 构建，Academic Monochrome 学术风格主题，暗色 / 亮色双模式，无前端框架，支持 Docker 一键部署

> 最后更新：2026-05-02

</div>

---

## 目录

- [项目介绍](#项目介绍)
- [功能特性](#功能特性)
- [技术栈](#技术栈)
- [项目结构](#项目结构)
- [数据库设计](#数据库设计)
- [快速部署](#快速部署)
- [初始化配置](#初始化配置)
- [环境变量](#环境变量)
- [页面一览](#页面一览)
- [开发说明](#开发说明)
- [开源协议](#开源协议)

---

## 项目介绍

**高校智慧交流论坛**是第十九届全国大学生计算机设计大赛（4C）Web 应用与开发类参赛作品。面向高校学生群体，围绕「学术交流、校园生活、职业发展、技术问答」四大场景构建，深度整合 DeepSeek 大语言模型，提供 AI 摘要、个性化推荐、AI 助手、AI 客服等智能功能。

项目采用原生 PHP + MySQL 构建，无需任何后端框架，前端仅引入 Quill.js 富文本编辑器，其余均为原生 JS。数据库表结构在首次访问时由 `includes/db_init.php` **自动创建**，无需手动执行 SQL。

---

## 功能特性

### 🏠 主页与内容发现

#### 精选主页（`index.php`）

- **Hero 背景大图**：管理员可在后台上传任意图片作为首页 Banner 背景图；鼠标移动触发平滑视差位移（lerp 插值 + requestAnimationFrame），滚动时图片自然跟随；未设置时回退主题色渐变
- 管理员可配置 **6 个精选槽位**，展示指定帖子卡片（16:9 比例，自动提取帖子首图）
- 槽位 1 全宽展示，2-3 双列，4-6 三列；无图帖子使用主题色渐变占位

#### 广场（`square.php`）

- 推荐区：基于用户兴趣标签 AI 匹配的个性化推荐帖子，未登录显示全站精选
- 最新帖子：支持**本校 / 全站**切换（按用户学校字段过滤）
- 侧栏：热帖 Top 10、近 3 天活跃板块

#### 热榜（`/pages/hot.php`）

- 综合评分：`浏览×0.3 + 点赞×2 + 评论×1.5`
- 支持本周 / 本月 / 全部时间范围筛选
- 分页展示，每页 20 条

#### 发现（`/pages/explore.php`）

- 读取 `user_interests` 表权重，按标签匹配推荐相关帖子
- 无历史数据时回退至全站精选 / 最热

---

### 📝 帖子系统

#### 发布与编辑（`/pages/publish.php`）

- **Quill.js 富文本编辑器**：加粗 / 斜体 / 下划线 / 删除线、标题、引用块、代码块、有序 / 无序列表、链接、图片上传（AJAX，5MB 限制，MIME 二次验证）
- 选择板块：4 个一级分区 × 4 个二级子分区，共 16 个子版块
- 支持编辑已发帖子（作者 / 管理员）
- 发帖后自动触发 AI 摘要（≤50 字）和关键标签（3-5 个）生成
- 编辑器内置 **AI 生成标签** 和 **预览 AI 摘要** 按钮

#### 帖子详情（`/pages/post.php`）

- 浏览量实时 +1，自动更新用户兴趣权重
- 展示 AI 摘要、标签云、面包屑导航
- **点赞 / 收藏**（AJAX 无刷新）
- 分享面板：复制链接、私信分享给关注的用户、发给 AI 分析
- 帖子可标记为**已解决**（问题类帖子）
- 可 **🚩 举报**（登录后，非本人帖子）
- 相关推荐：同板块最新 5 帖

#### 评论系统

- 顶级评论 + **嵌套回复**（二级结构），超过 2 条回复折叠显示
- 评论点赞
- 管理员可删除任意评论

#### 分区（`/pages/section.php`）

- 无参数时展示**全分区概览**网格（图标 + 颜色 + 帖子数 + 子分区标签）
- 具体分区支持按**最新 / 最热 / 已解决**排序
- 面包屑：首页 → 一级分区 → 二级分区

---

### 👤 用户系统

#### 注册与登录

- 邮箱 + 用户名 + 密码注册，可选邮箱验证
- 忘记密码：邮箱验证码重置
- 连续登录签到记录（`login_streak`）

#### 个人资料（`/pages/profile.php`）

- 头像上传（JPG/PNG，≤2MB）
- 个人简介、学校（985/211 高校下拉列表 + 搜索）
- 8 位唯一 **SCID**（站内识别码），可通过 SCID 搜索用户
- 对其他用户主页可 **🚩 举报**（登录后）
- 显示社团徽标（社长金色 / 副社长紫色 / 成员蓝色渐变）

#### 经验与等级

| 等级 | 名称 | 所需 EXP |
|------|------|---------|
| Lv1 | 新手 | 0 |
| Lv2 | 学徒 | 1,000 |
| Lv3 | 达人 | 5,000 |
| Lv4 | 精英 | 15,000 |
| Lv5 | 大神 | 30,000 |
| Lv6 | 传说 | 50,000 |

个人主页展示彩色等级徽章 + EXP 进度条。

#### 关注系统

- 关注 / 取关（AJAX 无刷新）
- 个人主页显示帖子数、粉丝数、关注数
- 帖子详情页作者信息卡含关注按钮

#### 站内通知（`/pages/notifications.php`）

- 触发场景：帖子被点赞 / 收藏 / 评论、有人关注、**客服回复工单**
- 导航栏红点实时显示未读数
- 一键全部已读

---

### 🤖 AI 功能（DeepSeek）

#### 摘要与标签

- 发帖 / 编辑后自动调用 DeepSeek API，生成 ≤50 字摘要 + 3-5 个标签
- 标签用于 `user_interests` 兴趣权重计算和个性化推荐

#### AI 助手（`/pages/ai_assistant.php`）

- 气泡聊天 UI，多轮对话，本地历史存储
- 系统预设校园场景：学习辅助、求职建议、论文思路、校园生活
- 支持清空对话；未配置 API Key 时显示提示

#### AI 客服（`/pages/support.php` 内嵌）

- 独立客服专用系统提示词，聚焦论坛使用帮助（账号/发帖/社团/积分/举报等）
- 调用记录写入 `ai_logs` 表（type='support'）
- AI 无法解决时引导用户转人工客服

---

### 🎧 客服中心（`/pages/support.php`）

> AI 优先，人工兜底的两级客服体系

#### 用户端

- **AI 客服区**：气泡对话 UI，快捷问题 chip（如"如何发帖？"），即时响应
- 首次 AI 回复后显示**「👨‍💼 AI 无法解决？转人工客服」**按钮
- 点击后弹出工单表单，主题自动预填最后一条用户消息
- AI 对话历史随工单一并提交，供管理员参考
- **我的工单**：展示所有历史工单及状态（待处理 / 已回复 / 已关闭），点击展开查看回复并可追加说明
- 管理员回复后触发站内通知

#### 管理端（`/admin/support.php`）

- 按状态（待处理 / 已回复 / 已关闭）+ 分类筛选工单
- 侧边栏显示待处理工单数角标
- 展开工单可查看：用户原始描述 + AI 对话上下文（可折叠）+ 回复历史
- 内联回复表单 + 一键关闭工单
- 管理员回复后状态自动更新为"已回复"，并推送站内通知给用户

---

### 🚩 举报系统

#### 用户端

- 帖子页操作栏 / 用户主页均有**🚩 举报**按钮（非本人）
- 举报弹窗（`includes/report_modal.php`）：
  - 帖子举报原因：侵犯知识产权、色情低俗、违规广告引流、涉政敏感、散布谣言、涉嫌诈骗、人身攻击/引战、垃圾信息、其他
  - 用户举报原因：个人信息违规、色情低俗、发布不实信息、人身攻击、赌博诈骗、违规引流、其他
- 补充说明输入框（≤200 字，选填）
- AJAX 提交，**24 小时内同一对象仅可举报一次**

#### 管理端（`/admin/reports.php`）

- 按状态（待处理 / 已处理 / 已驳回）+ 类型（帖子 / 用户 / 评论）筛选
- 侧边栏显示待处理举报数角标
- 操作：✔ 标记已处理 / ✕ 驳回 / 🗑 删帖（帖子类型）/ 🔒 封禁用户（弹窗选时长：1/3/7/30 天 / 永久）

---

### 🏛️ 社团系统

#### 社团广场（`/pages/clubs.php`）

两个 Tab：
- **📢 社团动态**：聚合当前学校所有社团的最新动态，未登录显示全部
- **🏛️ 社团列表**：16:9 背景图卡片网格（背景图 → 头像拉伸 → 主题色渐变）

#### 申请创建（`/pages/club_apply.php`）

- 填写名称 / 简介 / 创建目的，**强制上传社团图片**
- 学校自动读取申请人账号，不可修改
- 管理员审核通过后自动建团并设为社长

#### 社团详情（`/pages/club.php`）

- **📢 动态 Tab**：社长 / 副社长以社团名义发布动态
  - **Quill 富文本编辑器**：支持图文混排（图片 AJAX 上传）、格式、链接
  - 动态内容渲染支持富文本 HTML
  - 可删除自己发布的动态
- **👥 成员 Tab**：成员列表（社长→副社长→成员排序）、入团申请审核
- 成员管理（社长）：设置 / 取消副社长、踢出成员（必填原因）、移交社长
- 加入规则：每人只能加入一个社团，必须与社团学校相同

#### 社团管理（`/pages/club_edit.php`，社长）

- 背景图 / 头像更换（实时预览）
- 简介直接修改，改名提交审核

#### 我的社团（`/pages/my_clubs.php`）

- 展示已加入社团和待审核创建申请

---

### 💬 私信系统（`/pages/messages.php`）

- 一对一私聊：左侧会话列表 + 右侧消息气泡
- **群组聊天**：创建群组，邀请多名关注的用户
- **撤回消息**：发送后 2 分钟内可撤回（显示"消息已撤回"）
- 导航栏未读数实时显示（AJAX 3 秒轮询）
- 分享帖子：直接从帖子详情页私信分享给关注的人
- 管理端（`/admin/messages.php`）：查看所有群组记录 + SCID 查询私信记录

---

### 🔍 搜索（`/pages/search.php`）

- 搜索范围：帖子（标题 + 内容）/ 用户（用户名 + SCID）/ 板块
- 关键词高亮显示
- 结果按相关性排序

---

### ⚙️ 账号设置（`/pages/settings.php`）

- 修改头像 / 用户名 / 学校 / 个人简介
- 修改密码（需验证旧密码）
- 侧栏展示账号信息（邮箱 / 角色 / 等级 / 经验 / 注册时间 / 连续签到天数）

---

### 📄 静态信息页

| 页面 | URL |
|------|-----|
| 关于我们 | `/pages/about.php` |
| 使用规则 | `/pages/terms.php` |
| 隐私政策 | `/pages/privacy.php` |
| 联系我们 | `/pages/contact.php` |

- **关于我们**：项目简介、8 项功能特色卡片、技术栈标签、实时数据库统计（帖子 / 用户 / 评论 / 社团数）
- **使用规则**：账号 / 内容 / 互动 / 社团 / 违规处理 / 免责 6 节，版本号 v1.0
- **隐私政策**：收集范围（键值表）、使用方式、AI 第三方说明（DeepSeek）、数据安全（bcrypt 加密声明）、用户权利
- **联系我们**：渠道导引表（AI 客服 / 举报 / 工单 / 私信）+ FAQ 可折叠问答

底部链接由 `$base` 变量动态拼接，兼容任意路径深度。

---

### 🛠️ 管理后台（`/admin/`）

权限：`admin` 和 `owner` 角色。

| 页面 | 功能 |
|------|------|
| `index.php` | 数据总览（用户/帖子/评论/封禁数统计卡片，最新注册/发帖列表） |
| `users.php` | 用户管理：搜索、封禁/解封（指定天数+原因）、角色修改 |
| `posts.php` | 帖子管理：搜索、软删除、置顶、加精、标记已解决 |
| `sections.php` | 板块管理：新增/编辑/排序一级 & 二级分区，配置图标/颜色/描述 |
| `homepage.php` | Hero 背景图上传（≤5MB，实时预览）；6 个精选槽位搜索配置 |
| `clubs.php` | 建社申请审核、改名申请审核、社团停用/启用、历史记录 |
| `support.php` | 客服工单列表（筛选/内联回复/查看 AI 上下文/关闭）；待处理数角标 |
| `reports.php` | 举报列表（筛选/处理/驳回/删帖/封禁弹窗）；待处理数角标 |
| `messages.php` | 群组消息查阅；SCID 私信记录查询 |
| `settings.php` | DeepSeek API Key 配置、AI 调用统计 |
| `logs.php` | 管理员操作日志（筛选/分页，每页 30 条） |

---

### 🎨 界面与体验

- **Academic Monochrome 主题**：羊皮纸米黄底色（`#edeae2`）+ 深棕主色，字体采用 Playfair Display、EB Garamond、思源宋体，营造学术期刊质感
- **暗色 / 亮色模式切换**：localStorage 持久化，`<head>` 内立即执行防闪屏；暗色模式切换为深海军蓝（`#0c0d14`）
- 全站 CSS 变量驱动（`--primary / --bg-card / --border / --txt` 等），`color-scheme: dark` 确保浏览器原生控件跟随主题
- **Lucide Icons**：全站统一 SVG 图标，`stroke-width: 1.8` 细线风格与学术主题呼应
- 导航栏铺满全宽（无 max-width 限制），搜索框 `flex:1` 自适应
- 响应式布局，`layout-2col` 网格在移动端自动折叠

---

## 技术栈

| 层级 | 技术 | 说明 |
|------|------|------|
| 后端 | PHP 8.2 | 原生 PHP，无框架 |
| 数据库 | MySQL 8 / MariaDB | 首次访问自动建表 |
| 前端 | HTML / CSS / 原生 JS | 无前端框架依赖 |
| 富文本 | Quill.js 1.3.7 | 发帖 + 社团动态编辑器 |
| AI | DeepSeek API | 摘要 / 标签 / 对话 / 客服 |
| 部署 | Docker + Apache | PHP 8.2-apache 镜像 |
| 主题 | Academic Monochrome | 羊皮纸配色 + Playfair Display / EB Garamond / 思源宋体，暗色 / 亮色双模式 |
| 图标 | Lucide Icons | SVG 内联图标库 |

---

## 项目结构

```
.
├── index.php                     # 精选主页（Hero 背景 + 6 槽位）
├── square.php                    # 广场（推荐 + 最新 + 本校切换）
├── style.css                     # 全局样式（CSS 变量双主题）
├── config.php                    # 数据库连接 & 站点常量
├── Dockerfile                    # Docker 构建文件
├── docker-entrypoint.sh          # Docker 入口脚本
├── LICENSE                       # MIT 协议
│
├── config/
│   └── schools.php               # 预置高校列表（985/211/普本）
│
├── docs/
│   ├── 网站地图.md
│   └── 需求分析.md
│
├── pages/                        # 前台页面
│   ├── login.php                 # 登录
│   ├── register.php              # 注册
│   ├── logout.php                # 退出登录
│   ├── forgot_password.php       # 找回密码（邮箱验证码）
│   ├── settings.php              # 账号设置
│   ├── profile.php               # 用户主页
│   ├── publish.php               # 发帖 / 编辑（Quill 编辑器）
│   ├── post.php                  # 帖子详情（评论 / 点赞 / 收藏 / 举报）
│   ├── section.php               # 板块页（含全分区概览）
│   ├── search.php                # 全站搜索
│   ├── explore.php               # 发现（个性化推荐）
│   ├── hot.php                   # 热帖榜
│   ├── notifications.php         # 站内通知
│   ├── ai_assistant.php          # AI 助手对话
│   ├── clubs.php                 # 社团广场
│   ├── club.php                  # 社团详情（动态 Quill 编辑器 + 成员管理）
│   ├── club_apply.php            # 申请创建社团
│   ├── club_edit.php             # 社长管理社团
│   ├── my_clubs.php              # 我的社团
│   ├── messages.php              # 私信（私聊 + 群组）
│   ├── support.php               # 客服中心（AI 优先 + 人工工单）
│   ├── about.php                 # 关于我们
│   ├── terms.php                 # 使用规则
│   ├── privacy.php               # 隐私政策
│   └── contact.php               # 联系我们
│
├── admin/                        # 管理后台（需 admin / owner 角色）
│   ├── index.php                 # 数据总览
│   ├── users.php                 # 用户管理
│   ├── posts.php                 # 帖子管理
│   ├── sections.php              # 板块管理
│   ├── homepage.php              # 主页槽位 + Hero 背景图
│   ├── clubs.php                 # 社团管理
│   ├── support.php               # 客服工单管理
│   ├── reports.php               # 举报管理
│   ├── messages.php              # 私信内容查阅
│   ├── settings.php              # AI 设置
│   └── logs.php                  # 操作日志
│
├── actions/                      # 表单处理控制器（POST → redirect）
│   ├── auth.php                  # 注册 / 登录
│   ├── forgot_pw.php             # 密码重置
│   ├── post_save.php             # 保存帖子（含 AI 摘要 / 标签）
│   ├── post_action.php           # 帖子操作（点赞 / 收藏 / 删除 / 标记）
│   ├── comment_save.php          # 发表 / 删除评论
│   ├── follow_toggle.php         # 关注 / 取关（JSON）
│   ├── settings_save.php         # 保存账号设置
│   ├── club_action.php           # 全部社团操作
│   ├── homepage_slot_save.php    # 主页槽位配置
│   ├── hero_bg_upload.php        # Hero 背景图上传 / 删除
│   ├── ai_chat.php               # AI 助手对话接口
│   ├── ai_summary.php            # 手动触发 AI 摘要 / 标签
│   ├── ai_test.php               # DeepSeek 连通性测试
│   ├── message_action.php        # 私信发送 / 撤回 / 群组操作
│   ├── report_submit.php         # 举报提交（24h 重复检测）
│   ├── support_action.php        # 客服工单 CRUD
│   ├── support_ai_chat.php       # 客服专用 AI 接口
│   └── support_load_replies.php  # 工单回复 AJAX 加载
│
├── api/                          # AJAX 数据接口（返回 JSON）
│   ├── upload_image.php          # 图片上传（帖子 + 社团动态共用）
│   ├── search_posts.php          # 搜索帖子（主页管理用）
│   ├── user_search.php           # 搜索用户（私信选人）
│   ├── messages_poll.php         # 轮询未读消息数
│   └── get_following.php         # 获取关注列表（私信转发）
│
├── includes/                     # 公共组件
│   ├── db_init.php               # 数据库建表 & 初始化（自动执行）
│   ├── header.php                # 导航栏（含封禁检测 / 通知数 / 管理侧边栏）
│   ├── footer.php                # 页脚（含底部链接 / 主题切换 JS）
│   ├── helpers.php               # 公共函数库
│   └── report_modal.php          # 举报弹窗共享组件
│
├── assets/                       # 静态资源
│   ├── logo.svg
│   └── default_avatar.svg
│
└── uploads/                      # 用户上传文件（需写权限）
    ├── avatars/                  # 用户头像
    ├── posts/                    # 帖子内嵌图片 & 社团动态图片
    ├── clubs/                    # 社团头像 & 背景图
    └── hero/                     # 首页 Hero 背景大图
```

---

## 数据库设计

数据库表结构由 `includes/db_init.php` **首次访问时自动创建**，无需手动导入 SQL。  
已有旧库时，通过 `INFORMATION_SCHEMA` 查询自动补全缺少的列（兼容滚动升级）。

| 表名 | 说明 |
|------|------|
| `users` | 用户（含 SCID、EXP、封禁信息、学校、角色） |
| `posts` | 帖子（含 AI 摘要、标签、置顶 / 加精标志） |
| `comments` | 评论（支持两级嵌套，parent_id=0 为顶级） |
| `sections` | 板块（parent_id=0 为一级分区） |
| `post_likes` | 帖子点赞 |
| `post_favs` | 帖子收藏 |
| `comment_likes` | 评论点赞 |
| `follows` | 关注关系 |
| `notifications` | 站内通知（点赞 / 评论 / 关注 / 客服回复） |
| `user_interests` | 用户兴趣标签权重（推荐算法用） |
| `ai_logs` | AI 调用日志（type: summary / tags / chat / support） |
| `homepage_slots` | 精选主页槽位配置（position + post_id） |
| `site_settings` | 站点 key-value 配置（如 hero_bg、deepseek_api_key） |
| `password_resets` | 密码重置验证码（含过期时间） |
| `conversations` | 私信会话（私聊 / 群组） |
| `conversation_members` | 会话成员（含 last_read_at） |
| `messages` | 私信消息（含撤回标志） |
| `clubs` | 社团（含头像、背景图、状态） |
| `club_applications` | 建社申请（含审核状态 / 原因） |
| `club_members` | 社团成员（角色：president / vice_president / member） |
| `club_join_requests` | 入团申请 |
| `club_posts` | 社团动态（支持富文本 HTML） |
| `club_kick_logs` | 踢出记录 |
| `club_name_changes` | 社团改名申请（需管理员审核） |
| `admin_logs` | 管理员操作记录 |
| `reports` | 举报记录（type: post / user / comment；status: pending / handled / dismissed） |
| `support_tickets` | 客服工单（含 AI 对话上下文、分类、状态） |
| `support_replies` | 工单回复（is_admin 标识是否为客服回复） |

---

## 快速部署

### 方式一：Docker（推荐）

**前提**：已安装 Docker，并准备好 MySQL / MariaDB 数据库。

```bash
docker run -d \
  --name campus-forum \
  -p 8080:80 \
  -e MYSQL_HOST=your_db_host \
  -e MYSQL_USER=your_db_user \
  -e MYSQL_PASSWORD=your_db_pass \
  -e MYSQL_DATABASE=campus_forum \
  -e DEEPSEEK_API_KEY=sk-xxxxxxxxxxxxxxxx \
  ghcr.io/lingyunalingyun/4c-university-intelligence-forum:latest
```

访问 `http://localhost:8080`，首次打开自动建表（约 2-5 秒）。

---

### 方式二：PHP + Apache / Nginx（本地开发）

**系统要求**：PHP 8.0+（启用 `mysqli`、`curl`、`fileinfo`），MySQL 5.7+ / MariaDB 10.4+

```bash
# 1. 克隆仓库
git clone https://github.com/lingyunalingyun/4C-University-Intelligence-Forum.git
cd 4C-University-Intelligence-Forum

# 2. 创建数据库
mysql -u root -p -e "
  CREATE DATABASE campus_forum CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'forum_user'@'localhost' IDENTIFIED BY 'your_password';
  GRANT ALL PRIVILEGES ON campus_forum.* TO 'forum_user'@'localhost';
  FLUSH PRIVILEGES;
"

# 3. 设置上传目录权限
chmod -R 755 uploads/
chown -R www-data:www-data uploads/
```

修改 `config.php`：

```php
$servername  = 'localhost';
$username_db = 'forum_user';
$password_db = 'your_password';
$dbname      = 'campus_forum';

define('DEEPSEEK_API_KEY', 'sk-xxxxxxxxxxxxxxxx'); // 可选
```

---

### 方式三：宝塔面板

1. 新建 PHP 网站，PHP 版本选 **8.2**
2. 上传项目文件到网站根目录
3. 宝塔数据库面板创建数据库，记录账号密码
4. 修改 `config.php` 填写数据库信息
5. 确保 `uploads/` 目录权限 **755**

---

## 初始化配置

### 创建管理员账号

项目不提供默认管理员。部署完成后：

1. 注册普通账号
2. 数据库执行 SQL 升级为站长：

```sql
UPDATE users SET role = 'owner' WHERE username = '你的用户名';
```

3. 重新登录，导航栏出现"管理后台"入口

### 角色权限

| 角色 | 说明 |
|------|------|
| `user` | 普通用户（默认） |
| `admin` | 管理员，可访问全部后台模块、管理用户/帖子/社团/工单/举报 |
| `owner` | 站长，拥有全部权限，可修改 admin 角色 |

### 配置精选主页

管理后台 → **主页管理** → 在 6 个槽位中搜索并选择帖子 → 保存后首页立即生效。

### 配置 Hero 背景大图

管理后台 → **主页管理** → Hero 背景图区域上传 JPG/PNG/WebP（≤5MB，建议 1920×600+）。  
路径存储在 `site_settings`（`key='hero_bg'`），文件保存在 `uploads/hero/`。

### 配置 AI 功能

访问 [platform.deepseek.com](https://platform.deepseek.com/) 创建 API Key，填入 `config.php` 或通过环境变量注入：

```php
define('DEEPSEEK_API_KEY', 'sk-xxxxxxxxxxxxxxxx');
```

或在管理后台 → **AI 设置** 中通过 Web 界面配置（写入 `site_settings` 表）。

---

## 环境变量

| 变量名 | 说明 | 默认值 |
|--------|------|--------|
| `MYSQL_HOST` | 数据库主机地址 | `localhost` |
| `MYSQL_USER` | 数据库用户名 | — |
| `MYSQL_PASSWORD` | 数据库密码 | — |
| `MYSQL_DATABASE` | 数据库名称 | — |
| `MYSQL_PORT` | 数据库端口 | `3306` |
| `DEEPSEEK_API_KEY` | DeepSeek API 密钥（可选） | 空（AI 功能不可用） |
| `SITE_URL` | 站点完整 URL（邮件链接用） | 自动检测 HTTP_HOST |
| `PORT` | Docker 容器监听端口 | `80` |

---

## 页面一览

| 页面 | URL | 说明 |
|------|-----|------|
| 精选主页 | `/` | Hero 背景 + 6 精选槽位 |
| 广场 | `/square.php` | 推荐 + 最新 + 本校切换 |
| 热榜 | `/pages/hot.php` | 综合热度排行 |
| 发现 | `/pages/explore.php` | 个性化推荐 |
| 全分区概览 | `/pages/section.php` | 板块网格 |
| 具体板块 | `/pages/section.php?slug=tech` | 帖子列表 |
| 帖子详情 | `/pages/post.php?id=1` | 正文 + 评论 + 举报 |
| 发帖 | `/pages/publish.php` | Quill 富文本编辑器 |
| 搜索 | `/pages/search.php?q=关键词` | 帖子 / 用户 / 板块 |
| 个人主页 | `/pages/profile.php?id=1` | 含社团徽标 + 举报 |
| 社团广场 | `/pages/clubs.php` | 动态 / 列表双 Tab |
| 社团详情 | `/pages/club.php?id=1` | Quill 动态编辑器 + 成员管理 |
| 我的社团 | `/pages/my_clubs.php` | 已加入社团 |
| AI 助手 | `/pages/ai_assistant.php` | 多轮对话 |
| 客服中心 | `/pages/support.php` | AI 优先 + 人工工单 |
| 私信 | `/pages/messages.php` | 私聊 + 群组 |
| 通知 | `/pages/notifications.php` | 站内消息 |
| 设置 | `/pages/settings.php` | 账号信息修改 |
| 关于我们 | `/pages/about.php` | 项目介绍 + 实时统计 |
| 使用规则 | `/pages/terms.php` | 社区规范 |
| 隐私政策 | `/pages/privacy.php` | 数据说明 |
| 联系我们 | `/pages/contact.php` | 渠道导引 + FAQ |
| 管理后台 | `/admin/index.php` | 数据总览 |
| 用户管理 | `/admin/users.php` | 封禁 / 角色 |
| 帖子管理 | `/admin/posts.php` | 删除 / 置顶 / 加精 |
| 板块管理 | `/admin/sections.php` | 分区配置 |
| 主页管理 | `/admin/homepage.php` | Hero 图 + 精选槽位 |
| 社团管理 | `/admin/clubs.php` | 审核 / 停用 |
| 客服工单 | `/admin/support.php` | 回复 / 关闭 |
| 举报管理 | `/admin/reports.php` | 处理 / 封禁 / 删帖 |
| 私信记录 | `/admin/messages.php` | 合规查阅 |
| AI 设置 | `/admin/settings.php` | API Key + 统计 |
| 操作日志 | `/admin/logs.php` | 管理员行为审计 |

---

## 开发说明

### 公共函数库（`includes/helpers.php`）

| 函数 | 说明 |
|------|------|
| `h($str)` | htmlspecialchars 转义，防 XSS |
| `avatar_url($avatar, $base)` | 生成头像 URL，空值返回默认图 |
| `time_ago($datetime)` | 相对时间（刚刚 / N分钟前 / N天前） |
| `get_level($exp)` | 根据经验值计算等级（1-6） |
| `level_next($exp)` | 下一级所需 EXP |
| `level_badge($exp)` | 生成彩色等级徽章 HTML |
| `role_badge($role)` | 生成角色徽章 HTML |
| `render_post_item($p, $base)` | 渲染帖子列表条目（各页面共用） |
| `render_post_content($content)` | 渲染帖子/动态正文（Quill HTML 或 nl2br 兼容旧数据） |
| `sanitize_rich_html($html)` | 净化 Quill HTML，允许安全标签，过滤事件属性和非白名单 src |
| `add_notification(...)` | 写入站内通知 |
| `update_interest(...)` | 更新用户兴趣权重 |
| `log_admin_action(...)` | 记录管理员操作到 admin_logs |
| `deepseek_request($prompt)` | 调用 DeepSeek API（单次） |
| `ai_summary($title, $content)` | 生成帖子摘要 |
| `ai_tags($title, $content)` | 生成帖子标签 |
| `extract_cover_image($content)` | 从帖子 HTML 内容提取首张图片 URL |

### 新功能开发规范

- **新前台页面**：放 `pages/`，顶部手动 `session_start()`（在 `include header.php` 之前），用 `$base = '../'` 处理路径
- **新 action**：放 `actions/`，处理完 `header('Location: ...')` 重定向，需 `session_start()` + 权限校验
- **新 API**：放 `api/`，输出 `Content-Type: application/json`
- **新数据库表**：在 `includes/db_init.php` 末尾追加 `CREATE TABLE IF NOT EXISTS`；新增列用 INFORMATION_SCHEMA 检查后 `ALTER TABLE ADD COLUMN`
- **富文本内容保存**：调用 `sanitize_rich_html()` 净化后再存库
- **富文本内容展示**：调用 `render_post_content()` 渲染（自动兼容旧纯文本）

### 安全注意事项

- 所有用户输入必须经过 `h()` 转义或 `real_escape_string()` 处理后写库
- 文件上传通过 `finfo_open(FILEINFO_MIME_TYPE)` 校验真实 MIME 类型，白名单扩展名，限制文件大小
- 管理员 action 顶部校验 `$_SESSION['role']`
- 富文本内容写库前通过 `sanitize_rich_html()` 过滤危险属性（on* 事件、非 /uploads/ 的 img src 等）
- `session_start()` 必须在每个页面顶部手动调用，不能依赖 header.php 的延迟 include

---

## 开源协议

本项目以 [MIT License](./LICENSE) 开源，欢迎 Fork 和二次开发。

---

<div align="center">
  <sub>第十九届全国大学生计算机设计大赛（4C）参赛作品 · Web 应用与开发类 · 广西赛区 · 2026</sub>
</div>
