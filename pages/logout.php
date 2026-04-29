<?php
/*
 * pages/logout.php — 退出登录
 * 功能：销毁 Session，重定向到首页。
 * 权限：无需登录
 */
if (session_status() === PHP_SESSION_NONE) session_start();
session_destroy();
header('Location: ../index.php'); exit;
