(function() {
	var cta = document.getElementById('myo-review-cta');
	var form = document.getElementById('myo-review-form');
	var cancelBtn = document.getElementById('myo-review-cancel');
	var submitBtn = document.getElementById('myo-review-submit');
	var errorBox = document.getElementById('myo-review-form-error');
	var successBox = document.getElementById('myo-review-success');
	var config = window.myoReviewConfig || {};

	if (!cta || !form) return;

	function showForm() {
		cta.hidden = true;
		form.hidden = false;
		var nameField = document.getElementById('myo-review-name');
		if (nameField) nameField.focus();
	}

	function showCta() {
		form.hidden = true;
		cta.hidden = false;
	}

	function showError(message) {
		if (!errorBox) return;
		errorBox.textContent = message;
		errorBox.hidden = false;
	}

	function hideError() {
		if (!errorBox) return;
		errorBox.hidden = true;
		errorBox.textContent = '';
	}

	cta.addEventListener('click', showForm);
	if (cancelBtn) cancelBtn.addEventListener('click', showCta);

	form.addEventListener('submit', function(event) {
		event.preventDefault();
		hideError();

		var name = document.getElementById('myo-review-name');
		var email = document.getElementById('myo-review-email');

		if (!name || !name.value.trim()) {
			showError('Please enter your name.');
			return;
		}
		if (!email || !email.value.trim()) {
			showError('Please enter your email.');
			return;
		}

		var ratingInputs = form.querySelectorAll('.myo-star-rating input:checked');
		if (!ratingInputs.length) {
			showError('Please select at least one product you purchased and give it a star rating.');
			return;
		}

		var formData = new FormData(form);
		formData.append('action', 'myogenix_submit_review');
		formData.append('nonce', config.nonce || '');

		if (submitBtn) {
			submitBtn.disabled = true;
			submitBtn.textContent = 'Submitting...';
		}

		fetch(config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		})
			.then(function(response) { return response.json(); })
			.then(function(data) {
				if (data && data.success) {
					form.hidden = true;
					if (successBox) successBox.hidden = false;
				} else {
					var message = (data && data.data && data.data.message) || 'Something went wrong. Please try again.';
					showError(message);
				}
			})
			.catch(function() {
				showError('Something went wrong. Please check your connection and try again.');
			})
			.finally(function() {
				if (submitBtn) {
					submitBtn.disabled = false;
					submitBtn.textContent = 'Submit Review';
				}
			});
	});
})();
