<?php
require __DIR__ . '/../inc/bootstrap.php';
require_perm('p_edit');
page_start('Master edit');
echo '<div class="card"><p>This section is the next phase. The master settings it depends on are built under Master application.</p><a class="btn" href="' . BASE_URL . '/launcher.php">Back to main menu</a></div>';
page_end();
