<?php
require __DIR__ . '/../inc/bootstrap.php';
require_perm('p_master');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0); $n = post('name');
    if ($n === '') flash('Enter the main head name.', 'err');
    elseif (row('SELECT id FROM prolosmaimas WHERE maihea=? AND id<>?', [$n, $id])) flash('This main head already exists.', 'err');
    else { upsert('prolosmaimas', $id ?: null, ['maihea' => $n, 'maiheakan' => post('namekan')]); flash('Profit and loss main head saved.'); }
    go('master/pl_head.php');
}
$cur = ($i = (int)($_GET['id'] ?? 0)) ? row('SELECT * FROM prolosmaimas WHERE id=?', [$i]) : null;
$list = rows('SELECT id, maihea AS name, maiheakan AS namekan FROM prolosmaimas ORDER BY maihea');
page_start('Create new profit loss main head', 'master');
?>
<div class="layout">
  <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($cur['id'] ?? 0) ?>">
    <div class="field"><label for="name">Main head</label><div class="pair"><input type="text" id="name" name="name" placeholder="English" value="<?= e($cur['maihea'] ?? '') ?>" required autofocus><input type="text" lang="kn" name="namekan" placeholder="ಕನ್ನಡ" value="<?= e($cur['maiheakan'] ?? '') ?>"></div></div>
    <div class="actions"><button class="primary">Save</button><a class="btn" href="pl_head.php">Refresh</a><a class="btn" href="index.php">Exit</a></div>
  </form>
  <div class="tablewrap"><table class="grid"><tr><th>Main head</th><th></th></tr>
    <?php foreach ($list as $r): ?><tr><td><?= e($r['name']) ?><?= $r['namekan'] ? ' <span class="muted" lang="kn">' . e($r['namekan']) . '</span>' : '' ?></td><td><a href="pl_head.php?id=<?= (int)$r['id'] ?>">Edit</a></td></tr>
    <?php endforeach; if (!$list) echo '<tr><td colspan="2" class="empty">No main heads yet.</td></tr>'; ?></table></div>
</div>
<?php page_end();
