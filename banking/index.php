<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/auth.php';
try { $branches = rows('SELECT brcod,name_en,otp_login FROM branches ORDER BY brcod'); $users = rows('SELECT id,brcod,username FROM users ORDER BY username'); }
catch (Throwable $t) { header('Location: ' . BASE_URL . '/install.php'); exit; }
if (!$users) { header('Location: ' . BASE_URL . '/install.php'); exit; }
if (isset($_GET['reset'])) { unset($_SESSION['pending']); go('index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = row('SELECT * FROM users WHERE id=? AND brcod=?', [(int)$_POST['user_id'], post('brcod')]);
    $date = post('workdate') ?: date('Y-m-d');
    if (strcasecmp(post('bank'), BANK_CODE) !== 0) {
        flash('Bank code is wrong.', 'err');
    } elseif (!$u || !password_verify($_POST['password'] ?? '', $u['password_hash'])) {
        flash('User name or password is wrong.', 'err');
    } elseif ($date !== date('Y-m-d') && !$u['p_backdate']) {
        flash('This user can log in only with today\'s date.', 'err');
    } else {
        $br = row('SELECT otp_login FROM branches WHERE brcod=?', [$u['brcod']]);
        if (!$br || !$br['otp_login']) complete_login((int)$u['id'], $date);       // branch has 2-step off: old behaviour
        $hasApp = !empty($u['totp_secret']);
        $hasMobile = strlen(preg_replace('/\D/', '', (string)$u['mobile'])) >= 10;
        if (!$hasApp && !$hasMobile && !OTP_DEV_MODE) {
            flash('No mobile number is registered for this user. Ask an administrator to add it.', 'err');
            go('index.php');
        }
        session_regenerate_id(true);
        $_SESSION['pending'] = ['uid' => (int)$u['id'], 'date' => $date, 'at' => time(), 'name' => $u['username'], 'mobile' => (string)$u['mobile'], 'app' => $hasApp, 'sent' => 0];
        unset($_SESSION['dev_code']);
        if ($hasMobile || !$hasApp) {
            [$ok, $code] = otp_issue($u);
            $_SESSION['pending']['sent'] = time();
            if (OTP_DEV_MODE) $_SESSION['dev_code'] = $code;
            elseif (!$ok) flash('The SMS could not be sent. Try "Send a new code".', 'err');
        }
        go($hasApp && !$hasMobile ? 'login_otp.php?m=app' : 'login_otp.php');
    }
    go('index.php');
}
page_start('Sign in', 'login');
?>
<form method="post" class="card" style="max-width:440px;margin:auto"><?= csrf_field() ?>
  <div class="steps"><i class="on"></i><i></i><span>Step 1 of 2</span></div>
  <div class="field"><label for="bank">Bank code</label><input type="text" id="bank" name="bank" value="<?= e(BANK_CODE) ?>" required></div>
  <div class="field"><label for="workdate">Date</label><input type="date" id="workdate" name="workdate" value="<?= date('Y-m-d') ?>"></div>
  <div class="field"><label for="brcod">Branch</label>
    <select id="brcod" name="brcod"><?php foreach ($branches as $b): ?><option value="<?= e($b['brcod']) ?>"><?= e($b['brcod'] . '  ' . $b['name_en']) ?></option><?php endforeach ?></select></div>
  <div class="field"><label for="user_id">User</label>
    <select id="user_id" name="user_id" data-filter-by="#brcod"><?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>" data-parent="<?= e($u['brcod']) ?>"><?= e($u['username']) ?></option><?php endforeach ?></select></div>
  <div class="field"><label for="password">Password</label><input type="password" id="password" name="password" autocomplete="current-password" autofocus required></div>
  <div class="actions"><button class="primary">Continue</button></div>
</form>
<?php page_end();
