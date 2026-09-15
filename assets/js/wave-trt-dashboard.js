/* Filters only the authorized records already rendered by WordPress. */
(function () {
  'use strict';
  const root = document.getElementById('wave-trt');
  if (!root) return;
  const buttons = Array.from(root.querySelectorAll('[data-filter]'));
  const patients = Array.from(root.querySelectorAll('.wave-patient'));
  const search = root.querySelector('#wave-search');
  const counter = root.querySelector('#wave-result-count');
  let filter = 'all';
  function update() {
    const query = search.value.trim().toLowerCase();
    let count = 0;
    patients.forEach(function (patient) {
      const match = patient.dataset.filters.split(' ').includes(filter) && patient.dataset.search.includes(query);
      patient.hidden = !match;
      if (match) count++;
    });
    counter.textContent = count + (count === 1 ? ' patient' : ' patients');
    root.querySelector('#wave-empty').hidden = count !== 0;
    buttons.forEach(function (button) {
      const selected = button.dataset.filter === filter;
      button.classList.toggle('is-active', selected);
      button.setAttribute('aria-pressed', String(selected));
    });
  }
  buttons.forEach(function (button) {
    button.addEventListener('click', function () { filter = button.dataset.filter; update(); });
  });
  search.addEventListener('input', update);
}());
