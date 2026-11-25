<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/loans.php';
require_login();
header('Content-Type: application/json; charset=utf-8');
json_exceptions();
function out(array $d, int $code = 200): never { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
if (!can('p_account') && !can('p_transaction')) out(['error' => 'Not allowed.'], 403);
$t = (string)($_GET['t'] ?? ''); $l = (string)($_GET['loaid'] ?? '');
if (!ctype_digit($t) || !isset(LOAN_TYPES[(int)$t]) || !ctype_digit($l)) out(['error' => 'Choose the type of loan and loan name.'], 400);
$t = (int)$t; $l = (int)$l;
if (!row("SELECT id FROM scheme_master WHERE id=? AND active=1 AND kind IN ('" . implode("','", LOAN_TYPES[$t]['kinds']) . "')", [$l])) out(['error' => 'Unknown loan name.'], 400);
$op = $_GET['op'] ?? '';
if ($op === 'nextno') out(['n' => peek_loanum($t, $l)]);
if ($op === 'deposits' && $t < 4) {
    $c = (string)($_GET['cus'] ?? '');
    out(['rows' => ctype_digit($c) && $c !== '0' ? loan_deposits($t, $l, (int)$c) : []]);
}
out(['error' => 'Unknown request.'], 400);
