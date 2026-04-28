# 高校智慧交流论坛

<div align="center">

**该项目成品展示链接：https://paperchemis.top**

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green)
![Competition](https://img.shields.io/badge/第19届4C大赛-Web应用与开发类-orange)

**面向高校学生的全功能社区论坛平台**

集帖子发布、社团管理、AI 助手、个性化推荐于一体，采用暗色/亮色双主题设计。

</div>

---

## 目录

- [项目介绍](#项目介绍)
- [功能特性](#功能特性)
- [技术栈](#技术栈)
- [项目结构](#项目结构)
- [数据库设计](#数据库设计)
- [快速部署](#快速部署)
- [环境变量](#环境变量)
- [初始化配置](#初始化配置)
- [页面一览](#页面一览)
- [开发说明](#开发说明)
- [开源协议](#开源协议)

---

## 项目介绍

**高校智慧交流论坛**是第十九届全国大学生计算机设计大赛（4C）Web 应用与开发类参赛作品，面向高校学生群体，提供论坛发帖、社团组建、AI 对话、个性化内容推荐等功能。

项目采用原生 PHP + MySQL 构建，无需任何 PHP 框架，支持 Docker 一键部署，数据库表结构在首次访问时自动创建。

---

## 功能特性

### 🏠 主页与内容发现

#### 精选主页（首页）
- 管理员可配置 **6 个精选槽位**，展示指定帖子
- 槽位 1 为全宽英雄区，2-3 双列，4-6 三列
- 每张卡片 **16:9** 比例，自动提取帖子封面图作为背景
- 无图帖子使用主题色渐变占位，标题叠加在图片上方

#### 广场（square.php）
- **推荐区**：基于 AI 标签匹配的个性化推荐帖子
- **最新帖子**：按时间倒序的全站帖子流
- **侧栏**：热门帖子 Top 5、活跃板块入口

#### 热榜（/pages/hot.php）
- 按 `浏览×0.3 + 点赞×2 + 评论×1.5` 综合评分排行
- 支持按时间范围筛选：**本周 / 本月 / 全部**
- 分页展示，每页 20 条

#### 发现（/pages/explore.php）
- 根据用户历史浏览/点赞帖子的标签，计算**兴趣权重**
- 按权重匹配推荐相关帖子，有历史数据时精准推荐
- 未登录或无兴趣数据时，回退至全站精选/最热

---

### 📝 帖子系统

#### 发布与编辑
- 富文本编辑器，支持加粗、斜体、引用、代码块、图片上传
- **选择板块**：4 个一级分区，每个含 4 个二级子分区
- 支持**编辑已发帖子**（作者或管理员）
- 发帖后 AI 自动生成摘要（最多 50 字）和 3-5 个关键标签

#### 帖子详情（/pages/post.php）
- 浏览量实时 +1，自动更新用户兴趣权重
- 展示 AI 摘要、标签、面包屑导航
- **点赞 / 收藏**（AJAX，无刷新）
- 相关推荐：同板块最新 5 帖
- 帖子可标记为**已解决**

#### 评论系统
- 支持顶级评论与**嵌套回复**（两级结构）
- 评论点赞
- `@用户名` 触发站内通知
- 管理员可删除任意评论

#### 分区（/pages/section.php）
- 访问无 slug 时展示**全部分区概览**网格（每个分区显示颜色、图标、帖子数、子分区标签）
- 进入具体分区后支持按**最新 / 最热 / 已解决**排序
- 面包屑：首页 → 一级分区 → 二级分区

---

### 🏛️ 社团系统

#### 社团广场（/pages/clubs.php）
页面分两个 Tab：

**📢 社团动态**
- 聚合展示**当前学校所有社团**发布的最新动态
- 登录后按用户所在学校自动过滤，未登录显示全部
- 点击动态跳转到对应社团的动态 Tab

**🏛️ 社团详细**
- 以 **16:9 背景图卡片**网格展示所有社团
- 背景优先级：社团背景图 → 社团头像（拉伸填充）→ 主题色渐变
- 卡片左上角叠加社团小头像，底部渐变遮罩显示社团名、学校、人数

#### 申请创建社团（/pages/club_apply.php）
- 需填写：社团名称、简介、创建目的，并**强制上传社团图片**
- 附属学校自动读取申请人的账号学校，不可手动修改
- 管理员审核通过后，自动创建社团并将申请人设为社长
- 每人同时只能有一个待审核申请

#### 社团详情页（/pages/club.php）

分两个 Tab：

**📢 社团动态 Tab（默认）**
- 社长/副社长可以**以社团名义发布动态**（显示社团头像+名称，注明发布者）
- 支持删除自己发布的动态
- 所有访客均可查看

**👥 成员 Tab**
- 展示全部成员列表（社长→副社长→普通成员排序）
- 待审核入团申请数量徽章显示在 Tab 上
- 社长/副社长可审核（批准/拒绝）入团申请

**成员管理（社长专属）**
- 设置/取消副社长
- 踢出成员（必须填写原因，记录在踢出日志）
- 移交社长（选择现有成员接任）

**加入社团**
- 每人**只能加入一个社团**，申请时与批准时双重校验
- 必须与社团学校相同才可申请，不同学校显示提示
- 加入需填写申请理由，由社长/副社长审核
- 支持撤回待审核申请

#### 我的社团（/pages/my_clubs.php）
- 展示当前用户加入的所有社团，按角色排序（社长→副社长→成员）
- 社长可点击"管理"进入社团编辑页
- 待审核创建申请也在此页展示

#### 社团管理（/pages/club_edit.php，社长专属）
- **背景图**：上传 16:9 背景图（最大 5MB），实时预览，用于社团详细卡片展示
- **头像**：更换社团头像（最大 3MB），实时预览
- **简介**：直接修改，立即生效
- **改名**：提交改名申请，需管理员审核，审核期间不可重复申请

#### 个人主页社团徽标
- 用户主页自动展示所属社团的**渐变色徽标**
- 社长：金色渐变；副社长：紫色渐变；普通成员：蓝色渐变
- 点击徽标可跳转到社团页

---

### 👤 用户系统

#### 注册与登录
- 邮箱 + 用户名 + 密码注册，支持邮箱验证（可关闭）
- 忘记密码：邮箱发送验证码重置
- 登录后连续登录奖励（登录连击天数记录）

#### 个人资料
- 头像上传（JPG/PNG，最大 2MB）
- 个人简介、所在学校（从 985/211 等高校列表下拉选择）
- 8 位唯一 **SCID**（站内用户识别码），可通过 SCID 搜索用户

#### 经验与等级系统
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
- 关注 / 取关其他用户（AJAX，无刷新）
- 个人主页展示帖子数、粉丝数、关注数

#### 站内通知
- 触发场景：帖子被点赞、帖子被收藏、帖子被评论、评论被点赞、有人关注
- 导航栏实时显示未读通知红点
- 支持一键全部标记已读

---

### 🤖 AI 功能（DeepSeek）

#### 自动生成摘要
- 发帖/编辑后自动调用 DeepSeek API
- 提取帖子核心内容，生成 50 字以内摘要
- 摘要展示在帖子列表和详情页的"AI摘要"区块

#### 自动生成标签
- 同步生成 3-5 个关键标签（每个不超过 5 字）
- 标签用于个性化推荐算法的兴趣匹配

#### AI 助手（/pages/ai_assistant.php）
- 对话式交互界面（气泡聊天 UI）
- 系统预设校园场景：学习辅助、求职建议、论文思路、校园生活
- 支持清空对话历史
- 需登录使用；未配置 API Key 时不可用

---

### 🔍 搜索系统（/pages/search.php）
- 搜索范围：**帖子**（标题+内容）/ **用户**（用户名+SCID）/ **板块**
- 关键词高亮显示
- 搜索结果按相关性排序

---

### ⚙️ 账号设置（/pages/settings.php）
- 修改头像、用户名、邮箱、个人简介
- 下拉选择学校（985/211 等高校预置列表，支持输入搜索）
- 修改密码（需验证旧密码）

---

### 🛠️ 管理后台（/admin/）

访问权限：`admin` 和 `owner` 角色。

#### 数据总览（index.php）
- 统计卡片：注册用户总数/今日新增、帖子总数/今日新增、评论总数、封禁用户数
- 最新注册用户列表（8 条）
- 最新帖子列表（8 条）
- 快捷入口按钮：用户管理、帖子管理、板块管理、主页管理、社团管理、操作记录

#### 用户管理（users.php）
- 搜索用户（用户名/邮箱/SCID）
- 临时封禁（指定天数 + 封禁原因）/ 解封
- 修改用户角色（user / admin，owner 角色限 owner 操作）
- 封禁用户在下次登录时自动检测是否到期解封

#### 帖子管理（posts.php）
- 搜索帖子（标题关键词）
- 删除帖子（软删除，状态改为 deleted）
- 置顶 / 取消置顶
- 加精 / 取消加精
- 标记已解决 / 取消

#### 板块管理（sections.php）
- 新增、编辑、排序一级/二级分区
- 配置分区图标（Emoji）、颜色、描述

#### 主页管理（homepage.php）
- 可视化 6 槽位布局预览
- 每个槽位独立配置：输入关键词搜索帖子，点击选择后保存
- 支持清空单个槽位
- 所有操作 AJAX 完成，无需刷新

#### 社团管理（clubs.php）
- **待审核 Tab**：帖子卡片形式展示申请，一键批准（含 confirm 弹窗）/ 拒绝（弹窗填写原因）
- **改名申请 Tab**：待审核的社团改名申请，批准后自动更新社团名
- **全部社团 Tab**：社团数据表格，支持停用/启用
- **审核记录 Tab**：历史申请记录（状态、审核人、时间）

#### 操作记录（logs.php）
- 记录所有管理员操作（社团审核、帖子管理、用户封禁等）
- 按管理员账号 + 操作类型关键词筛选
- 分页展示（每页 30 条）
- 字段：时间、管理员、操作类型、目标类型、目标 ID、详情

---

### 🎨 界面与体验

- **暗色 / 亮色主题切换**：localStorage 持久化，刷新不闪烁（`<head>` 内立即执行脚本）
- 全站 CSS 变量驱动，`color-scheme: dark` 确保浏览器原生控件跟随主题
- 响应式布局，移动端适配
- 帖子列表使用 `render_post_item()` 统一渲染函数，覆盖广场、探索、搜索、个人主页等场景

---

## 技术栈

| 层级 | 技术 | 说明 |
|------|------|------|
| 后端语言 | PHP 8.2 | 原生 PHP，无框架 |
| 数据库 | MySQL 8 / MariaDB | 首次访问自动建表 |
| 前端 | HTML / CSS / 原生 JS | 无前端框架 |
| 富文本 | Quill.js | 帖子编辑器 |
| AI | DeepSeek API | 摘要、标签、对话 |
| 部署 | Docker + Apache | PHP 8.2-apache 镜像 |
| 主题 | CSS 变量 | 暗色/亮色双主题 |

---

## 项目结构

```
.
├── index.php                   # 精选主页（管理员配置槽位）
├── square.php                  # 广场（推荐+最新帖子流）
├── style.css                   # 全局样式（CSS 变量双主题）
├── config.php                  # 数据库连接 & 站点常量
├── Dockerfile                  # Docker 构建文件
├── docker-entrypoint.sh        # Docker 入口（端口动态配置）
├── LICENSE                     # MIT 开源协议
│
├── config/
│   └── schools.php             # 预置高校列表（985/211）
│
├── pages/                      # 前台页面
│   ├── login.php               # 登录
│   ├── register.php            # 注册
│   ├── logout.php              # 退出登录
│   ├── forgot_password.php     # 找回密码（邮箱验证码）
│   ├── settings.php            # 账号设置
│   ├── profile.php             # 用户主页（帖子/收藏/关注/粉丝）
│   ├── publish.php             # 发帖/编辑帖子
│   ├── post.php                # 帖子详情（评论/点赞/收藏）
│   ├── section.php             # 板块页（含全分区概览）
│   ├── search.php              # 全站搜索
│   ├── explore.php             # 发现（个性化推荐）
│   ├── hot.php                 # 热帖榜
│   ├── notifications.php       # 站内通知
│   ├── ai_assistant.php        # AI 助手对话
│   ├── clubs.php               # 社团广场（动态/详细双Tab）
│   ├── club.php                # 社团详情（动态/成员双Tab）
│   ├── club_apply.php          # 申请创建社团
│   ├── club_edit.php           # 社长管理社团
│   └── my_clubs.php            # 我的社团
│
├── admin/                      # 管理后台（需 admin/owner 角色）
│   ├── index.php               # 数据总览
│   ├── users.php               # 用户管理（封禁/角色）
│   ├── posts.php               # 帖子管理（删除/置顶/加精）
│   ├── sections.php            # 板块管理
│   ├── homepage.php            # 主页槽位配置
│   ├── clubs.php               # 社团管理（审核/改名/停用）
│   └── logs.php                # 管理员操作记录
│
├── actions/                    # 表单处理控制器（POST 入口）
│   ├── auth.php                # 注册/登录
│   ├── forgot_pw.php           # 密码重置
│   ├── post_save.php           # 保存帖子（含 AI 摘要/标签）
│   ├── post_action.php         # 帖子操作（点赞/收藏/删除/标记）
│   ├── comment_save.php        # 发表/删除评论
│   ├── follow_toggle.php       # 关注/取关（返回 JSON）
│   ├── settings_save.php       # 保存账号设置
│   ├── club_action.php         # 全部社团操作
│   ├── homepage_slot_save.php  # 主页槽位保存/清除
│   ├── ai_chat.php             # AI 助手对话接口
│   └── ai_summary.php          # 手动触发 AI 摘要
│
├── api/                        # AJAX 数据接口（返回 JSON）
│   └── search_posts.php        # 搜索帖子（主页管理用）
│
├── includes/                   # 公共组件
│   ├── db_init.php             # 数据库建表（自动执行）
│   ├── header.php              # 顶部导航（含封禁检测/通知数）
│   ├── footer.php              # 页脚
│   └── helpers.php             # 公共函数库
│
├── assets/                     # 静态资源
│   ├── logo.svg
│   └── default_avatar.svg
│
└── uploads/                    # 用户上传文件（需可写权限）
    ├── avatars/                # 用户头像
    └── clubs/                  # 社团头像 & 背景图
```

---

## 数据库设计

数据库表结构在首次访问时由 `includes/db_init.php` **自动创建**，无需手动导入 SQL。

| 表名 | 说明 |
|------|------|
| `users` | 用户（含 SCID、经验值、封禁信息、学校认证） |
| `posts` | 帖子（含 AI 摘要、标签、置顶/加精标志） |
| `comments` | 评论（支持两级嵌套） |
| `sections` | 板块（支持父子两级结构） |
| `post_likes` | 帖子点赞 |
| `post_favs` | 帖子收藏 |
| `comment_likes` | 评论点赞 |
| `follows` | 关注关系 |
| `notifications` | 站内通知 |
| `user_interests` | 用户兴趣标签权重（推荐算法） |
| `ai_logs` | AI 调用日志 |
| `homepage_slots` | 精选主页槽位配置 |
| `clubs` | 社团（含头像、背景图、状态） |
| `club_applications` | 建社申请（含审核状态） |
| `club_members` | 社团成员（含角色：社长/副社长/成员） |
| `club_join_requests` | 入团申请 |
| `club_posts` | 社团动态 |
| `club_kick_logs` | 踢出记录 |
| `club_name_changes` | 社团改名申请 |
| `admin_logs` | 管理员操作记录 |
| `password_resets` | 密码重置验证码 |

> 已有旧库时，`db_init.php` 通过 `INFORMATION_SCHEMA` 查询自动补全缺少的列（如 `avatar`、`banner`、`scid`），兼容滚动升级。

---

## 快速部署

### 方式一：Docker（推荐）

**前提**：已安装 Docker，并准备好 MySQL/MariaDB 数据库。

```bash
docker run -d \
  --name campus-forum \
  -p 8080:80 \
  -e MYSQL_HOST=your_db_host \
  -e MYSQL_USER=your_db_user \
  -e MYSQL_PASSWORD=your_db_pass \
  -e MYSQL_DATABASE=your_db_name \
  -e DEEPSEEK_API_KEY=sk-xxxxxxxxxxxxxxxx \
  -e SITE_URL=http://localhost:8080 \
  ghcr.io/lingyunalingyun/4c-university-intelligence-forum:latest
```

访问 `http://localhost:8080`，首次打开会自动建表，约需 2-5 秒。

**自定义端口**（如需在平台部署）：

```bash
docker run -d -p 8080:3000 -e PORT=3000 \
  -e MYSQL_HOST=... \
  your-image
```

---

### 方式二：PHP + Apache/Nginx（本地开发）

**系统要求**
- PHP 8.0+，已启用 `mysqli`、`curl`、`fileinfo` 扩展
- MySQL 5.7+ 或 MariaDB 10.4+
- Apache（推荐）或 Nginx

**步骤**

1. **克隆仓库**

```bash
git clone https://github.com/lingyunalingyun/4C-University-Intelligence-Forum.git
cd 4C-University-Intelligence-Forum
```

2. **配置数据库**

在 MySQL 中创建数据库：

```sql
CREATE DATABASE campus_forum CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'forum_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON campus_forum.* TO 'forum_user'@'localhost';
FLUSH PRIVILEGES;
```

3. **修改 `config.php`**

```php
// 将默认的 getenv() 改为直接赋值，或通过环境变量注入
$servername  = 'localhost';
$username_db = 'forum_user';
$password_db = 'your_password';
$dbname      = 'campus_forum';

define('DEEPSEEK_API_KEY', 'sk-xxxxxxxxxxxxxxxx'); // 可选
```

4. **设置上传目录权限**

```bash
chmod -R 755 uploads/
chown -R www-data:www-data uploads/   # Apache 用户
```

5. **Apache 虚拟主机配置（参考）**

```apache
<VirtualHost *:80>
    ServerName forum.local
    DocumentRoot /var/www/html/4C-University-Intelligence-Forum
    <Directory /var/www/html/4C-University-Intelligence-Forum>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

6. **访问站点**

打开浏览器访问配置的域名，数据库表自动初始化。

---

### 方式三：宝塔面板

1. 在宝塔新建 PHP 网站，PHP 版本选 8.2
2. 上传项目文件到网站根目录
3. 在宝塔数据库面板创建数据库，记录账号密码
4. 修改 `config.php` 填写数据库信息
5. 确保 `uploads/` 目录权限为 755

---

## 环境变量

| 变量名 | 说明 | 默认值 |
|--------|------|--------|
| `MYSQL_HOST` | 数据库主机地址 | `localhost` |
| `MYSQL_USER` | 数据库用户名 | `svh_7x62hla` |
| `MYSQL_PASSWORD` | 数据库密码 | `rpuj3nwele` |
| `MYSQL_DATABASE` | 数据库名称 | `svh_7x62hla` |
| `MYSQL_PORT` | 数据库端口 | `3306` |
| `DEEPSEEK_API_KEY` | DeepSeek API 密钥 | 空（AI 功能不可用） |
| `SITE_URL` | 站点完整 URL（用于邮件链接） | 自动检测 HTTP_HOST |
| `PORT` | Docker 容器监听端口 | `80` |

> **注意**：`DEEPSEEK_API_KEY` 不填时，AI 摘要、AI 标签、AI 助手均不可用，其余功能不受影响。

---

## 初始化配置

### 创建管理员账号

项目不提供默认管理员账号。部署完成后：

1. 在网站注册一个普通账号
2. 在数据库执行以下 SQL 将其升级为站长（owner）：

```sql
UPDATE users SET role = 'owner' WHERE username = '你的用户名';
```

3. 重新登录，导航栏出现"管理后台"入口

### 角色权限说明

| 角色 | 说明 |
|------|------|
| `user` | 普通用户（默认） |
| `admin` | 管理员，可访问管理后台、管理用户/帖子/社团 |
| `owner` | 站长，拥有全部权限，可修改其他 admin 角色 |

### 配置精选主页

1. 以管理员登录，进入**管理后台 → 主页管理**
2. 在 6 个槽位中搜索并选择想要展示的帖子
3. 保存后首页立即生效

### 配置 AI 功能

获取 DeepSeek API Key：访问 [platform.deepseek.com](https://platform.deepseek.com/) 注册并创建 API Key。

通过环境变量注入或直接在 `config.php` 中配置：

```php
define('DEEPSEEK_API_KEY', 'sk-xxxxxxxxxxxxxxxx');
```

---

## 页面一览

| 页面 | URL | 说明 |
|------|-----|------|
| 精选主页 | `/` | 管理员配置的 16:9 大图精选帖子 |
| 广场 | `/square.php` | 推荐 + 最新帖子双栏布局 |
| 热榜 | `/pages/hot.php` | 综合热度排行 |
| 发现 | `/pages/explore.php` | 个性化推荐 |
| 分区概览 | `/pages/section.php` | 全部板块网格 |
| 具体板块 | `/pages/section.php?slug=tech` | 板块帖子列表 |
| 帖子详情 | `/pages/post.php?id=1` | 内容+评论+推荐 |
| 发帖 | `/pages/publish.php` | 富文本编辑器 |
| 搜索 | `/pages/search.php?q=关键词` | 帖子/用户/板块 |
| 个人主页 | `/pages/profile.php?id=1` | 含社团徽标 |
| 社团广场 | `/pages/clubs.php` | 动态/详细双Tab |
| 社团详情 | `/pages/club.php?id=1` | 动态/成员双Tab |
| 我的社团 | `/pages/my_clubs.php` | 已加入社团管理 |
| AI 助手 | `/pages/ai_assistant.php` | 对话式 AI |
| 通知 | `/pages/notifications.php` | 站内消息 |
| 设置 | `/pages/settings.php` | 账号信息修改 |
| 管理后台 | `/admin/index.php` | 数据总览 |
| 操作记录 | `/admin/logs.php` | 管理员行为日志 |

---

## 开发说明

### 公共函数库（includes/helpers.php）

| 函数 | 说明 |
|------|------|
| `h($str)` | `htmlspecialchars` 转义，防 XSS |
| `avatar_url($avatar, $base)` | 生成头像 URL，无头像返回默认图 |
| `time_ago($datetime)` | 相对时间（刚刚/N分钟前/N天前） |
| `get_level($exp)` | 根据经验值计算等级（1-6） |
| `level_badge($exp)` | 生成彩色等级徽章 HTML |
| `role_badge($role)` | 生成角色徽章 HTML |
| `render_post_item($p, $base)` | 渲染帖子列表条目（各页面共用） |
| `add_notification(...)` | 写入站内通知 |
| `update_interest(...)` | 更新用户兴趣权重 |
| `log_admin_action(...)` | 记录管理员操作日志 |
| `deepseek_request($prompt)` | 调用 DeepSeek API |
| `ai_summary($title, $content)` | 生成帖子摘要 |
| `ai_tags($title, $content)` | 生成帖子标签 |
| `extract_cover_image($content)` | 从帖子 HTML 提取首图 URL |

### 新增功能开发建议

- **新页面**：放入 `pages/`，顶部添加 `session_start()`（在 `include header.php` 之前），使用 `isset($_SESSION['user_id'])` 检查登录状态
- **新 action**：放入 `actions/`，处理完后用 `header('Location: ...')` 重定向
- **新 API**：放入 `api/`，返回 `Content-Type: application/json`
- **数据库新表**：在 `includes/db_init.php` 末尾添加 `CREATE TABLE IF NOT EXISTS`；新列用 INFORMATION_SCHEMA 检查后 `ALTER TABLE ADD COLUMN`

### 注意事项

- 所有用户输入必须经过 `h()` 或 `real_escape_string()` 处理
- 文件上传严格校验扩展名（白名单）和文件大小
- 管理员操作需在 action 文件顶部验证 `$_SESSION['role']`
- `session_start()` 必须在读取 `$_SESSION` 之前调用（别依赖 header.php 的延迟调用）

---

## 开源协议

本项目以 [MIT License](./LICENSE) 开源，欢迎 Fork 和二次开发。

---

<div align="center">
  <sub>第十九届全国大学生计算机设计大赛（4C）参赛作品 · Web 应用与开发类 · 广西赛区</sub>
</div>
