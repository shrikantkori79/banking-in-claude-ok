<?php
// Run once: http://localhost/banking/install.php  (creates DB, tables, first branch and admin user)
require __DIR__ . '/inc/config.php';
header('Content-Type: text/plain; charset=utf-8');
$root = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$root->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$sql = preg_replace('~(^|[ \t])--[^\n]*~m', '', file_get_contents(__DIR__ . '/sql/schema.sql'));   // drop comments, even ones that contain a semicolon
foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) $pdo->exec($stmt);
if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) { exit("Already installed. Delete install.php from the server.\n"); }

$pdo->prepare('INSERT INTO branches (brcod,name_en) VALUES (?,?)')->execute(['011', 'Head Office']);
$pass = 'Change@123';
$pdo->prepare('INSERT INTO users (brcod,username,password_hash,p_dateclose,p_backdate,p_edit,p_editvoucher,p_passing,p_cashier,p_master,p_account,p_transaction,p_user,p_branch)
               VALUES (?,?,?,1,1,1,1,1,1,1,1,1,1,1)')->execute(['011', 'BANK', password_hash($pass, PASSWORD_DEFAULT)]);
foreach (['President', 'Vice President', 'Director', 'Manager'] as $d) $pdo->prepare('INSERT IGNORE INTO desmas (name) VALUES (?)')->execute([$d]);
$pdo->exec("INSERT INTO prolosmaimas (maihea) VALUES ('INCOME AND EXPENCE')");
echo "Installed.\nLogin -> branch 011, user BANK, password $pass\nChange the password at once (Edit User) and delete install.php.\n";
