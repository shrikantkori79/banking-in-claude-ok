<?php
// Run once while signed in after updating the files. Safe to run again.
require __DIR__ . '/inc/bootstrap.php';
require_perm('p_master');
header('Content-Type: text/plain; charset=utf-8');
function has_table(string $t): bool { return (bool)row('SELECT 1 x FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?', [$t]); }
function has_col(string $t, string $c): bool { return (bool)row('SELECT 1 x FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?', [$t, $c]); }
function step(string $msg, string $sql): void { db()->exec($sql); echo "$msg\n"; }

// 1. first-version generic scheme table
if (has_col('scheme_master', 'code')) step('Dropped old scheme_master.', 'DROP TABLE scheme_master');
// 2. profit loss tables use the legacy names prolosmaimas / prolosmas (heaid -> prolosmaimas.id)
if (has_table('plmaimas') && !has_table('prolosmaimas')) step('plmaimas -> prolosmaimas', 'RENAME TABLE plmaimas TO prolosmaimas');
if (has_table('placcmas') && !has_table('prolosmas')) step('placcmas -> prolosmas', 'RENAME TABLE placcmas TO prolosmas');
if (has_table('prolosmas') && has_col('prolosmas', 'maiid')) step('prolosmas.maiid -> heaid', 'ALTER TABLE prolosmas CHANGE maiid heaid INT NOT NULL');
// 2b. legacy column names (maihea/maiheakan, subhea/subheakan, accnam/accnamkan)
function rename_col(string $t, string $old, string $new, string $def): void {
    if (has_table($t) && has_col($t, $old) && !has_col($t, $new)) step("$t.$old -> $new", "ALTER TABLE `$t` CHANGE `$old` `$new` $def");
}
rename_col('balmaimas', 'name', 'maihea', 'VARCHAR(120) NOT NULL');   rename_col('balmaimas', 'namekan', 'maiheakan', 'VARCHAR(120) NULL');
rename_col('balsubmas', 'name', 'subhea', 'VARCHAR(120) NOT NULL');   rename_col('balsubmas', 'namekan', 'subheakan', 'VARCHAR(120) NULL');
rename_col('balaccmas', 'name', 'accnam', 'VARCHAR(120) NOT NULL');   rename_col('balaccmas', 'namekan', 'accnamkan', 'VARCHAR(120) NULL');
rename_col('prolosmaimas', 'name', 'maihea', 'VARCHAR(120) NOT NULL'); rename_col('prolosmaimas', 'namekan', 'maiheakan', 'VARCHAR(120) NULL');
rename_col('prolosmas', 'name', 'accnam', 'VARCHAR(120) NOT NULL');   rename_col('prolosmas', 'namekan', 'accnamkan', 'VARCHAR(120) NULL');
if (has_table('balmaimas') && has_col('balmaimas', 'headtype')) {
    step('balmaimas.headtype -> typ (1 Assets, 2 Liabilities) + pri_ord', 'ALTER TABLE balmaimas ADD typ TINYINT NOT NULL DEFAULT 1, ADD pri_ord INT NOT NULL DEFAULT 0');
    db()->exec("UPDATE balmaimas SET typ = IF(headtype='Liabilities', 2, 1), pri_ord = id");
    db()->exec('ALTER TABLE balmaimas DROP COLUMN headtype');
}
if (has_table('scheme_master') && !has_col('scheme_master', 'pledge_compulsory')) step('scheme_master.pledge_compulsory added', 'ALTER TABLE scheme_master ADD pledge_compulsory TINYINT(1) NOT NULL DEFAULT 0');
foreach (['pig_mode' => 'TINYINT NOT NULL DEFAULT 1', 'calc_daybase' => 'TINYINT(1) NOT NULL DEFAULT 0', 'calc_production' => 'TINYINT(1) NOT NULL DEFAULT 0'] as $c => $def)
    if (has_table('scheme_master') && !has_col('scheme_master', $c)) step("scheme_master.$c added", "ALTER TABLE scheme_master ADD $c $def");
// 2c. agents table uses the legacy name newpigage
if (has_table('pigrdagent') && !has_table('newpigage')) step('pigrdagent -> newpigage', 'RENAME TABLE pigrdagent TO newpigage');
if (has_table('users') && !has_col('users', 'totp_secret')) step('users.totp_secret added', 'ALTER TABLE users ADD totp_secret VARCHAR(64) NULL AFTER mobile');
// 3. create anything missing
$sql = preg_replace('~(^|[ \t])--[^\n]*~m', '', file_get_contents(__DIR__ . '/sql/schema.sql'));   // drop comments, even ones that contain a semicolon
foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) db()->exec($stmt);
// 4. scheme link tables keep the full id reference
if (has_col('scheme_pl', 'placcid')) {
    step('scheme_pl now stores heaid + proid', 'ALTER TABLE scheme_pl CHANGE placcid proid INT NOT NULL, ADD heaid INT NOT NULL DEFAULT 0 AFTER scheme_id');
    db()->exec('UPDATE scheme_pl l JOIN prolosmas a ON a.id=l.proid SET l.heaid=a.heaid');
}
if (has_col('scheme_bs', 'balaccid')) {
    step('scheme_bs now stores maiid + subid + accid', 'ALTER TABLE scheme_bs CHANGE balaccid accid INT NOT NULL, ADD maiid INT NOT NULL DEFAULT 0 AFTER scheme_id, ADD subid INT NOT NULL DEFAULT 0 AFTER maiid');
    db()->exec('UPDATE scheme_bs l JOIN balaccmas a ON a.id=l.accid SET l.maiid=a.maiid, l.subid=a.subid');
}
// 5. pigmy and RD accounts have separate tables: move rows saved in the earlier combined table
if (has_table('pigrdacc')) {
    $c = 'heaid,subheaid,agenum,accnum,cusid,macnam,accdat,clodat,matdat,gurid,nom,rel,gurrel,loasta,useidcre,brcod';
    db()->exec("INSERT INTO newpigacc ($c) SELECT $c FROM pigrdacc WHERE heaid=0");
    db()->exec("INSERT INTO newrdacc ($c,matamo) SELECT $c,matamo FROM pigrdacc WHERE heaid=1");
    db()->exec('DROP TABLE pigrdacc');
    echo "Moved pigmy and RD accounts into newpigacc and newrdacc.\n";
}
echo "Schema is up to date. Delete upgrade.php.\n";
