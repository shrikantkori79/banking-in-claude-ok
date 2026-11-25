<?php
require __DIR__ . '/../inc/bootstrap.php';
require_perm('p_master');
const TYPES = [1 => 'Assets', 2 => 'Liabilities'];   // balmaimas.typ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0); $typ = (int)post('typ'); $n = post('maihea'); $ord = (int)post('pri_ord');
    if (!isset(TYPES[$typ]) || $n === '') flash('Choose the type of head and enter the main head name.', 'err');
    elseif ($ord < 0 || $ord > 9999) flash('Print order must be between 0 and 9999.', 'err');
    elseif (row('SELECT id FROM balmaimas WHERE maihea=? AND id<>?', [$n, $id])) flash('This main head already exists.', 'err');
    else { upsert('balmaimas', $id ?: null, ['typ' => $typ, 'maihea' => $n, 'maiheakan' => post('maiheakan'), 'pri_ord' => $ord]); flash('Main head saved.'); }
    go('master/bs_head.php');
}
$cur = ($i = (int)($_GET['id'] ?? 0)) ? row('SELECT * FROM balmaimas WHERE id=?', [$i]) : null;
$list = rows('SELECT * FROM balmaimas ORDER BY typ, pri_ord, maihea');
$nextOrd = (int)row('SELECT COALESCE(MAX(pri_ord),0)+1 p FROM balmaimas')['p'];
page_start('Balancesheet head master', 'master');
?>
<div class="layout">
  <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($cur['id'] ?? 0) ?>">
    <div class="field"><label for="typ">Type of head</label><select id="typ" name="typ"><option value="">Select</option>
      <?php foreach (TYPES as $k => $t): ?><option value="<?= $k ?>" <?= (int)($cur['typ'] ?? 0) === $k ? 'selected' : '' ?>><?= $t ?></option><?php endforeach ?></select></div>
    <div class="field"><label for="maihea">Main head</label><div class="pair"><input type="text" id="maihea" name="maihea" placeholder="English" value="<?= e($cur['maihea'] ?? '') ?>" required autofocus><input type="text" lang="kn" name="maiheakan" placeholder="ಕನ್ನಡ" value="<?= e($cur['maiheakan'] ?? '') ?>"></div></div>
    <div class="field"><label for="pri_ord">Print order</label><input type="number" min="0" max="9999" id="pri_ord" name="pri_ord" value="<?= e($cur['pri_ord'] ?? $nextOrd) ?>"></div>
    <div class="actions"><button class="primary">Save</button><a class="btn" href="bs_head.php">Refresh</a><a class="btn" href="index.php">Exit</a></div>
  </form>
  <div class="tablewrap"><table class="grid"><tr><th>Main head</th><th>Type</th><th>Order</th><th></th></tr>
    <?php foreach ($list as $r): ?><tr><td><?= e($r['maihea']) ?><?= $r['maiheakan'] ? ' <span class="muted" lang="kn">' . e($r['maiheakan']) . '</span>' : '' ?></td><td><?= e(TYPES[$r['typ']] ?? '') ?></td><td><?= (int)$r['pri_ord'] ?></td><td><a href="bs_head.php?id=<?= (int)$r['id'] ?>">Edit</a></td></tr>
    <?php endforeach; if (!$list) echo '<tr><td colspan="4" class="empty">No main heads yet.</td></tr>'; ?></table></div>
</div>
<?php page_end();
