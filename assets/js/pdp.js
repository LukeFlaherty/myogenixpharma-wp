document.addEventListener( 'DOMContentLoaded', function () {

	/* -----------------------------------------------------------------------
	   Accordion helper (shared by both FAQ sections)
	----------------------------------------------------------------------- */
	function initAccordion( selector ) {
		var questions = Array.prototype.slice.call( document.querySelectorAll( selector ) );
		questions.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var isExpanded = this.getAttribute( 'aria-expanded' ) === 'true';
				var answer     = document.getElementById( this.getAttribute( 'aria-controls' ) );
				if ( ! answer ) return;
				questions.forEach( function ( otherBtn ) {
					if ( otherBtn === btn ) return;
					otherBtn.setAttribute( 'aria-expanded', 'false' );
					var other = document.getElementById( otherBtn.getAttribute( 'aria-controls' ) );
					if ( other ) other.classList.remove( 'is-open' );
				} );
				this.setAttribute( 'aria-expanded', String( ! isExpanded ) );
				if ( isExpanded ) {
					answer.classList.remove( 'is-open' );
				} else {
					answer.classList.add( 'is-open' );
				}
			} );
		} );
	}

	initAccordion( '.myogenix-pdp__faq-question' );
	initAccordion( '.myogenix-pdp__cq-question' );

	/* -----------------------------------------------------------------------
	   Product configurator
	----------------------------------------------------------------------- */
	var cfg = document.getElementById( 'pdp-cfg' );
	if ( ! cfg ) return;

	var doses     = JSON.parse( cfg.getAttribute( 'data-doses' )     || '[]' );
	var pricesOt  = JSON.parse( cfg.getAttribute( 'data-prices-ot' ) || '[]' );
	var CONSULT   = 79;
	var DISC      = 0.10;

	var state = {
		ptype:         'subscribe',
		months:        1,
		selectedDoses: [ doses[ doses.length - 1 ] ]
	};

	function subPrice( idx ) {
		return Math.round( pricesOt[ idx ] * ( 1 - DISC ) );
	}

	function doseIdx( dose ) {
		return doses.indexOf( dose );
	}

	function weeklyMg( dose ) {
		var mg = parseFloat( dose );
		return ( mg / 4 ).toFixed( 2 );
	}

	var MONTH_LABELS = [ 'First', 'Second', 'Third' ];

	/* --- Dose section render -------------------------------------------- */
	function renderDoses() {
		var wrap = document.getElementById( 'pdp-doses' );
		if ( ! wrap ) return;

		// Sync state.selectedDoses length to state.months
		while ( state.selectedDoses.length < state.months ) {
			state.selectedDoses.push( state.selectedDoses[ state.selectedDoses.length - 1 ] || doses[ doses.length - 1 ] );
		}
		state.selectedDoses = state.selectedDoses.slice( 0, state.months );

		var html = '';
		for ( var i = 0; i < state.months; i++ ) {
			var dose  = state.selectedDoses[ i ];
			var badge = i === 0 ? 'Starting dose' : 'Prev: ' + state.selectedDoses[ i - 1 ];
			var opts  = doses.map( function ( d ) {
				return '<option value="' + d + '"' + ( d === dose ? ' selected' : '' ) + '>' + d + '</option>';
			} ).join( '' );

			html +=
				'<div class="pdp-cfg__dose-card">' +
					'<div class="pdp-cfg__dose-header">' +
						'<span class="pdp-cfg__dose-month">' + MONTH_LABELS[ i ] + ' month</span>' +
						'<span class="pdp-cfg__dose-badge">' + badge + '</span>' +
					'</div>' +
					'<div class="pdp-cfg__dose-select-wrap">' +
						'<select class="pdp-cfg__dose-select" data-mi="' + i + '">' + opts + '</select>' +
						'<span class="pdp-cfg__dose-chevron">' +
							'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>' +
						'</span>' +
					'</div>' +
					'<p class="pdp-cfg__dose-detail">' + dose + '/month = <strong>' + weeklyMg( dose ) + ' mg/week</strong> · 4 injections per month</p>' +
				'</div>';
		}
		wrap.innerHTML = html;

		// Bind selects
		Array.prototype.slice.call( wrap.querySelectorAll( '.pdp-cfg__dose-select' ) ).forEach( function ( sel ) {
			sel.addEventListener( 'change', function () {
				state.selectedDoses[ parseInt( this.getAttribute( 'data-mi' ) ) ] = this.value;
				renderDoses();
				renderSummary();
			} );
		} );
	}

	/* --- Order summary render ------------------------------------------- */
	function renderSummary() {
		var wrap = document.getElementById( 'pdp-summary' );
		if ( ! wrap ) return;

		var html  = '<p class="pdp-cfg__summary-label">Order Summary</p>';
		var total = 0;

		if ( state.ptype === 'subscribe' ) {
			var totalSaving = 0;
			for ( var i = 0; i < state.months; i++ ) {
				var di    = doseIdx( state.selectedDoses[ i ] );
				var sp    = subPrice( di );
				var sav   = pricesOt[ di ] - sp;
				total    += sp;
				totalSaving += sav;
				html += '<div class="pdp-cfg__summary-line"><span>' + MONTH_LABELS[ i ] + ' month — ' + state.selectedDoses[ i ] + '</span><span>$' + sp + '</span></div>';
			}
			if ( totalSaving > 0 ) {
				html += '<div class="pdp-cfg__summary-line"><span>Subscription savings (10%)</span><span class="pdp-cfg__summary-savings">−$' + totalSaving + '</span></div>';
			}
			html += '<div class="pdp-cfg__summary-total"><span>Total today</span><span class="pdp-cfg__summary-total-price">$' + total + '</span></div>';
			var lastDi     = doseIdx( state.selectedDoses[ state.months - 1 ] );
			var renewPrice = subPrice( lastDi );
			html += '<div class="pdp-cfg__summary-note"><strong>Auto-renews</strong> at <strong>' + state.selectedDoses[ state.months - 1 ] + ' · $' + renewPrice + '/mo</strong> after your supply ends. Cancel anytime before renewal.</div>';

		} else {
			for ( var j = 0; j < state.months; j++ ) {
				var di2  = doseIdx( state.selectedDoses[ j ] );
				var ot   = pricesOt[ di2 ];
				total   += ot;
				html    += '<div class="pdp-cfg__summary-line"><span>' + MONTH_LABELS[ j ] + ' month — ' + state.selectedDoses[ j ] + '</span><span>$' + ot + '</span></div>';
			}
			html += '<div class="pdp-cfg__summary-line"><span>Provider consultation</span><span>$' + CONSULT + '</span></div>';
			total += CONSULT;
			html += '<div class="pdp-cfg__summary-total"><span>Total today</span><span class="pdp-cfg__summary-total-price">$' + total + '</span></div>';
			html += '<div class="pdp-cfg__summary-note">A licensed provider reviews your order before it ships. The consult fee is non-refundable once your order is approved.</div>';
		}

		wrap.innerHTML = html;
	}

	/* --- CTA / disclaimer update ---------------------------------------- */
	function updateCTA() {
		var btn  = document.getElementById( 'pdp-cta' );
		var disc = document.getElementById( 'pdp-disclaimer' );
		if ( ! btn || ! disc ) return;
		if ( state.ptype === 'subscribe' ) {
			btn.textContent  = 'Start subscription \u2192';
			disc.textContent = 'By subscribing, you authorize recurring charges at your renewal dose price. Cancel anytime.';
		} else {
			btn.textContent  = 'Order one-time \u2192';
			disc.textContent = 'A licensed provider reviews your order before it ships. The consult fee is non-refundable once your order is approved.';
		}
	}

	function render() {
		renderDoses();
		renderSummary();
		updateCTA();
	}

	/* --- Event bindings ------------------------------------------------- */
	Array.prototype.slice.call( cfg.querySelectorAll( '.pdp-cfg__ptype' ) ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			state.ptype = this.getAttribute( 'data-ptype' );
			Array.prototype.slice.call( cfg.querySelectorAll( '.pdp-cfg__ptype' ) ).forEach( function ( b ) {
				b.classList.remove( 'pdp-cfg__ptype--active' );
			} );
			this.classList.add( 'pdp-cfg__ptype--active' );
			render();
		} );
	} );

	Array.prototype.slice.call( cfg.querySelectorAll( '.pdp-cfg__supply' ) ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			state.months = parseInt( this.getAttribute( 'data-months' ), 10 );
			Array.prototype.slice.call( cfg.querySelectorAll( '.pdp-cfg__supply' ) ).forEach( function ( b ) {
				b.classList.remove( 'pdp-cfg__supply--active' );
			} );
			this.classList.add( 'pdp-cfg__supply--active' );
			render();
		} );
	} );

	/*
	 * CTA — sync selections to WC hidden variations form and add to cart.
	 *
	 * Attribute slug mapping (based on CLAUDE.md product attributes):
	 *   pa_dosage                   = selected dose  (e.g. "10mg")
	 *   pa_vial                     = supply months  (1→"1-vial", 2→"2-vial", 3→"3-vial")
	 *   pa_wm-subscription-plan     = subscribe plan (subscribe 1/2mo→"1-month", 3mo→"3-month")
	 *                                                 (one-time = leave blank / no plan attribute)
	 *
	 * If the variation is not found (button stays disabled), the handler falls back
	 * to a URL-based add-to-cart so the user can still proceed.
	 */
	var VIAL_MAP = { 1: '1-vial', 2: '2-vial', 3: '3-vial' };
	var PLAN_MAP = {
		subscribe: { 1: '1-month', 2: '1-month', 3: '3-month' },
		onetime:   { 1: '',        2: '',        3: ''        }
	};

	var ctaBtn = document.getElementById( 'pdp-cta' );
	if ( ctaBtn ) {
		ctaBtn.addEventListener( 'click', function () {
			var wcForm = document.querySelector( '.variations_form' );

			if ( ! wcForm ) {
				// Simple (non-variable) product fallback
				var pid = cfg.getAttribute( 'data-product-id' );
				if ( pid ) window.location.href = '/?add-to-cart=' + pid + '&quantity=1';
				return;
			}

			var dosage = state.selectedDoses[ 0 ];
			var vial   = VIAL_MAP[ state.months ] || '1-vial';
			var plan   = ( PLAN_MAP[ state.ptype ] || {} )[ state.months ] || '';

			function setSelect( name, val ) {
				var sel = wcForm.querySelector( 'select[name="' + name + '"]' );
				if ( sel ) {
					sel.value = val;
					sel.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}
			}

			setSelect( 'attribute_pa_dosage', dosage );
			setSelect( 'attribute_pa_vial', vial );
			setSelect( 'attribute_pa_wm-subscription-plan', plan );

			// Give WC ~600 ms to resolve the variation before clicking
			setTimeout( function () {
				var addBtn = wcForm.querySelector( '.single_add_to_cart_button:not(.disabled):not([disabled])' );

				if ( addBtn ) {
					addBtn.click();
				} else {
					// Fallback: URL-based add-to-cart (works even if variation JS failed)
					var pid  = cfg.getAttribute( 'data-product-id' );
					var url  = window.location.pathname + '?add-to-cart=' + pid + '&quantity=1';
					url += '&attribute_pa_dosage=' + encodeURIComponent( dosage );
					url += '&attribute_pa_vial=' + encodeURIComponent( vial );
					if ( plan ) url += '&attribute_pa_wm-subscription-plan=' + encodeURIComponent( plan );
					window.location.href = url;
				}
			}, 600 );
		} );
	}

	/* Initial render */
	render();

} );
