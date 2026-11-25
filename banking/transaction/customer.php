<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/lookups.php';
require __DIR__ . '/../inc/upload.php';
require_perm('p_account');
$isCo = ($_GET['type'] ?? '') === 'company';
$typeKey = $isCo ? 'company' : 'individual';
$work = $_SESSION['workdate'] ?? date('Y-m-d');
$me = user();

function valid_date(string $s): bool { $d = DateTime::createFromFormat('Y-m-d', $s); return $d && $d->format('Y-m-d') === $s; }
function maxlen(string $v, int $n, string $label): void { if (mb_strlen($v) > $n) throw new InvalidArgumentException("$label is too long (maximum $n characters)."); }
function phone(string $v): string {
    $v = preg_replace('/[\s-]/', '', $v);
    if ($v !== '' && !preg_match('/^\d{10}$/', $v)) throw new InvalidArgumentException('Mobile number must be 10 digits.');
    return $v;
}
function dob_check(string $v, string $who): string {
    if (!valid_date($v)) throw new InvalidArgumentException("Enter the date of birth for $who.");
    if ($v > date('Y-m-d')) throw new InvalidArgumentException('Date of birth cannot be in the future.');
    return $v;
}
$stored = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $join = post('joidat');
        if (!valid_date($join) || $join > date('Y-m-d')) throw new InvalidArgumentException('Enter a valid join date that is not in the future.');
        if ($join !== $work && !can('p_backdate')) throw new InvalidArgumentException('You do not have permission for back date entry.');
        $name = post('cusnam');
        if ($name === '') throw new InvalidArgumentException($isCo ? 'Enter the company name.' : 'Enter the customer name.');
        maxlen($name, 150, 'Name'); maxlen(post('cusnamkan'), 150, 'Kannada name');
        $d = ['custype' => $isCo ? 2 : 1, 'cusnam' => $name, 'cusnamkan' => post('cusnamkan'), 'joidat' => $join,
              'plaid' => 0, 'occid' => 0, 'quaid' => 0, 'priid' => 0, 'casid' => 0, 'gen' => 0, 'dob' => null,
              'brcod' => $me['brcod'], 'created_by' => $me['id']];
        if (!$isCo) {
            $d['plaid'] = lookup_check('place', post('plaid')); $d['occid'] = lookup_check('occ', post('occid')); $d['quaid'] = lookup_check('qual', post('quaid'));
            $d['priid'] = lookup_check('pri', post('priid')); $d['casid'] = lookup_check('caste', post('casid'));
            $d['gen'] = (int)post('gen') === 2 ? 2 : 1;
            $d['dob'] = dob_check(post('dob'), 'the customer');
        }
        $d['mobnum'] = phone(post('mobnum'));
        $em = post('emaid');
        if ($em !== '' && !filter_var($em, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid e-mail address.');
        $d['emaid'] = $em; maxlen($em, 100, 'E-mail');
        foreach (['add1', 'add2', 'add3'] as $a) { $d[$a] = post($a); maxlen($d[$a], 100, 'Address'); }
        $d['nom'] = post('nom'); maxlen($d['nom'], 100, 'Nominee'); $d['rel'] = post('rel'); maxlen($d['rel'], 50, 'Relation');
        $intro = post('introducer'); $d['intnum'] = 0;
        if ($intro !== '') {
            if (!preg_match('/^(\d+)/', $intro, $m) || !row('SELECT cusid FROM newcustomer WHERE cusid=?', [(int)$m[1]])) throw new InvalidArgumentException('Introducer must be an existing customer. Pick one from the suggestions or leave it blank.');
            $d['intnum'] = (int)$m[1];
        }
        $dir = post('dirid'); $d['dirid'] = 0;
        if ($dir !== '') {
            if (!ctype_digit($dir) || !row('SELECT id FROM director WHERE id=?', [(int)$dir])) throw new InvalidArgumentException('Choose the director from the list.');
            $d['dirid'] = (int)$dir;
        }
        $kycs = [];
        foreach (($_POST['kyc'] ?? []) as $r) {
            if (!is_array($r)) continue;
            $kid = trim((string)($r['kycid'] ?? '')); $det = trim((string)($r['kycdet'] ?? '')); $exp = trim((string)($r['expdat'] ?? ''));
            if ($kid === '' && $det === '' && $exp === '') continue;
            if ($kid === '' || $det === '') throw new InvalidArgumentException('Each KYC row needs a KYC type and its number.');
            maxlen($det, 60, 'KYC number');
            if ($exp !== '' && !valid_date($exp)) throw new InvalidArgumentException('Check the KYC expiry date.');
            $kycs[] = ['kycid' => lookup_check('kyc', $kid), 'kycdet' => $det, 'expdat' => $exp ?: null];
        }
        // images are checked first so nothing is saved when one is bad
        $cphoto = check_image($_FILES['photo'] ?? null, 'photo'); $csign = check_image($_FILES['sign'] ?? null, 'signature');
        $dirs = [];
        if ($isCo) {
            foreach (($_POST['dir'] ?? []) as $i => $r) {
                if (!is_array($r)) continue;
                $dn = trim((string)($r['cusnam'] ?? ''));
                if ($dn === '') throw new InvalidArgumentException('Every director needs a name. Remove empty director blocks.');
                maxlen($dn, 150, 'Director name'); maxlen(trim((string)($r['cusnamkan'] ?? '')), 150, 'Director Kannada name');
                $mob = preg_replace('/[\s-]/', '', trim((string)($r['mobnum'] ?? '')));
                if ($mob !== '' && !preg_match('/^\d{10}$/', $mob)) throw new InvalidArgumentException("Mobile number of $dn must be 10 digits.");
                $dirs[] = ['idx' => (string)$i, 'cusnam' => $dn, 'cusnamkan' => trim((string)($r['cusnamkan'] ?? '')), 'mobnum' => $mob,
                    'plaid' => lookup_check('place', (string)($r['plaid'] ?? '')), 'quaid' => lookup_check('qual', (string)($r['quaid'] ?? '')), 'casid' => lookup_check('caste', (string)($r['casid'] ?? '')),
                    'priid' => lookup_check('pri', (string)($r['priid'] ?? '')), 'occid' => lookup_check('occ', (string)($r['occid'] ?? '')),
                    'gen' => (int)($r['gen'] ?? 1) === 2 ? 2 : 1, 'dob' => dob_check(trim((string)($r['dob'] ?? '')), $dn),
                    'DESI' => lookup_check('des', (string)($r['DESI'] ?? '')), 'traper' => isset($r['traper']) ? 1 : 0,
                    'ph' => check_image(file_at('dir_photo', (string)$i), "photo of $dn"), 'sg' => check_image(file_at('dir_sign', (string)$i), "signature of $dn")];
            }
            if (!$dirs) throw new InvalidArgumentException('Add at least one director for the company.');
        }
        if ($cphoto) $d['photo'] = $stored[] = store_image($_FILES['photo'], $cphoto, 'c');
        if ($csign) $d['sign'] = $stored[] = store_image($_FILES['sign'], $csign, 's');
        foreach ($dirs as &$r) {
            $r['photo'] = $r['ph'] ? ($stored[] = store_image(file_at('dir_photo', $r['idx']), $r['ph'], 'dp')) : null;
            $r['sign'] = $r['sg'] ? ($stored[] = store_image(file_at('dir_sign', $r['idx']), $r['sg'], 'ds')) : null;
        } unset($r);

        db()->beginTransaction();
        $cusid = upsert('newcustomer', null, $d);
        foreach ($kycs as $k) upsert('cuskyc', null, ['cusid' => $cusid] + $k);
        foreach ($dirs as $r) { unset($r['idx'], $r['ph'], $r['sg']); upsert('joicusdet', null, ['cusid' => $cusid] + $r); }
        db()->commit();
        flash("Customer $cusid saved: $name.");
        go("transaction/customer.php?type=$typeKey");
    } catch (InvalidArgumentException $ex) {
        foreach ($stored as $f) @unlink(__DIR__ . '/../uploads/cus/' . $f);
        flash($ex->getMessage(), 'err');
    } catch (Throwable $ex) {
        if (db()->inTransaction()) db()->rollBack();
        foreach ($stored as $f) @unlink(__DIR__ . '/../uploads/cus/' . $f);
        flash(fail_msg($ex, 'Could not save. Nothing was changed.'), 'err');
    }
}

$next = (int)row('SELECT COALESCE(MAX(cusid),0)+1 n FROM newcustomer')['n'];
$directors = rows("SELECT id, dirnam FROM director WHERE sta='A' AND (allbra=1 OR BRACOD=?) ORDER BY dirnam", [$me['brcod']]);
$o = fn(string $k, string $def = '') => e($_POST[$k] ?? $def);

function lk_field(string $label, string $name, string $key, string $val, string $id): void {
    $t = LOOKUPS[$key][3];
    echo '<div class="field"><label for="' . $id . '">' . e($label) . '</label><div class="sel"><select id="' . $id . '" name="' . e($name) . '" data-lk="' . $key . '" data-val="' . e($val) . '"></select>'
       . '<button type="button" class="plus" data-lk-add="' . $key . '" title="' . e($t) . '" aria-label="Add new ' . e($t) . '">+</button></div></div>';
}
function dir_block(string $i, array $v): void {
    $p = fn($k) => e($v[$k] ?? ''); $n = "dir[$i]";
    ?><fieldset class="dirblock"><legend>Director</legend>
      <div class="field"><label>Director name</label><div class="pair"><input type="text" name="<?= $n ?>[cusnam]" value="<?= $p('cusnam') ?>" placeholder="English" maxlength="150" required><input type="text" lang="kn" name="<?= $n ?>[cusnamkan]" value="<?= $p('cusnamkan') ?>" placeholder="ಕನ್ನಡ" maxlength="150"></div></div>
      <div class="two"><div>
        <?php lk_field('Place', "{$n}[plaid]", 'place', $v['plaid'] ?? '', "d{$i}pl"); lk_field('Qualification', "{$n}[quaid]", 'qual', $v['quaid'] ?? '', "d{$i}qu"); lk_field('Caste', "{$n}[casid]", 'caste', $v['casid'] ?? '', "d{$i}ca"); ?>
        <div class="field"><label>Gender</label><div><label class="check"><input type="radio" name="<?= $n ?>[gen]" value="1" <?= (int)($v['gen'] ?? 1) !== 2 ? 'checked' : '' ?>>Male</label><label class="check"><input type="radio" name="<?= $n ?>[gen]" value="2" <?= (int)($v['gen'] ?? 1) === 2 ? 'checked' : '' ?>>Female</label></div></div>
      </div><div>
        <?php lk_field('Priority', "{$n}[priid]", 'pri', $v['priid'] ?? '', "d{$i}pr"); lk_field('Occupation', "{$n}[occid]", 'occ', $v['occid'] ?? '', "d{$i}oc"); ?>
        <div class="field"><label>Date of birth</label><input type="date" name="<?= $n ?>[dob]" value="<?= $p('dob') ?>" required></div>
        <div class="field"><label>Mobile no</label><input type="text" name="<?= $n ?>[mobnum]" value="<?= $p('mobnum') ?>" inputmode="numeric" maxlength="10"></div>
      </div></div>
      <?php lk_field('Designation', "{$n}[DESI]", 'des', $v['DESI'] ?? '', "d{$i}de"); ?>
      <label class="check"><input type="checkbox" name="<?= $n ?>[traper]" value="1" <?= !empty($v['traper']) ? 'checked' : '' ?>>Transaction permission</label>
      <div class="two"><div class="field"><label>Photo</label><input type="file" name="dir_photo[<?= $i ?>]" accept="image/jpeg,image/png"></div><div class="field"><label>Signature</label><input type="file" name="dir_sign[<?= $i ?>]" accept="image/jpeg,image/png"></div></div>
      <button type="button" class="danger" data-deldir>Remove this director</button>
    </fieldset><?php
}
$title = $isCo ? 'Create company account' : 'Create new customer';
page_start($title, 'txn');
$kycRows = array_values(array_filter($_POST['kyc'] ?? [], 'is_array'));
$dirRows = array_values(array_filter($_POST['dir'] ?? [], 'is_array'));
?>
<form method="post" enctype="multipart/form-data" class="stack" autocomplete="off"><?= csrf_field() ?>
  <div class="card">
    <div class="two"><div>
      <div class="field"><label for="joidat">Join date</label><input type="date" id="joidat" name="joidat" value="<?= $o('joidat', $work) ?>" required></div>
      <div class="field"><label for="cid">CID</label><input type="text" id="cid" value="<?= $next ?>" readonly aria-describedby="cidnote"></div>
      <div class="field"><label for="cusnam"><?= $isCo ? 'Company name' : 'Customer name' ?></label><div class="pair"><input type="text" id="cusnam" name="cusnam" value="<?= $o('cusnam') ?>" placeholder="English" maxlength="150" required autofocus><input type="text" lang="kn" name="cusnamkan" value="<?= $o('cusnamkan') ?>" placeholder="ಕನ್ನಡ" maxlength="150"></div></div>
      <p class="muted" id="cidnote">The CID is given automatically when you save.</p>
    </div><div>
      <div class="field"><label for="photo">Photo</label><input type="file" id="photo" name="photo" accept="image/jpeg,image/png"></div>
      <div class="field"><label for="sign">Signature</label><input type="file" id="sign" name="sign" accept="image/jpeg,image/png"></div>
      <p class="muted">JPG or PNG, up to 1 MB each. Choose the files again if the form shows an error.</p>
    </div></div>
  </div>

  <?php if (!$isCo): ?><div class="card"><h2>Personal detail</h2><div class="two"><div>
    <?php lk_field('Place', 'plaid', 'place', $_POST['plaid'] ?? '', 'plaid'); lk_field('Qualification', 'quaid', 'qual', $_POST['quaid'] ?? '', 'quaid'); lk_field('Priority', 'priid', 'pri', $_POST['priid'] ?? '', 'priid'); ?>
    <div class="field"><label for="dob">D.O.B</label><input type="date" id="dob" name="dob" value="<?= $o('dob') ?>" required></div>
  </div><div>
    <?php lk_field('Caste', 'casid', 'caste', $_POST['casid'] ?? '', 'casid'); lk_field('Occupation', 'occid', 'occ', $_POST['occid'] ?? '', 'occid'); ?>
    <div class="field"><label>Gender</label><div><label class="check"><input type="radio" name="gen" value="1" <?= (int)($_POST['gen'] ?? 1) !== 2 ? 'checked' : '' ?>>Male</label><label class="check"><input type="radio" name="gen" value="2" <?= (int)($_POST['gen'] ?? 1) === 2 ? 'checked' : '' ?>>Female</label></div></div>
  </div></div></div><?php endif ?>

  <div class="card"><h2>Contact detail</h2><div class="two"><div>
    <div class="field"><label for="mobnum">Contact no</label><input type="text" id="mobnum" name="mobnum" value="<?= $o('mobnum') ?>" inputmode="numeric" maxlength="10"></div>
    <div class="field"><label for="emaid">E-mail ID</label><input type="email" id="emaid" name="emaid" value="<?= $o('emaid') ?>" maxlength="100"></div>
  </div><div>
    <div class="field"><label for="add1">Address</label><input type="text" id="add1" name="add1" value="<?= $o('add1') ?>" maxlength="100"></div>
    <div class="field"><label for="add2"></label><input type="text" id="add2" name="add2" value="<?= $o('add2') ?>" maxlength="100" aria-label="Address line 2"></div>
    <div class="field"><label for="add3"></label><input type="text" id="add3" name="add3" value="<?= $o('add3') ?>" maxlength="100" aria-label="Address line 3"></div>
  </div></div></div>

  <div class="card"><h2>Introducer detail</h2><div class="two"><div>
    <div class="field"><label for="introducer">Introducer no</label><input type="text" id="introducer" name="introducer" list="introlist" value="<?= $o('introducer') ?>" placeholder="Type a customer number or name; blank = none"><datalist id="introlist"></datalist></div>
    <div class="field"><label for="nom">Nominee</label><input type="text" id="nom" name="nom" value="<?= $o('nom') ?>" maxlength="100"></div>
  </div><div>
    <div class="field"><label for="rel">Relation</label><input type="text" id="rel" name="rel" value="<?= $o('rel') ?>" maxlength="50"></div>
    <div class="field"><label for="dirid">Director</label><select id="dirid" name="dirid"><option value="">None</option>
      <?php foreach ($directors as $r): ?><option value="<?= (int)$r['id'] ?>" <?= (string)($_POST['dirid'] ?? '') === (string)$r['id'] ? 'selected' : '' ?>><?= e($r['dirnam']) ?></option><?php endforeach ?></select></div>
  </div></div></div>

  <div class="card"><h2>KYC information</h2>
    <div data-rows><table class="minigrid"><thead><tr><th>KYC</th><th>KYC detail</th><th>Exp date</th><th></th></tr></thead><tbody>
      <?php foreach ($kycRows as $n => $r): ?><tr><td><select name="kyc[e<?= $n ?>][kycid]" data-lk="kyc" data-val="<?= e($r['kycid'] ?? '') ?>" aria-label="KYC type"></select></td>
        <td><input type="text" name="kyc[e<?= $n ?>][kycdet]" value="<?= e($r['kycdet'] ?? '') ?>" maxlength="60" aria-label="KYC number"></td><td><input type="date" name="kyc[e<?= $n ?>][expdat]" value="<?= e($r['expdat'] ?? '') ?>" aria-label="Expiry date"></td>
        <td><button type="button" data-del class="danger">Remove</button></td></tr><?php endforeach ?>
    </tbody></table>
    <template><tr><td><select name="kyc[__i__][kycid]" data-lk="kyc" data-val="" aria-label="KYC type"></select></td><td><input type="text" name="kyc[__i__][kycdet]" maxlength="60" aria-label="KYC number"></td><td><input type="date" name="kyc[__i__][expdat]" aria-label="Expiry date"></td><td><button type="button" data-del class="danger">Remove</button></td></tr></template>
    <button type="button" data-add style="margin-top:8px">Add KYC row</button> <button type="button" class="plus" data-lk-add="kyc" data-no-target title="KYC type" aria-label="Add new KYC type">+ New KYC type</button></div>
  </div>

  <?php if ($isCo): ?><div class="card"><h2>Enter director list</h2>
    <div id="dirs"><?php foreach ($dirRows as $n => $r) dir_block('e' . $n, $r); ?></div>
    <template id="dirtpl"><?php dir_block('__i__', []); ?></template>
    <button type="button" id="adddir">Add director</button></div><?php endif ?>

  <div class="card"><div class="actions" style="margin:0;padding:0;border:0"><button class="primary">Save</button><a class="btn" href="customer.php?type=<?= $typeKey ?>">Refresh</a><a class="btn" href="index.php">Exit</a></div></div>
</form>

<dialog id="lkdlg"><form id="lkform"><h2>Add new</h2>
  <div class="field"><label for="lkname">Name</label><input type="text" id="lkname" name="name" maxlength="100" required></div>
  <div class="field" id="lkkanrow"><label for="lkkan">Kannada name</label><input type="text" id="lkkan" lang="kn" name="namekan" maxlength="100"></div>
  <p class="err" role="alert"></p>
  <div class="actions"><button class="primary">Add</button><button type="button" id="lkcancel">Cancel</button></div></form></dialog>
<script>window.LKDATA = <?= lookups_json() ?>; window.LKURL = <?= json_encode(BASE_URL . '/transaction/lookup_add.php') ?>; window.CUSURL = <?= json_encode(BASE_URL . '/transaction/customer_lookup.php') ?>; window.CSRF = <?= json_encode(csrf()) ?>; window.NEEDDIR = <?= $isCo && !$dirRows ? 'true' : 'false' ?>;</script>
<script src="<?= asset('rows.js') ?>"></script>
<script src="<?= asset('customer.js') ?>"></script>
<?php page_end();
