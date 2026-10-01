(function () {
	'use strict';
	const root = document.getElementById('wave-billing');
	if (!root) return;
	const rows = Array.from(root.querySelectorAll('[data-billing-row]'));
	const cards = Array.from(root.querySelectorAll('[data-billing-filter]'));
	const search = document.getElementById('wave-billing-search');
	const count = document.getElementById('wave-billing-result-count');
	const empty = document.getElementById('wave-billing-empty');
	let filter = 'all';

	function apply() {
		const term = (search.value || '').trim().toLowerCase();
		let visible = 0;
		rows.forEach(function (row) {
			const filterMatch = filter === 'all' || (` ${row.dataset.filters || ''} `).includes(` ${filter} `);
			const searchMatch = !term || (row.dataset.search || '').includes(term);
			row.hidden = !(filterMatch && searchMatch);
			if (!row.hidden) visible++;
		});
		count.textContent = `${visible} of ${rows.length} orders shown`;
		empty.hidden = visible !== 0;
	}

	cards.forEach(function (card) {
		card.addEventListener('click', function () {
			filter = card.dataset.billingFilter;
			cards.forEach(function (item) { item.classList.toggle('is-active', item === card); });
			apply();
		});
	});
	search.addEventListener('input', apply);
	apply();
}());
