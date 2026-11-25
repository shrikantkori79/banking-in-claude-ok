<?php
require __DIR__ . '/../inc/bootstrap.php';
require_perm('p_master');

const KINDS = ['share' => 'Share master settings', 'deposit' => 'Create deposit ledger (SB / current)', 'term' => 'Create TD master', 'fd' => 'FD master', 'cc' => 'Create CC master',
               'pigmy' => 'Pigmy master', 'rd' => 'Create RD master', 'loan' => 'Loan master'];
const NAME_LABEL = ['share' => 'Share name', 'rd' => 'RD name'];
// which sections each master shows
const SECTIONS = [
    'share'   => ['face', 'links'],
    'deposit' => ['txn', 'intsb', 'links'],
    'cc'      => ['appdate', 'slabs_cc', 'loan', 'maturity', 'premat_type'],
    'rd'      => ['appdate', 'slabs_rd', 'comm', 'loan', 'default', 'premat'],
    'term'    => ['appdate', 'slabs_td', 'loan', 'maturity', 'premat'],
    'loan'    => ['loan_int', 'pledge', 'links'],
    'fd'      => ['appdate', 'slabs_cc', 'loan', 'maturity', 'premat_type'],
    'pigmy'   => ['appdate', 'pig_mode', 'slabs_pg', 'pig_calc', 'comm', 'loan'],
];
const INT_TYPES = [1 => 'Simple interest', 2 => 'Penal interest'];
const PLEDGE = [1 => 'Agricultural land', 2 => 'Non agricultural land', 3 => 'Gold asset', 4 => 'Vehicle', 5 => 'Against constructed house', 6 => 'Pay slip', 7 => 'Director', 8 => 'Sureties'];
$kind = $_REQUEST['kind'] ?? 'share';
if (!isset(KINDS[$kind])) { http_response_code(404); exit('Unknown master.'); }
$sec = SECTIONS[$kind];
$has = fn(string $s) => in_array($s, $sec, true);
$self = "master/scheme.php?kind=$kind";

function num(string $k, float $max = 99999999999.0): float {
    $v = (float)str_replace(',', '', post($k));
    if ($v < 0 || $v > $max) throw new InvalidArgumentException('A value is out of range.');
    return $v;
}
function grid_rows(string $key, array $fields): array {
    $out = [];
    foreach (($_POST[$key] ?? []) as $r) {
        if (!is_array($r)) continue;
        $row = []; $empty = true;
        foreach ($fields as $f) { $v = trim((string)($r[$f] ?? '')); if ($v !== '') $empty = false; $row[$f] = $v; }
        if (!$empty) $out[] = $row;
    }
    return $out;
}
function links_in(string $p, string $table, string $col): array {
    $ids = $_POST["{$p}_acc"] ?? []; $amts = $_POST["{$p}_amt"] ?? []; $out = [];
    foreach ($ids as $i => $id) {
        $id = (int)$id; $amt = (float)($amts[$i] ?? 0);
        if (!$id || isset($out[$id])) continue;
        if ($amt < 0) throw new InvalidArgumentException('Amounts cannot be negative.');
        if (!row("SELECT id FROM `$table` WHERE id=?", [$id])) throw new InvalidArgumentException('A selected account no longer exists.');
        $out[$id] = $amt;
    }
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    try {
        $name = post('name');
        if ($name === '') throw new InvalidArgumentException('Enter the name.');
        if (row('SELECT id FROM scheme_master WHERE kind=? AND name=? AND id<>?', [$kind, $name, $id])) throw new InvalidArgumentException('This name is already used in this master.');
        $d = ['kind' => $kind, 'name' => $name, 'namekan' => post('namekan'), 'active' => isset($_POST['active']) ? 1 : 0];
        if ($has('face'))  $d['facevalue'] = num('facevalue');
        if ($has('txn'))   { $d['bal_cheque'] = num('bal_cheque'); $d['bal_nocheque'] = num('bal_nocheque'); $d['min_tran'] = num('min_tran'); $d['max_cash_tran'] = num('max_cash_tran'); }
        if ($has('intsb')) { $d['min_days'] = (int)num('min_days', 3650); $d['intrate'] = num('intrate', 100); }
        if ($has('basic')) { $d['intrate'] = num('intrate', 100); $d['penalrate'] = num('penalrate', 100); $d['period_months'] = (int)num('period_months', 1200); $d['min_tran'] = num('min_tran'); }
        if ($has('appdate')) $d['intappdate'] = post('intappdate') ?: null;
        if ($has('loan'))  { $d['loan_pct'] = num('loan_pct', 100); $d['loan_int'] = num('loan_int', 100); $d['loan_pen'] = num('loan_pen', 100); }
        if ($has('maturity')) { $d['auto_renew'] = isset($_POST['auto_renew']) ? 1 : 0; $d['int_after_mat'] = num('int_after_mat', 100); $d['mat_days'] = (int)num('mat_days', 3650); }
        if ($has('premat') || $has('premat_type')) $d['premat_ded'] = num('premat_ded', 100);
        if ($has('premat_type')) {
            $t = (int)post('premat_typeid');
            if ($t && !row("SELECT id FROM scheme_master WHERE id=? AND kind='term'", [$t])) throw new InvalidArgumentException('Choose a term deposit master for the deduction.');
            $d['premat_typeid'] = $t ?: null;
        }
        if ($has('default')) { $d['def_days'] = (int)num('def_days', 3650); $d['def_pen'] = num('def_pen'); $d['def_pen_pct'] = isset($_POST['def_pen_pct']) ? 1 : 0; if ($d['def_pen_pct'] && $d['def_pen'] > 100) throw new InvalidArgumentException('Penalty % cannot exceed 100.'); }

        if ($has('pig_mode')) $d['pig_mode'] = (int)post('pig_mode') === 2 ? 2 : 1;
        if ($has('pig_calc')) { $d['calc_daybase'] = isset($_POST['calc_daybase']) ? 1 : 0; $d['calc_production'] = isset($_POST['calc_production']) ? 1 : 0; }
        if ($has('pledge')) $d['pledge_compulsory'] = isset($_POST['pledge_compulsory']) ? 1 : 0;
        $slabs = [];
        foreach (grid_rows('slab', ['slabname', 'from_m', 'from_d', 'to_m', 'to_d', 'times', 'intrate']) as $r) {
            if ($r['intrate'] === '' || (float)$r['intrate'] < 0 || (float)$r['intrate'] > 100) throw new InvalidArgumentException('Every slab row needs an interest % between 0 and 100.');
            foreach (['from_m', 'from_d', 'to_m', 'to_d', 'times'] as $f) if ($r[$f] !== '' && (!ctype_digit($r[$f]) || (int)$r[$f] > 1200)) throw new InvalidArgumentException('Months, days and times must be whole numbers.');
            foreach (['from_d', 'to_d'] as $f) if ((int)$r[$f] > 31) throw new InvalidArgumentException('Days must be between 0 and 31.');
            if ((int)$r['times'] === 1) throw new InvalidArgumentException('Times of deposit must be blank or 0, or 2 and above.');
            $slabs[] = $r;
        }
        if ($kind === 'term' || $kind === 'pigmy') {
            $by = [];
            foreach ($slabs as $r) {
                $f = (int)$r['from_m'] * 30 + (int)$r['from_d']; $t = (int)$r['to_m'] * 30 + (int)$r['to_d'];
                if ($f > $t) throw new InvalidArgumentException('In every row the From period must not be later than the To period.');
                $by[$r['slabname']][] = [$f, $t];
            }
            if ($kind === 'pigmy') foreach ($by as $g) {
                usort($g, fn($a, $b) => $a[0] <=> $b[0]);
                for ($i = 1; $i < count($g); $i++) if ($g[$i][0] <= $g[$i - 1][1]) throw new InvalidArgumentException('Period ranges overlap. Each month and day range must start after the previous one ends.');
            }
        }
        $comm = [];
        foreach (grid_rows('comm', ['minamt', 'maxamt', 'pct']) as $r) {
            if ((float)$r['minamt'] < 0 || (float)$r['maxamt'] < (float)$r['minamt'] || (float)$r['pct'] < 0 || (float)$r['pct'] > 100) throw new InvalidArgumentException('Check the agent commission rows: max must not be below min, and % must be 0 to 100.');
            $comm[] = $r;
        }
        $ints = [];
        if ($has('loan_int')) foreach (grid_rows('int', ['intname', 'inttyp', 'intrate', 'app_after', 'effdate']) as $r) {
            if ($r['intname'] === '' && $r['intrate'] === '') continue;
            if ($r['intname'] === '' || !isset(INT_TYPES[(int)$r['inttyp']])) throw new InvalidArgumentException('Every interest row needs a name and a type.');
            if ($r['intrate'] === '' || (float)$r['intrate'] < 0 || (float)$r['intrate'] > 100) throw new InvalidArgumentException('Interest % must be between 0 and 100.');
            if ($r['app_after'] !== '' && (!ctype_digit($r['app_after']) || (int)$r['app_after'] > 36500)) throw new InvalidArgumentException('Applicable after must be a whole number of days.');
            if ($r['effdate'] !== '' && !DateTime::createFromFormat('Y-m-d', $r['effdate'])) throw new InvalidArgumentException('Check the applicable date.');
            $ints[] = $r;
        }
        $pledge = [];
        if ($has('pledge')) foreach (($_POST['pledge'] ?? []) as $pid) { $pid = (int)$pid; if (isset(PLEDGE[$pid])) $pledge[$pid] = $pid; }
        $pl = $has('links') ? links_in('pl', 'prolosmas', 'id') : [];
        $bs = $has('links') ? links_in('bs', 'balaccmas', 'id') : [];

        $pdo = db(); $pdo->beginTransaction();
        $id = upsert('scheme_master', $id ?: null, $d);
        foreach (['scheme_slab', 'scheme_comm', 'scheme_int', 'scheme_pledge', 'scheme_pl', 'scheme_bs'] as $t) q("DELETE FROM `$t` WHERE scheme_id=?", [$id]);
        foreach ($slabs as $r) q('INSERT INTO scheme_slab (scheme_id,slabname,from_m,from_d,to_m,to_d,times,intrate) VALUES (?,?,?,?,?,?,?,?)',
            [$id, $r['slabname'], (int)$r['from_m'], (int)$r['from_d'], (int)$r['to_m'], (int)$r['to_d'], (int)$r['times'], (float)$r['intrate']]);
        foreach ($ints as $r) q('INSERT INTO scheme_int (scheme_id,intname,inttyp,intrate,app_after,effdate) VALUES (?,?,?,?,?,?)',
            [$id, $r['intname'], (int)$r['inttyp'], (float)$r['intrate'], (int)$r['app_after'], $r['effdate'] ?: null]);
        foreach ($pledge as $pid) q('INSERT INTO scheme_pledge (scheme_id,pledgeid) VALUES (?,?)', [$id, $pid]);
        foreach ($comm as $r) q('INSERT INTO scheme_comm (scheme_id,minamt,maxamt,pct) VALUES (?,?,?,?)', [$id, (float)$r['minamt'], (float)$r['maxamt'], (float)$r['pct']]);
        foreach ($pl as $a => $amt) { $h = row('SELECT heaid FROM prolosmas WHERE id=?', [$a]); q('INSERT INTO scheme_pl (scheme_id,heaid,proid,amount) VALUES (?,?,?,?)', [$id, $h['heaid'], $a, $amt]); }
        foreach ($bs as $a => $amt) { $h = row('SELECT maiid,subid FROM balaccmas WHERE id=?', [$a]); q('INSERT INTO scheme_bs (scheme_id,maiid,subid,accid,amount) VALUES (?,?,?,?,?)', [$id, $h['maiid'], $h['subid'], $a, $amt]); }
        $pdo->commit();
        flash('Saved.'); go("$self&id=$id");
    } catch (InvalidArgumentException $ex) {
        flash($ex->getMessage(), 'err');
    } catch (Throwable $ex) {
        if (db()->inTransaction()) db()->rollBack();
        flash(fail_msg($ex, 'Could not save. Nothing was changed.'), 'err');
    }
    go($self . ($id ? "&id=$id" : ''));
}

$cur = ($i = (int)($_GET['id'] ?? 0)) ? row('SELECT * FROM scheme_master WHERE id=? AND kind=?', [$i, $kind]) : null;
$sid = (int)($cur['id'] ?? 0);
$slabs = $sid ? rows('SELECT * FROM scheme_slab WHERE scheme_id=? ORDER BY id', [$sid]) : [];
$comms = $sid ? rows('SELECT * FROM scheme_comm WHERE scheme_id=? ORDER BY minamt', [$sid]) : [];
$ints = $sid ? rows('SELECT * FROM scheme_int WHERE scheme_id=? ORDER BY inttyp, id', [$sid]) : ($kind === 'loan' ? [
    ['intname' => 'SI1', 'inttyp' => 1, 'intrate' => 0, 'app_after' => 0, 'effdate' => ''], ['intname' => 'SI2', 'inttyp' => 1, 'intrate' => 0, 'app_after' => 0, 'effdate' => ''],
    ['intname' => 'PI', 'inttyp' => 2, 'intrate' => 0, 'app_after' => 0, 'effdate' => ''], ['intname' => 'PI2', 'inttyp' => 2, 'intrate' => 0, 'app_after' => 0, 'effdate' => '']] : []);
$pledged = $sid ? array_column(rows('SELECT pledgeid FROM scheme_pledge WHERE scheme_id=?', [$sid]), 'pledgeid') : [];
$plAll = rows("SELECT a.id, CONCAT(m.maihea,'--',a.accnam) lbl FROM prolosmas a JOIN prolosmaimas m ON m.id=a.heaid ORDER BY m.maihea, a.accnam");
$bsAll = rows("SELECT a.id, CONCAT(m.maihea,'--',s.subhea,'--',a.accnam) lbl FROM balaccmas a JOIN balmaimas m ON m.id=a.maiid JOIN balsubmas s ON s.id=a.subid ORDER BY m.maihea, s.subhea, a.accnam");
$plSel = $sid ? rows("SELECT l.proid id, l.amount, CONCAT(m.maihea,'--',a.accnam) lbl FROM scheme_pl l JOIN prolosmas a ON a.id=l.proid JOIN prolosmaimas m ON m.id=a.heaid WHERE l.scheme_id=?", [$sid]) : [];
$bsSel = $sid ? rows("SELECT l.accid id, l.amount, CONCAT(m.maihea,'--',s.subhea,'--',a.accnam) lbl FROM scheme_bs l JOIN balaccmas a ON a.id=l.accid JOIN balmaimas m ON m.id=a.maiid JOIN balsubmas s ON s.id=a.subid WHERE l.scheme_id=?", [$sid]) : [];
$terms = rows("SELECT id,name FROM scheme_master WHERE kind='term' ORDER BY name");
$slabNames = array_column(rows("SELECT DISTINCT slabname FROM scheme_slab WHERE slabname<>'' ORDER BY slabname"), 'slabname');
$list = rows('SELECT id,name,active FROM scheme_master WHERE kind=? ORDER BY id', [$kind]);
$v = fn(string $k, $def = '') => e($cur[$k] ?? $def);

function slab_cols(string $kind): array {
    return match ($kind) {
        'pigmy' => [['from_m', 'From month', 'number'], ['from_d', 'From day', 'number'], ['to_m', 'To month', 'number'], ['to_d', 'To day', 'number'], ['intrate', 'Int % / commission %', 'number']],
        'cc', 'fd' => [['slabname', 'Slab name', 'text'], ['to_m', 'To month', 'number'], ['to_d', 'To day', 'number'], ['intrate', 'Int %', 'number'], ['times', 'Times of deposit', 'number']],
        'rd' => [['to_m', 'To month', 'number'], ['intrate', 'Int %', 'number']],
        default => [['from_m', 'From month', 'number'], ['from_d', 'From day', 'number'], ['to_m', 'To month', 'number'], ['to_d', 'To day', 'number'], ['intrate', 'Int %', 'number'], ['slabname', 'Slab name', 'text']],
    };
}
function grid_html(string $key, array $cols, array $data): void {
    $cell = function (array $c, string $idx, $val) use ($key): string {
        $name = $key . '[' . $idx . '][' . $c[0] . ']'; $lab = e($c[1]);
        if ($c[2] === 'select') {
            $h = '<td><select name="' . $name . '" aria-label="' . $lab . '">';
            foreach ($c[3] as $k => $t) $h .= '<option value="' . $k . '"' . ((string)$val === (string)$k ? ' selected' : '') . '>' . e($t) . '</option>';
            return $h . '</select></td>';
        }
        if ($c[2] === 'text') $attr = 'type="text" maxlength="60"' . ($key === 'slab' ? ' list="slabnames"' : '');
        elseif ($c[2] === 'date') $attr = 'type="date"';
        else $attr = 'type="number" min="0" step="' . (in_array($c[0], ['intrate', 'pct'], true) ? '0.01' : '1') . '"' . ($c[0] === 'times' ? ' placeholder="0"' : '');
        return '<td><input ' . $attr . ' name="' . $name . '" value="' . e($val) . '" aria-label="' . $lab . '"></td>';
    };
    echo '<div data-rows><table class="minigrid"><thead><tr>';
    foreach ($cols as $c) echo '<th>' . e($c[1]) . '</th>';
    echo '<th></th></tr></thead><tbody>';
    foreach ($data as $n => $r) { echo '<tr>'; foreach ($cols as $c) echo $cell($c, 'e' . $n, $r[$c[0]] ?? ''); echo '<td><button type="button" data-del class="danger">Remove</button></td></tr>'; }
    echo '</tbody></table><template><tr>';
    foreach ($cols as $c) echo $cell($c, '__i__', '');
    echo '<td><button type="button" data-del class="danger">Remove</button></td></tr></template><button type="button" data-add style="margin-top:8px">Add row</button></div>';
}
function link_box(string $name, string $title, array $all, array $sel): void {
    echo '<div data-links="' . $name . '"><h2>' . e($title) . '</h2><div class="picker"><select aria-label="' . e($title) . '"><option value="">Select account</option>';
    foreach ($all as $a) echo '<option value="' . (int)$a['id'] . '">' . e($a['lbl']) . '</option>';
    echo '</select><button type="button" data-pick>Add</button></div><table class="minigrid"><thead><tr><th>Account</th><th>Amount</th><th></th></tr></thead><tbody>';
    foreach ($sel as $r) echo '<tr><td>' . e($r['lbl']) . '</td><td><input type="hidden" name="' . $name . '_acc[]" value="' . (int)$r['id'] . '"><input type="number" step="0.01" min="0" name="' . $name . '_amt[]" value="' . e($r['amount']) . '" aria-label="Amount"></td><td><button type="button" data-del class="danger">Remove</button></td></tr>';
    echo '</tbody></table>' . ($all ? '' : '<p class="muted">No accounts yet. Create them under the Profit Loss / Balancesheet heads menus first.</p>') . '</div>';
}
function pct(string $label, string $name, $val, string $step = '0.01'): void {
    echo '<div class="field narrow"><label for="' . $name . '">' . e($label) . '</label><input type="number" step="' . $step . '" min="0" id="' . $name . '" name="' . $name . '" value="' . e($val) . '"></div>';
}

page_start(KINDS[$kind], 'master');
?>
<datalist id="slabnames"><?php foreach ($slabNames as $n): ?><option value="<?= e($n) ?>"><?php endforeach ?></datalist>
<div class="layout wide">
<form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $sid ?>">
  <div class="card">
    <div class="field"><label for="name"><?= e(NAME_LABEL[$kind] ?? 'Deposit name') ?></label><div class="pair"><input type="text" id="name" name="name" placeholder="English" value="<?= $v('name') ?>" required autofocus><input type="text" lang="kn" name="namekan" placeholder="ಕನ್ನಡ" value="<?= $v('namekan') ?>"></div></div>
    <?php if ($has('face')) pct('Face value', 'facevalue', $cur['facevalue'] ?? ''); ?>
    <?php if ($has('appdate')): ?><div class="field narrow"><label for="intappdate">Interest applicable date</label><input type="date" id="intappdate" name="intappdate" value="<?= $v('intappdate') ?>"></div><?php endif ?>
  </div>

  <?php if ($has('txn')): ?><div class="card"><h2>Transaction detail</h2><div class="two"><div>
    <?php pct('Balance with cheque', 'bal_cheque', $cur['bal_cheque'] ?? 0); pct('Min transaction amount', 'min_tran', $cur['min_tran'] ?? 0); ?></div><div>
    <?php pct('Without cheque', 'bal_nocheque', $cur['bal_nocheque'] ?? 0); pct('Max cash tran limit', 'max_cash_tran', $cur['max_cash_tran'] ?? 0); ?></div></div></div><?php endif ?>
  <?php if ($has('intsb')): ?><div class="card"><h2>Interest settings</h2><div class="two"><?php pct('Min applicable days', 'min_days', $cur['min_days'] ?? 0, '1'); pct('Interest %', 'intrate', $cur['intrate'] ?? 0); ?></div></div><?php endif ?>
  <?php if ($has('basic')): ?><div class="card"><h2>Terms</h2><div class="two"><?php pct('Interest %', 'intrate', $cur['intrate'] ?? 0); pct('Penal interest %', 'penalrate', $cur['penalrate'] ?? 0); pct('Period (months)', 'period_months', $cur['period_months'] ?? 0, '1'); pct('Min transaction amount', 'min_tran', $cur['min_tran'] ?? 0); ?></div>
    <p class="muted">Loan, FD and Pigmy screens were not shared yet, so these are general fields.</p></div><?php endif ?>

  <?php if ($has('pig_mode')): ?><div class="card"><h2>Interest / commission</h2>
    <label class="check"><input type="radio" name="pig_mode" value="1" <?= (int)($cur['pig_mode'] ?? 1) === 1 ? 'checked' : '' ?>>Pay interest</label>
    <label class="check"><input type="radio" name="pig_mode" value="2" <?= (int)($cur['pig_mode'] ?? 1) === 2 ? 'checked' : '' ?>>Receive commission</label>
    <p class="muted">When a pigmy account is closed within one of the periods below: Pay interest works out interest for the depositor, and Receive commission works out the commission and returns it to the bank.</p></div><?php endif ?>
  <?php if ($has('pig_calc')): ?><div class="card"><h2>Interest calculation format</h2>
    <label class="check"><input type="checkbox" name="calc_daybase" value="1" <?= !empty($cur['calc_daybase']) ? 'checked' : '' ?>>Day base</label>
    <label class="check"><input type="checkbox" name="calc_production" value="1" <?= !empty($cur['calc_production']) ? 'checked' : '' ?>>Production</label></div><?php endif ?>
  <?php if ($has('slabs_cc') || $has('slabs_rd') || $has('slabs_td') || $has('slabs_pg')): ?><div class="card"><h2><?= $has('slabs_rd') ? 'Instalment detail: interest by month' : ($has('slabs_pg') ? 'Instalment detail: interest or commission by period' : 'Maturity period and interest slabs') ?></h2>
    <?php if ($has('slabs_cc')): ?><p class="muted">Slab name is only a label. Times of deposit: leave blank (saved as 0) to choose simple or compound interest when the account is opened. 2 doubles the deposit at maturity, 3 triples it, and so on.</p><?php endif ?>
    <?php grid_html('slab', slab_cols($kind), $slabs); ?></div><?php endif ?>
  <?php if ($has('comm')): ?><div class="card"><h2>Agent commission details</h2><?php grid_html('comm', [['minamt', 'Min amount', 'number'], ['maxamt', 'Max amount', 'number'], ['pct', '%', 'number']], $comms); ?></div><?php endif ?>

  <?php if ($has('loan') && $kind !== 'loan'): ?><div class="card"><h2>Loan details</h2><div class="two"><?php pct('% of loan on deposit', 'loan_pct', $cur['loan_pct'] ?? 0); pct('Interest % on loan', 'loan_int', $cur['loan_int'] ?? 0); pct('Penal interest % on loan', 'loan_pen', $cur['loan_pen'] ?? 0); ?></div></div><?php endif ?>
  <?php if ($has('maturity')): ?><div class="card"><h2>Maturity settings</h2><div class="two"><div>
    <label class="check"><input type="checkbox" name="auto_renew" value="1" <?= !empty($cur['auto_renew']) ? 'checked' : '' ?>>Auto renewal account</label></div><div>
    <?php pct('Interest % after maturity', 'int_after_mat', $cur['int_after_mat'] ?? 0); pct('Interest after maturity days', 'mat_days', $cur['mat_days'] ?? 0, '1'); ?></div></div></div><?php endif ?>
  <?php if ($has('default')): ?><div class="card"><h2>Instalment default settings</h2><div class="two"><?php pct('Days after instalment due', 'def_days', $cur['def_days'] ?? 0, '1'); pct('Penalty amount or % on deposit', 'def_pen', $cur['def_pen'] ?? 0); ?></div>
    <label class="check"><input type="checkbox" name="def_pen_pct" value="1" <?= !empty($cur['def_pen_pct']) ? 'checked' : '' ?>>Penalty is a percentage</label></div><?php endif ?>
  <?php if ($has('premat') || $has('premat_type')): ?><div class="card"><h2>Pre maturity settings</h2>
    <?php if ($has('premat_type')): ?><div class="field narrow"><label for="premat_typeid">Deduction deposit type</label><select id="premat_typeid" name="premat_typeid"><option value="">None</option><?php foreach ($terms as $t): ?><option value="<?= (int)$t['id'] ?>" <?= (int)($cur['premat_typeid'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>><?= (int)$t['id'] . '-' . e($t['name']) ?></option><?php endforeach ?></select></div><?php endif ?>
    <?php pct('Deduction %', 'premat_ded', $cur['premat_ded'] ?? 0); ?></div><?php endif ?>

  <?php if ($has('loan_int')): ?><div class="card"><h2>Interest detail</h2>
    <p class="muted">Name each rate (for example SI1 for simple interest, PI for penal), then set its percentage, how many days after the loan it applies and the date it takes effect.</p>
    <?php grid_html('int', [['intname', 'Interest name', 'text'], ['inttyp', 'Type', 'select', INT_TYPES], ['intrate', 'Int %', 'number'], ['app_after', 'Applicable after (days)', 'number'], ['effdate', 'Applicable date', 'date']], $ints); ?></div><?php endif ?>
  <?php if ($has('pledge')): ?><div class="card"><h2>Pledge detail</h2>
    <label class="check"><input type="checkbox" name="pledge_compulsory" value="1" <?= !empty($cur['pledge_compulsory']) ? 'checked' : '' ?>>Pledge is compulsory</label>
    <div class="two"><?php foreach (PLEDGE as $pid => $pt): ?><label class="check"><input type="checkbox" name="pledge[]" value="<?= $pid ?>" <?= in_array($pid, $pledged) ? 'checked' : '' ?>><?= e($pt) ?></label><?php endforeach ?></div></div><?php endif ?>
  <?php if ($has('links')): ?><div class="card"><h2>Income / expense and balancesheet heads</h2><p class="muted">Pick the accounts this master posts to and the amount for each, such as a form fee or entrance fee.</p>
    <div class="two"><?php link_box('pl', 'Income and expense accounts', $plAll, $plSel); link_box('bs', 'Balancesheet accounts', $bsAll, $bsSel); ?></div></div><?php endif ?>

  <div class="card"><label class="check"><input type="checkbox" name="active" value="1" <?= !$cur || $cur['active'] ? 'checked' : '' ?>>Active</label>
    <div class="actions"><button class="primary">Save</button><a class="btn" href="scheme.php?kind=<?= e($kind) ?>">Refresh</a><a class="btn" href="index.php">Exit</a></div></div>
</form>
<aside class="card sidelist"><h2><?= e(NAME_LABEL[$kind] ?? 'Existing') ?>s</h2>
  <?php foreach ($list as $r): ?><a class="<?= (int)$r['id'] === $sid ? 'on' : '' ?>" href="scheme.php?kind=<?= e($kind) ?>&id=<?= (int)$r['id'] ?>"><?= (int)$r['id'] . '-' . e($r['name']) ?><?= $r['active'] ? '' : ' (inactive)' ?></a><?php endforeach;
  if (!$list) echo '<p class="empty">None yet. Fill the form and save the first one.</p>'; ?></aside>
</div>
<script src="<?= asset('rows.js') ?>"></script>
<?php page_end();
