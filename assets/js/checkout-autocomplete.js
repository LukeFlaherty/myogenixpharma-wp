(function () {
	'use strict';

	// Supports both Classic WooCommerce checkout and WC Blocks (React-based).
	// React-controlled inputs ignore direct .value = x — we must use the native
	// HTMLInputElement prototype setter to bypass React's internal tracking, then
	// dispatch a synthetic input/change event so React re-renders with the new value.

	var FIELDS = {
		billing: {
			address1: '#billing_address_1, #billing-address_1, input[autocomplete="billing address-line1"]',
			city:     '#billing_city, #billing-city, input[autocomplete="billing city"]',
			state:    '#billing_state, #billing-state, select[autocomplete="billing region"], input[autocomplete="billing region"]',
			postcode: '#billing_postcode, #billing-postcode, input[autocomplete="billing postal-code"]',
			country:  '#billing_country, #billing-country, select[autocomplete="billing country"]',
		},
		shipping: {
			address1: '#shipping_address_1, #shipping-address_1, input[autocomplete="shipping address-line1"]',
			city:     '#shipping_city, #shipping-city, input[autocomplete="shipping city"]',
			state:    '#shipping_state, #shipping-state, select[autocomplete="shipping region"], input[autocomplete="shipping region"]',
			postcode: '#shipping_postcode, #shipping-postcode, input[autocomplete="shipping postal-code"]',
			country:  '#shipping_country, #shipping-country, select[autocomplete="shipping country"]',
		}
	};

	function q( selector ) {
		return document.querySelector( selector );
	}

	function setField( el, value ) {
		if ( ! el || ! value ) return;
		var proto  = el.tagName === 'SELECT' ? HTMLSelectElement.prototype : HTMLInputElement.prototype;
		var setter = Object.getOwnPropertyDescriptor( proto, 'value' );
		if ( setter && setter.set ) {
			setter.set.call( el, value );
		} else {
			el.value = value;
		}
		el.dispatchEvent( new Event( 'input',  { bubbles: true } ) );
		el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	function fillFromPlace( place, section ) {
		var map = {};
		( place.address_components || [] ).forEach( function ( c ) {
			c.types.forEach( function ( t ) { map[ t ] = c; } );
		} );

		var streetNum = map.street_number ? map.street_number.long_name : '';
		var route     = map.route         ? map.route.long_name         : '';
		var addr1     = [ streetNum, route ].filter( Boolean ).join( ' ' );
		var city      = ( map.locality || map.sublocality_level_1 || {} ).long_name || '';
		var state     = ( map.administrative_area_level_1 || {} ).short_name || '';
		var zip       = ( map.postal_code || {} ).long_name  || '';
		var country   = ( map.country     || {} ).short_name || '';

		var f = FIELDS[ section ];

		if ( addr1 )   setField( q( f.address1 ), addr1 );
		if ( city )    setField( q( f.city ),     city );
		if ( zip )     setField( q( f.postcode ), zip );

		// Set country before state — in WC Blocks, changing country re-renders
		// the state dropdown, so state must be set after that re-render settles.
		if ( country ) setField( q( f.country ), country );
		if ( state ) {
			setTimeout( function () { setField( q( f.state ), state ); }, 400 );
		}
	}

	function attachToInput( section, selector ) {
		var input = q( selector );
		if ( ! input || input.tagName !== 'INPUT' || input.dataset.placesReady ) return;
		input.dataset.placesReady = '1';

		var ac = new google.maps.places.Autocomplete( input, {
			types: [ 'address' ],
			componentRestrictions: { country: 'us' }, // US only — remove for international
			fields: [ 'address_components' ]
		} );

		ac.addListener( 'place_changed', function () {
			fillFromPlace( ac.getPlace(), section );
		} );
	}

	function tryAttach() {
		attachToInput( 'billing',  FIELDS.billing.address1 );
		attachToInput( 'shipping', FIELDS.shipping.address1 );
	}

	// Called by the Google Maps script once the Places library is ready.
	// MutationObserver keeps watching so WC Blocks' async render and the
	// "ship to different address" toggle are both handled.
	window.initCheckoutAutocomplete = function () {
		tryAttach();

		var observer = new MutationObserver( tryAttach );
		observer.observe( document.body, { childList: true, subtree: true } );
	};
}());
