<?php
require __DIR__ . '/inc/bootstrap.php';
require_perm('p_user');
$me = user();
$users = rows('SELECT id,username,mobile FROM users WHERE brcod=? ORDER BY username', [$me['brcod']]);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = row('SELECT * FROM users WHERE id=? AND brcod=?', [(int)$_POST['uid'], $me['brcod']]);
    $new = post('new_username'); $np = $_POST['new_password'] ?? '';
    if (!$u || !password_verify($_POST['old_password'] ?? '', $u['password_hash'])) flash('Old password is wrong.', 'err');
    elseif ($np !== '' && ($np !== ($_POST['confirm'] ?? '') || strlen($np) < 6)) flash('New password must be at least 6 characters and match the confirmation.', 'err');
    elseif ($new !== '' && $new !== $u['username'] && row('SELECT id FROM users WHERE brcod=? AND username=?', [$me['brcod'], $new])) flash('That user name is taken.', 'err');
    else {
        $d = [];
        if ($new !== '') $d['username'] = $new;
        if ($np !== '') $d['password_hash'] = password_hash($np, PASSWORD_DEFAULT);
        if (post('new_mobile') !== '') $d['mobile'] = post('new_mobile');
        if ($d) upsert('users', (int)$u['id'], $d);
        flash('User updated.');
    }
    go('user_edit.php');
}
page_start('Edit user');
?>
<form method="post" class="card" style="max-width:760px"><?= csrf_field() ?>
  <div class="layout" style="grid-template-columns:1fr 1fr">
    <div>
      <div class="field"><label for="uid">Old user name</label><select id="uid" name="uid"><?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>"><?= e($u['username']) ?></option><?php endforeach ?></select></div>
      <div class="field"><label for="old_password">Old password</label><input type="password" id="old_password" name="old_password" required></div>
    </div>
    <div>
      <div class="field"><label for="new_username">New user name</label><input type="text" id="new_username" name="new_username"></div>
      <div class="field"><label for="new_password">New password</label><input type="password" id="new_password" name="new_password" autocomplete="new-password"></div>
      <div class="field"><label for="confirm">Confirm password</label><input type="password" id="confirm" name="confirm" autocomplete="new-password"></div>
      <div class="field"><label for="new_mobile">New mobile</label><input type="text" id="new_mobile" name="new_mobile" inputmode="numeric" maxlength="10"></div>
    </div>
  </div>
  <p class="muted">Leave a "new" field empty to keep its current value.</p>
  <div class="actions"><button class="primary">Update user</button><a class="btn" href="user_branch.php">Exit</a></div>
</form>
<?php page_end();
