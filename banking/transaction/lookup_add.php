<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/lookups.php';
require_login();
header('Content-Type: application/json; charset=utf-8');
json_exceptions();
function out(int $code, array $d): never { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(405, ['error' => 'POST only.']);
if (!can('p_account')) out(403, ['error' => 'You do not have account creation permission.']);
csrf_check();
$k = $_POST['kind'] ?? '';
if (!isset(LOOKUPS[$k])) out(400, ['error' => 'Unknown list.']);
[$t, $n, $kn, $label] = LOOKUPS[$k];
$name = trim((string)($_POST['name'] ?? '')); $kan = trim((string)($_POST['namekan'] ?? ''));
if ($name === '' || mb_strlen($name) > 100 || mb_strlen($kan) > 100) out(422, ['error' => 'Enter a name of up to 100 characters.']);
if (row("SELECT id FROM `$t` WHERE `$n`=?", [$name])) out(409, ['error' => ucfirst($label) . ' "' . $name . '" already exists.']);
$id = upsert($t, null, $kn ? [$n => $name, $kn => $kan] : [$n => $name]);
out(200, ['id' => $id, 'name' => $name]);
