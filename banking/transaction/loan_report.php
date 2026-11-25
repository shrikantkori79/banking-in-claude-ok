<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/loans.php';
require_login();
if (!can('p_account') && !can('p_transaction')) require_perm('p_account');
$t = (string)($_GET['t'] ?? ''); $l = (int)($_GET['l'] ?? 0); $n = (int)($_GET['n'] ?? 0);
if (!ctype_digit($t) || !isset(LOAN_TYPES[(int)$t])) { http_response_code(404); exit('Loan not found.'); }
$t = (int)$t;
$loan = row('SELECT * FROM ' . loan_header_table($t) . ' WHERE typofloa=? AND loaid=? AND loanum=? AND cerno=1', [$t, $l, $n]);
if (!$loan) { http_response_code(404); exit('Loan not found.'); }
$sch = row('SELECT * FROM scheme_master WHERE id=?', [$l]);
$cus = row('SELECT c.cusid, c.cusnam, c.mobnum, c.add1, c.add2, c.add3, p.planam FROM newcustomer c LEFT JOIN newpla p ON p.id=c.plaid WHERE c.cusid=?', [$loan['cusid']]);
$sur = rows('SELECT c.cusid, c.cusnam FROM newloa_sur_dir s JOIN newcustomer c ON c.cusid=s.surid WHERE s.typofloa=? AND s.loaid=? AND s.loanum=? AND s.surordir=1', [$t, $l, $n]);
$dir = rows('SELECT d.dirnam FROM newloa_sur_dir s JOIN director d ON d.id=s.dirid WHERE s.typofloa=? AND s.loaid=? AND s.loanum=? AND s.surordir=0', [$t, $l, $n]);
page_start('Loan ' . $n . ' - ' . $sch['name'], 'txn');
?>
<div class="noprint" style="display:flex;gap:10px;margin-bottom:14px"><button class="primary" onclick="window.print()">Print</button><a class="btn" href="loan_new.php">New loan</a><a class="btn" href="index.php">Exit</a></div>
<div class="card"><h2>Loan detail</h2><div class="two"><div>
  <p><b>Loan date:</b> <?= e(dmy($loan['loadat'])) ?></p><p><b>Type of loan:</b> <?= e(LOAN_TYPES[$t]['label']) ?></p><p><b>Loan name:</b> <?= e($sch['name']) ?></p><p><b>Loan no:</b> <?= $n ?></p>
</div><div>
  <p><b>Sanctioned loan:</b> <?= number_format((float)$loan['sanloa'], 2) ?></p><p><b>Paid by:</b> <?= $loan['paymode'] ? 'Contra' : 'Cash' ?></p><p><b>Status:</b> <?= $loan['clodat'] === LOAN_OPEN ? 'Open' : 'Closed ' . e(dmy($loan['clodat'])) ?></p>
</div></div></div>
<div class="card"><h2>Customer</h2><p><b><?= (int)$cus['cusid'] ?> - <?= e($cus['cusnam']) ?></b><?= $cus['planam'] ? ', ' . e($cus['planam']) : '' ?></p>
  <p class="muted"><?= e(trim(($cus['add1'] ?? '') . ' ' . ($cus['add2'] ?? '') . ' ' . ($cus['add3'] ?? ''))) ?> <?= e($cus['mobnum']) ?></p></div>
<?php if ($sur || $dir): ?><div class="card"><h2>Sureties and directors</h2>
  <?php if ($sur): ?><p><b>Sureties:</b> <?= e(implode(', ', array_map(fn($r) => $r['cusid'] . ' - ' . $r['cusnam'], $sur))) ?></p><?php endif ?>
  <?php if ($dir): ?><p><b>Directors:</b> <?= e(implode(', ', array_column($dir, 'dirnam'))) ?></p><?php endif ?></div><?php endif ?>
<?php if ($t === 4):
  $pl = array_column(rows('SELECT pledgeid FROM newloa_pledge WHERE typofloa=? AND loaid=? AND loanum=?', [$t, $l, $n]), 'pledgeid');
  $int = loan_interest($l, $loan['loadat']);
  $sc = loan_schedule((float)$loan['sanloa'], (int)$loan['instyp'], (int)$loan['noofins'], (int)$loan['dayas'], $loan['loadat']); ?>
  <?php if ($pl): ?><div class="card"><h2>Pledge</h2><p><?= e(implode(', ', array_map(fn($p) => LOAN_PLEDGE[$p] ?? '', $pl))) ?></p></div><?php endif ?>
  <div class="card"><h2>Interest</h2><div class="tablewrap"><table class="grid"><tr><th>Name</th><th>Type</th><th>Int %</th><th>Applicable after (days)</th></tr>
    <?php foreach ($int as $r): ?><tr><td><?= e($r['intname']) ?></td><td><?= $r['inttyp'] === 2 ? 'Penal' : 'Simple' ?></td><td><?= number_format($r['intrate'], 2) ?></td><td><?= $r['app_after'] ?></td></tr><?php endforeach;
    if (!$int) echo '<tr><td colspan="4" class="empty">No interest set in the loan master.</td></tr>'; ?></table></div></div>
  <div class="card"><h2>Instalment detail</h2><p class="muted"><?= (int)$loan['noofins'] ?> instalments, <?= LOAN_INST[(int)$loan['instyp']] ?? '' ?>, after every <?= (int)$loan['dayas'] ?> <?= (int)$loan['instyp'] === 1 ? 'month(s)' : 'day(s)' ?>. Interest is charged separately.</p>
    <div class="tablewrap"><table class="grid"><tr><th>No</th><th>Due date</th><th>Instalment</th></tr>
    <?php foreach ($sc['rows'] as $r): ?><tr><td><?= $r['no'] ?></td><td><?= e(dmy($r['date'])) ?></td><td><?= number_format($r['amount'], 2) ?></td></tr><?php endforeach ?></table></div></div>
<?php else:
  $deps = rows('SELECT depnum, depagenum, depcerno FROM newloa_dep_list WHERE typofloa=? AND loaid=? AND loanum=?', [$t, $l, $n]); ?>
  <div class="card"><h2>Deposits pledged</h2><div class="tablewrap"><table class="grid"><tr><th>Acc no</th><th>Agent</th></tr>
    <?php foreach ($deps as $d): ?><tr><td><?= (int)$d['depnum'] ?></td><td><?= $d['depagenum'] ? (int)$d['depagenum'] : '' ?></td></tr><?php endforeach ?></table></div></div>
  <div class="card"><h2>Rates from the deposit master</h2><p>Loan %: <?= number_format((float)$sch['loan_pct'], 2) ?> &nbsp; Interest %: <?= number_format((float)$sch['loan_int'], 2) ?> &nbsp; Penal interest %: <?= number_format((float)$sch['loan_pen'], 2) ?></p></div>
<?php endif;
page_end();
