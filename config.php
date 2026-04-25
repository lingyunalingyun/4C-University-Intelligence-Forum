<?php
$servername  = getenv('MYSQL_HOST')     ?: "localhost";
$username_db = getenv('MYSQL_USER')     ?: "svh_7x62hla";
$password_db = getenv('MYSQL_PASSWORD') ?: "rpuj3nwele";
$dbname      = getenv('MYSQL_DATABASE') ?: "campus_forum";
$port        = (int)(getenv('MYSQL_PORT') ?: 3306);

$conn = new mysqli($servername, $username_db, $password_db, $dbname, $port);
if ($conn->connect_error) {
    die("数据库连接失败：" . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// 自动识别部署地址
$_proto   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_siteurl = getenv('SITE_URL') ?: ($_proto . '://' . $_host);

define('SITE_URL',               rtrim($_siteurl, '/'));
define('SITE_NAME',              '高校智慧交流论坛');
define('MAIL_FROM',              'noreply@example.com');
define('EMAIL_VERIFY_REQUIRED',  false);
define('DEEPSEEK_API_KEY',       getenv('DEEPSEEK_API_KEY') ?: '');

require_once __DIR__ . '/includes/db_init.php';
