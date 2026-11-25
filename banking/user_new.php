<?php
require __DIR__ . '/inc/bootstrap.php';
require_perm('p_user');
$me = user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = post('username'); $pw = $_POST['password'] ?? '';
    if ($name === '' || strlen($pw) < 6) flash('Enter a user name and a password of at least 6 characters.', 'err');
    elseif ($pw !== ($_POST['confirm'] ?? '')) flash('Password and confirm password are different.', 'err');
    elseif (row('SELECT id FROM users WHERE brcod=? AND username=?', [$me['brcod'], $name])) flash('This user name already exists in the branch.', 'err');
    else {
        $d = ['brcod' => $me['brcod'], 'username' => $name, 'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'mobile' => post('mobile')];
        foreach (PERMS as $k => $_) $d[$k] = isset($_POST[$k]) ? 1 : 0;
        upsert('users', null, $d);
        flash("User $name created."); go('user_new.php');
    }
}
$br = row('SELECT * FROM branches WHERE brcod=?', [$me['brcod']]);
page_start('Create new user');
?>
<p class="muted"><?= e($br['brcod'] . '  ' . $br['name_en']) ?></p>
<form method="post" class="layout"><?= csrf_field() ?>
  <div class="card">
    <div class="field"><label for="username">User name</label><input type="text" id="username" name="username" required autofocus></div>
    <div class="field"><label for="password">Password</label><input type="password" id="password" name="password" required autocomplete="new-password"></div>
    <div class="field"><label for="confirm">Confirm password</label><input type="password" id="confirm" name="confirm" required autocomplete="new-password"></div>
    <div class="field"><label for="mobile">Mobile number</label><input type="text" id="mobile" name="mobile" inputmode="numeric" maxlength="10"></div>
    <div class="actions"><button class="primary">Save user</button><a class="btn" href="user_branch.php">Exit</a></div>
  </div>
  <div class="card"><h2>Permissions</h2>
    <?php foreach (PERMS as $k => $label): ?><label class="check"><input type="checkbox" name="<?= $k ?>" value="1"><?= e($label) ?></label><?php endforeach ?>
  </div>
</form>
<?php page_end();
