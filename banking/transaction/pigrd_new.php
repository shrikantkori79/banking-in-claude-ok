<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/accounts.php';
require __DIR__ . '/../inc/pigrd.php';
require_perm('p_account');
$me = user();
$work = $_SESSION['workdate'] ?? date('Y-m-d');
$schemes = pr_schemes();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $accdat = post('accdat');
        if (!valid_date($accdat) || $accdat > date('Y-m-d')) throw new InvalidArgumentException('Enter a valid account date that is not in the future.');
        if ($accdat !== $work && !can('p_backdate')) throw new InvalidArgumentException('You do not have permission for back date entry.');
        $hs = post('heaid');
        if (!ctype_digit($hs) || !isset(PR_HEADS[(int)$hs])) throw new InvalidArgumentException('Choose the head of account.');
        $h = (int)$hs;
        $sub = post('subheaid');
        if (!ctype_digit($sub) || !in_array((int)$sub, array_column($schemes[$h], 'id'), true)) throw new InvalidArgumentException('Choose the sub head from the list.');
        $sub = (int)$sub;
        $ag = post('agenum');
        if (!ctype_digit($ag) || !row('SELECT id FROM newpigage WHERE heaid=? AND subheaid=? AND agenum=? AND clodat=?', [$h, $sub, (int)$ag, AGENT_OPEN]))
            throw new InvalidArgumentException('Choose an agent. If there is none, add one with "New agent".');
        $ag = (int)$ag;
        preg_match('/^\s*(\d+)/', post('customer'), $m);
        $c = isset($m[1]) ? row('SELECT cusid, custype, cusnam, dob FROM newcustomer WHERE cusid=?', [(int)$m[1]]) : null;
        if (!$c) throw new InvalidArgumentException('Pick the customer from the suggestions.');
        $mac = post('macnam'); if ($mac === '') $mac = mb_substr(trim($c['cusnam']), 0, 12);
        if (mb_strlen($mac) > 30) throw new InvalidArgumentException('Mac name is too long (maximum 30 characters).');
        $nom = post('nom'); $rel = post('rel');
        if (mb_strlen($nom) > 100 || mb_strlen($rel) > 50) throw new InvalidArgumentException('Nominee or relation is too long.');
        $gid = 0; $grel = '';
        if ((int)$c['custype'] === 1 && is_minor($c['dob'], $accdat)) {
            preg_match('/^\s*(\d+)/', post('guardian'), $g);
            $gr = isset($g[1]) ? row('SELECT cusid, custype, dob FROM newcustomer WHERE cusid=?', [(int)$g[1]]) : null;
            if (!$gr || (int)$gr['custype'] !== 1 || (int)$gr['cusid'] === (int)$c['cusid'] || $gr['dob'] === null || is_minor($gr['dob'], $accdat))
                throw new InvalidArgumentException($c['cusnam'] . ' is a minor. Choose an adult customer as guardian.');
            $grel = post('gurrel');
            if ($grel === '' || mb_strlen($grel) > 50) throw new InvalidArgumentException('Enter the guardian relation.');
            $gid = (int)$gr['cusid'];
        }
        $matdat = null; $matamo = 0.0; $inst = null;
        if ($h === 0) {
            $matdat = post('matdat');
            if (!valid_date($matdat) || $matdat <= $accdat) throw new InvalidArgumentException('Maturity date must be after the account date.');
        } else {
            $amt = (float)post('insamo'); $typ = (int)post('instyp'); $n = (int)post('noofins'); $rate = (float)post('intper'); $off = (float)post('offper');
            if ($amt <= 0 || $amt > 99999999) throw new InvalidArgumentException('Enter the instalment amount.');
            if (!isset(INST_TYPES[$typ])) throw new InvalidArgumentException('Choose the instalment type.');
            if ($n < 1 || $n * INST_MONTHS[$typ] > 360) throw new InvalidArgumentException('Number of instalments must be at least 1 and the period at most 360 months.');
            if ($rate < 0 || $rate > 100 || $off < 0 || $off > 100) throw new InvalidArgumentException('Interest and offer % must be between 0 and 100.');
            $sch = rd_schedule($amt, $typ, $n, $rate + $off, $accdat);
            $matdat = $sch['matdat']; $matamo = $sch['matamo'];
            $inst = ['accdat' => $accdat, 'insamo' => $amt, 'instyp' => $typ, 'intper' => $rate, 'noofins' => $n, 'offid' => 0, 'offper' => $off];
        }
        db()->beginTransaction();
        $no = reserve_pigseq($h, $sub, $ag);
        $acc = ['heaid' => $h, 'subheaid' => $sub, 'agenum' => $ag, 'accnum' => $no, 'cusid' => (int)$c['cusid'], 'macnam' => $mac, 'accdat' => $accdat, 'matdat' => $matdat,
            'gurid' => $gid, 'nom' => $nom, 'rel' => $rel, 'gurrel' => $grel, 'useidcre' => $me['id'], 'brcod' => $me['brcod']];
        if ($h === 1) $acc['matamo'] = $matamo;
        upsert(pr_acc_table($h), null, $acc);
        if ($inst) upsert('rdinst', null, ['subheaid' => $sub, 'agenum' => $ag, 'accnum' => $no] + $inst);
        db()->commit();
        flash("Account $no created under agent $ag.");
        go('transaction/pigrd_new.php');
    } catch (InvalidArgumentException $ex) {
        flash($ex->getMessage(), 'err');
    } catch (Throwable $ex) {
        if (db()->inTransaction()) db()->rollBack();
        flash(fail_msg($ex, 'Could not save. Nothing was changed.'), 'err');
    }
}
$o = fn(string $k, string $d = '') => e($_POST[$k] ?? $d);
$isRd = (string)($_POST['heaid'] ?? '') === '1';
page_start('Create pigmy / RD account', 'txn');
?>
<form method="post" class="layout wide pr" id="prform" autocomplete="off"><?= csrf_field() ?>
<div class="stack">
  <div class="card"><h2>Accounting detail</h2><div class="two">
    <div><div class="field"><label for="accdat">Account date</label><input type="date" id="accdat" name="accdat" value="<?= $o('accdat', $work) ?>" required></div></div>
    <div><div class="field"><label for="matdat">Maturity date</label><input type="date" id="matdat" name="matdat" value="<?= $o('matdat') ?>"></div></div>
    <div><div class="field"><label for="heaid">Head of account</label><select id="heaid" name="heaid" required><option value="">Select</option>
      <?php foreach (PR_HEADS as $k => $d): ?><option value="<?= $k ?>" <?= (string)($_POST['heaid'] ?? '') === (string)$k ? 'selected' : '' ?>><?= e($d['label']) ?></option><?php endforeach ?></select></div></div>
    <div><div class="field"><label for="subheaid">Sub head</label><select id="subheaid" name="subheaid" required data-val="<?= $o('subheaid') ?>"><option value="">Select</option></select></div></div>
    <div><div class="field"><label for="agenum">Agent no / name</label><div class="sel"><select id="agenum" name="agenum" required data-val="<?= $o('agenum') ?>"><option value="">Select</option></select><button type="button" class="plus" id="newagent" title="Add an agent">New agent</button></div></div></div>
    <div><div class="field"><label for="accno">Account no</label><input type="text" id="accno" readonly aria-describedby="agnote"></div></div>
  </div><p class="muted" id="agnote">Account numbers start from 1 for each agent. The number is given when you save.</p></div>

  <div class="card"><h2>Customer detail</h2>
    <div class="field"><label for="cust">Customer ID</label><input type="text" id="cust" name="customer" list="custlist" value="<?= $o('customer') ?>" placeholder="Type a customer number or name"><datalist id="custlist"></datalist></div>
    <p class="muted" id="cinfo" aria-live="polite"></p>
    <div class="field"><label for="macnam">Mac name</label><input type="text" id="macnam" name="macnam" maxlength="30" value="<?= $o('macnam') ?>"></div>
    <div class="two"><div class="field"><label for="nom">Nominee</label><input type="text" id="nom" name="nom" maxlength="100" value="<?= $o('nom') ?>"></div><div class="field"><label for="rel">Relation</label><input type="text" id="rel" name="rel" maxlength="50" value="<?= $o('rel') ?>"></div></div>
    <div id="gurbox" hidden><h2>Guardian detail</h2><p class="muted">This customer is under 18, so a guardian is needed.</p>
      <div class="field"><label for="gur">Guardian ID</label><input type="text" id="gur" name="guardian" list="custlist" value="<?= $o('guardian') ?>" placeholder="Type a customer number or name"></div>
      <p class="muted" id="ginfo" aria-live="polite"></p>
      <div class="field"><label for="gurrel">Relation</label><input type="text" id="gurrel" name="gurrel" maxlength="50" value="<?= $o('gurrel') ?>"></div></div>
  </div>
  <div class="card"><div class="actions" style="margin:0;padding:0;border:0"><button class="primary">Save</button><a class="btn" href="pigrd_new.php">Refresh</a><a class="btn" href="index.php">Exit</a></div></div>
</div>

<aside class="stack" id="rdbox" <?= $isRd ? '' : 'hidden' ?>>
  <div class="card"><h2>Transactional detail</h2><div class="two compact"><div>
    <div class="field"><label for="insamo">Instalment amt</label><input type="number" id="insamo" name="insamo" min="1" step="0.01" value="<?= $o('insamo') ?>"></div>
    <div class="field"><label for="noofins">No of instalment</label><input type="number" id="noofins" name="noofins" min="1" step="1" value="<?= $o('noofins') ?>"></div>
    <div class="field"><label for="offper">Offer %</label><input type="number" id="offper" name="offper" min="0" max="100" step="0.01" value="<?= $o('offper', '0') ?>" aria-describedby="offnote"></div>
  </div><div>
    <div class="field"><label for="instyp">Instalment type</label><select id="instyp" name="instyp"><option value="">Select</option>
      <?php foreach (INST_TYPES as $k => $t): ?><option value="<?= $k ?>" <?= (string)($_POST['instyp'] ?? '') === (string)$k ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach ?></select></div>
    <div class="field"><label for="intper">Interest %</label><input type="number" id="intper" name="intper" min="0" max="100" step="0.01" value="<?= $o('intper') ?>"></div>
    <div class="field"><label for="matamo">Maturity amount</label><input type="text" id="matamo" readonly></div>
  </div></div><p class="muted" id="offnote">Offer % is added to the interest rate.</p></div>
  <div class="card" id="schedcard"><h2>Instalment detail</h2>
    <div id="printhead"><p><b>Account name:</b> <span id="ph_name"></span></p><p><b>Acc no:</b> <span id="ph_no"></span></p></div>
    <div class="tablewrap"><table class="grid" id="sched"><thead><tr><th>Months</th><th>Dep amo</th><th>Total dep</th><th>Int earned</th><th>Maturity amt</th><th>Instalment date</th></tr></thead><tbody></tbody></table></div>
    <button type="button" id="printsched" style="margin-top:10px">Print instalment</button></div>
</aside>
</form>

<dialog id="agdlg"><form id="agform"><h2>New agent</h2>
  <p class="muted">An agent is an existing customer. Create the customer first if needed.</p>
  <div class="field"><label for="agcust">Customer</label><input type="text" id="agcust" name="customer" list="custlist" placeholder="Type a customer number or name" required></div>
  <p class="err" role="alert"></p>
  <div class="actions"><button class="primary">Add agent</button><button type="button" id="agcancel">Cancel</button></div></form></dialog>
<script>window.SCH = <?= json_encode($schemes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>; window.CSRF = <?= json_encode(csrf()) ?>;
window.URLS = <?= json_encode(['api' => BASE_URL . '/transaction/pigrd_api.php', 'lookup' => BASE_URL . '/transaction/customer_lookup.php', 'info' => BASE_URL . '/transaction/customer_info.php']) ?>;</script>
<script src="<?= asset('pigrd.js') ?>"></script>
<?php page_end();
