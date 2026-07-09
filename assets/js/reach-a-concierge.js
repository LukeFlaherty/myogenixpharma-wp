(function () {
	'use strict';

	var WEBHOOK_URL = 'https://services.leadconnectorhq.com/hooks/CTnsDDgYzrLg4A5wA7Q3/webhook-trigger/67a479f9-e823-4253-b8b7-b6fc1bf5f6ab';

	document.addEventListener('DOMContentLoaded', function () {
		var form      = document.getElementById('rac-form');
		var emailEl   = document.getElementById('rac-email');
		var productsEl = document.getElementById('rac-products');
		var messageEl = document.getElementById('rac-message');
		var errEl     = document.getElementById('rac-error');
		var btn       = document.getElementById('rac-submit');

		if (!form) return;

		// Prefill email from Klaviyo URL param, e.g. ?email=jane%40example.com
		var params = new URLSearchParams(window.location.search);
		var prefillEmail = params.get('email');
		if (prefillEmail) {
			emailEl.value = decodeURIComponent(prefillEmail);
		}

		form.addEventListener('submit', function (e) {
			e.preventDefault();

			var email = emailEl.value.trim();
			var selectedProducts = Array.prototype.map.call(
				productsEl.selectedOptions || [],
				function (opt) { return opt.value; }
			);
			var message = messageEl.value.trim();

			if (!email || !/\S+@\S+\.\S+/.test(email)) {
				errEl.textContent = 'Please enter a valid email address.';
				errEl.style.display = 'block';
				return;
			}

			errEl.style.display = 'none';
			btn.disabled = true;
			btn.textContent = 'Sending…';

			fetch(WEBHOOK_URL, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					email: email,
					products: selectedProducts,
					message: message,
					source: 'Reach a Concierge Page'
				})
			}).then(function () {
				form.style.display = 'none';
				document.getElementById('rac-success').style.display = 'block';
			}).catch(function () {
				btn.disabled = false;
				btn.textContent = 'Contact Me';
				errEl.textContent = 'Something went wrong. Please try again.';
				errEl.style.display = 'block';
			});
		});
	});
})();
