<?php
const PR_HEADS = [0 => ['label' => 'Pigmy deposit', 'kind' => 'pigmy'], 1 => ['label' => 'RD deposit', 'kind' => 'rd']];
const INST_TYPES = [1 => 'Monthly', 2 => 'Quarterly', 3 => 'Half yearly', 4 => 'Yearly'];
const INST_MONTHS = [1 => 1, 2 => 3, 3 => 6, 4 => 12];
const AGENT_OPEN = '1979-03-01';

function add_months(string $d, int $m): string {
    $dt = new DateTime($d); $day = (int)$dt->format('j');
    $dt->modify('first day of this month'); $dt->modify(($m >= 0 ? '+' : '') . $m . ' months');
    $dt->setDate((int)$dt->format('Y'), (int)$dt->format('n'), min($day, (int)$dt->format('t')));
    return $dt->format('Y-m-d');
}
/**
 * RD schedule. An instalment is paid at the start of each period and interest is added every month at rate/12,
 * so a monthly RD of 1000 at 11.5% for 12 months gives 12774.
 */
function rd_schedule(float $amt, int $type, int $n, float $rate, string $accdat): array {
    $pm = INST_MONTHS[$type]; $i = $rate / 100 / 12; $bal = 0.0; $dep = 0.0; $rows = [];
    for ($k = 1; $k <= $n; $k++) {
        $bal = ($bal + $amt) * pow(1 + $i, $pm); $dep += $amt;
        $rows[] = ['months' => $k * $pm, 'dep' => $amt, 'total' => $dep, 'int' => $bal - $dep, 'mat' => $bal, 'date' => add_months($accdat, ($k - 1) * $pm)];
    }
    return ['rows' => $rows, 'matamo' => round($bal), 'matdat' => add_months($accdat, $n * $pm)];
}
/** Account table of a head: 0 = pigmy, 1 = RD. */
function pr_acc_table(int $h): string { return $h === 0 ? 'newpigacc' : 'newrdacc'; }
function pig_seq_max(int $h, int $s, int $a): int {
    return $a === 0
        ? (int)row('SELECT COALESCE(MAX(agenum),0) m FROM newpigage WHERE heaid=? AND subheaid=?', [$h, $s])['m']
        : (int)row('SELECT COALESCE(MAX(accnum),0) m FROM ' . pr_acc_table($h) . ' WHERE heaid=? AND subheaid=? AND agenum=?', [$h, $s, $a])['m'];
}
function peek_pigseq(int $h, int $s, int $a): int {
    $r = row('SELECT last FROM pigseq WHERE heaid=? AND subheaid=? AND agenum=?', [$h, $s, $a]);
    return $r ? (int)$r['last'] + 1 : pig_seq_max($h, $s, $a) + 1;
}
/** Next agent number (agenum = 0) or next account number of an agent. Call inside a transaction. */
function reserve_pigseq(int $h, int $s, int $a): int {
    $r = row('SELECT last FROM pigseq WHERE heaid=? AND subheaid=? AND agenum=? FOR UPDATE', [$h, $s, $a]);
    if (!$r) { $n = pig_seq_max($h, $s, $a) + 1; q('INSERT INTO pigseq (heaid,subheaid,agenum,last) VALUES (?,?,?,?)', [$h, $s, $a, $n]); return $n; }
    $n = (int)$r['last'] + 1; q('UPDATE pigseq SET last=? WHERE heaid=? AND subheaid=? AND agenum=?', [$n, $h, $s, $a]);
    return $n;
}
function pr_schemes(): array {
    $out = [];
    foreach (PR_HEADS as $h => $d) $out[$h] = array_map(fn($r) => ['id' => (int)$r['id'], 'name' => $r['name']], rows('SELECT id, name FROM scheme_master WHERE active=1 AND kind=? ORDER BY name', [$d['kind']]));
    return $out;
}
