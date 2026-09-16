(function () {
  'use strict';
  document.querySelectorAll('[data-wa-search]').forEach(function (input) {
    var panel = input.closest('.wa-panel');
    var rows = Array.from(panel.querySelectorAll('[data-wa-row]'));
    var count = panel.querySelector('.wa-search-count');
    function update() {
      var query = input.value.trim().toLowerCase();
      var visible = 0;
      rows.forEach(function (row) {
        row.hidden = !row.textContent.toLowerCase().includes(query);
        if (!row.hidden) visible++;
      });
      count.textContent = visible + ' of ' + rows.length + ' records shown';
    }
    input.addEventListener('input', update);
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
})();
