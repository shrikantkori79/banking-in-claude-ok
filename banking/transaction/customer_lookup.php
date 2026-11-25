<?php
require __DIR__ . '/../inc/bootstrap.php';
require_login();
header('Content-Type: application/json; charset=utf-8');
json_exceptions();
if (!can('p_account') && !can('p_transaction')) { http_response_code(403); echo '[]'; exit; }
$q = trim((string)($_GET['q'] ?? ''));
if ($q === '') { echo '[]'; exit; }
$like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
$r = rows('SELECT cusid, cusnam FROM newcustomer WHERE cusnam LIKE ? OR CAST(cusid AS CHAR) LIKE ? ORDER BY cusnam LIMIT 15', [$like, $q . '%']);
echo json_encode(array_map(fn($x) => $x['cusid'] . ' - ' . $x['cusnam'], $r), JSON_UNESCAPED_UNICODE);
