<?php
require __DIR__ . '/../inc/bootstrap.php';
require_perm('p_master');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0); $mai = (int)post('maiid'); $n = post('name');
    if (!$mai || $n === '' || !row('SELECT id FROM balmaimas WHERE id=?', [$mai])) flash('Select a main head and enter the sub head name.', 'err');
    elseif (row('SELECT id FROM balsubmas WHERE maiid=? AND subhea=? AND id<>?', [$mai, $n, $id])) flash('This sub head already exists under the main head.', 'err');
    else { upsert('balsubmas', $id ?: null, ['maiid' => $mai, 'subhea' => $n, 'subheakan' => post('namekan')]); flash('Sub head saved.'); }
    go('master/bs_subhead.php');
}
$cur = ($i = (int)($_GET['id'] ?? 0)) ? row('SELECT * FROM balsubmas WHERE id=?', [$i]) : null;
$mains = rows('SELECT id, maihea AS name FROM balmaimas ORDER BY pri_ord, maihea');
$list = rows('SELECT s.id, s.maiid, s.subhea AS name, s.subheakan AS namekan, m.maihea AS mname FROM balsubmas s JOIN balmaimas m ON m.id=s.maiid ORDER BY m.maihea, s.subhea');
page_start('Balancesheet sub head', 'master');
?>
<div class="layout">
  <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($cur['id'] ?? 0) ?>">
    <div class="field"><label for="maiid">Main head</label><select id="maiid" name="maiid"><option value="">Select main head</option><?php foreach ($mains as $m): ?><option value="<?= (int)$m['id'] ?>" <?= (int)($cur['maiid'] ?? 0) === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach ?></select></div>
    <div class="field"><label for="name">Sub head</label><div class="pair"><input type="text" id="name" name="name" placeholder="English" value="<?= e($cur['subhea'] ?? '') ?>" required><input type="text" lang="kn" name="namekan" placeholder="ಕನ್ನಡ" value="<?= e($cur['subheakan'] ?? '') ?>"></div></div>
    <div class="actions"><button class="primary">Save</button><a class="btn" href="bs_subhead.php">Refresh</a><a class="btn" href="index.php">Exit</a></div>
  </form>
  <div class="tablewrap"><table class="grid"><tr><th>Main head</th><th>Sub head</th><th></th></tr>
    <?php foreach ($list as $r): ?><tr><td><?= e($r['mname']) ?></td><td><?= e($r['name']) ?><?= $r['namekan'] ? ' <span class="muted" lang="kn">' . e($r['namekan']) . '</span>' : '' ?></td><td><a href="bs_subhead.php?id=<?= (int)$r['id'] ?>">Edit</a></td></tr>
    <?php endforeach; if (!$list) echo '<tr><td colspan="3" class="empty">No sub heads yet. Pick a main head and add the first one.</td></tr>'; ?></table></div>
</div>
<?php page_end();
