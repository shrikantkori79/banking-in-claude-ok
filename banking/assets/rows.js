// Editable grids: <div data-rows> with <template>, [data-add] button, rows removable with [data-del]
document.querySelectorAll('[data-rows]').forEach(function (box) {
  var tb = box.querySelector('tbody'), tpl = box.querySelector('template'), n = 0;
  var add = box.querySelector('[data-add]');
  if (add) add.addEventListener('click', function () {
    tb.insertAdjacentHTML('beforeend', tpl.innerHTML.split('__i__').join('n' + (n++)));
    var f = tb.lastElementChild.querySelector('input'); if (f) f.focus();
  });
  box.addEventListener('click', function (ev) { if (ev.target.matches('[data-del]')) ev.target.closest('tr').remove(); });
});
// Account pickers: pick from the list, press Add, then enter the amount
document.querySelectorAll('[data-links]').forEach(function (box) {
  var sel = box.querySelector('select'), tb = box.querySelector('tbody'), name = box.dataset.links;
  box.querySelector('[data-pick]').addEventListener('click', function () {
    if (!sel.value) return;
    if (tb.querySelector('input[type=hidden][value="' + sel.value + '"]')) return;
    var tr = document.createElement('tr');
    tr.innerHTML = '<td></td><td><input type="hidden" name="' + name + '_acc[]"><input type="number" step="0.01" min="0" name="' + name + '_amt[]" value="0" aria-label="Amount"></td><td><button type="button" data-del class="danger">Remove</button></td>';
    tr.children[0].textContent = sel.options[sel.selectedIndex].text;
    tr.querySelector('input[type=hidden]').value = sel.value;
    tb.appendChild(tr);
  });
  box.addEventListener('click', function (ev) { if (ev.target.matches('[data-del]')) ev.target.closest('tr').remove(); });
});
