<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/accounts.php';
require __DIR__ . '/../inc/pigrd.php';
require_login();
header('Content-Type: application/json; charset=utf-8');
json_exceptions();
function out(array $d, int $code = 200): never { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
$h = (string)($_REQUEST['heaid'] ?? ''); $s = (string)($_REQUEST['subheaid'] ?? '');
if (!ctype_digit($h) || !isset(PR_HEADS[(int)$h]) || !ctype_digit($s)) out(['error' => 'Choose the head and sub head.'], 400);
$h = (int)$h; $s = (int)$s;
if (!row('SELECT id FROM scheme_master WHERE id=? AND kind=? AND active=1', [$s, PR_HEADS[$h]['kind']])) out(['error' => 'Unknown sub head.'], 400);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!can('p_account')) out(['error' => 'You do not have account creation permission.'], 403);
    csrf_check();
    if (!preg_match('/^\s*(\d+)/', (string)($_POST['customer'] ?? ''), $m)) out(['error' => 'Pick the agent from the customers list.'], 422);
    $c = row('SELECT cusid, cusnam FROM newcustomer WHERE cusid=?', [(int)$m[1]]);
    if (!$c) out(['error' => 'Customer not found.'], 422);
    if (row('SELECT id FROM newpigage WHERE heaid=? AND subheaid=? AND cusid=?', [$h, $s, $c['cusid']])) out(['error' => 'This customer is already an agent here.'], 409);
    try {
        db()->beginTransaction();
        $n = reserve_pigseq($h, $s, 0);
        upsert('newpigage', null, ['heaid' => $h, 'subheaid' => $s, 'agenum' => $n, 'cusid' => (int)$c['cusid'], 'joidat' => $_SESSION['workdate'] ?? date('Y-m-d'), 'brcod' => user()['brcod']]);
        db()->commit();
    } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); out(['error' => fail_msg($e, 'Could not add the agent.')], 500); }
    out(['agenum' => $n, 'name' => $c['cusnam']]);
}
$op = $_GET['op'] ?? '';
if ($op === 'agents') {
    out(array_map(fn($r) => ['agenum' => (int)$r['agenum'], 'name' => $r['cusnam']],
        rows("SELECT a.agenum, c.cusnam FROM newpigage a JOIN newcustomer c ON c.cusid=a.cusid WHERE a.heaid=? AND a.subheaid=? AND a.clodat=? ORDER BY a.agenum", [$h, $s, AGENT_OPEN])));
}
if ($op === 'next') {
    $a = (string)($_GET['agenum'] ?? '');
    out(['n' => ctype_digit($a) && (int)$a > 0 ? peek_pigseq($h, $s, (int)$a) : null]);
}
out(['error' => 'Unknown request.'], 400);
