(function () {
  var LK = window.LKDATA.lk, KN = window.LKDATA.kn;

  // fill every <select data-lk="..."> from the lookup lists (also selects added later)
  function fill(sel, want) {
    var keep = want !== undefined ? want : (sel.dataset.filled ? sel.value : (sel.dataset.val || ''));
    sel.innerHTML = '';
    var o = document.createElement('option'); o.value = ''; o.textContent = 'Select'; sel.appendChild(o);
    (LK[sel.dataset.lk] || []).forEach(function (it) {
      var op = document.createElement('option'); op.value = it.id; op.textContent = it.name; sel.appendChild(op);
    });
    sel.value = keep; sel.dataset.filled = '1';
  }
  document.querySelectorAll('select[data-lk]').forEach(function (s) { fill(s); });
  new MutationObserver(function (muts) {
    muts.forEach(function (m) { m.addedNodes.forEach(function (n) {
      if (n.nodeType !== 1) return;
      var list = n.matches('select[data-lk]') ? [n] : [];
      n.querySelectorAll('select[data-lk]').forEach(function (s) { list.push(s); });
      list.forEach(function (s) { fill(s); });
    }); });
  }).observe(document.body, { childList: true, subtree: true });

  // add a new place / caste / KYC type ... from the form
  var dlg = document.getElementById('lkdlg'), form = document.getElementById('lkform'), kind = null, target = null;
  var err = dlg.querySelector('.err');
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-lk-add]'); if (!b) return;
    kind = b.dataset.lkAdd;
    target = b.hasAttribute('data-no-target') ? null : b.parentElement.querySelector('select');
    form.reset(); err.textContent = '';
    dlg.querySelector('h2').textContent = 'Add new ' + (b.title || kind);
    document.getElementById('lkkanrow').hidden = !KN[kind];
    dlg.showModal(); document.getElementById('lkname').focus();
  });
  document.getElementById('lkcancel').addEventListener('click', function () { dlg.close(); });
  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var fd = new FormData(form); fd.append('kind', kind); fd.append('csrf', window.CSRF);
    fetch(window.LKURL, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        if (!res.ok) { err.textContent = res.j.error || 'Could not add.'; return; }
        LK[kind].push({ id: res.j.id, name: res.j.name });
        LK[kind].sort(function (a, b) { return a.name.localeCompare(b.name); });
        document.querySelectorAll('select[data-lk="' + kind + '"]').forEach(function (s) { fill(s, s === target ? String(res.j.id) : s.value); });
        dlg.close();
      })
      .catch(function () { err.textContent = 'Could not reach the server. Try again.'; });
  });

  // introducer suggestions: customers by number or name
  var intro = document.getElementById('introducer'), list = document.getElementById('introlist'), timer;
  intro.addEventListener('input', function () {
    clearTimeout(timer);
    var q = intro.value.trim(); if (!q) return;
    timer = setTimeout(function () {
      fetch(window.CUSURL + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (a) {
        list.innerHTML = ''; a.forEach(function (t) { var o = document.createElement('option'); o.value = t; list.appendChild(o); });
      });
    }, 200);
  });

  // company directors
  var dirs = document.getElementById('dirs'), tpl = document.getElementById('dirtpl'), add = document.getElementById('adddir'), n = 0;
  if (add) {
    var addBlock = function () { dirs.insertAdjacentHTML('beforeend', tpl.innerHTML.split('__i__').join('n' + (n++))); };
    add.addEventListener('click', addBlock);
    dirs.addEventListener('click', function (ev) { if (ev.target.matches('[data-deldir]')) ev.target.closest('.dirblock').remove(); });
    if (window.NEEDDIR) addBlock();
  }
})();
