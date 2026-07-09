(function () {
	'use strict';

	var WEBHOOK_URL = 'https://services.leadconnectorhq.com/hooks/CTnsDDgYzrLg4A5wA7Q3/webhook-trigger/67a479f9-e823-4253-b8b7-b6fc1bf5f6ab';

	document.addEventListener('DOMContentLoaded', function () {
		var form        = document.getElementById('rac-form');
		var emailEl     = document.getElementById('rac-email');
		var phoneEl     = document.getElementById('rac-phone');
		var productsEl  = document.getElementById('rac-products');
		var messageEl   = document.getElementById('rac-message');
		var errEl       = document.getElementById('rac-error');
		var btn         = document.getElementById('rac-submit');

		if (!form) return;

		// Prefill email from Klaviyo URL param, e.g. ?email=jane%40example.com
		var params = new URLSearchParams(window.location.search);
		var prefillEmail = params.get('email');
		if (prefillEmail) {
			emailEl.value = decodeURIComponent(prefillEmail);
		}

		// Click-to-toggle product cards.
		productsEl.addEventListener('click', function (e) {
			var card = e.target.closest('.rac-product-card');
			if (!card) return;
			var pressed = card.getAttribute('aria-pressed') === 'true';
			card.setAttribute('aria-pressed', pressed ? 'false' : 'true');
		});

		form.addEventListener('submit', function (e) {
			e.preventDefault();

			var email = emailEl.value.trim();
			var phone = phoneEl.value.trim();
			var selectedCategories = Array.prototype.map.call(
				productsEl.querySelectorAll('.rac-product-card[aria-pressed="true"]'),
				function (card) { return card.getAttribute('data-product'); }
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

			// GHL contact fields don't include "products interested in" out of the
			// box, so also send a single pre-formatted "notes" string — map the
			// workflow's Notes/custom field to {{notes}} in addition to (or instead
			// of) the discrete fields below.
			var interestsText = selectedCategories.length ? selectedCategories.join(', ') : 'None selected';
			var notesLines = [ 'Interested in: ' + interestsText ];
			if (message) notesLines.push('Message: ' + message);
			var notes = notesLines.join('\n');

			fetch(WEBHOOK_URL, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					email: email,
					phone: phone,
					categories: selectedCategories,
					categories_text: interestsText,
					message: message,
					notes: notes,
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
