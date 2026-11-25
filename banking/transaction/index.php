<?php
require __DIR__ . '/../inc/bootstrap.php';
require_login();
if (!can('p_transaction') && !can('p_account')) require_perm('p_transaction');
page_start('Transaction application', 'txn');
echo '<div class="tiles">';
foreach (txn_menu() as $group => $items) foreach ($items as [$t, $h]) echo '<a class="tile" href="' . e($h) . '"><b>' . e($t) . '</b><small>' . e($group) . '</small></a>';
echo '</div>';
page_end();
