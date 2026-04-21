document.addEventListener( 'DOMContentLoaded', function () {

	/* -----------------------------------------------------------------------
	   Accordion helper (shared by FAQ + Common Questions sections)
	----------------------------------------------------------------------- */
	function initAccordion( selector ) {
		var items = Array.prototype.slice.call( document.querySelectorAll( selector ) );
		items.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var isExpanded = this.getAttribute( 'aria-expanded' ) === 'true';
				var answer     = document.getElementById( this.getAttribute( 'aria-controls' ) );
				if ( ! answer ) return;

				items.forEach( function ( other ) {
					if ( other === btn ) return;
					other.setAttribute( 'aria-expanded', 'false' );
					var otherAns = document.getElementById( other.getAttribute( 'aria-controls' ) );
					if ( otherAns ) otherAns.classList.remove( 'is-open' );
				} );

				this.setAttribute( 'aria-expanded', String( ! isExpanded ) );
				answer.classList.toggle( 'is-open', ! isExpanded );
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

	var doses        = JSON.parse( cfg.getAttribute( 'data-doses' )         || '[]' );
	var supplyPrices = JSON.parse( cfg.getAttribute( 'data-supply-prices' ) || '[0,0,0]' );

	/*
	 * WC attribute slug → term slug mapping (confirmed via WP-CLI 2026-04-21)
	 *   attribute_pa_dosage               → value from doses array (e.g. "10mg")
	 *   attribute_pa_wm-bottle            → "1-bottle" | "2-bottle" | "3-bottle"
	 *   attribute_pa_wm-subscription-plan → "1-month" | "3-month"
	 */
	var BOTTLE_MAP = { 1: '1-bottle', 2: '2-bottle', 3: '3-bottle' };
	var PLAN_MAP   = { 1: '1-month',  2: '1-month',  3: '3-month'  };
	var RENEW_MAP  = { 1: 'monthly',  2: 'monthly',  3: 'every 3 months' };

	var state = {
		months: 1,
		dose:   doses[ 0 ] || ''
	};

	function weeklyMg( dose ) {
		var mg = parseFloat( dose );
		return isNaN( mg ) ? '\u2014' : ( mg / 4 ).toFixed( 2 );
	}

	/* --- Dose selector -------------------------------------------------- */
	function renderDose() {
		var wrap = document.getElementById( 'pdp-dose' );
		if ( ! wrap || ! doses.length ) return;

		var opts = doses.map( function ( d ) {
			return '<option value="' + d + '"' + ( d === state.dose ? ' selected' : '' ) + '>' + d + '</option>';
		} ).join( '' );

		var doseNote = state.months > 1
			? 'All ' + state.months + ' bottles ship at this dose. Adjust your dose at renewal.'
			: '';

		wrap.innerHTML =
			'<div class="pdp-cfg__dose-card">' +
				'<div class="pdp-cfg__dose-select-wrap">' +
					'<select class="pdp-cfg__dose-select" id="pdp-dose-select">' + opts + '</select>' +
					'<span class="pdp-cfg__dose-chevron">' +
						'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>' +
					'</span>' +
				'</div>' +
				'<p class="pdp-cfg__dose-detail">' +
					state.dose + '/month = <strong>' + weeklyMg( state.dose ) + ' mg/week</strong> &middot; 4 injections per month' +
				'</p>' +
				( doseNote ? '<p class="pdp-cfg__dose-note">' + doseNote + '</p>' : '' ) +
			'</div>';

		document.getElementById( 'pdp-dose-select' ).addEventListener( 'change', function () {
			state.dose = this.value;
			renderDose();
			renderSummary();
		} );
	}

	/* --- Order summary -------------------------------------------------- */
	function renderSummary() {
		var wrap = document.getElementById( 'pdp-summary' );
		if ( ! wrap ) return;

		var price     = supplyPrices[ state.months - 1 ] || 0;
		var planLabel = state.months === 3 ? '3-month subscription' : 'Monthly subscription';
		var renewNote = 'Auto-renews ' + RENEW_MAP[ state.months ] + ' at <strong>$' + price.toFixed( 2 ) + '</strong>. Cancel anytime before renewal.';

		wrap.innerHTML =
			'<p class="pdp-cfg__summary-label">Order Summary</p>' +
			'<div class="pdp-cfg__summary-line"><span>' + state.months + '-month supply &middot; ' + state.dose + '</span><span>$' + price.toFixed( 2 ) + '</span></div>' +
			'<div class="pdp-cfg__summary-line"><span>Plan</span><span>' + planLabel + '</span></div>' +
			'<div class="pdp-cfg__summary-total">' +
				'<span>Total today</span>' +
				'<span class="pdp-cfg__summary-total-price">$' + price.toFixed( 2 ) + '</span>' +
			'</div>' +
			'<div class="pdp-cfg__summary-note">' + renewNote + '</div>';
	}

	function render() {
		renderDose();
		renderSummary();
	}

	/* --- Supply button bindings ----------------------------------------- */
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

	/* -----------------------------------------------------------------------
	   CTA — GET-based add-to-cart (no POST = no "Confirm Form Resubmission").
	   Looks up the variation_id from WC's embedded JSON so WC Subscriptions
	   can resolve the correct variation reliably.
	----------------------------------------------------------------------- */
	var ctaBtn = document.getElementById( 'pdp-cta' );
	if ( ctaBtn ) {
		ctaBtn.addEventListener( 'click', function () {
			var pid    = cfg.getAttribute( 'data-product-id' );
			var bottle = BOTTLE_MAP[ state.months ] || '1-bottle';
			var plan   = PLAN_MAP[ state.months ]   || '1-month';

			/* Clear any previous error */
			var prevErr = document.getElementById( 'pdp-cta-error' );
			if ( prevErr ) prevErr.remove();

			function showCtaError( msg ) {
				var err = document.createElement( 'p' );
				err.id = 'pdp-cta-error';
				err.style.cssText = 'color:#c0392b;font-size:0.85rem;text-align:center;margin:8px 0 0;padding:10px 14px;background:#fff0ee;border-radius:6px;border:1px solid #f5c6c0;line-height:1.5;';
				err.textContent = msg;
				ctaBtn.insertAdjacentElement( 'afterend', err );
				console.error( '[Myogenix PDP]', msg );
			}

			if ( ! pid ) {
				showCtaError( 'Could not determine product. Please refresh and try again.' );
				return;
			}

			/*
			 * Resolve the variation_id from the WC form's embedded JSON.
			 * WC embeds all variation data in data-product_variations when
			 * variation count is below the ajax threshold (default 100).
			 */
			var variationId = 0;
			var wcForm = document.querySelector( '.variations_form' );
			if ( wcForm ) {
				var raw = wcForm.getAttribute( 'data-product_variations' );
				if ( raw && raw !== 'false' ) {
					try {
						var variations = JSON.parse( raw );
						for ( var i = 0; i < variations.length; i++ ) {
							var v = variations[ i ];
							var a = v.attributes;
							/* Empty string in attribute means "any" — treat as match */
							var doseOk   = ! a[ 'attribute_pa_dosage' ]               || a[ 'attribute_pa_dosage' ]               === state.dose;
							var bottleOk = ! a[ 'attribute_pa_wm-bottle' ]            || a[ 'attribute_pa_wm-bottle' ]            === bottle;
							var planOk   = ! a[ 'attribute_pa_wm-subscription-plan' ] || a[ 'attribute_pa_wm-subscription-plan' ] === plan;
							if ( doseOk && bottleOk && planOk ) {
								variationId = v.variation_id;
								break;
							}
						}
					} catch ( e ) {
						console.warn( '[Myogenix PDP] Could not parse data-product_variations:', e );
					}
				}
			}

			console.log(
				'[Myogenix PDP] Adding to cart → dosage:', state.dose,
				'| bottle:', bottle,
				'| plan:', plan,
				'| variation_id:', variationId || '(not found — will rely on attribute params)'
			);

			if ( ! variationId ) {
				showCtaError(
					'Could not match a product variation for: ' + state.dose + ', ' + state.months + ' month(s). ' +
					'Please check that this combination exists in WP Admin → Products → Variations.'
				);
				return;
			}

			var url = window.location.pathname + '?add-to-cart=' + pid + '&quantity=1';
			url += '&variation_id='                              + variationId;
			url += '&attribute_pa_dosage='                       + encodeURIComponent( state.dose );
			url += '&attribute_pa_wm-bottle='                    + encodeURIComponent( bottle );
			url += '&attribute_pa_wm-subscription-plan='         + encodeURIComponent( plan );

			window.location.href = url;
		} );
	}

	/* Debug: log WC form attribute selects on page load */
	var wcForm = document.querySelector( '.variations_form' );
	if ( wcForm ) {
		var dbgSelects = wcForm.querySelectorAll( 'select[name^="attribute_"]' );
		if ( dbgSelects.length ) {
			console.log( '[Myogenix PDP] WC variation form found. Attributes:' );
			Array.prototype.slice.call( dbgSelects ).forEach( function ( sel ) {
				var opts = Array.prototype.slice.call( sel.options ).map( function ( o ) { return o.value || '(any)'; } );
				console.log( '  ' + sel.name + ' \u2192 [' + opts.join( ', ' ) + ']' );
			} );
		}
	} else {
		console.warn( '[Myogenix PDP] No .variations_form on page.' );
	}

	/* Initial render */
	render();

} );
