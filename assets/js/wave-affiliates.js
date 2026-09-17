(function () {
  'use strict';
  document.querySelectorAll('[data-wa-search]').forEach(function (input) {
    var panel = input.closest('.wa-panel');
    var table = panel.querySelector('.wa-table');
    if (!table) return;
    var rows = Array.from(table.querySelectorAll('[data-wa-row]'));
    var count = panel.querySelector('.wa-search-count');
    var limit = Number(panel.dataset.waLimit) || rows.length;
    var expanded = panel.dataset.waAll === '1';
    var expand = panel.querySelector('[data-wa-expand]');
    var sort = panel.querySelector('[data-wa-sort]');
    function update() {
      var query = input.value.trim().toLowerCase();
      var matching = 0;
      var visible = 0;
      rows.forEach(function (row) {
        var match = row.textContent.toLowerCase().includes(query);
        if (match) matching++;
        row.hidden = !match || (!query && !expanded && visible >= limit);
        if (!row.hidden) visible++;
      });
      count.textContent = query ? matching + ' matching affiliates / records' : 'Showing ' + visible + ' of ' + rows.length;
      if (expand) {
        expand.hidden = Boolean(query) || rows.length <= limit;
        expand.textContent = expanded ? 'Show top ' + limit : 'Show all ' + rows.length + ' affiliates';
        expand.setAttribute('aria-expanded', String(expanded));
      }
    }
    input.addEventListener('input', update);
    if (expand) expand.addEventListener('click', function (event) {
      event.preventDefault();
      expanded = !expanded;
      update();
    });
    if (sort) sort.addEventListener('change', function () {
      rows.sort(function (a, b) {
        return Number(b.dataset[sort.value]) - Number(a.dataset[sort.value]) || Number(b.dataset.visits) - Number(a.dataset.visits);
      });
      rows.forEach(function (row, index) {
        table.querySelector('tbody').appendChild(row);
        row.querySelector('[data-wa-rank]').textContent = index + 1;
      });
      update();
    });
    update();
  });
  document.querySelectorAll('.wa-copy').forEach(function (button) {
    button.addEventListener('click', async function () {
      var input = button.parentElement.querySelector('input');
      input.select();
      try {
        await navigator.clipboard.writeText(input.value);
        button.textContent = 'Copied';
      } catch (error) {
        button.textContent = 'URL selected — copy manually';
      }
    });
  });
  document.querySelectorAll('select[name="decision"]').forEach(function (select) {
    select.addEventListener('change', function () {
      var amount = select.closest('form').querySelector('input[name="amount"]');
      amount.disabled = select.value === 'reject';
      amount.required = select.value !== 'reject';
    });
  });
})();
