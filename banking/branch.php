<?php
require __DIR__ . '/inc/bootstrap.php';
require_perm('p_branch');
$new = isset($_GET['new']);
$branches = rows('SELECT * FROM branches ORDER BY brcod');
$id = $new ? 0 : (int)($_GET['id'] ?? 0);
$cur = null; foreach ($branches as $b) if ((int)$b['id'] === $id) $cur = $b;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $bid = (int)($_POST['id'] ?? 0);
    $code = post('brcod');
    $d = ['name_en' => post('name_en'), 'name_kn' => post('name_kn'), 'incharge' => post('incharge'), 'place' => post('place'), 'contact' => post('contact'), 'address' => post('address'),
          'voucher_print' => isset($_POST['voucher_print']) ? 1 : 0, 'passing_perm' => isset($_POST['passing_perm']) ? 1 : 0, 'otp_login' => isset($_POST['otp_login']) ? 1 : 0];
    if ($d['name_en'] === '' || (!$bid && !preg_match('/^\d{1,5}$/', $code))) { flash('Enter a numeric branch code and the branch name.', 'err'); go($bid ? "branch.php?id=$bid" : 'branch.php?new=1'); }
    if (!$bid) {
        if (row('SELECT id FROM branches WHERE brcod=?', [$code])) { flash('Branch code already exists.', 'err'); go('branch.php?new=1'); }
        $d['brcod'] = $code;
    }
    $bid = upsert('branches', $bid ?: null, $d);
    flash('Branch saved.'); go("branch.php?id=$bid");
}
page_start($new ? 'Create new branch' : 'Edit branch');
?>
<form method="post" class="layout"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($cur['id'] ?? 0) ?>">
  <div class="card">
    <div class="field"><label for="brcod">Branch code</label><input type="text" id="brcod" name="brcod" value="<?= e($cur['brcod'] ?? '') ?>" <?= $cur ? 'readonly' : 'required autofocus' ?>></div>
    <div class="field"><label for="name_en">Branch name (English)</label><input type="text" id="name_en" name="name_en" value="<?= e($cur['name_en'] ?? '') ?>" required></div>
    <div class="field"><label for="name_kn">Branch name (Kannada)</label><input type="text" id="name_kn" lang="kn" name="name_kn" value="<?= e($cur['name_kn'] ?? '') ?>"></div>
    <div class="field"><label for="incharge">Incharge name</label><input type="text" id="incharge" name="incharge" value="<?= e($cur['incharge'] ?? '') ?>"></div>
    <div class="field"><label for="place">Place</label><input type="text" id="place" name="place" value="<?= e($cur['place'] ?? '') ?>"></div>
    <div class="field"><label for="contact">Contact number</label><input type="text" id="contact" name="contact" value="<?= e($cur['contact'] ?? '') ?>"></div>
    <div class="field"><label for="address">Address</label><textarea id="address" name="address"><?= e($cur['address'] ?? '') ?></textarea></div>
    <label class="check"><input type="checkbox" name="voucher_print" value="1" <?= !empty($cur['voucher_print']) ? 'checked' : '' ?>>Voucher print</label>
    <label class="check"><input type="checkbox" name="passing_perm" value="1" <?= !empty($cur['passing_perm']) ? 'checked' : '' ?>>Passing permission</label>
    <label class="check"><input type="checkbox" name="otp_login" value="1" <?= !empty($cur['otp_login']) ? 'checked' : '' ?>>2-step login (SMS code / authenticator app) for this branch</label>
    <div class="actions"><button class="primary">Save branch</button><a class="btn" href="user_branch.php">Exit</a></div>
  </div>
  <div class="tablewrap"><table class="grid"><tr><th>Code</th><th>Branch</th><th></th></tr>
    <?php foreach ($branches as $b): ?><tr><td><?= e($b['brcod']) ?></td><td><?= e($b['name_en']) ?></td><td><a href="branch.php?id=<?= (int)$b['id'] ?>">Edit</a></td></tr><?php endforeach ?></table></div>
</form>
<?php page_end();
