<?php
require __DIR__ . '/inc/bootstrap.php';
require_perm('p_master');
$out = "-- " . APP_NAME . " backup " . date('Y-m-d H:i:s') . "\nSET FOREIGN_KEY_CHECKS=0;\n";
foreach (db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
    $out .= "\nDROP TABLE IF EXISTS `$t`;\n" . db()->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1] . ";\n";
    foreach (db()->query("SELECT * FROM `$t`") as $r) {
        $out .= "INSERT INTO `$t` VALUES (" . implode(',', array_map(fn($v) => $v === null ? 'NULL' : db()->quote((string)$v), array_values($r))) . ");\n";
    }
}
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="banking_' . date('Ymd_His') . '.sql"');
echo $out . "SET FOREIGN_KEY_CHECKS=1;\n";
