# 高校智慧交流论坛

> 第十九届全国大学生计算机设计大赛（4C）参赛作品  
> Web 应用与开发类 · 广西赛区

一个面向高校学生的综合性社区论坛平台，集帖子发布、社团管理、AI 助手、个性化推荐于一体。

---

## 功能概览

### 论坛核心
- 多级板块（学术交流 / 校园生活 / 职业发展 / 技术问答）
- 富文本发帖，支持图片上传
- 评论 / 点赞 / 收藏 / @回复
- 帖子置顶、加精、已解决标记
- 全站搜索（标题 + 内容 + 用户）

### 主页与广场
- **管理员精选主页**：管理员配置 6 个展示槽位，16:9 大图卡片展示精选帖子
- **广场**：最新帖子、热门推荐、活跃板块侧栏
- **热榜**：按点赞 / 浏览 / 评论排行
- **发现**：基于用户兴趣标签的个性化推荐

### 社团系统
- 用户申请创建社团（需上传图片、填写简介和目的），管理员审核
- **每人只能加入一个社团**，加入需社长 / 副社长审核
- 社长可：设置副社长、踢出成员（需填原因）、移交社长、编辑社团简介/头像
- 社团改名需管理员二次审核
- 个人主页展示所属社团渐变色徽标（社长金 / 副社长紫 / 成员蓝）

### 用户系统
- 注册 / 登录 / 忘记密码（邮箱验证码）
- 经验值 & 6 级等级体系（新手→学徒→达人→精英→大神→传说）
- 8 位唯一 SCID，支持 SCID 搜索
- 关注 / 粉丝、站内通知（点赞 / 评论 / 关注）
- 学校认证字段，社团与学校绑定

### AI 功能（DeepSeek）
- 发帖时 AI 自动生成摘要和标签
- 站内 AI 助手对话

### 管理后台
- 用户管理（封禁 / 解封 / 改角色）
- 帖子管理（删除 / 置顶 / 加精）
- 板块管理
- 主页槽位管理（搜索帖子并配置展示位）
- 社团管理（审核建社申请 / 改名申请 / 启停社团）
- **操作记录**：所有管理员操作均记录，支持按人员 / 类型筛选

---

## 技术栈

| 层级 | 技术 |
|------|------|
| 后端 | PHP 8.2（原生，无框架） |
| 数据库 | MySQL 8 / MariaDB |
| 前端 | 原生 HTML / CSS / JS（无前端框架） |
| AI | DeepSeek API |
| 部署 | Docker（PHP 8.2 + Apache） |
| 主题 | CSS 变量暗色 / 亮色双主题 |

---

## 目录结构

```
.
├── index.php              # 精选主页
├── square.php             # 广场（最新 + 推荐）
├── style.css              # 全局样式
├── config.php             # 数据库 & 常量配置
├── Dockerfile
├── docker-entrypoint.sh
│
├── pages/                 # 前台页面
│   ├── clubs.php          # 社团列表
│   ├── club.php           # 社团详情 & 成员管理
│   ├── club_apply.php     # 申请创建社团
│   ├── club_edit.php      # 社长管理社团
│   ├── my_clubs.php       # 我的社团
│   ├── profile.php        # 用户主页
│   ├── post.php           # 帖子详情
│   ├── publish.php        # 发帖
│   ├── explore.php        # 发现（个性化推荐）
│   ├── section.php        # 板块
│   ├── hot.php            # 热榜
│   ├── search.php         # 搜索
│   ├── ai_assistant.php   # AI 助手
│   ├── notifications.php  # 通知
│   └── settings.php       # 账号设置
│
├── admin/                 # 管理后台
│   ├── index.php          # 数据总览
│   ├── users.php          # 用户管理
│   ├── posts.php          # 帖子管理
│   ├── sections.php       # 板块管理
│   ├── homepage.php       # 主页槽位配置
│   ├── clubs.php          # 社团管理
│   └── logs.php           # 操作记录
│
├── actions/               # 表单处理（POST）
│   ├── club_action.php
│   ├── post_action.php
│   ├── post_save.php
│   ├── comment_save.php
│   ├── auth.php
│   ├── settings_save.php
│   ├── follow_toggle.php
│   └── homepage_slot_save.php
│
├── api/                   # AJAX 接口
│   └── search_posts.php
│
├── includes/
│   ├── db_init.php        # 数据库表结构（自动建表）
│   ├── header.php         # 顶部导航
│   ├── footer.php         # 底部
│   └── helpers.php        # 公共函数
│
├── assets/                # 静态资源
└── uploads/               # 用户上传文件
    ├── avatars/
    └── clubs/
```

---

## 快速部署

### 方式一：Docker（推荐）

```bash
docker run -d \
  -p 8080:80 \
  -e MYSQL_HOST=your_db_host \
  -e MYSQL_USER=your_db_user \
  -e MYSQL_PASSWORD=your_db_pass \
  -e MYSQL_DATABASE=your_db_name \
  -e DEEPSEEK_API_KEY=your_key \
  ghcr.io/lingyunalingyun/4c-university-intelligence-forum:latest
```

访问 `http://localhost:8080`，数据库表结构首次访问时自动创建。

### 方式二：本地 PHP + MySQL

1. 克隆仓库到 Web 服务器目录（Apache / Nginx）
2. 确保 PHP 8.x 已安装 `mysqli` 扩展
3. 复制并修改配置：

```bash
cp config.php.example config.php   # 若无示例文件则直接编辑 config.php
```

4. 在 `config.php` 中填写数据库连接信息
5. 确保 `uploads/` 目录可写：

```bash
chmod -R 755 uploads/
```

6. 访问站点，数据库表结构自动初始化

---

## 环境变量

| 变量 | 说明 | 默认值 |
|------|------|--------|
| `MYSQL_HOST` | 数据库地址 | `localhost` |
| `MYSQL_USER` | 数据库用户名 | — |
| `MYSQL_PASSWORD` | 数据库密码 | — |
| `MYSQL_DATABASE` | 数据库名 | — |
| `MYSQL_PORT` | 数据库端口 | `3306` |
| `DEEPSEEK_API_KEY` | DeepSeek API 密钥（AI 功能） | 空（AI 功能不可用） |
| `SITE_URL` | 站点 URL（用于邮件链接） | 自动检测 |

---

## 初始管理员

数据库初始化后无默认管理员账号。注册第一个账号后，在数据库中手动将其角色升级：

```sql
UPDATE users SET role = 'owner' WHERE username = '你的用户名';
```

---

## 主要页面截图

| 页面 | 路径 |
|------|------|
| 精选主页 | `/index.php` |
| 广场 | `/square.php` |
| 社团列表 | `/pages/clubs.php` |
| 个人主页（含社团徽标） | `/pages/profile.php?id=1` |
| 管理后台 | `/admin/index.php` |
| 操作记录 | `/admin/logs.php` |

---

## License

MIT
