// Dependent dropdowns: <select data-filter-by="#main"> options carry data-parent="<id>"
document.querySelectorAll('select[data-filter-by]').forEach(function (child) {
  var parent = document.querySelector(child.dataset.filterBy);
  var all = Array.from(child.options);
  function apply() {
    var keep = child.value;
    child.innerHTML = '';
    all.forEach(function (o) {
      if (!o.dataset.parent || o.dataset.parent === parent.value) child.appendChild(o);
    });
    child.value = keep;
    if (child.selectedIndex < 0) child.selectedIndex = 0;
  }
  parent.addEventListener('change', apply);
  apply();
});
// "All branches" disables the branch picker
var all = document.querySelector('[data-all-branches]');
if (all) {
  var sel = document.querySelector(all.dataset.allBranches);
  var sync = function () { sel.disabled = all.checked; };
  all.addEventListener('change', sync); sync();
}
document.querySelectorAll('[data-confirm]').forEach(function (b) {
  b.addEventListener('click', function (ev) { if (!confirm(b.dataset.confirm)) ev.preventDefault(); });
});
var first = document.querySelector('[autofocus]'); if (first) first.focus();
