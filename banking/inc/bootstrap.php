<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';

const PERMS = [
    'p_dateclose'   => 'Allow date close / open date',
    'p_backdate'    => 'Allow back date entry',
    'p_edit'        => 'Allow edit',
    'p_editvoucher' => 'Allow edit voucher while entry',
    'p_passing'     => 'Passing permission',
    'p_cashier'     => 'Cashier permission',
    'p_master'      => 'Master setting permission',
    'p_account'     => 'Account creation permission',
    'p_transaction' => 'Transaction permission',
    'p_user'        => 'User creation permission',
    'p_branch'      => 'Branch creation',
];

function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}
function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
function rows(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function row(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r ?: null; }
function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function post(string $k): string { return trim((string)($_POST[$k] ?? '')); }
function upsert(string $table, ?int $id, array $d): int {
    $cols = array_keys($d);
    if ($id) {
        $set = implode(',', array_map(fn($c) => "`$c`=?", $cols));
        q("UPDATE `$table` SET $set WHERE id=?", [...array_values($d), $id]);
        return $id;
    }
    q("INSERT INTO `$table` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")", array_values($d));
    return (int)db()->lastInsertId();
}

/** Message for an unexpected failure. With APP_DEBUG on (see config.php) it includes the real error, which helps during setup. */
function fail_msg(Throwable $ex, string $fallback): string {
    error_log($ex->getMessage());
    return (defined('APP_DEBUG') && APP_DEBUG) ? $fallback . ' [' . $ex->getMessage() . ']' : $fallback;
}

/** URL of a file in assets/ with a version, so browsers load the newest copy after an update. */
function asset(string $f): string {
    $v = @filemtime(__DIR__ . '/../assets/' . $f) ?: 1;
    return BASE_URL . '/assets/' . $f . '?v=' . $v;
}

/** For endpoints that answer in JSON: any unexpected error is returned as JSON instead of an HTML error page. */
function json_exceptions(): void {
    set_exception_handler(function (Throwable $e) {
        if (!headers_sent()) { http_response_code(500); header('Content-Type: application/json; charset=utf-8'); }
        echo json_encode(['error' => fail_msg($e, 'Server error.')], JSON_UNESCAPED_UNICODE);
    });
}

function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf() . '">'; }
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); exit('Session expired. Reload the page and try again.'); }
}

function flash(string $msg, string $type = 'ok'): void { $_SESSION['flash'] = [$msg, $type]; }
function go(string $path): never { header('Location: ' . BASE_URL . '/' . ltrim($path, '/')); exit; }

function user(): ?array { return $_SESSION['user'] ?? null; }
function require_login(): array { if (!user()) go('index.php'); return user(); }
function can(string $perm): bool { return (bool)(user()[$perm] ?? false); }
function require_perm(string $perm): void {
    require_login();
    if (!can($perm)) { http_response_code(403); page_start('Not allowed'); echo '<div class="card"><p>Your user does not have this permission. Ask an administrator to enable it under Edit Permissions.</p></div>'; page_end(); exit; }
}
function dmy(?string $d): string { return $d ? date('d/M/Y', strtotime($d)) : ''; }

/* ---- layout ---- */
function master_menu(): array {
    return [
        'Deposit Master' => [['Share Master', 'scheme.php?kind=share'], ['Deposit (SB) Master', 'scheme.php?kind=deposit'], ['Term Master', 'scheme.php?kind=term'],
            ['FD Master', 'scheme.php?kind=fd'], ['CC Master', 'scheme.php?kind=cc'], ['Pigmy Master', 'scheme.php?kind=pigmy'], ['RD Master', 'scheme.php?kind=rd']],
        'Loan Master' => [['Loan Masters', 'scheme.php?kind=loan']],
        'Other Balancesheet Heads' => [['Balancesheet Head Master', 'bs_head.php'], ['Balancesheet Sub Head', 'bs_subhead.php'], ['Balancesheet Heads / Account Name', 'bs_account.php']],
        'Profit Loss Heads' => [['Profit Loss Main Head', 'pl_head.php'], ['Profit Loss Heads / Account Name', 'pl_account.php']],
        'Other Settings' => [['Director Master', 'director.php']],
    ];
}

function txn_menu(): array {
    $s = 'soon.php?f=';
    return [
        'Create Accounts' => [['Individual customer', 'customer.php?type=individual'], ['Company customer', 'customer.php?type=company'], ['Share / SB / TD / CC account', 'account_new.php'],
            ['Pigmy / RD account', 'pigrd_new.php'], ['Loan account', 'loan_new.php'], ['Fixed instalment loan account', $s . 'fixed-loan'],
            ['Balancesheet heads', '../master/bs_account.php'], ['Profit or loss heads', '../master/pl_account.php']],
        'Transactions' => [['Cash and voucher entry', $s . 'transactions']],
        'Reports' => [['Customer list', 'customers.php']],
        'Shortcuts / Settings' => [['Main menu', '../launcher.php']],
    ];
}

function page_start(string $title, string $section = 'plain'): void {
    $u = user();
    $base = BASE_URL;
    $assets = $base . '/assets';
    echo "<!doctype html><html lang=\"en\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">";
    echo "<title>" . e($title) . " · " . APP_NAME . "</title><link rel=\"stylesheet\" href=\"" . asset('style.css') . "\"></head><body class=\"" . e($section) . "\">";
    if ($section === 'master') {
        $mb = $base . '/master/';
        echo '<header class="topbar"><a class="brand" href="' . $base . '/launcher.php">' . APP_NAME . '<small>Head office settings</small></a><nav class="menu">';
        foreach (master_menu() as $label => $items) {
            echo '<div class="mi" tabindex="0"><span>' . e($label) . '</span><div class="dd">';
            foreach ($items as [$t, $href]) echo '<a href="' . $mb . $href . '">' . e($t) . '</a>';
            echo '</div></div>';
        }
        echo '<a class="mi plain-link" href="' . $base . '/launcher.php">Quit</a></nav></header>';
    } elseif ($section === 'txn') {
        $tb = $base . '/transaction/';
        echo '<header class="topbar"><a class="brand" href="' . $base . '/transaction/index.php">' . APP_NAME . '<small>Transaction application</small></a><nav class="menu">';
        foreach (txn_menu() as $label => $items) {
            echo '<div class="mi" tabindex="0"><span>' . e($label) . '</span><div class="dd">';
            foreach ($items as [$t, $href]) echo '<a href="' . $tb . $href . '">' . e($t) . '</a>';
            echo '</div></div>';
        }
        echo '<a class="mi plain-link" href="' . $base . '/launcher.php">Quit</a></nav></header>';
    } elseif ($u) {
        echo '<header class="topbar"><a class="brand" href="' . $base . '/launcher.php">' . APP_NAME . '</a><nav class="menu"><a class="mi plain-link" href="' . $base . '/launcher.php">Main menu</a><a class="mi plain-link" href="' . $base . '/logout.php">Log out</a></nav></header>';
    }
    echo '<main>';
    if (!empty($_SESSION['flash'])) { [$m, $t] = $_SESSION['flash']; unset($_SESSION['flash']); echo '<div class="flash ' . e($t) . '" role="status">' . e($m) . '</div>'; }
    echo '<h1>' . e($title) . '</h1>';
}
function page_end(): void {
    $u = user();
    echo '</main>';
    if ($u) {
        echo '<footer class="statusbar"><span>User <b>' . e($u['username']) . '</b></span><span>User ID <b>' . e($u['id']) . '</b></span><span>Branch <b>' . e($u['brcod']) . '</b></span><span>Date <b>' . e(dmy($_SESSION['workdate'] ?? date('Y-m-d'))) . '</b></span></footer>';
    }
    echo '<script src="' . asset('app.js') . '"></script></body></html>';
}
