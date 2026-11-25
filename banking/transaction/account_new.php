<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/accounts.php';
require_perm('p_account');
$me = user();
$work = $_SESSION['workdate'] ?? date('Y-m-d');

$schemes = [];
foreach (HEADS as $h => $def) $schemes[$h] = array_map(fn($r) => ['id' => (int)$r['id'], 'name' => $r['name']],
    rows("SELECT id, name FROM scheme_master WHERE active=1 AND kind IN ('" . implode("','", $def['kinds']) . "') ORDER BY name"));
$directors = rows("SELECT id, dirnam FROM director WHERE sta='A' AND (allbra=1 OR BRACOD=?) ORDER BY dirnam", [$me['brcod']]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $accdat = post('accdat');
        if (!valid_date($accdat) || $accdat > date('Y-m-d')) throw new InvalidArgumentException('Enter a valid account date that is not in the future.');
        if ($accdat !== $work && !can('p_backdate')) throw new InvalidArgumentException('You do not have permission for back date entry.');
        $hs = post('heaid');
        if (!ctype_digit($hs) || !isset(HEADS[(int)$hs])) throw new InvalidArgumentException('Choose the head of account.');
        $h = (int)$hs;
        $sub = post('subheaid');
        if (!ctype_digit($sub) || !in_array((int)$sub, array_column($schemes[$h], 'id'), true)) throw new InvalidArgumentException('Choose the sub head from the list.');
        $sub = (int)$sub;
        $joi = isset($_POST['joi']) ? 1 : 0; $typ = -1;
        if ($joi) {
            $t = post('typofjoi');
            if (!ctype_digit($t) || !isset(JOINT_TYPES[(int)$t])) throw new InvalidArgumentException('Choose the type of joint.');
            $typ = (int)$t;
        }
        $dirid = 0; $dr = post('dirid');
        if ($dr !== '') {
            if (!ctype_digit($dr) || !in_array((int)$dr, array_column($directors, 'id'), true)) throw new InvalidArgumentException('Choose the director from the list.');
            $dirid = (int)$dr;
        }
        $holders = []; $seen = [];
        foreach (($_POST['holder'] ?? []) as $r) {
            if (!is_array($r)) continue;
            $cid = (int)($r['cusid'] ?? 0);
            $c = $cid ? row('SELECT cusid, custype, cusnam, dob FROM newcustomer WHERE cusid=?', [$cid]) : null;
            if (!$c) throw new InvalidArgumentException('A selected customer was not found.');
            if (isset($seen[$cid])) throw new InvalidArgumentException('The same customer is added twice.');
            $seen[$cid] = 1;
            $nom = trim((string)($r['nom'] ?? '')); $rel = trim((string)($r['rel'] ?? ''));
            if (mb_strlen($nom) > 100 || mb_strlen($rel) > 50) throw new InvalidArgumentException('Nominee or relation is too long.');
            $gid = 0; $grel = '';
            if ((int)$c['custype'] === 1 && is_minor($c['dob'], $accdat)) {
                $gid = (int)($r['gurid'] ?? 0); $grel = trim((string)($r['gurrel'] ?? ''));
                $g = $gid ? row('SELECT cusid, custype, dob FROM newcustomer WHERE cusid=?', [$gid]) : null;
                if (!$g || (int)$g['custype'] !== 1 || $gid === $cid || is_minor($g['dob'], $accdat) || $g['dob'] === null)
                    throw new InvalidArgumentException($c['cusnam'] . ' is a minor. Choose an adult customer as guardian.');
                if ($grel === '' || mb_strlen($grel) > 50) throw new InvalidArgumentException('Enter the guardian relation for ' . $c['cusnam'] . '.');
            }
            $holders[] = ['cusid' => $cid, 'nom' => $nom, 'rel' => $rel, 'gurid' => $gid, 'gurrel' => $grel];
        }
        if ($joi && count($holders) < 2) throw new InvalidArgumentException('A joint account needs at least two customers.');
        if (!$joi && count($holders) !== 1) throw new InvalidArgumentException($holders ? 'Only one customer is allowed unless Joint is ticked.' : 'Add the customer to the list.');

        $pending = HEADS[$h]['pending'];
        $t = $pending ? 'depacc_tmp' : 'depacc'; $tj = $t . 'joi';
        db()->beginTransaction();
        $no = reserve_accnum($h, $sub);
        upsert($t, null, ['heaid' => $h, 'subheaid' => $sub, 'accnum' => $no, 'joi' => $joi, 'typofjoi' => $typ, 'entdatope' => $work, 'enttimope' => date('H:i:s'),
            'useidcre' => $me['id'], 'accdat' => $accdat, 'dirid' => $dirid, 'sta' => $pending ? 2 : 3, 'brcod' => $me['brcod']]);
        foreach ($holders as $r) upsert($tj, null, ['heaid' => $h, 'subheaid' => $sub, 'accnum' => $no] + $r);
        $feeTotal = 0.0;
        if (!$pending) foreach (fee_lines($sub)['rows'] as $f) {   // share and SB: apply the master's fees
            upsert('depaccfee', null, ['heaid' => $h, 'subheaid' => $sub, 'accnum' => $no, 'src' => $f['src'], 'maiid' => $f['maiid'], 'subid' => $f['subid'], 'accid' => $f['accid'], 'amount' => $f['amount']]);
            $feeTotal += $f['amount'];
        }
        db()->commit();
        flash('Account ' . $no . ' created' . ($pending ? ' and waiting to be passed.' : ($feeTotal > 0 ? '. Fees applied: ' . number_format($feeTotal, 2) . '.' : '.')));
        go('transaction/account_new.php');
    } catch (InvalidArgumentException $ex) {
        flash($ex->getMessage(), 'err');
    } catch (Throwable $ex) {
        if (db()->inTransaction()) db()->rollBack();
        flash(fail_msg($ex, 'Could not save. Nothing was changed.'), 'err');
    }
}

// rows kept after a failed save
$rows = [];
foreach (($_POST['holder'] ?? []) as $r) {
    if (!is_array($r)) continue;
    $c = row('SELECT cusnam FROM newcustomer WHERE cusid=?', [(int)($r['cusid'] ?? 0)]);
    $g = row('SELECT cusnam FROM newcustomer WHERE cusid=?', [(int)($r['gurid'] ?? 0)]);
    $rows[] = $r + ['cname' => $c['cusnam'] ?? '', 'gname' => $g['cusnam'] ?? ''];
}
$o = fn(string $k, string $d = '') => e($_POST[$k] ?? $d);
page_start('Create deposit account', 'txn');
?>
<form method="post" class="layout wide" id="accform" autocomplete="off"><?= csrf_field() ?>
<div class="stack">
  <div class="card"><h2>Accounting detail</h2><div class="two"><div>
    <div class="field"><label for="accdat">Account date</label><input type="date" id="accdat" name="accdat" value="<?= $o('accdat', $work) ?>" required></div>
    <div class="field"><label for="heaid">Head of account</label><select id="heaid" name="heaid" required><option value="">Select</option>
      <?php foreach (HEADS as $k => $def): ?><option value="<?= $k ?>" <?= (string)($_POST['heaid'] ?? '') === (string)$k ? 'selected' : '' ?>><?= e($def['label']) ?></option><?php endforeach ?></select></div>
    <div class="field"><label for="accno">Account no</label><input type="text" id="accno" readonly value="" aria-describedby="accnote"></div>
    <p class="muted" id="accnote">Given automatically when you save.</p>
  </div><div>
    <div class="field"><label for="subheaid">Sub head</label><select id="subheaid" name="subheaid" required data-val="<?= $o('subheaid') ?>"><option value="">Select</option></select></div>
    <div class="field"><label>Joint</label><label class="check"><input type="checkbox" id="joi" name="joi" value="1" <?= isset($_POST['joi']) ? 'checked' : '' ?>>Joint account</label></div>
    <div class="field" id="jointrow" hidden><label for="typofjoi">Type of joint</label><select id="typofjoi" name="typofjoi"><option value="">Select</option>
      <?php foreach (JOINT_TYPES as $k => $t): ?><option value="<?= $k ?>" <?= (string)($_POST['typofjoi'] ?? '') === (string)$k ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach ?></select></div>
    <div class="field"><label for="dirid">Director</label><select id="dirid" name="dirid"><option value="">None</option>
      <?php foreach ($directors as $r): ?><option value="<?= (int)$r['id'] ?>" <?= (string)($_POST['dirid'] ?? '') === (string)$r['id'] ? 'selected' : '' ?>><?= e($r['dirnam']) ?></option><?php endforeach ?></select></div>
  </div></div></div>

  <div class="card"><h2>Customer detail</h2>
    <div class="field"><label for="cust">Customer ID</label><input type="text" id="cust" list="custlist" placeholder="Type a customer number or name"><datalist id="custlist"></datalist></div>
    <p class="muted" id="cinfo" aria-live="polite"></p>
    <div class="two"><div class="field"><label for="nom">Nominee</label><input type="text" id="nom" maxlength="100"></div><div class="field"><label for="rel">Relation</label><input type="text" id="rel" maxlength="50"></div></div>
    <div id="gurbox" hidden><h2>Guardian detail</h2><p class="muted">This customer is under 18, so a guardian is needed.</p>
      <div class="field"><label for="gur">Guardian ID</label><input type="text" id="gur" list="custlist" placeholder="Type a customer number or name"></div>
      <p class="muted" id="ginfo" aria-live="polite"></p>
      <div class="field"><label for="gurrel">Relation</label><input type="text" id="gurrel" maxlength="50"></div></div>
    <p class="err" id="msg" role="alert"></p>
    <button type="button" id="addholder">Add to list</button>
  </div>

  <div class="card"><h2>Customers on this account</h2>
    <div class="tablewrap"><table class="grid" id="holders"><thead><tr><th>Sl</th><th>Cus ID</th><th>Name</th><th>Nominee</th><th>Relation</th><th>Gur ID</th><th>Guardian</th><th>Gur relation</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $n => $r): ?><tr><td class="sl"></td><td><input type="hidden" name="holder[e<?= $n ?>][cusid]" value="<?= (int)$r['cusid'] ?>"><input type="hidden" name="holder[e<?= $n ?>][nom]" value="<?= e($r['nom'] ?? '') ?>"><input type="hidden" name="holder[e<?= $n ?>][rel]" value="<?= e($r['rel'] ?? '') ?>"><input type="hidden" name="holder[e<?= $n ?>][gurid]" value="<?= (int)($r['gurid'] ?? 0) ?>"><input type="hidden" name="holder[e<?= $n ?>][gurrel]" value="<?= e($r['gurrel'] ?? '') ?>"><?= (int)$r['cusid'] ?></td>
      <td><?= e($r['cname']) ?></td><td><?= e($r['nom'] ?? '') ?></td><td><?= e($r['rel'] ?? '') ?></td><td><?= (int)($r['gurid'] ?? 0) ?: '' ?></td><td><?= e($r['gname']) ?></td><td><?= e($r['gurrel'] ?? '') ?></td><td><button type="button" class="danger" data-delrow>Remove</button></td></tr><?php endforeach ?>
    </tbody></table></div>
  </div>
  <div class="card"><div class="actions" style="margin:0;padding:0;border:0"><button class="primary">Save</button><a class="btn" href="account_new.php">Refresh</a><a class="btn" href="index.php">Exit</a></div></div>
</div>
<aside class="stack">
  <div class="card preview"><h2>Customer</h2><p class="muted" id="pname">No customer selected.</p><img id="pphoto" alt="Customer photo" hidden><img id="psign" alt="Customer signature" hidden></div>
  <div class="card"><h2>Voucher detail</h2>
    <div class="tablewrap"><table class="grid" id="feetbl"><thead><tr><th>Sl</th><th>Head</th><th>Head name</th><th>Amount</th></tr></thead><tbody></tbody>
      <tfoot><tr><td colspan="3"><b>Total</b></td><td id="feetotal"></td></tr></tfoot></table></div>
    <p class="muted" id="feenote"></p>
    <p class="muted">These fees come from the share or deposit master and are saved with the account. Receipt numbers and cash posting start with the transaction phase.</p></div>
</aside>
</form>
<script>window.SCH = <?= json_encode($schemes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
window.URLS = <?= json_encode(['lookup' => BASE_URL . '/transaction/customer_lookup.php', 'info' => BASE_URL . '/transaction/customer_info.php', 'next' => BASE_URL . '/transaction/acc_next.php', 'fees' => BASE_URL . '/transaction/acc_fees.php']) ?>;</script>
<script src="<?= asset('account.js') ?>"></script>
<?php page_end();
