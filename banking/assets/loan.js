(function () {
  var $ = function (id) { return document.getElementById(id); };
  var S = window.LOANS, ST = window.STICKY || {}, URL = window.URLS;
  var type = $('typofloa'), name = $('loaid'), cust = $('cust'), curCus = null, picked = {};
  (ST.dep || []).forEach(function (k) { picked[k] = 1; });

  function scheme() { var l = S[type.value] || []; for (var i = 0; i < l.length; i++) if (String(l[i].id) === name.value) return l[i]; return null; }
  function parseId(t) { var m = /^\s*(\d+)/.exec(t || ''); return m ? m[1] : null; }
  function info(id) { return fetch(URL.info + '?id=' + id + '&asof=' + encodeURIComponent($('loadat').value), { credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
  function cell(t) { var c = document.createElement('td'); c.textContent = t === null || t === undefined ? '' : t; return c; }
  function dmy(s) { if (!s) return ''; var p = s.split('-'); return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : s; }
  function f2(n) { return Number(n).toFixed(2); }

  function fillNames(keep) {
    name.innerHTML = '<option value="">Select</option>';
    (S[type.value] || []).forEach(function (s) { var o = document.createElement('option'); o.value = s.id; o.textContent = s.name; name.appendChild(o); });
    name.value = keep || '';
  }
  function layout() {
    var t = type.value, dep = t !== '' && t !== '4', oth = t === '4';
    $('depcard').hidden = !dep; $('depgrp').hidden = !dep; $('insgrp').hidden = !oth; $('intcard').hidden = !oth;
  }

  // interest and pledge settings of the chosen loan master
  function renderInterest(s) {
    var tb = $('inttbl').tBodies[0]; tb.innerHTML = '';
    ((s && s.interest) || []).forEach(function (r) {
      var tr = document.createElement('tr');
      [r.intname, r.inttyp === 2 ? 'Penal' : 'Simple', f2(r.intrate), r.app_after, dmy(r.effdate)].forEach(function (v) { tr.appendChild(cell(v)); }); tb.appendChild(tr);
    });
    if (s && !(s.interest || []).length) { var tr2 = document.createElement('tr'), c = cell('No interest set in the loan master.'); c.colSpan = 5; tr2.appendChild(c); tb.appendChild(tr2); }
  }
  function syncPledge() {
    var box = $('pledgebox'); box.innerHTML = '';
    document.querySelectorAll('.pledgebtn[aria-pressed="true"]').forEach(function (b) {
      var i = document.createElement('input'); i.type = 'hidden'; i.name = 'pledge[]'; i.value = b.dataset.id; box.appendChild(i);
    });
  }
  function pledgeUI(s) {
    var other = type.value === '4';
    document.querySelectorAll('.pledgebtn').forEach(function (b) {
      var id = parseInt(b.dataset.id, 10), ok = other && (!s || !s.pledges.length || s.pledges.indexOf(id) >= 0);
      b.disabled = !ok; if (!ok) b.setAttribute('aria-pressed', 'false');
    });
    $('pledgenote').textContent = !other ? 'For deposit loans the pledged deposits are the security.'
      : (s && s.compulsory ? 'Security is compulsory for this loan: choose a pledge type, or add a surety or a director.' : 'Pledge types follow the loan master. The detail forms for each pledge type are added later.');
    syncPledge();
  }
  document.querySelectorAll('.pledgebtn').forEach(function (b) {
    b.addEventListener('click', function () { b.setAttribute('aria-pressed', b.getAttribute('aria-pressed') === 'true' ? 'false' : 'true'); syncPledge(); });
  });

  function applyScheme() {
    var s = scheme(), t = type.value; $('loanno').value = '';
    renderInterest(t === '4' ? s : null); pledgeUI(t === '4' ? s : null);
    if (!s) { clearDeps(); recalc(); return; }
    fetch(URL.api + '?op=nextno&t=' + t + '&loaid=' + s.id, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { $('loanno').value = j.n || ''; });
    if (t !== '4') { $('lpct').value = f2(s.loan_pct); $('lint').value = f2(s.loan_int); $('lpen').value = f2(s.loan_pen); loadDeps(); }
    recalc();
  }
  type.addEventListener('change', function () { fillNames(''); layout(); picked = {}; applyScheme(); });
  name.addEventListener('change', function () { picked = {}; applyScheme(); });

  // customer
  function resolveCus() {
    curCus = null; $('cinfo').textContent = '';
    var id = parseId(cust.value); if (!id) { clearDeps(); return Promise.resolve(); }
    return info(id).then(function (j) {
      if (!j.ok) { $('cinfo').textContent = 'Customer not found.'; clearDeps(); return; }
      curCus = j; $('cinfo').textContent = j.name + (j.age !== null ? ', age ' + j.age : '') + (j.minor ? ' - a minor cannot take a loan' : '');
      loadDeps();
    });
  }
  var list = $('custlist'), timer;
  function suggest(input) {
    clearTimeout(timer); var q = input.value.trim(); if (!q) return;
    timer = setTimeout(function () {
      fetch(URL.lookup + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (a) {
        list.innerHTML = ''; a.forEach(function (t) { var o = document.createElement('option'); o.value = t; list.appendChild(o); });
      });
    }, 200);
  }
  cust.addEventListener('input', function () { suggest(this); });
  $('surin').addEventListener('input', function () { suggest(this); });
  cust.addEventListener('change', resolveCus);
  $('loadat').addEventListener('change', resolveCus);

  // deposits of the customer (deposit loans)
  function clearDeps() { $('deptbl').tBodies[0].innerHTML = ''; $('depnote').textContent = ''; recalc(); }
  function loadDeps() {
    var tb = $('deptbl').tBodies[0]; tb.innerHTML = ''; $('depnote').textContent = '';
    var s = scheme(); if (!s || type.value === '4' || !curCus) { recalc(); return; }
    fetch(URL.api + '?op=deposits&t=' + type.value + '&loaid=' + s.id + '&cus=' + curCus.id, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      var rows = j.rows || [], noBal = rows.length > 0 && rows.every(function (r) { return r.balance === null; });
      rows.forEach(function (r) {
        var tr = document.createElement('tr'), c0 = document.createElement('td'), cb = document.createElement('input');
        cb.type = 'checkbox'; cb.name = 'dep[]'; cb.value = r.key; cb.checked = !!picked[r.key]; cb.setAttribute('aria-label', 'Select deposit ' + r.depnum);
        cb.dataset.bal = r.balance === null ? '' : r.balance;
        cb.addEventListener('change', function () { if (cb.checked) picked[r.key] = 1; else delete picked[r.key]; recalc(); });
        c0.appendChild(cb); tr.appendChild(c0);
        [r.depnum, r.agenum || '', r.balance === null ? '-' : f2(r.balance), dmy(r.accdat), dmy(r.matdat), r.intper === null ? '' : f2(r.intper)].forEach(function (v) { tr.appendChild(cell(v)); });
        tb.appendChild(tr);
      });
      $('depnote').textContent = !rows.length ? 'No open deposits are available for this customer in this deposit type. Closed deposits and deposits pledged to a running loan are not shown.'
        : (noBal ? 'Deposit balances come from the deposit transactions, which are not built yet, so these loans cannot be saved yet.' : '');
      recalc();
    });
  }

  // totals
  function recalc() {
    var s = scheme(), san = parseFloat($('sanloa').value);
    if (type.value !== '' && type.value !== '4') {
      var sum = 0, unknown = false, any = false;
      document.querySelectorAll('#deptbl input[type=checkbox]:checked').forEach(function (cb) { any = true; if (cb.dataset.bal === '') unknown = true; else sum += parseFloat(cb.dataset.bal); });
      $('selbal').value = !any ? '' : (unknown ? 'not available' : f2(sum));
      $('maxloan').value = !any || unknown || !s ? '' : f2(sum * s.loan_pct / 100);
    }
    var n = parseInt($('noofins').value, 10);
    $('insamo').value = san > 0 && n > 0 ? Math.round(san / n) : '';
    $('everylbl').textContent = $('instyp').value === '2' ? 'After every (days)' : ($('instyp').value === '1' ? 'After every (months)' : 'After every');
  }
  ['sanloa', 'noofins', 'instyp', 'dayas'].forEach(function (k) { $(k).addEventListener('input', recalc); $(k).addEventListener('change', recalc); });

  // sureties and directors
  function addRow(tbl, cells, hidden, val, counter) {
    var tb = $(tbl).tBodies[0], tr = document.createElement('tr');
    cells.forEach(function (t) { tr.appendChild(cell(t)); });
    var i = document.createElement('input'); i.type = 'hidden'; i.name = hidden; i.value = val; tr.cells[0].appendChild(i);
    var td = document.createElement('td'), b = document.createElement('button'); b.type = 'button'; b.className = 'danger'; b.textContent = 'Remove';
    b.addEventListener('click', function () { tr.remove(); $(counter).textContent = tb.rows.length; }); td.appendChild(b); tr.appendChild(td);
    tb.appendChild(tr); $(counter).textContent = tb.rows.length;
  }
  function hasVal(tbl, name, v) { return !!$(tbl).querySelector('input[name="' + name + '"][value="' + v + '"]'); }
  $('addsur').addEventListener('click', function () {
    var id = parseId($('surin').value), say = function (t) { $('surinfo').textContent = t; }; say('');
    if (!id) { say('Pick a customer first.'); return; }
    info(id).then(function (j) {
      if (!j.ok) { say('Customer not found.'); return; }
      if (curCus && j.id === curCus.id) { say('The borrower cannot be a surety.'); return; }
      if (hasVal('surtbl', 'sur[]', j.id)) { say('Already added.'); return; }
      addRow('surtbl', [j.id, j.name], 'sur[]', j.id, 'surcount'); $('surin').value = '';
    });
  });
  $('adddir').addEventListener('click', function () {
    var sel = $('dirin'); if (!sel.value || hasVal('dirtbl', 'dirs[]', sel.value)) return;
    addRow('dirtbl', [sel.options[sel.selectedIndex].text], 'dirs[]', sel.value, 'dircount'); sel.value = '';
  });

  // restore after a failed save
  if (ST.type !== undefined && ST.type !== '') { type.value = ST.type; fillNames(ST.loaid); }
  layout();
  (ST.sur || []).forEach(function (r) { addRow('surtbl', [r.id, r.name], 'sur[]', r.id, 'surcount'); });
  (ST.dirs || []).forEach(function (r) { addRow('dirtbl', [r.name], 'dirs[]', r.id, 'dircount'); });
  applyScheme();
  (ST.pledge || []).forEach(function (p) { var b = document.querySelector('.pledgebtn[data-id="' + p + '"]'); if (b && !b.disabled) b.setAttribute('aria-pressed', 'true'); });
  syncPledge();
  if (cust.value) resolveCus();
  recalc();
})();
