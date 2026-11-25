<?php
require __DIR__ . '/../inc/bootstrap.php';
require_perm('p_master');
page_start('Master settings', 'master');
echo '<div class="tiles">';
foreach (master_menu() as $group => $items) foreach ($items as [$t, $h]) echo '<a class="tile" href="' . e($h) . '"><b>' . e($t) . '</b><small>' . e($group) . '</small></a>';
echo '</div>';
page_end();
