<?php
require __DIR__ . '/../inc/bootstrap.php';
require_perm('p_master');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    if (post('act') === 'new_main') {
        if (post('n_name') === '') flash('Enter the main head name.', 'err');
        elseif (row('SELECT id FROM prolosmaimas WHERE maihea=?', [post('n_name')])) flash('This main head already exists.', 'err');
        else { upsert('prolosmaimas', null, ['maihea' => post('n_name'), 'maiheakan' => post('n_namekan')]); flash('Main head created.'); }
    } else {
        $mai = (int)post('maiid');
        if (!row('SELECT id FROM prolosmaimas WHERE id=?', [$mai]) || post('name') === '') flash('Select a main head and enter the account name.', 'err');
        elseif (row('SELECT id FROM prolosmas WHERE heaid=? AND accnam=? AND id<>?', [$mai, post('name'), $id])) flash('This account name already exists under the main head.', 'err');
        else { upsert('prolosmas', $id ?: null, ['heaid' => $mai, 'accnam' => post('name'), 'accnamkan' => post('namekan')]); flash('Account name saved.'); }
    }
    go('master/pl_account.php');
}
$cur = ($i = (int)($_GET['id'] ?? 0)) ? row('SELECT * FROM prolosmas WHERE id=?', [$i]) : null;
$mains = rows('SELECT id, maihea AS name FROM prolosmaimas ORDER BY maihea');
$list = rows('SELECT a.id, a.heaid, a.accnam AS name, a.accnamkan AS namekan, m.maihea AS mname FROM prolosmas a JOIN prolosmaimas m ON m.id=a.heaid ORDER BY m.maihea, a.accnam');
page_start('Create new profit loss heads', 'master');
?>
<div class="layout">
  <div>
  <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($cur['id'] ?? 0) ?>">
    <div class="field"><label for="maiid">Main head</label><select id="maiid" name="maiid"><option value="">Select main head</option><?php foreach ($mains as $m): ?><option value="<?= (int)$m['id'] ?>" <?= (int)($cur['heaid'] ?? 0) === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach ?></select></div>
    <div class="field"><label for="name">Account name</label><div class="pair"><input type="text" id="name" name="name" placeholder="English" value="<?= e($cur['accnam'] ?? '') ?>" required><input type="text" lang="kn" name="namekan" placeholder="ಕನ್ನಡ" value="<?= e($cur['accnamkan'] ?? '') ?>"></div></div>
    <div class="actions"><button class="primary">Save</button><a class="btn" href="pl_account.php">Refresh</a><a class="btn" href="index.php">Exit</a></div>
  </form>
  <details class="card" style="margin-top:14px"><summary>Create a new main head</summary>
    <form method="post" style="margin-top:12px"><?= csrf_field() ?><input type="hidden" name="act" value="new_main">
      <div class="field"><label>Name</label><div class="pair"><input type="text" name="n_name" placeholder="English"><input type="text" lang="kn" name="n_namekan" placeholder="ಕನ್ನಡ"></div></div>
      <button>Create main head</button></form></details>
  </div>
  <div class="tablewrap"><table class="grid"><tr><th>Main head</th><th>Account name</th><th></th></tr>
    <?php foreach ($list as $r): ?><tr><td><?= e($r['mname']) ?></td><td><?= e($r['name']) ?><?= $r['namekan'] ? ' <span class="muted" lang="kn">' . e($r['namekan']) . '</span>' : '' ?></td><td><a href="pl_account.php?id=<?= (int)$r['id'] ?>">Edit</a></td></tr>
    <?php endforeach; if (!$list) echo '<tr><td colspan="3" class="empty">No account names yet.</td></tr>'; ?></table></div>
</div>
<?php page_end();
