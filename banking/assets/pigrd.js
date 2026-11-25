(function () {
  var $ = function (id) { return document.getElementById(id); };
  var heaid = $('heaid'), sub = $('subheaid'), agent = $('agenum'), accno = $('accno'), accdat = $('accdat'), matdat = $('matdat');
  var rdbox = $('rdbox'), URL = window.URLS, cur = null, gcur = null, matEdited = false;
  var PM = { 1: 1, 2: 3, 3: 6, 4: 12 };

  function ymd(d) { return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); }
  function parse(s) { var p = (s || '').split('-'); return p.length === 3 ? new Date(+p[0], +p[1] - 1, +p[2]) : null; }
  function addMonths(d, m) { var t = new Date(d.getFullYear(), d.getMonth() + m + 1, 0).getDate(); return new Date(d.getFullYear(), d.getMonth() + m, Math.min(d.getDate(), t)); }
  function dmy(d) { return ('0' + d.getDate()).slice(-2) + '/' + ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][d.getMonth()] + '/' + d.getFullYear(); }
  function f2(n) { return Number(n).toFixed(2); }

  // sub head, agents, account number
  function fillSub(keep) {
    sub.innerHTML = '<option value="">Select</option>';
    (window.SCH[heaid.value] || []).forEach(function (s) { var o = document.createElement('option'); o.value = s.id; o.textContent = s.name; sub.appendChild(o); });
    sub.value = keep || '';
  }
  // calls the server and always returns {ok, status, j}; j is null when the reply is not JSON (for example a PHP error page)
  function api(url, opts) {
    return fetch(url, Object.assign({ credentials: 'same-origin' }, opts || {})).then(function (r) {
      return r.text().then(function (t) { var d = null; try { d = JSON.parse(t); } catch (e) {} return { ok: r.ok && d !== null, status: r.status, j: d }; });
    });
  }
  function apiError(res, fallback) {
    return (res.j && res.j.error) || fallback + ' (server answered ' + res.status + '). Check that upgrade.php has been run after the last update.';
  }
  function loadAgents(keep) {
    agent.innerHTML = '<option value="">Select</option>'; accno.value = '';
    if (heaid.value === '' || sub.value === '') return Promise.resolve();
    return api(URL.api + '?op=agents&heaid=' + heaid.value + '&subheaid=' + sub.value).then(function (res) {
      var a = res.j;
      if (!res.ok || !Array.isArray(a)) { $('agnote').textContent = apiError(res, 'Could not load the agents'); return; }
      $('agnote').textContent = 'Account numbers start from 1 for each agent. The number is given when you save.';
      a.forEach(function (x) { var o = document.createElement('option'); o.value = x.agenum; o.textContent = x.agenum + ' - ' + x.name; agent.appendChild(o); });
      if (!a.length) { var o = document.createElement('option'); o.value = ''; o.textContent = 'No agents yet. Use New agent'; agent.appendChild(o); }
      agent.value = keep || ''; nextNo();
    });
  }
  function nextNo() {
    accno.value = '';
    if (!agent.value) return;
    api(URL.api + '?op=next&heaid=' + heaid.value + '&subheaid=' + sub.value + '&agenum=' + agent.value).then(function (res) {
      if (!res.ok) { $('agnote').textContent = apiError(res, 'Could not get the account number'); return; }
      accno.value = res.j.n || '';
    });
  }
  function syncHead() {
    var rd = heaid.value === '1';
    rdbox.hidden = !rd; matdat.readOnly = rd;
    if (rd) recalc(); else if (!matEdited) defaultMat();
  }
  heaid.addEventListener('change', function () { fillSub(''); loadAgents(''); syncHead(); });
  sub.addEventListener('change', function () { loadAgents(''); });
  agent.addEventListener('change', nextNo);

  // maturity date
  function defaultMat() { var d = parse(accdat.value); if (d && heaid.value === '0') matdat.value = ymd(addMonths(d, 12)); }
  matdat.addEventListener('input', function () { matEdited = true; });
  accdat.addEventListener('change', function () { defaultMat(); recalc(); resolveMain().then(resolveGur); });

  // new agent dialog
  var dlg = $('agdlg'), form = $('agform'), err = dlg.querySelector('.err');
  $('newagent').addEventListener('click', function () {
    if (heaid.value === '' || sub.value === '') { alert('Choose the head of account and sub head first.'); return; }
    form.reset(); err.textContent = ''; dlg.showModal(); $('agcust').focus();
  });
  $('agcancel').addEventListener('click', function () { dlg.close(); });
  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var fd = new FormData(form); fd.append('heaid', heaid.value); fd.append('subheaid', sub.value); fd.append('csrf', window.CSRF);
    api(URL.api, { method: 'POST', body: fd }).then(function (res) {
      if (!res.ok) { err.textContent = apiError(res, 'Could not add the agent'); return; }
      dlg.close(); loadAgents(String(res.j.agenum));
    }).catch(function () { err.textContent = 'Could not reach the server. Check that the site is running.'; });
  });

  // customer pickers
  var list = $('custlist'), timer;
  function parseId(t) { var m = /^\s*(\d+)/.exec(t || ''); return m ? m[1] : null; }
  function suggest(input) {
    clearTimeout(timer); var q = input.value.trim(); if (!q) return;
    timer = setTimeout(function () {
      fetch(URL.lookup + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (a) {
        list.innerHTML = ''; a.forEach(function (t) { var o = document.createElement('option'); o.value = t; list.appendChild(o); });
      });
    }, 200);
  }
  function info(id) { return fetch(URL.info + '?id=' + id + '&asof=' + encodeURIComponent(accdat.value), { credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
  function resolveMain() {
    cur = null; $('gurbox').hidden = true; $('cinfo').textContent = '';
    var id = parseId($('cust').value); if (!id) return Promise.resolve();
    return info(id).then(function (j) {
      if (!j.ok) { $('cinfo').textContent = 'Customer not found.'; return; }
      cur = j; $('cinfo').textContent = j.name + (j.age !== null ? ', age ' + j.age : '') + (j.minor ? ' (minor)' : '');
      if (!$('macnam').value || $('macnam').dataset.auto === '1') { $('macnam').value = j.name.trim().slice(0, 12); $('macnam').dataset.auto = '1'; }
      $('gurbox').hidden = !j.minor;
    });
  }
  function resolveGur() {
    gcur = null; $('ginfo').textContent = '';
    var id = parseId($('gur').value); if (!id) return Promise.resolve();
    return info(id).then(function (j) {
      if (!j.ok) { $('ginfo').textContent = 'Guardian not found.'; return; }
      gcur = j; $('ginfo').textContent = j.name + (j.age !== null ? ', age ' + j.age : '') + (j.minor || j.type !== 1 || j.age === null ? ' - not a valid guardian' : '');
    });
  }
  $('cust').addEventListener('input', function () { suggest(this); });
  $('gur').addEventListener('input', function () { suggest(this); });
  $('agcust').addEventListener('input', function () { suggest(this); });
  $('cust').addEventListener('change', resolveMain);
  $('gur').addEventListener('change', resolveGur);
  $('macnam').addEventListener('input', function () { this.dataset.auto = '0'; });

  // RD instalment detail (same rule as the server: interest added every month at rate/12)
  function recalc() {
    var tb = $('sched').tBodies[0]; tb.innerHTML = ''; $('matamo').value = '';
    if (heaid.value !== '1') return;
    var amt = parseFloat($('insamo').value), typ = parseInt($('instyp').value, 10), n = parseInt($('noofins').value, 10),
        rate = (parseFloat($('intper').value) || 0) + (parseFloat($('offper').value) || 0), d0 = parse(accdat.value);
    if (!(amt > 0) || !PM[typ] || !(n >= 1) || n * PM[typ] > 360 || !d0) return;
    var pm = PM[typ], i = rate / 100 / 12, bal = 0, dep = 0;
    for (var k = 1; k <= n; k++) {
      bal = (bal + amt) * Math.pow(1 + i, pm); dep += amt;
      var tr = document.createElement('tr');
      [k * pm, f2(amt), f2(dep), f2(bal - dep), f2(bal), dmy(addMonths(d0, (k - 1) * pm))].forEach(function (v) { var c = document.createElement('td'); c.textContent = v; tr.appendChild(c); });
      tb.appendChild(tr);
    }
    $('matamo').value = Math.round(bal);
    matdat.value = ymd(addMonths(d0, n * pm));
  }
  ['insamo', 'instyp', 'noofins', 'intper', 'offper'].forEach(function (k) { $(k).addEventListener('input', recalc); $(k).addEventListener('change', recalc); });
  $('printsched').addEventListener('click', function () {
    if (!$('sched').tBodies[0].rows.length) { alert('Fill the instalment amount, type, number of instalments and interest % first.'); return; }
    if (!cur) { alert('Pick the customer first.'); return; }
    if (!$('accno').value) { alert('Choose the agent first so the account number is known.'); return; }
    $('ph_name').textContent = cur.name; $('ph_no').textContent = $('accno').value;
    document.body.classList.add('printing-sched'); window.print();
  });
  window.addEventListener('afterprint', function () { document.body.classList.remove('printing-sched'); });

  fillSub(sub.dataset.val);
  loadAgents(agent.dataset.val).then(function () { syncHead(); });
  if (!matdat.value) defaultMat();
  if ($('cust').value) resolveMain().then(function () { if ($('gur').value) resolveGur(); });
  recalc();
})();
