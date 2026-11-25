(function () {
  var $ = function (id) { return document.getElementById(id); };
  var heaid = $('heaid'), sub = $('subheaid'), accno = $('accno'), accdat = $('accdat');
  var joi = $('joi'), jointrow = $('jointrow'), body = $('holders').tBodies[0], msg = $('msg');
  var cur = null, gcur = null, counter = 0;

  function say(t) { msg.textContent = t || ''; }
  function parseId(t) { var m = /^\s*(\d+)/.exec(t || ''); return m ? m[1] : null; }

  // sub head list follows the head of account
  function fillSub(keep) {
    var list = window.SCH[heaid.value] || [];
    sub.innerHTML = '<option value="">Select</option>';
    list.forEach(function (s) { var o = document.createElement('option'); o.value = s.id; o.textContent = s.name; sub.appendChild(o); });
    sub.value = keep || '';
  }
  function nextNo() {
    accno.value = '';
    if (heaid.value === '' || sub.value === '') return;
    fetch(window.URLS.next + '?heaid=' + heaid.value + '&subheaid=' + sub.value, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); }).then(function (j) { accno.value = j.n === null ? '' : j.n; });
  }
  function loadFees() {
    var tb = $('feetbl').tBodies[0], note = $('feenote'); tb.innerHTML = ''; $('feetotal').textContent = ''; note.textContent = '';
    if (!(heaid.value === '0' || heaid.value === '1') || sub.value === '') { note.textContent = heaid.value === '2' || heaid.value === '3' ? 'Term and CC accounts have no opening fees.' : 'Choose the head and sub head to see the fees.'; return; }
    fetch(window.URLS.fees + '?heaid=' + heaid.value + '&subheaid=' + sub.value, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      j.rows.forEach(function (r, i) {
        var tr = document.createElement('tr');
        [i + 1, r.head, r.name, Number(r.amount).toFixed(2)].forEach(function (v) { var c = document.createElement('td'); c.textContent = v; tr.appendChild(c); });
        tb.appendChild(tr);
      });
      $('feetotal').textContent = Number(j.total).toFixed(2);
      if (!j.rows.length) note.textContent = 'This master has no fees set.';
    });
  }
  heaid.addEventListener('change', function () { fillSub(''); nextNo(); loadFees(); });
  sub.addEventListener('change', function () { nextNo(); loadFees(); });
  fillSub(sub.dataset.val); nextNo(); loadFees();

  // joint checkbox
  function syncJoint() { jointrow.hidden = !joi.checked; }
  joi.addEventListener('change', function () {
    if (!joi.checked && body.rows.length > 1) { say('Remove the extra customers first, or keep Joint ticked.'); joi.checked = true; }
    syncJoint();
  });
  syncJoint();

  // customer pickers (customer id or name)
  var list = $('custlist'), timer;
  function suggest(input) {
    clearTimeout(timer);
    var q = input.value.trim(); if (!q) return;
    timer = setTimeout(function () {
      fetch(window.URLS.lookup + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (a) {
        list.innerHTML = ''; a.forEach(function (t) { var o = document.createElement('option'); o.value = t; list.appendChild(o); });
      });
    }, 200);
  }
  function info(id) {
    return fetch(window.URLS.info + '?id=' + id + '&asof=' + encodeURIComponent(accdat.value), { credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }
  function resolveMain() {
    say(''); cur = null; $('gurbox').hidden = true; $('cinfo').textContent = '';
    $('pname').textContent = 'No customer selected.'; $('pphoto').hidden = true; $('psign').hidden = true;
    var id = parseId($('cust').value); if (!id) return Promise.resolve();
    return info(id).then(function (j) {
      if (!j.ok) { $('cinfo').textContent = 'Customer not found.'; return; }
      cur = j;
      $('cinfo').textContent = j.name + (j.age !== null ? ', age ' + j.age : '') + (j.minor ? ' (minor)' : '');
      $('pname').textContent = j.id + ' - ' + j.name;
      [['pphoto', j.photo], ['psign', j.sign]].forEach(function (p) { var im = $(p[0]); if (p[1]) { im.src = p[1]; im.hidden = false; } });
      $('gurbox').hidden = !j.minor;
    });
  }
  function resolveGur() {
    gcur = null; $('ginfo').textContent = '';
    var id = parseId($('gur').value); if (!id) return Promise.resolve();
    return info(id).then(function (j) {
      if (!j.ok) { $('ginfo').textContent = 'Guardian not found.'; return; }
      gcur = j;
      $('ginfo').textContent = j.name + (j.age !== null ? ', age ' + j.age : '') + (j.minor || j.type !== 1 || j.age === null ? ' - not a valid guardian' : '');
    });
  }
  $('cust').addEventListener('input', function () { suggest(this); });
  $('gur').addEventListener('input', function () { suggest(this); });
  $('cust').addEventListener('change', resolveMain);
  $('gur').addEventListener('change', resolveGur);
  accdat.addEventListener('change', function () { resolveMain().then(resolveGur); });

  // list of customers on the account
  function renumber() { Array.prototype.forEach.call(body.rows, function (tr, i) { tr.cells[0].textContent = i + 1; }); }
  function td(text) { var c = document.createElement('td'); c.textContent = text; return c; }
  $('addholder').addEventListener('click', function () {
    say('');
    if (!cur) { say('Pick a customer first.'); return; }
    if (!joi.checked && body.rows.length >= 1) { say('Tick Joint to add more than one customer.'); return; }
    if (body.querySelector('input[name$="[cusid]"][value="' + cur.id + '"]')) { say('This customer is already in the list.'); return; }
    var gid = '', gname = '', grel = $('gurrel').value.trim();
    if (cur.minor) {
      if (!gcur || gcur.minor || gcur.type !== 1 || gcur.age === null || gcur.id === cur.id) { say('Choose an adult customer as guardian.'); return; }
      if (!grel) { say('Enter the guardian relation.'); return; }
      gid = gcur.id; gname = gcur.name;
    } else { grel = ''; }
    var n = 'n' + (counter++), tr = document.createElement('tr'), c1 = document.createElement('td'), c2 = td('');
    c1.className = 'sl';
    var first = document.createElement('td');
    [['cusid', cur.id], ['nom', $('nom').value.trim()], ['rel', $('rel').value.trim()], ['gurid', gid || 0], ['gurrel', grel]].forEach(function (f) {
      var i = document.createElement('input'); i.type = 'hidden'; i.name = 'holder[' + n + '][' + f[0] + ']'; i.value = f[1]; first.appendChild(i);
    });
    first.appendChild(document.createTextNode(cur.id));
    var del = document.createElement('td'), b = document.createElement('button');
    b.type = 'button'; b.className = 'danger'; b.setAttribute('data-delrow', ''); b.textContent = 'Remove'; del.appendChild(b);
    [c1, first, td(cur.name), td($('nom').value.trim()), td($('rel').value.trim()), td(gid), td(gname), td(grel), del].forEach(function (c) { tr.appendChild(c); });
    body.appendChild(tr); renumber();
    ['cust', 'nom', 'rel', 'gur', 'gurrel'].forEach(function (k) { $(k).value = ''; });
    cur = null; gcur = null; $('gurbox').hidden = true; $('cinfo').textContent = ''; $('ginfo').textContent = '';
    $('pname').textContent = 'No customer selected.'; $('pphoto').hidden = true; $('psign').hidden = true;
  });
  body.addEventListener('click', function (ev) { if (ev.target.matches('[data-delrow]')) { ev.target.closest('tr').remove(); renumber(); } });
  renumber();
})();
