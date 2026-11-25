<?php
require __DIR__ . '/inc/bootstrap.php';
require_perm('p_user');
$me = user();
$users = rows('SELECT * FROM users WHERE brcod=? ORDER BY username', [$me['brcod']]);
$sel = (int)($_REQUEST['uid'] ?? ($users[0]['id'] ?? 0));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $d = [];
    foreach (PERMS as $k => $_) $d[$k] = isset($_POST[$k]) ? 1 : 0;
    if ($sel === (int)$me['id'] && !$d['p_user']) { flash('You cannot remove your own user-creation permission.', 'err'); go("permissions.php?uid=$sel"); }
    upsert('users', $sel, $d);
    if ($sel === (int)$me['id']) { $_SESSION['user'] = array_merge($me, $d); }
    flash('Permissions updated.'); go("permissions.php?uid=$sel");
}
$cur = null; foreach ($users as $u) if ((int)$u['id'] === $sel) $cur = $u;
page_start('Edit permissions');
?>
<form method="get" class="card" style="max-width:560px;margin-bottom:16px"><div class="field" style="margin:0"><label for="uid">User name</label>
  <select id="uid" name="uid" onchange="this.form.submit()"><?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>" <?= (int)$u['id'] === $sel ? 'selected' : '' ?>><?= e($u['username']) ?></option><?php endforeach ?></select></div></form>
<?php if ($cur): ?>
<form method="post" class="card" style="max-width:560px"><?= csrf_field() ?><input type="hidden" name="uid" value="<?= $sel ?>">
  <div class="perm-grid"><?php foreach (PERMS as $k => $label): ?><label class="check"><input type="checkbox" name="<?= $k ?>" value="1" <?= $cur[$k] ? 'checked' : '' ?>><?= e($label) ?></label><?php endforeach ?></div>
  <div class="actions"><button class="primary">Update permissions</button><a class="btn" href="user_branch.php">Exit</a></div>
</form>
<?php endif; page_end();
