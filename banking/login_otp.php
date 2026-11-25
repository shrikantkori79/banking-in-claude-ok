<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/auth.php';
$p = $_SESSION['pending'] ?? null;
if (!$p || time() - $p['at'] > PENDING_TTL) { unset($_SESSION['pending']); flash('Please sign in again.', 'err'); go('index.php'); }
$uid = (int)$p['uid'];
$u = row('SELECT * FROM users WHERE id=?', [$uid]);
if (!$u) go('index.php?reset=1');
$app = !empty($u['totp_secret']);
$mode = (($_GET['m'] ?? '') === 'app' && $app) ? 'app' : 'sms';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = post('act');
    if ($act === 'resend') {
        if (time() - (int)$p['sent'] < OTP_RESEND_WAIT) { flash('Wait a few seconds before asking for a new code.', 'err'); }
        else {
            [$ok, $code] = otp_issue($u);
            $_SESSION['pending']['sent'] = time();
            if (OTP_DEV_MODE) $_SESSION['dev_code'] = $code;
            flash($ok || OTP_DEV_MODE ? 'A new code has been sent.' : 'The SMS could not be sent.', $ok || OTP_DEV_MODE ? 'ok' : 'err');
        }
        go('login_otp.php');
    }
    $code = preg_replace('/\D/', '', post('code'));
    if ($mode === 'app' || post('m') === 'app') {
        if (totp_check((string)$u['totp_secret'], $code)) complete_login($uid, $p['date']);
        flash('That code is not correct.', 'err'); go('login_otp.php?m=app');
    }
    $err = otp_verify($uid, $code);
    if ($err === '') complete_login($uid, $p['date']);
    flash($err, 'err'); go('login_otp.php');
}
page_start($mode === 'app' ? 'Authenticator code' : 'SMS code', 'login');
?>
<form method="post" class="card" style="max-width:440px;margin:auto" autocomplete="off"><?= csrf_field() ?>
  <input type="hidden" name="m" value="<?= $mode ?>">
  <p>Welcome, <b><?= e($u['username']) ?></b></p>
  <?php if ($mode === 'app'): ?>
    <p>Enter the 6-digit code shown in your authenticator app.</p>
  <?php else: ?>
    <p>Enter the 6-digit code sent to <b><?= e(mask_mobile((string)$u['mobile']) ?: 'your mobile') ?></b>. It works for 5 minutes.</p>
    <?php if (OTP_DEV_MODE && !empty($_SESSION['dev_code'])): ?><div class="devcode">Test mode: your code is <b><?= e($_SESSION['dev_code']) ?></b> (turn off OTP_DEV_MODE in inc/config.php for real use)</div><?php endif ?>
  <?php endif ?>
  <div class="steps"><i class="done"></i><i class="on"></i><span>Step 2 of 2</span></div>
  <div class="field" style="grid-template-columns:1fr"><input class="otp" type="text" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6" placeholder="······" autocomplete="one-time-code" autofocus required></div>
  <div class="actions"><button class="primary">Sign in</button></div>
  <div class="linkrow">
    <?php if ($mode === 'sms'): ?><button class="linkbtn" name="act" value="resend" formnovalidate>Send a new code</button><?php endif ?>
    <?php if ($app && $mode === 'sms'): ?><a href="login_otp.php?m=app">Use authenticator app</a><?php endif ?>
    <?php if ($mode === 'app' && strlen(preg_replace('/\D/', '', (string)$u['mobile'])) >= 10): ?><a href="login_otp.php">Use SMS code</a><?php endif ?>
    <a href="index.php?reset=1">Start again</a>
  </div>
</form>
<?php page_end();
