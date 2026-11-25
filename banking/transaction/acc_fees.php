<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/accounts.php';
require_login();
header('Content-Type: application/json; charset=utf-8');
json_exceptions();
$h = $_GET['heaid'] ?? ''; $s = $_GET['subheaid'] ?? '';
if (!ctype_digit((string)$h) || !in_array((int)$h, [0, 1], true) || !ctype_digit((string)$s)) { echo '{"rows":[],"total":0}'; exit; }
echo json_encode(fee_lines((int)$s), JSON_UNESCAPED_UNICODE);
