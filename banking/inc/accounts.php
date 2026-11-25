<?php
const HEADS = [
    0 => ['label' => 'Member share', 'kinds' => ['share'], 'pending' => false],
    1 => ['label' => 'Member savings / current / others', 'kinds' => ['deposit'], 'pending' => false],
    2 => ['label' => 'Member term deposit', 'kinds' => ['term'], 'pending' => true],
    3 => ['label' => 'Member CC deposit', 'kinds' => ['cc', 'fd'], 'pending' => true],
];
const JOINT_TYPES = [0 => 'Either or survivor', 1 => 'Anyone or survivor', 2 => 'Former or survivor', 3 => 'Latter or survivor', 4 => 'Jointly'];
const MINOR_AGE = 18;

if (!function_exists('valid_date')) {
    function valid_date(string $s): bool { $d = DateTime::createFromFormat('Y-m-d', $s); return $d && $d->format('Y-m-d') === $s; }
}
function is_minor(?string $dob, string $asof): bool {
    return $dob !== null && $dob !== '' && $dob > date('Y-m-d', strtotime($asof . ' -' . MINOR_AGE . ' years'));
}
function peek_accnum(int $h, int $s): int {
    $r = row('SELECT last FROM accseq WHERE heaid=? AND subheaid=?', [$h, $s]);
    if ($r) return (int)$r['last'] + 1;
    return max_accnum($h, $s) + 1;
}
function max_accnum(int $h, int $s): int {
    return (int)row('SELECT GREATEST((SELECT COALESCE(MAX(accnum),0) FROM depacc WHERE heaid=? AND subheaid=?), (SELECT COALESCE(MAX(accnum),0) FROM depacc_tmp WHERE heaid=? AND subheaid=?)) m', [$h, $s, $h, $s])['m'];
}
/** Gives the next account number. Call inside a database transaction. */
function reserve_accnum(int $h, int $s): int {
    $r = row('SELECT last FROM accseq WHERE heaid=? AND subheaid=? FOR UPDATE', [$h, $s]);
    if (!$r) { $n = max_accnum($h, $s) + 1; q('INSERT INTO accseq (heaid,subheaid,last) VALUES (?,?,?)', [$h, $s, $n]); return $n; }
    $n = (int)$r['last'] + 1;
    q('UPDATE accseq SET last=? WHERE heaid=? AND subheaid=?', [$n, $h, $s]);
    return $n;
}

/** Fee lines of a share / deposit master: its linked profit-loss and balance sheet accounts with their amounts. */
function fee_lines(int $scheme): array {
    $pl = rows("SELECT p.heaid AS maiid, 0 AS subid, p.proid AS accid, p.amount, m.maihea AS head, a.accnam AS name
                FROM scheme_pl p JOIN prolosmas a ON a.id=p.proid JOIN prolosmaimas m ON m.id=p.heaid WHERE p.scheme_id=? ORDER BY p.id", [$scheme]);
    $bs = rows("SELECT b.maiid, b.subid, b.accid, b.amount, m.maihea AS head, CONCAT(s.subhea,'--',a.accnam) AS name
                FROM scheme_bs b JOIN balaccmas a ON a.id=b.accid JOIN balmaimas m ON m.id=b.maiid JOIN balsubmas s ON s.id=b.subid WHERE b.scheme_id=? ORDER BY b.id", [$scheme]);
    foreach ($pl as &$r) $r['src'] = 'pl'; unset($r);
    foreach ($bs as &$r) $r['src'] = 'bs'; unset($r);
    $all = array_merge($pl, $bs);
    return ['rows' => array_map(fn($r) => ['src' => $r['src'], 'maiid' => (int)$r['maiid'], 'subid' => (int)$r['subid'], 'accid' => (int)$r['accid'], 'head' => $r['head'], 'name' => $r['name'], 'amount' => (float)$r['amount']], $all),
            'total' => array_sum(array_map(fn($r) => (float)$r['amount'], $all))];
}
