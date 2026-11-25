<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
page_start('Main menu', 'launch');
$tiles = [
    ['Master application', 'Behaviour of the banking application', 'master/index.php', 'p_master', ''],
    ['Transaction application', 'Accounts, transactions and reports', 'transaction/index.php', 'p_transaction', ''],
    ['Cashier and passing', 'Cash counter and voucher passing', 'cashier/index.php', 'p_cashier', ''],
    ['Master edit', 'Change earlier master settings', 'master_edit/index.php', 'p_edit', 'edit'],
    ['Transaction edit', 'Correct transactions and accounts', 'transaction_edit/index.php', 'p_edit', 'edit'],
    ['User and branch settings', 'Users, permissions, branches, day start and end', 'user_branch.php', null, ''],
    ['My security', 'Authenticator app for 2-step login', 'security.php', null, ''],
    ['Backup to this computer', 'Download a SQL dump of all tables', 'backup.php', 'p_master', 'risk'],
];
echo '<div class="tiles">';
foreach ($tiles as [$t, $d, $href, $perm, $cls]) {
    $ok = $perm === null || can($perm) || ($perm === 'p_cashier' && can('p_passing'));
    echo '<a class="tile ' . $cls . ($ok ? '' : ' disabled') . '" href="' . BASE_URL . '/' . $href . '"><b>' . e($t) . '</b><small>' . e($d) . '</small></a>';
}
echo '<a class="tile risk" href="' . BASE_URL . '/logout.php"><b>Quit</b><small>Log out of this session</small></a></div>';
page_end();
