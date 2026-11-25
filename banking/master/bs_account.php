<?php
require __DIR__ . '/../inc/bootstrap.php';
require_perm('p_master');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = post('act'); $id = (int)($_POST['id'] ?? 0);
    if ($act === 'new_main') {
        if (post('n_name') === '' || !in_array((int)post('n_type'), [1, 2], true)) flash('Enter the main head name and its type.', 'err');
        elseif (row('SELECT id FROM balmaimas WHERE maihea=?', [post('n_name')])) flash('This main head already exists.', 'err');
        else { upsert('balmaimas', null, ['typ' => (int)post('n_type'), 'maihea' => post('n_name'), 'maiheakan' => post('n_namekan'), 'pri_ord' => (int)row('SELECT COALESCE(MAX(pri_ord),0)+1 p FROM balmaimas')['p']]); flash('Main head created.'); }
    } elseif ($act === 'new_sub') {
        $mai = (int)post('n_maiid');
        if (!$mai || post('n_name') === '') flash('Select the main head above, then enter the sub head name.', 'err');
        elseif (row('SELECT id FROM balsubmas WHERE maiid=? AND subhea=?', [$mai, post('n_name')])) flash('This sub head already exists.', 'err');
        else { upsert('balsubmas', null, ['maiid' => $mai, 'subhea' => post('n_name'), 'subheakan' => post('n_namekan')]); flash('Sub head created.'); }
    } else {
        $mai = (int)post('maiid'); $sub = (int)post('subid');
        if (!row('SELECT id FROM balsubmas WHERE id=? AND maiid=?', [$sub, $mai]) || post('name') === '') flash('Select a main head, a sub head that belongs to it, and enter the account name.', 'err');
        elseif (row('SELECT id FROM balaccmas WHERE subid=? AND accnam=? AND id<>?', [$sub, post('name'), $id])) flash('This account name already exists under the sub head.', 'err');
        else { upsert('balaccmas', $id ?: null, ['maiid' => $mai, 'subid' => $sub, 'accnam' => post('name'), 'accnamkan' => post('namekan')]); flash('Account name saved.'); }
    }
    go('master/bs_account.php');
}
$cur = ($i = (int)($_GET['id'] ?? 0)) ? row('SELECT * FROM balaccmas WHERE id=?', [$i]) : null;
$mains = rows('SELECT id, maihea AS name FROM balmaimas ORDER BY pri_ord, maihea');
$subs = rows('SELECT id, maiid, subhea AS name FROM balsubmas ORDER BY subhea');
$list = rows('SELECT a.id, a.maiid, a.subid, a.accnam AS name, a.accnamkan AS namekan, m.maihea AS mname, s.subhea AS sname FROM balaccmas a JOIN balmaimas m ON m.id=a.maiid JOIN balsubmas s ON s.id=a.subid ORDER BY m.maihea, s.subhea, a.accnam');
page_start('Balancesheet heads and account names', 'master');
?>
<div class="layout">
  <div>
  <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($cur['id'] ?? 0) ?>">
    <div class="field"><label for="maiid">Main head</label><select id="maiid" name="maiid"><option value="">Select main head</option><?php foreach ($mains as $m): ?><option value="<?= (int)$m['id'] ?>" <?= (int)($cur['maiid'] ?? 0) === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach ?></select></div>
    <div class="field"><label for="subid">Sub head</label><select id="subid" name="subid" data-filter-by="#maiid"><option value="">Select sub head</option><?php foreach ($subs as $s): ?><option value="<?= (int)$s['id'] ?>" data-parent="<?= (int)$s['maiid'] ?>" <?= (int)($cur['subid'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach ?></select></div>
    <div class="field"><label for="name">Account name</label><div class="pair"><input type="text" id="name" name="name" placeholder="English" value="<?= e($cur['accnam'] ?? '') ?>" required><input type="text" lang="kn" name="namekan" placeholder="ಕನ್ನಡ" value="<?= e($cur['accnamkan'] ?? '') ?>"></div></div>
    <div class="actions"><button class="primary">Save</button><a class="btn" href="bs_account.php">Refresh</a><a class="btn" href="index.php">Exit</a></div>
  </form>
  <details class="card" style="margin-top:14px"><summary>Create a new main head</summary>
    <form method="post" style="margin-top:12px"><?= csrf_field() ?><input type="hidden" name="act" value="new_main">
      <div class="field"><label>Type</label><select name="n_type"><option value="1">Assets</option><option value="2">Liabilities</option></select></div>
      <div class="field"><label>Name</label><div class="pair"><input type="text" name="n_name" placeholder="English"><input type="text" lang="kn" name="n_namekan" placeholder="ಕನ್ನಡ"></div></div>
      <button>Create main head</button></form></details>
  <details class="card" style="margin-top:14px"><summary>Create a new sub head</summary>
    <form method="post" style="margin-top:12px"><?= csrf_field() ?><input type="hidden" name="act" value="new_sub">
      <div class="field"><label>Main head</label><select name="n_maiid"><option value="">Select</option><?php foreach ($mains as $m): ?><option value="<?= (int)$m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach ?></select></div>
      <div class="field"><label>Name</label><div class="pair"><input type="text" name="n_name" placeholder="English"><input type="text" lang="kn" name="n_namekan" placeholder="ಕನ್ನಡ"></div></div>
      <button>Create sub head</button></form></details>
  </div>
  <div class="tablewrap"><table class="grid"><tr><th>Main head</th><th>Sub head</th><th>Account name</th><th></th></tr>
    <?php foreach ($list as $r): ?><tr><td><?= e($r['mname']) ?></td><td><?= e($r['sname']) ?></td><td><?= e($r['name']) ?><?= $r['namekan'] ? ' <span class="muted" lang="kn">' . e($r['namekan']) . '</span>' : '' ?></td><td><a href="bs_account.php?id=<?= (int)$r['id'] ?>">Edit</a></td></tr>
    <?php endforeach; if (!$list) echo '<tr><td colspan="4" class="empty">No account names yet.</td></tr>'; ?></table></div>
</div>
<?php page_end();
