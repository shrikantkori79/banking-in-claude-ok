<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
page_start('User and branch settings');
$items = [
    ['Create new user', 'user_new.php', 'p_user'], ['Edit user name / password', 'user_edit.php', 'p_user'], ['Edit permissions', 'permissions.php', 'p_user'],
    ['Create new branch', 'branch.php?new=1', 'p_branch'], ['Edit branch settings', 'branch.php', 'p_branch'],
    ['Go to master settings', 'master/index.php', 'p_master'], ['Day start / day end', 'day.php', 'p_dateclose'],
];
echo '<div class="card menu-list" style="max-width:460px">';
foreach ($items as [$t, $h, $p]) if (can($p)) echo '<a href="' . BASE_URL . '/' . $h . '">' . e($t) . '</a>';
echo '</div>';
page_end();
