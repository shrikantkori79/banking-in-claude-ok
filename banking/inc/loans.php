<?php
require_once __DIR__ . '/accounts.php';
require_once __DIR__ . '/pigrd.php';
const LOAN_TYPES = [
    0 => ['label' => 'Term deposit loan', 'kinds' => ['term']],
    1 => ['label' => 'CC deposit loan', 'kinds' => ['cc', 'fd']],
    2 => ['label' => 'Pigmy deposit loan', 'kinds' => ['pigmy']],
    3 => ['label' => 'RD deposit loan', 'kinds' => ['rd']],
    4 => ['label' => 'Other loan', 'kinds' => ['loan']],
];
const LOAN_INST = [1 => 'Monthly', 2 => 'Day wise'];
const LOAN_PLEDGE = [1 => 'Agri land', 2 => 'NA land', 3 => 'Gold asset', 4 => 'Vehicle', 5 => 'Constructed house', 6 => 'Pay slip'];
const LOAN_OPEN = '1979-03-01';

/** Loan names for every loan type, with the master settings the form needs. */
function loan_schemes(): array {
    $out = [];
    foreach (LOAN_TYPES as $t => $def) {
        $list = rows("SELECT id, name, loan_pct, loan_int, loan_pen, pledge_compulsory FROM scheme_master WHERE active=1 AND kind IN ('" . implode("','", $def['kinds']) . "') ORDER BY name");
        foreach ($list as &$r) {
            $r = ['id' => (int)$r['id'], 'name' => $r['name'], 'loan_pct' => (float)$r['loan_pct'], 'loan_int' => (float)$r['loan_int'], 'loan_pen' => (float)$r['loan_pen'], 'compulsory' => (int)$r['pledge_compulsory']];
            if ($t === 4) {
                $r['pledges'] = array_map('intval', array_column(rows('SELECT pledgeid FROM scheme_pledge WHERE scheme_id=?', [$r['id']]), 'pledgeid'));
                $r['interest'] = loan_interest($r['id'], null);
            }
        } unset($r);
        $out[$t] = $list;
    }
    return $out;
}
/** Interest names set in the loan master. With a date, only rates that already apply on that date. */
function loan_interest(int $scheme, ?string $asof): array {
    $rows = rows('SELECT intname, inttyp, intrate, app_after, effdate FROM scheme_int WHERE scheme_id=? ORDER BY inttyp, id', [$scheme]);
    if ($asof !== null) $rows = array_values(array_filter($rows, fn($r) => $r['effdate'] === null || $r['effdate'] <= $asof));
    return array_map(fn($r) => ['intname' => $r['intname'], 'inttyp' => (int)$r['inttyp'], 'intrate' => (float)$r['intrate'], 'app_after' => (int)$r['app_after'], 'effdate' => $r['effdate']], $rows);
}
function loan_header_table(int $t): string { return $t === 4 ? 'newloa' : 'newloa_dep'; }

function peek_loanum(int $t, int $loaid): int {
    $r = row('SELECT last FROM loaseq WHERE typofloa=? AND loaid=?', [$t, $loaid]);
    return $r ? (int)$r['last'] + 1 : max_loanum($t, $loaid) + 1;
}
function max_loanum(int $t, int $loaid): int {
    return (int)row('SELECT COALESCE(MAX(loanum),0) m FROM ' . loan_header_table($t) . ' WHERE typofloa=? AND loaid=?', [$t, $loaid])['m'];
}
/** Next loan number. Call inside a transaction. */
function reserve_loanum(int $t, int $loaid): int {
    $r = row('SELECT last FROM loaseq WHERE typofloa=? AND loaid=? FOR UPDATE', [$t, $loaid]);
    if (!$r) { $n = max_loanum($t, $loaid) + 1; q('INSERT INTO loaseq (typofloa,loaid,last) VALUES (?,?,?)', [$t, $loaid, $n]); return $n; }
    $n = (int)$r['last'] + 1; q('UPDATE loaseq SET last=? WHERE typofloa=? AND loaid=?', [$n, $t, $loaid]);
    return $n;
}

/**
 * Deposits a customer can pledge: open, passed, not pledged to a loan that is still open.
 * Deposit balance, deposit date, maturity date and interest % of term and CC deposits come from the deposit
 * transactions, which are not built yet, so deposit_balance() returns null until then.
 */
function loan_deposits(int $t, int $sub, int $cus): array {
    if ($t === 0 || $t === 1) {
        $list = rows("SELECT a.accnum AS depnum, 0 AS agenum, a.cerno, a.accdat, NULL AS matdat, NULL AS intper
                      FROM depacc a JOIN depaccjoi j ON j.heaid=a.heaid AND j.subheaid=a.subheaid AND j.accnum=a.accnum
                      WHERE a.heaid=? AND a.subheaid=? AND j.cusid=? AND a.clodat=? AND a.sta=3 ORDER BY a.accnum", [$t === 0 ? 2 : 3, $sub, $cus, LOAN_OPEN]);
    } elseif ($t === 2) {
        $list = rows("SELECT accnum AS depnum, agenum, 1 AS cerno, accdat, matdat, NULL AS intper FROM newpigacc WHERE subheaid=? AND cusid=? AND clodat=? ORDER BY agenum, accnum", [$sub, $cus, LOAN_OPEN]);
    } else {
        $list = rows("SELECT a.accnum AS depnum, a.agenum, 1 AS cerno, a.accdat, a.matdat, r.intper FROM newrdacc a LEFT JOIN rdinst r ON r.subheaid=a.subheaid AND r.agenum=a.agenum AND r.accnum=a.accnum
                      WHERE a.subheaid=? AND a.cusid=? AND a.clodat=? ORDER BY a.agenum, a.accnum", [$sub, $cus, LOAN_OPEN]);
    }
    $free = [];
    foreach ($list as $d) {
        $used = row("SELECT l.id FROM newloa_dep_list l JOIN newloa_dep h ON h.typofloa=l.typofloa AND h.loaid=l.loaid AND h.loanum=l.loanum AND h.cerno=l.cerno
                     WHERE l.typofloa=? AND l.loaid=? AND l.depnum=? AND l.depagenum=? AND l.sta=1 AND h.clodat=? LIMIT 1", [$t, $sub, $d['depnum'], $d['agenum'], LOAN_OPEN]);
        if ($used) continue;
        $free[] = ['key' => $d['agenum'] . ':' . $d['depnum'] . ':' . $d['cerno'], 'depnum' => (int)$d['depnum'], 'agenum' => (int)$d['agenum'], 'cerno' => (int)$d['cerno'],
                   'accdat' => $d['accdat'], 'matdat' => $d['matdat'], 'intper' => $d['intper'] !== null ? (float)$d['intper'] : null, 'balance' => deposit_balance($t, $sub, (int)$d['agenum'], (int)$d['depnum'])];
    }
    return $free;
}
function deposit_balance(int $t, int $sub, int $agenum, int $depnum): ?float { return null; }

function loan_schedule(float $san, int $instyp, int $n, int $every, string $date): array {
    $each = round($san / $n); $rows = [];
    for ($k = 1; $k <= $n; $k++) {
        $due = $instyp === 1 ? add_months($date, $k * $every) : (new DateTime($date))->modify('+' . ($k * $every) . ' days')->format('Y-m-d');
        $rows[] = ['no' => $k, 'date' => $due, 'amount' => $k < $n ? $each : $san - $each * ($n - 1)];
    }
    return ['each' => $each, 'rows' => $rows];
}
