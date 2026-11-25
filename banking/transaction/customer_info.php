<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/accounts.php';
require_login();
header('Content-Type: application/json; charset=utf-8');
json_exceptions();
if (!can('p_account') && !can('p_transaction')) { http_response_code(403); echo '{"ok":false}'; exit; }
$asof = (string)($_GET['asof'] ?? '');
if (!valid_date($asof)) $asof = date('Y-m-d');
$c = row('SELECT cusid, custype, cusnam, dob, photo, sign FROM newcustomer WHERE cusid=?', [(int)($_GET['id'] ?? 0)]);
if (!$c) { echo '{"ok":false}'; exit; }
$u = fn($f) => $f ? BASE_URL . '/uploads/cus/' . rawurlencode($f) : '';
echo json_encode(['ok' => true, 'id' => (int)$c['cusid'], 'type' => (int)$c['custype'], 'name' => $c['cusnam'], 'dob' => $c['dob'],
    'age' => $c['dob'] ? (int)(new DateTime($c['dob']))->diff(new DateTime($asof))->y : null,
    'minor' => (int)$c['custype'] === 1 && is_minor($c['dob'], $asof), 'photo' => $u($c['photo']), 'sign' => $u($c['sign'])], JSON_UNESCAPED_UNICODE);
