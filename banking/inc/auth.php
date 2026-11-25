<?php
declare(strict_types=1);
/** 2-step login helpers: SMS one-time code and authenticator-app (TOTP) code. */

const OTP_TTL = 300;          // SMS code works for 5 minutes
const OTP_MAX_TRIES = 5;      // wrong entries allowed per code
const OTP_RESEND_WAIT = 30;   // seconds before a new code can be requested
const PENDING_TTL = 600;      // step 2 must be finished within 10 minutes of step 1

function mask_mobile(string $m): string {
    $m = preg_replace('/\D/', '', $m);
    return strlen($m) >= 6 ? substr($m, 0, 2) . str_repeat('x', strlen($m) - 5) . substr($m, -3) : '';
}

function sms_send(string $mobile, string $text): bool {
    if (SMS_API_URL === '') { error_log("SMS (no gateway set) to $mobile: $text"); return false; }
    $url = str_replace(['{mobile}', '{message}'], [rawurlencode($mobile), rawurlencode($text)], SMS_API_URL);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    $res = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return $res !== false && $code >= 200 && $code < 300;
}

/** Creates a fresh code for the user and sends it. Returns [sent_ok, plain_code]. */
function otp_issue(array $u): array {
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    q('REPLACE INTO login_otp (user_id,code_hash,expires_at,attempts,sent_at) VALUES (?,?,?,0,?)',
      [$u['id'], password_hash($code, PASSWORD_DEFAULT), date('Y-m-d H:i:s', time() + OTP_TTL), date('Y-m-d H:i:s')]);
    $ok = sms_send(preg_replace('/\D/', '', (string)$u['mobile']), "Your " . APP_NAME . " login code is $code. Valid for 5 minutes. Do not share it.");
    return [$ok, $code];
}

function otp_verify(int $uid, string $code): string {   // returns '' when correct, else the error text
    $r = row('SELECT * FROM login_otp WHERE user_id=?', [$uid]);
    if (!$r) return 'No code was sent. Use "Send a new code".';
    if (strtotime($r['expires_at']) < time()) return 'The code has expired. Send a new code.';
    if ($r['attempts'] >= OTP_MAX_TRIES) return 'Too many wrong tries. Send a new code.';
    q('UPDATE login_otp SET attempts=attempts+1 WHERE user_id=?', [$uid]);
    if (!preg_match('/^\d{6}$/', $code) || !password_verify($code, $r['code_hash'])) return 'That code is not correct.';
    q('DELETE FROM login_otp WHERE user_id=?', [$uid]);
    return '';
}

/* ---- authenticator app (RFC 6238 TOTP, 30 s, 6 digits) ---- */
function b32_encode(string $bin): string {
    $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; $out = '';
    foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    foreach (str_split($bits, 5) as $chunk) $out .= $a[bindec(str_pad($chunk, 5, '0'))];
    return $out;
}
function b32_decode(string $s): string {
    $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; $out = '';
    foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s))) as $c) $bits .= str_pad(decbin(strpos($a, $c)), 5, '0', STR_PAD_LEFT);
    foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $out .= chr(bindec($byte));
    return $out;
}
function totp_new_secret(): string { return b32_encode(random_bytes(20)); }
function totp_code(string $secret, int $slice): string {
    $h = hash_hmac('sha1', pack('N2', 0, $slice), b32_decode($secret), true);
    $o = ord($h[19]) & 15;
    $n = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string)($n % 1000000), 6, '0', STR_PAD_LEFT);
}
function totp_check(string $secret, string $code): bool {
    if (!preg_match('/^\d{6}$/', $code) || $secret === '') return false;
    $now = intdiv(time(), 30);
    for ($i = -1; $i <= 1; $i++) if (hash_equals(totp_code($secret, $now + $i), $code)) return true;
    return false;
}

/** Final step: puts the user in the session and opens the main menu. */
function complete_login(int $uid, string $date): never {
    $u = row('SELECT * FROM users WHERE id=?', [$uid]);
    if (!$u) go('index.php');
    session_regenerate_id(true);
    unset($u['password_hash'], $u['totp_secret']);
    unset($_SESSION['pending']);
    $_SESSION['user'] = $u;
    $_SESSION['workdate'] = $date;
    go('launcher.php');
}
