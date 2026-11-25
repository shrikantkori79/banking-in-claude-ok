<?php
// key => [table, name column, Kannada column or null, label]
const LOOKUPS = [
    'place' => ['newpla', 'planam', 'kanplanam', 'place'],
    'qual'  => ['newqua', 'quanam', 'kanquanam', 'qualification'],
    'occ'   => ['newocc', 'occnam', 'kanoccnam', 'occupation'],
    'pri'   => ['newpri', 'prinam', 'kanprinam', 'priority'],
    'caste' => ['newcas', 'casnam', 'kancasnam', 'caste'],
    'kyc'   => ['newkyc', 'kycnam', null, 'KYC type'],
    'des'   => ['desmas', 'name', null, 'designation'],
];
function lookup_list(string $k): array {
    [$t, $n, $kn] = LOOKUPS[$k];
    $kncol = $kn ? "`$kn`" : "''";
    return rows("SELECT id, `$n` AS name, $kncol AS kn FROM `$t` WHERE `$n` NOT IN ('0','') ORDER BY `$n`");
}
function lookup_check(string $k, string $v): int {
    if ($v === '') return 0;
    if (!ctype_digit($v) || !row('SELECT id FROM `' . LOOKUPS[$k][0] . '` WHERE id=?', [(int)$v])) throw new InvalidArgumentException('Choose the ' . LOOKUPS[$k][3] . ' from the list.');
    return (int)$v;
}
function lookups_json(): string {
    $all = []; $kn = [];
    foreach (LOOKUPS as $k => $def) { $all[$k] = array_map(fn($r) => ['id' => (int)$r['id'], 'name' => $r['name']], lookup_list($k)); $kn[$k] = $def[2] !== null; }
    return json_encode(['lk' => $all, 'kn' => $kn], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
}
