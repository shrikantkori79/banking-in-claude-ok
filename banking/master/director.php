<?php
require __DIR__ . '/../inc/bootstrap.php';
require_perm('p_master');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (post('act') === 'add_des') {
        $n = post('des_name');
        if ($n !== '') { q('INSERT IGNORE INTO desmas (name) VALUES (?)', [$n]); flash('Designation added.'); } else flash('Type the designation first.', 'err');
        go('master/director.php' . ($_POST['id'] ? '?id=' . (int)$_POST['id'] : ''));
    }
    $id = (int)($_POST['id'] ?? 0);
    $all = isset($_POST['allbra']) ? 1 : 0;
    $bra = $all ? null : post('bracod');
    $end = post('enddat');
    if (post('dirnam') === '' || (!$all && $bra === '') || post('desid') === '') { flash('Enter the director name, designation and branch (or tick All branches).', 'err'); go('master/director.php' . ($id ? "?id=$id" : '')); }
    $mob = post('MOBNUM');
    if ($mob !== '' && !preg_match('/^\d{10}$/', $mob)) { flash('Mobile number must be 10 digits.', 'err'); go('master/director.php' . ($id ? "?id=$id" : '')); }
    $d = ['dirnam' => post('dirnam'), 'dirnamkan' => post('dirnamkan'), 'desid' => (int)post('desid'), 'JOIDAT' => post('JOIDAT') ?: null, 'ADDR' => post('ADDR'),
          'MOBNUM' => $mob, 'allbra' => $all, 'BRACOD' => $bra, 'enddat' => $end ?: null, 'sta' => $end ? 'C' : 'A'];
    upsert('director', $id ?: null, $d);
    flash('Director saved.'); go('master/director.php');
}
$cur = ($i = (int)($_GET['id'] ?? 0)) ? row('SELECT * FROM director WHERE id=?', [$i]) : null;
$branches = rows('SELECT brcod,name_en FROM branches ORDER BY brcod');
$des = rows('SELECT * FROM desmas ORDER BY name');
$list = rows('SELECT d.*, s.name AS des FROM director d LEFT JOIN desmas s ON s.id=d.desid ORDER BY d.sta, d.dirnam');
page_start('Director master', 'master');
?>
<div class="layout">
  <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($cur['id'] ?? 0) ?>">
    <div class="field"><label for="bracod">Branch name</label>
      <div><select id="bracod" name="bracod"><option value="">Select branch</option><?php foreach ($branches as $b): ?><option value="<?= e($b['brcod']) ?>" <?= ($cur['BRACOD'] ?? '') === $b['brcod'] ? 'selected' : '' ?>><?= e($b['brcod'] . '  ' . $b['name_en']) ?></option><?php endforeach ?></select>
      <label class="check"><input type="checkbox" name="allbra" value="1" data-all-branches="#bracod" <?= !empty($cur['allbra']) ? 'checked' : '' ?>>All branches</label></div></div>
    <div class="field"><label for="dirnam">Director name</label><div class="pair"><input type="text" id="dirnam" name="dirnam" placeholder="English" value="<?= e($cur['dirnam'] ?? '') ?>" required><input type="text" lang="kn" name="dirnamkan" placeholder="ಕನ್ನಡ" value="<?= e($cur['dirnamkan'] ?? '') ?>"></div></div>
    <div class="field"><label for="desid">Designation</label>
      <select id="desid" name="desid"><option value="">Select designation</option><?php foreach ($des as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)($cur['desid'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach ?></select></div>
    <div class="field"><label for="JOIDAT">Joining date</label><input type="date" id="JOIDAT" name="JOIDAT" value="<?= e($cur['JOIDAT'] ?? date('Y-m-d')) ?>"></div>
    <div class="field"><label for="ADDR">Address</label><textarea id="ADDR" name="ADDR"><?= e($cur['ADDR'] ?? '') ?></textarea></div>
    <div class="field"><label for="MOBNUM">Mobile no</label><input type="text" id="MOBNUM" name="MOBNUM" inputmode="numeric" maxlength="10" value="<?= e($cur['MOBNUM'] ?? '') ?>"></div>
    <?php if ($cur): ?><div class="field"><label for="enddat">Retired / end date</label><input type="date" id="enddat" name="enddat" value="<?= e($cur['enddat'] ?? '') ?>"></div><?php endif ?>
    <div class="actions"><button class="primary">Save</button><a class="btn" href="director.php">Refresh</a><a class="btn" href="index.php">Exit</a></div>
  </form>
  <div>
    <form method="post" class="card" style="margin-bottom:14px"><?= csrf_field() ?><input type="hidden" name="act" value="add_des"><input type="hidden" name="id" value="<?= (int)($cur['id'] ?? 0) ?>">
      <div class="field" style="margin:0"><label for="des_name">New designation</label><div class="with-btn"><input type="text" id="des_name" name="des_name"><button>Add</button></div></div></form>
    <div class="tablewrap"><table class="grid"><tr><th>Name</th><th>Designation</th><th>Joined</th><th>Branch</th><th>Mobile</th><th>Status</th><th></th></tr>
      <?php foreach ($list as $r): ?><tr><td><?= e($r['dirnam']) ?><?= $r['dirnamkan'] ? '<br><span class="muted" lang="kn">' . e($r['dirnamkan']) . '</span>' : '' ?></td><td><?= e($r['des']) ?></td><td><?= e(dmy($r['JOIDAT'])) ?></td>
        <td><?= $r['allbra'] ? 'All' : e($r['BRACOD']) ?></td><td><?= e($r['MOBNUM']) ?></td><td><?= $r['sta'] === 'A' ? 'Active' : 'Ended ' . e(dmy($r['enddat'])) ?></td><td><a href="director.php?id=<?= (int)$r['id'] ?>">Edit</a></td></tr>
      <?php endforeach; if (!$list) echo '<tr><td colspan="7" class="empty">No directors yet. Fill the form and save the first one.</td></tr>'; ?></table></div>
  </div>
</div>
<?php page_end();
