( function () {
	'use strict';

	var WEBHOOK_URL = 'https://services.leadconnectorhq.com/hooks/CTnsDDgYzrLg4A5wA7Q3/webhook-trigger/67a479f9-e823-4253-b8b7-b6fc1bf5f6ab';
	var SESSION_KEY = 'myogenix_purchase_help_popup_seen';
	var DELAY_MS = 30000;

	function hideLegacyPopup() {
		var legacy = document.getElementById( 'lcp-overlay' );
		if ( legacy ) {
			legacy.style.display = 'none';
			legacy.setAttribute( 'aria-hidden', 'true' );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		hideLegacyPopup();
		if ( window.MutationObserver ) {
			new MutationObserver( hideLegacyPopup ).observe( document.body, { childList: true, subtree: true } );
		}

		var popup = document.getElementById( 'myo-purchase-popup' );
		if ( ! popup ) return;

		try {
			if ( window.sessionStorage && sessionStorage.getItem( SESSION_KEY ) === '1' ) return;
		} catch ( e ) {}

		var form = document.getElementById( 'myo-purchase-popup-form' );
		var emailEl = document.getElementById( 'myo-purchase-popup-email' );
		var phoneEl = document.getElementById( 'myo-purchase-popup-phone' );
		var messageEl = document.getElementById( 'myo-purchase-popup-message' );
		var errorEl = document.getElementById( 'myo-purchase-popup-error' );
		var submitBtn = document.getElementById( 'myo-purchase-popup-submit' );
		var successEl = document.getElementById( 'myo-purchase-popup-success' );
		var lastFocused = null;

		function remember() {
			try {
				if ( window.sessionStorage ) sessionStorage.setItem( SESSION_KEY, '1' );
			} catch ( e ) {}
		}

		function openPopup() {
			lastFocused = document.activeElement;
			popup.hidden = false;
			document.body.classList.add( 'myo-purchase-popup-open' );
			remember();
			window.setTimeout( function () {
				if ( emailEl ) emailEl.focus();
			}, 80 );
		}

		function closePopup() {
			popup.hidden = true;
			document.body.classList.remove( 'myo-purchase-popup-open' );
			if ( lastFocused && typeof lastFocused.focus === 'function' ) lastFocused.focus();
		}

		window.setTimeout( openPopup, DELAY_MS );

		popup.querySelectorAll( '[data-myo-popup-close]' ).forEach( function (el) {
			el.addEventListener( 'click', closePopup );
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && ! popup.hidden ) closePopup();
		} );

		if ( ! form ) return;
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var email = emailEl ? emailEl.value.trim() : '';
			var phone = phoneEl ? phoneEl.value.trim() : '';
			var message = messageEl ? messageEl.value.trim() : '';

			if ( ! email && ! phone ) {
				errorEl.textContent = 'Please enter an email or phone number.';
				errorEl.hidden = false;
				return;
			}

			errorEl.hidden = true;
			submitBtn.disabled = true;
			submitBtn.textContent = 'Sending...';

			var notes = [
				'Popup request: Help with purchase',
				'Message: ' + ( message || 'Help me understand which products are right for me and how the process works.' )
			].join( '\n' );

			fetch( WEBHOOK_URL, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( {
					email: email,
					phone: phone,
					categories: [],
					categories_text: 'Purchase guidance',
					message: message,
					notes: notes,
					source: 'Purchase Help Popup'
				} )
			} ).then( function () {
				form.hidden = true;
				successEl.hidden = false;
			} ).catch( function () {
				submitBtn.disabled = false;
				submitBtn.textContent = 'Contact me';
				errorEl.textContent = 'Something went wrong. You can also email customersupport@myogenixpharma.com.';
				errorEl.hidden = false;
			} );
		} );
	} );
}() );
