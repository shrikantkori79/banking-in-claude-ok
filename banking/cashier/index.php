<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/accounts.php';
require_login();
if (!can('p_cashier') && !can('p_passing')) require_perm('p_passing');
$me = user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    require_perm('p_passing');
    $id = (int)($_POST['id'] ?? 0); $act = post('act');
    try {
        db()->beginTransaction();
        $a = row('SELECT * FROM depacc_tmp WHERE id=? AND brcod=? FOR UPDATE', [$id, $me['brcod']]);
        if (!$a) throw new InvalidArgumentException('This account was already passed or removed.');
        if (MAKER_CHECKER_DIFFERENT_USER && (int)$a['useidcre'] === (int)$me['id']) throw new InvalidArgumentException('The user who created an account cannot pass it. Ask another user with passing permission.');
        $k = ['heaid' => $a['heaid'], 'subheaid' => $a['subheaid'], 'accnum' => $a['accnum']];
        if ($act === 'pass') {
            unset($a['id']);
            $a['sta'] = 3; $a['passed_by'] = $me['id']; $a['passed_at'] = date('Y-m-d H:i:s');
            upsert('depacc', null, $a);
            $hs = rows('SELECT * FROM depaccjoi_tmp WHERE heaid=? AND subheaid=? AND accnum=?', array_values($k));
            foreach ($hs as $h) { unset($h['id']); upsert('depaccjoi', null, $h); }
            $msg = 'Account ' . $k['accnum'] . ' passed.';
        } elseif ($act === 'reject') {
            $msg = 'Account ' . $k['accnum'] . ' rejected and removed. Its number is not reused.';
        } else throw new InvalidArgumentException('Unknown action.');
        q('DELETE FROM depaccjoi_tmp WHERE heaid=? AND subheaid=? AND accnum=?', array_values($k));
        q('DELETE FROM depacc_tmp WHERE id=?', [$id]);
        db()->commit();
        flash($msg);
    } catch (InvalidArgumentException $ex) {
        if (db()->inTransaction()) db()->rollBack();
        flash($ex->getMessage(), 'err');
    } catch (Throwable $ex) {
        if (db()->inTransaction()) db()->rollBack();
        flash(fail_msg($ex, 'Could not complete. Nothing was changed.'), 'err');
    }
    go('cashier/index.php');
}

$pend = rows("SELECT a.id, a.heaid, a.accnum, a.accdat, a.joi, a.useidcre, s.name AS sname, u.username AS maker,
        (SELECT GROUP_CONCAT(CONCAT(c.cusid,' - ',c.cusnam) SEPARATOR ', ') FROM depaccjoi_tmp j JOIN newcustomer c ON c.cusid=j.cusid WHERE j.heaid=a.heaid AND j.subheaid=a.subheaid AND j.accnum=a.accnum) AS holders
    FROM depacc_tmp a LEFT JOIN scheme_master s ON s.id=a.subheaid LEFT JOIN users u ON u.id=a.useidcre WHERE a.brcod=? ORDER BY a.id", [$me['brcod']]);
page_start('Cashier and passing');
?>
<h2 style="font-size:1.05rem">Accounts waiting to be passed</h2>
<div class="tablewrap"><table class="grid"><tr><th>Head</th><th>Sub head</th><th>Acc no</th><th>Date</th><th>Customers</th><th>Created by</th><th></th></tr>
<?php foreach ($pend as $r): $mine = (int)$r['useidcre'] === (int)$me['id']; ?>
<tr><td><?= e(HEADS[(int)$r['heaid']]['label'] ?? '') ?></td><td><?= e($r['sname']) ?></td><td><?= (int)$r['accnum'] ?></td><td><?= e(dmy($r['accdat'])) ?></td><td><?= e($r['holders']) ?><?= $r['joi'] ? ' (joint)' : '' ?></td><td><?= e($r['maker']) ?></td>
<td><?php if (can('p_passing')): ?><form method="post" style="display:flex;gap:6px"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
  <button class="primary" name="act" value="pass" <?= MAKER_CHECKER_DIFFERENT_USER && $mine ? 'disabled title="Another user must pass this account"' : '' ?>>Pass</button>
  <button class="danger" name="act" value="reject" data-confirm="Reject and remove this account?" <?= MAKER_CHECKER_DIFFERENT_USER && $mine ? 'disabled' : '' ?>>Reject</button></form><?php endif ?></td></tr>
<?php endforeach; if (!$pend) echo '<tr><td colspan="7" class="empty">Nothing is waiting.</td></tr>'; ?></table></div>
<p class="muted" style="margin-top:12px">Term and CC accounts stay here with status 2 (pending) until a different user passes them. Passing moves them to the main account tables with status 3 (completed).</p>
<p><a class="btn" href="<?= BASE_URL ?>/launcher.php">Back to main menu</a></p>
<?php page_end();
