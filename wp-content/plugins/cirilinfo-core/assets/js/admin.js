(function () {
  'use strict';
  document.querySelectorAll('.ci-repeater').forEach(function (box) {
    var tbody = box.querySelector('tbody'), tpl = box.querySelector('template.ci-repeater-tpl');
    if (!tbody || !tpl) { return; }
    var next = tbody.children.length + Date.now() % 1000;
    box.querySelector('.ci-repeater-add').addEventListener('click', function () {
      var html = tpl.innerHTML.replace(/__i__/g, String(next++));
      tbody.insertAdjacentHTML('beforeend', html);
    });
    box.addEventListener('click', function (e) {
      var del = e.target.closest('.ci-repeater-del');
      if (del) { var tr = del.closest('tr'); if (tr && tbody.children.length > 1) { tr.remove(); } else if (tr) { tr.querySelectorAll('input').forEach(function (i) { if (i.type === 'checkbox') { i.checked = false; } else { i.value = ''; } }); } }
    });
  });
})();
