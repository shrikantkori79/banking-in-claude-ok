<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/accounts.php';
require_login();
header('Content-Type: application/json');
json_exceptions();
$h = $_GET['heaid'] ?? ''; $s = $_GET['subheaid'] ?? '';
if (!ctype_digit((string)$h) || !isset(HEADS[(int)$h]) || !ctype_digit((string)$s)) { echo '{"n":null}'; exit; }
echo json_encode(['n' => peek_accnum((int)$h, (int)$s)]);
