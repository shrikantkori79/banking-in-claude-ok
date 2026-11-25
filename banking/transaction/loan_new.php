<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/loans.php';
require_perm('p_account');
$me = user();
$work = $_SESSION['workdate'] ?? date('Y-m-d');
$schemes = loan_schemes();
$directors = rows("SELECT id, dirnam FROM director WHERE sta='A' AND (allbra=1 OR BRACOD=?) ORDER BY dirnam", [$me['brcod']]);
$dirIds = array_map('intval', array_column($directors, 'id'));

function ids_in(string $key): array {
    $out = [];
    foreach ((array)($_POST[$key] ?? []) as $v) { $v = (int)$v; if ($v > 0 && !in_array($v, $out, true)) $out[] = $v; }
    return $out;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $date = post('loadat');
        if (!valid_date($date) || $date > date('Y-m-d')) throw new InvalidArgumentException('Enter a valid loan date that is not in the future.');
        if ($date !== $work && !can('p_backdate')) throw new InvalidArgumentException('You do not have permission for back date entry.');
        $ts = post('typofloa');
        if (!ctype_digit($ts) || !isset(LOAN_TYPES[(int)$ts])) throw new InvalidArgumentException('Choose the type of loan.');
        $t = (int)$ts;
        $sch = null; foreach ($schemes[$t] as $s) if ((string)$s['id'] === post('loaid')) $sch = $s;
        if (!$sch) throw new InvalidArgumentException('Choose the loan name.');
        $loaid = $sch['id'];
        preg_match('/^\s*(\d+)/', post('customer'), $m);
        $c = isset($m[1]) ? row('SELECT cusid, custype, cusnam, dob FROM newcustomer WHERE cusid=?', [(int)$m[1]]) : null;
        if (!$c) throw new InvalidArgumentException('Pick the customer from the suggestions.');
        if ((int)$c['custype'] === 1 && is_minor($c['dob'], $date)) throw new InvalidArgumentException($c['cusnam'] . ' is a minor and cannot take a loan.');
        $cus = (int)$c['cusid'];
        $pay = post('paymode') === '1' ? 1 : 0;

        $surs = ids_in('sur');
        foreach ($surs as $sid) {
            if ($sid === $cus) throw new InvalidArgumentException('The borrower cannot be a surety.');
            if (!row('SELECT cusid FROM newcustomer WHERE cusid=?', [$sid])) throw new InvalidArgumentException('A surety was not found.');
        }
        $dirs = ids_in('dirs');
        foreach ($dirs as $d) if (!in_array($d, $dirIds, true)) throw new InvalidArgumentException('Choose directors from the list.');
        $pledges = [];
        if ($t === 4) foreach (ids_in('pledge') as $p) {
            if (!isset(LOAN_PLEDGE[$p]) || ($sch['pledges'] && !in_array($p, $sch['pledges'], true))) throw new InvalidArgumentException('This pledge type is not allowed for this loan.');
            $pledges[] = $p;
        }
        $san = (float)str_replace(',', '', post('sanloa'));
        if ($san <= 0 || $san > 99999999) throw new InvalidArgumentException('Enter the sanctioned loan amount.');

        $deps = [];
        if ($t < 4) {
            $eligible = []; foreach (loan_deposits($t, $loaid, $cus) as $d) $eligible[$d['key']] = $d;
            foreach ((array)($_POST['dep'] ?? []) as $k) {
                if (!isset($eligible[$k])) throw new InvalidArgumentException('A selected deposit is closed, already pledged, or not this customer\'s.');
                $deps[$k] = $eligible[$k];
            }
            if (!$deps) throw new InvalidArgumentException('Select at least one deposit to pledge.');
            $bal = 0.0;
            foreach ($deps as $d) {
                if ($d['balance'] === null) throw new InvalidArgumentException('The balance of the selected deposit is not available yet. Deposit loans can be saved once deposit transactions are built.');
                $bal += $d['balance'];
            }
            $max = round($bal * $sch['loan_pct'] / 100, 2);
            if ($san > $max) throw new InvalidArgumentException('Sanctioned loan cannot be more than the maximum loan amount of ' . number_format($max, 2) . '.');
        } else {
            $ity = (int)post('instyp'); $n = (int)post('noofins'); $every = (int)post('dayas');
            if (!isset(LOAN_INST[$ity])) throw new InvalidArgumentException('Choose the instalment type.');
            if ($n < 1 || $n > 600) throw new InvalidArgumentException('Number of instalments must be between 1 and 600.');
            if ($every < 1 || $every > ($ity === 1 ? 120 : 365)) throw new InvalidArgumentException('Enter "After every" as ' . ($ity === 1 ? '1 to 120 months.' : '1 to 365 days.'));
            $sched = loan_schedule($san, $ity, $n, $every, $date);
            if ($sch['compulsory'] && !$pledges && !$surs && !$dirs) throw new InvalidArgumentException('This loan needs security. Choose a pledge type, or add a surety or a director.');
        }
        db()->beginTransaction();
        $no = reserve_loanum($t, $loaid);
        $key = ['typofloa' => $t, 'loaid' => $loaid, 'loanum' => $no, 'cerno' => 1];
        if ($t === 4) {
            upsert('newloa', null, $key + ['cusid' => $cus, 'sanloa' => $san, 'instyp' => $ity, 'dayas' => $every, 'noofins' => $n, 'insamo' => $sched['each'], 'gapins' => 0,
                'loadat' => $date, 'paymode' => $pay, 'brcod' => $me['brcod'], 'useidcre' => $me['id']]);
            foreach ($pledges as $p) upsert('newloa_pledge', null, $key + ['pledgeid' => $p]);
        } else {
            upsert('newloa_dep', null, $key + ['cusid' => $cus, 'sanloa' => $san, 'loadat' => $date, 'paymode' => $pay, 'brcod' => $me['brcod'], 'useidcre' => $me['id']]);
            foreach ($deps as $d) upsert('newloa_dep_list', null, ['typofloa' => $t, 'loaid' => $loaid, 'loanum' => $no, 'cerno' => 1, 'depnum' => $d['depnum'], 'depagenum' => $d['agenum'], 'depcerno' => $d['cerno']]);
        }
        foreach ($surs as $sid) upsert('newloa_sur_dir', null, $key + ['surid' => $sid, 'dirid' => 0, 'surordir' => 1]);
        foreach ($dirs as $d) upsert('newloa_sur_dir', null, $key + ['surid' => 0, 'dirid' => $d, 'surordir' => 0]);
        db()->commit();
        flash("Loan $no created.");
        go("transaction/loan_report.php?t=$t&l=$loaid&n=$no");
    } catch (InvalidArgumentException $ex) {
        flash($ex->getMessage(), 'err');
    } catch (Throwable $ex) {
        if (db()->inTransaction()) db()->rollBack();
        flash(fail_msg($ex, 'Could not save. Nothing was changed.'), 'err');
    }
}
// values kept after a failed save
$nameOf = fn(int $id) => (row('SELECT cusnam FROM newcustomer WHERE cusid=?', [$id])['cusnam'] ?? '');
$dirName = fn(int $id) => (row('SELECT dirnam FROM director WHERE id=?', [$id])['dirnam'] ?? '');
$sticky = ['type' => $_POST['typofloa'] ?? '', 'loaid' => $_POST['loaid'] ?? '', 'pay' => $_POST['paymode'] ?? '0',
    'sur' => array_map(fn($i) => ['id' => $i, 'name' => $nameOf($i)], ids_in('sur')), 'dirs' => array_map(fn($i) => ['id' => $i, 'name' => $dirName($i)], ids_in('dirs')),
    'pledge' => ids_in('pledge'), 'dep' => array_values((array)($_POST['dep'] ?? []))];
$o = fn(string $k, string $d = '') => e($_POST[$k] ?? $d);
page_start('Create new loan', 'txn');
?>
<form method="post" class="layout wide" id="loanform" autocomplete="off"><?= csrf_field() ?>
<div class="stack">
  <div class="card"><h2>Loan detail</h2><div class="two"><div>
    <div class="field"><label for="loadat">Loan date</label><input type="date" id="loadat" name="loadat" value="<?= $o('loadat', $work) ?>" required></div>
    <div class="field"><label for="typofloa">Type of loan</label><select id="typofloa" name="typofloa" required><option value="">Select</option>
      <?php foreach (LOAN_TYPES as $k => $d): ?><option value="<?= $k ?>" <?= (string)($_POST['typofloa'] ?? '') === (string)$k ? 'selected' : '' ?>><?= e($d['label']) ?></option><?php endforeach ?></select></div>
    <div class="field"><label for="loaid">Loan name</label><select id="loaid" name="loaid" required><option value="">Select</option></select></div>
  </div><div>
    <div class="field"><label for="loanno">Loan no</label><div class="pair"><input type="text" id="loanno" readonly aria-label="Loan number"><input type="text" value="1" readonly aria-label="Certificate number"></div></div>
    <div class="field"><label>Paid by</label><div><label class="check"><input type="radio" name="paymode" value="0" <?= ($_POST['paymode'] ?? '0') !== '1' ? 'checked' : '' ?>>Cash</label><label class="check"><input type="radio" name="paymode" value="1" <?= ($_POST['paymode'] ?? '0') === '1' ? 'checked' : '' ?>>Contra</label></div></div>
  </div></div></div>

  <div class="card"><h2>Customer detail</h2>
    <div class="field"><label for="cust">Customer ID</label><input type="text" id="cust" name="customer" list="custlist" value="<?= $o('customer') ?>" placeholder="Type a customer number or name"><datalist id="custlist"></datalist></div>
    <p class="muted" id="cinfo" aria-live="polite"></p></div>

  <div class="card"><h2>Loan surities</h2><div class="two"><div>
    <div class="field"><label for="surin">Surity ID <span id="surcount" class="muted">0</span></label><div class="with-btn"><input type="text" id="surin" list="custlist" placeholder="Customer number or name"><button type="button" id="addsur">Add</button></div></div>
    <p class="muted" id="surinfo" aria-live="polite"></p>
    <table class="minigrid" id="surtbl"><thead><tr><th>Cus ID</th><th>Name</th><th></th></tr></thead><tbody></tbody></table>
  </div><div>
    <div class="field"><label for="dirin">Director <span id="dircount" class="muted">0</span></label><div class="with-btn"><select id="dirin"><option value="">Select</option>
      <?php foreach ($directors as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['dirnam']) ?></option><?php endforeach ?></select><button type="button" id="adddir">Add</button></div></div>
    <table class="minigrid" id="dirtbl"><thead><tr><th>Name</th><th></th></tr></thead><tbody></tbody></table>
  </div></div></div>

  <div class="card"><h2>Fledge detail</h2><div class="sel" style="flex-wrap:wrap" id="pledgebtns">
    <?php foreach (LOAN_PLEDGE as $k => $n): ?><button type="button" class="pledgebtn" data-id="<?= $k ?>" aria-pressed="false"><?= e($n) ?></button><?php endforeach ?></div>
    <p class="muted" id="pledgenote">Pledge types follow the loan master. The detail forms for each pledge type are added later.</p><div id="pledgebox"></div></div>
  <div class="card"><div class="actions" style="margin:0;padding:0;border:0"><button class="primary">Save</button><a class="btn" href="loan_new.php">Refresh</a><a class="btn" href="index.php">Exit</a></div></div>
</div>

<aside class="stack">
  <div class="card" id="depcard" hidden><h2>Select deposit</h2>
    <div class="tablewrap"><table class="grid" id="deptbl"><thead><tr><th></th><th>Acc no</th><th>Agent</th><th>Balance</th><th>Deposit date</th><th>Maturity date</th><th>Int %</th></tr></thead><tbody></tbody></table></div>
    <p class="muted" id="depnote"></p></div>
  <div class="card" id="loancard"><h2>Loan detail</h2>
    <div class="field narrow"><label for="sanloa">Sanctioned loan</label><input type="number" id="sanloa" name="sanloa" min="1" step="0.01" value="<?= $o('sanloa') ?>"></div>
    <div id="depgrp" hidden>
      <div class="field narrow"><label for="selbal">Selected deposit</label><input type="text" id="selbal" readonly></div>
      <div class="field narrow"><label for="lpct">Loan %</label><input type="text" id="lpct" readonly></div>
      <div class="field narrow"><label for="lint">Int %</label><input type="text" id="lint" readonly></div>
      <div class="field narrow"><label for="lpen">Penal int %</label><input type="text" id="lpen" readonly></div>
      <div class="field narrow"><label for="maxloan">Max loan amount</label><input type="text" id="maxloan" readonly></div>
    </div>
    <div id="insgrp" hidden>
      <div class="field narrow"><label for="noofins">No of instalment</label><input type="number" id="noofins" name="noofins" min="1" max="600" value="<?= $o('noofins') ?>"></div>
      <div class="field narrow"><label for="instyp">Instalment type</label><select id="instyp" name="instyp"><option value="">Select</option>
        <?php foreach (LOAN_INST as $k => $t): ?><option value="<?= $k ?>" <?= (string)($_POST['instyp'] ?? '') === (string)$k ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach ?></select></div>
      <div class="field narrow"><label for="dayas" id="everylbl">After every</label><input type="number" id="dayas" name="dayas" min="1" value="<?= $o('dayas') ?>"></div>
      <div class="field narrow"><label for="insamo">Instalment amt</label><input type="text" id="insamo" readonly></div>
    </div></div>
  <div class="card" id="intcard" hidden><h2>Interest from the loan master</h2>
    <table class="minigrid" id="inttbl"><thead><tr><th>Name</th><th>Type</th><th>Int %</th><th>After (days)</th><th>From</th></tr></thead><tbody></tbody></table></div>
  <div class="card"><p class="muted">Voucher detail and the cash or contra posting start with the transaction phase. After saving, the loan details and instalment schedule open as a report you can print.</p></div>
</aside>
</form>
<script>window.LOANS = <?= json_encode($schemes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>; window.STICKY = <?= json_encode($sticky, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
window.URLS = <?= json_encode(['api' => BASE_URL . '/transaction/loan_api.php', 'lookup' => BASE_URL . '/transaction/customer_lookup.php', 'info' => BASE_URL . '/transaction/customer_info.php']) ?>;</script>
<script src="<?= asset('loan.js') ?>"></script>
<?php page_end();
