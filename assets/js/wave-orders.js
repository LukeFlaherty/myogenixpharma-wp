(function () {
  'use strict';
  var search = document.getElementById('wo-customer-search');
  if (search) {
    var timer, controller;
    var form = search.closest('form');
    var results = document.getElementById('wo-customer-results');
    search.addEventListener('input', function () {
      clearTimeout(timer);
      if (controller) controller.abort();
      results.replaceChildren();
      var query = search.value.trim();
      if (query.length < 3) return;
      timer = setTimeout(async function () {
        controller = new AbortController();
        results.textContent = 'Searching…';
        try {
          var url = new URL(waveOrders.ajax);
          url.search = new URLSearchParams({action: 'wave_orders_customer_search', nonce: waveOrders.nonce, q: query});
          var response = await fetch(url, {credentials: 'same-origin', signal: controller.signal});
          var payload = await response.json();
          if (!response.ok || !payload.success) throw new Error('Search unavailable');
          results.replaceChildren();
          if (!payload.data.length) results.textContent = 'No account found. Enter the customer’s name and email below.';
          payload.data.forEach(function (customer) {
            var button = document.createElement('button');
            button.type = 'button'; button.className = 'wo-customer-result'; button.textContent = customer.label;
            button.addEventListener('click', function () {
              form.elements.customer_id.value = customer.id;
              ['email', 'first_name', 'last_name'].forEach(function (key) { form.elements[key].value = customer[key]; });
              form.elements.email.readOnly = true;
              document.getElementById('wo-customer-selected').textContent = 'Selected: ' + customer.label;
              results.replaceChildren();
            });
            results.appendChild(button);
          });
        } catch (error) { if (error.name !== 'AbortError') results.textContent = 'Search unavailable. Try again or enter the exact customer email below.'; }
      }, 250);
    });
    document.getElementById('wo-new-customer').addEventListener('click', function () {
      form.elements.customer_id.value = '0'; form.elements.email.readOnly = false;
      ['email', 'first_name', 'last_name'].forEach(function (key) { form.elements[key].value = ''; });
      document.getElementById('wo-customer-selected').textContent = 'New customer or guest. An exact email match will reuse an existing account.';
      search.value = ''; results.replaceChildren(); form.elements.first_name.focus();
    });
    var productSearch = document.getElementById('wo-product-search');
    var selects = Array.from(document.querySelectorAll('#wo-lines select'));
    var choices = Array.from(selects[0].options).slice(1).map(function (o) { return {value:o.value, label:o.textContent}; });
    productSearch.addEventListener('input', function () {
      var q = productSearch.value.trim().toLowerCase();
      selects.forEach(function (select) {
        var value = select.value;
        while (select.options.length > 1) select.remove(1);
        choices.filter(function (o) { return o.value === value || o.label.toLowerCase().includes(q); }).forEach(function (o) { select.add(new Option(o.label, o.value)); });
        select.value = value;
      });
    });
  }
  var recent = document.getElementById('wo-recent-search');
  if (recent) recent.addEventListener('input', function () {
    document.querySelectorAll('[data-wo-recent]').forEach(function (row) { row.hidden = !row.textContent.toLowerCase().includes(recent.value.trim().toLowerCase()); });
  });
  var copy = document.getElementById('wo-copy');
  if (copy) copy.addEventListener('click', async function () {
    var link = document.getElementById('wo-link'); link.select();
    try { await navigator.clipboard.writeText(link.value); copy.textContent = 'Copied'; }
    catch (error) { copy.textContent = 'Link selected — copy manually'; }
  });
  document.querySelectorAll('.wo-form').forEach(function (form) {
    form.addEventListener('submit', function () { form.querySelectorAll('button[type="submit"],button:not([type])').forEach(function (b) { b.disabled = true; }); });
  });
})();
