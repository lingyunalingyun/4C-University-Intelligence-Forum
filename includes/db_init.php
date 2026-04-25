<?php
// 用户表
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(50)  NOT NULL UNIQUE,
    email           VARCHAR(100) NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL,
    avatar          VARCHAR(255) DEFAULT '',
    bio             TEXT,
    school          VARCHAR(100) DEFAULT '',
    school_verified TINYINT(1)   DEFAULT 0,
    role            ENUM('user','admin','owner') DEFAULT 'user',
    exp             INT          DEFAULT 0,
    login_streak    INT          DEFAULT 0,
    last_login_date DATE         DEFAULT NULL,
    email_verified  TINYINT(1)   DEFAULT 0,
    verify_token    VARCHAR(64)  DEFAULT NULL,
    verify_token_expires DATETIME DEFAULT NULL,
    is_banned       TINYINT(1)   DEFAULT 0,
    ban_reason      TEXT,
    ban_until       DATETIME     DEFAULT NULL,
    banned_by       INT          DEFAULT NULL,
    created_at      DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 板块表（parent_id=0 为一级分区）
$conn->query("CREATE TABLE IF NOT EXISTS sections (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(50)  NOT NULL,
    slug        VARCHAR(50)  NOT NULL UNIQUE,
    parent_id   INT          DEFAULT 0,
    description TEXT,
    icon        VARCHAR(10)  DEFAULT '',
    color       VARCHAR(20)  DEFAULT '#2563eb',
    sort_order  INT          DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 帖子表
$conn->query("CREATE TABLE IF NOT EXISTS posts (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT          NOT NULL,
    section_id    INT          NOT NULL,
    title         VARCHAR(200) NOT NULL,
    content       TEXT         NOT NULL,
    summary       VARCHAR(300) DEFAULT '',
    tags          VARCHAR(500) DEFAULT '',
    status        ENUM('published','pending','deleted') DEFAULT 'published',
    views         INT          DEFAULT 0,
    like_count    INT          DEFAULT 0,
    comment_count INT          DEFAULT 0,
    fav_count     INT          DEFAULT 0,
    is_solved     TINYINT(1)   DEFAULT 0,
    is_pinned     TINYINT(1)   DEFAULT 0,
    is_featured   TINYINT(1)   DEFAULT 0,
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 评论表
$conn->query("CREATE TABLE IF NOT EXISTS comments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    post_id     INT  NOT NULL,
    user_id     INT  NOT NULL,
    content     TEXT NOT NULL,
    parent_id   INT  DEFAULT 0,
    like_count  INT  DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 点赞 / 收藏 / 评论点赞
$conn->query("CREATE TABLE IF NOT EXISTS post_likes (
    user_id INT, post_id INT, PRIMARY KEY(user_id, post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS post_favs (
    user_id INT, post_id INT, PRIMARY KEY(user_id, post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS comment_likes (
    user_id INT, comment_id INT, PRIMARY KEY(user_id, comment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 关注关系
$conn->query("CREATE TABLE IF NOT EXISTS follows (
    follower_id  INT,
    following_id INT,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(follower_id, following_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 站内通知
$conn->query("CREATE TABLE IF NOT EXISTS notifications (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT  NOT NULL,
    type         VARCHAR(30) NOT NULL,
    from_user_id INT  DEFAULT 0,
    post_id      INT  DEFAULT 0,
    comment_id   INT  DEFAULT 0,
    message      TEXT,
    is_read      TINYINT(1) DEFAULT 0,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 用户兴趣标签（个性化推荐）
$conn->query("CREATE TABLE IF NOT EXISTS user_interests (
    user_id    INT,
    tag        VARCHAR(50),
    weight     FLOAT   DEFAULT 1.0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(user_id, tag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// AI 调用日志
$conn->query("CREATE TABLE IF NOT EXISTS ai_logs (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT DEFAULT 0,
    type       VARCHAR(30),
    prompt     TEXT,
    result     TEXT,
    tokens_used INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 密码重置
$conn->query("CREATE TABLE IF NOT EXISTS password_resets (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(100) NOT NULL,
    code       VARCHAR(10)  NOT NULL,
    expires_at DATETIME     NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 初始化板块数据（只在空表时执行）
$check = $conn->query("SELECT COUNT(*) as cnt FROM sections");
if ($check && $check->fetch_assoc()['cnt'] == 0) {
    $sections = [
        // 一级分区
        [0, 'academic', '学术交流', '📚', '#4f46e5', '课程互助、考研备考、论文讨论、竞赛交流', 1],
        [0, 'campus',   '校园生活', '🏫', '#0ea5e9', '活动通知、二手交易、失物招领、美食推荐', 2],
        [0, 'career',   '职业发展', '💼', '#10b981', '实习内推、求职经验、竞赛组队、简历互评', 3],
        [0, 'tech',     '技术问答', '💻', '#f59e0b', '编程求助、项目展示、工具分享、Bug求助', 4],
    ];
    $stmt = $conn->prepare("INSERT INTO sections (parent_id,slug,name,icon,color,description,sort_order) VALUES (?,?,?,?,?,?,?)");
    foreach ($sections as $s) {
        $stmt->bind_param("isssssi", $s[0],$s[1],$s[2],$s[3],$s[4],$s[5],$s[6]);
        $stmt->execute();
    }

    // 二级子分区
    $subs = [
        ['academic', [['course','课程互助','📖'],['postgrad','考研备考','✏️'],['paper','论文讨论','📄'],['contest','竞赛交流','🏆']]],
        ['campus',   [['activity','活动通知','📣'],['trade','二手交易','🛒'],['lost','失物招领','🔍'],['food','美食推荐','🍜']]],
        ['career',   [['intern','实习内推','🌟'],['job','求职经验','💡'],['team','竞赛组队','🤝'],['resume','简历互评','📋']]],
        ['tech',     [['code','编程求助','⌨️'],['project','项目展示','🚀'],['tools','工具分享','🔧'],['bug','Bug求助','🐛']]],
    ];
    foreach ($subs as $g) {
        $parent_res = $conn->query("SELECT id FROM sections WHERE slug='" . $g[0] . "'");
        $parent_id  = $parent_res->fetch_assoc()['id'];
        $order = 1;
        foreach ($g[1] as $sub) {
            $stmt->bind_param("isssssi", $parent_id, $sub[0], $sub[1], $sub[2], $color, $desc, $order);
            $color = '#6b7280'; $desc = '';
            $stmt->execute();
            $order++;
        }
    }
    $stmt->close();
}
