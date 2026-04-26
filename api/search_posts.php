<?php
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 1) { echo '[]'; exit; }

$q_esc = $conn->real_escape_string($q);
$res = $conn->query("SELECT p.id, p.title, u.username, s.name as section_name
    FROM posts p
    JOIN users u ON u.id = p.user_id
    JOIN sections s ON s.id = p.section_id
    WHERE p.status = 'published'
      AND (p.title LIKE '%$q_esc%' OR p.tags LIKE '%$q_esc%')
    ORDER BY p.created_at DESC
    LIMIT 10");

$out = [];
if ($res) while ($r = $res->fetch_assoc()) $out[] = $r;
echo json_encode($out, JSON_UNESCAPED_UNICODE);
