<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/auth.php';
$me = require_login();
$cur = row('SELECT totp_secret FROM users WHERE id=?', [$me['id']]);
$enabled = !empty($cur['totp_secret']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (post('act') === 'enable') {
        $sec = $_SESSION['new_totp'] ?? '';
        if ($sec && totp_check($sec, preg_replace('/\D/', '', post('code')))) {
            q('UPDATE users SET totp_secret=? WHERE id=?', [$sec, $me['id']]); unset($_SESSION['new_totp']);
            flash('Authenticator app is now on for your login.');
        } else flash('That code is not correct. Try again.', 'err');
    } elseif (post('act') === 'disable') {
        q('UPDATE users SET totp_secret=NULL WHERE id=?', [$me['id']]); unset($_SESSION['new_totp']);
        flash('Authenticator app removed.');
    }
    go('security.php');
}
if (!$enabled) $sec = $_SESSION['new_totp'] ??= totp_new_secret();
page_start('My security');
?>
<div class="card" style="max-width:560px">
<?php if ($enabled): ?>
  <p>The authenticator app is <b>on</b> for <b><?= e($me['username']) ?></b>. You can pick it as "Use authenticator app" on the second sign-in step.</p>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="disable"><button onclick="return confirm('Remove the authenticator app?')">Remove authenticator app</button></form>
<?php else: ?>
  <p>1. In Google Authenticator / Microsoft Authenticator, choose <b>Add account → Enter a setup key</b>.</p>
  <div class="field narrow"><label>Account</label><input type="text" readonly value="<?= e(APP_NAME . ' ' . $me['username']) ?>"></div>
  <div class="field narrow"><label>Key</label><input type="text" readonly value="<?= e($sec) ?>" onclick="this.select()"></div>
  <div class="field narrow"><label>Type</label><input type="text" readonly value="Time based"></div>
  <p>2. Type the 6-digit code the app shows to confirm.</p>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="enable">
    <div class="field narrow"><label for="code">Code</label><input type="text" id="code" name="code" inputmode="numeric" maxlength="6" required></div>
    <div class="actions"><button class="primary">Turn on</button></div></form>
<?php endif ?>
</div>
<?php page_end();
