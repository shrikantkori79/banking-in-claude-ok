<?php
require __DIR__ . '/inc/bootstrap.php';
require_perm('p_dateclose');
$me = user(); $date = $_SESSION['workdate'] ?? date('Y-m-d');
$day = row('SELECT * FROM daymas WHERE brcod=? AND workdate=?', [$me['brcod'], $date]);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (post('act') === 'start') {
        if ($day) flash('This day is already ' . strtolower($day['status']) . '.', 'err');
        else { q('INSERT INTO daymas (brcod,workdate,status,opened_by) VALUES (?,?,?,?)', [$me['brcod'], $date, 'OPEN', $me['id']]); flash('Day started.'); }
    } elseif (post('act') === 'end') {
        if (!$day || $day['status'] !== 'OPEN') flash('There is no open day to end.', 'err');
        else { q("UPDATE daymas SET status='CLOSED', closed_by=? WHERE id=?", [$me['id'], $day['id']]); flash('Day ended. Transactions for this date are locked.'); }
    }
    go('day.php');
}
page_start('Day start / day end');
?>
<div class="card" style="max-width:520px">
  <p>Branch <b><?= e($me['brcod']) ?></b>, working date <b><?= e(dmy($date)) ?></b>: <b><?= e($day['status'] ?? 'NOT STARTED') ?></b></p>
  <form method="post" class="actions" style="border:0;padding:0"><?= csrf_field() ?>
    <button class="primary" name="act" value="start" <?= $day ? 'disabled' : '' ?>>Day start</button>
    <button name="act" value="end" data-confirm="End the day for this branch? Entries for this date will be locked." <?= ($day['status'] ?? '') === 'OPEN' ? '' : 'disabled' ?>>Day end</button>
    <a class="btn" href="user_branch.php">Exit</a>
  </form>
</div>
<?php page_end();
