<?php
require __DIR__ . '/../inc/bootstrap.php';
require_login();
if (!can('p_account') && !can('p_transaction')) require_perm('p_account');
$q = trim((string)($_GET['q'] ?? ''));
$like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
$list = rows("SELECT c.cusid, c.custype, c.cusnam, c.cusnamkan, c.mobnum, c.joidat, p.planam FROM newcustomer c LEFT JOIN newpla p ON p.id=c.plaid
              " . ($q !== '' ? 'WHERE c.cusnam LIKE ? OR CAST(c.cusid AS CHAR) LIKE ? OR c.mobnum LIKE ?' : '') . ' ORDER BY c.cusid DESC LIMIT 200', $q !== '' ? [$like, $q . '%', $like] : []);
page_start('Customer list', 'txn');
?>
<form method="get" class="card" style="margin-bottom:14px;max-width:520px"><div class="with-btn"><input type="text" name="q" value="<?= e($q) ?>" placeholder="Search by name, customer id or mobile" aria-label="Search"><button class="primary">Search</button></div></form>
<div class="tablewrap"><table class="grid"><tr><th>CID</th><th>Name</th><th>Type</th><th>Place</th><th>Mobile</th><th>Joined</th></tr>
<?php foreach ($list as $r): ?><tr><td><?= (int)$r['cusid'] ?></td><td><?= e($r['cusnam']) ?><?= $r['cusnamkan'] ? ' <span class="muted" lang="kn">' . e($r['cusnamkan']) . '</span>' : '' ?></td><td><?= $r['custype'] == 2 ? 'Company' : 'Individual' ?></td><td><?= e($r['planam']) ?></td><td><?= e($r['mobnum']) ?></td><td><?= e(dmy($r['joidat'])) ?></td></tr>
<?php endforeach; if (!$list) echo '<tr><td colspan="6" class="empty">No customers found.</td></tr>'; ?></table></div>
<?php page_end();
