document.addEventListener( 'DOMContentLoaded', function () {

	function pdpLog( msg )  { console.log(   '[Myogenix PDP] ' + msg ); }
	function pdpWarn( msg ) { console.warn(  '[Myogenix PDP] ' + msg ); }
	function pdpError( msg ){ console.error( '[Myogenix PDP] ' + msg ); }

	function showUserError( msg ) {
		var existing = document.getElementById( 'pdp-user-error' );
		if ( existing ) existing.remove();
		var el = document.createElement( 'p' );
		el.id = 'pdp-user-error';
		el.className = 'pdp-cfg__error';
		el.textContent = msg;
		var disclaimer = document.getElementById( 'pdp-disclaimer' );
		if ( disclaimer && disclaimer.parentNode ) {
			disclaimer.parentNode.insertBefore( el, disclaimer.nextSibling );
		}
	}

	/* -----------------------------------------------------------------------
	   Accordion helper (Common Questions section)
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

	initAccordion( '.myogenix-pdp__cq-question' );

	/* -----------------------------------------------------------------------
	   Product configurator
	----------------------------------------------------------------------- */
	var cfg = document.getElementById( 'pdp-cfg' );
	if ( ! cfg ) return;

	var doses         = JSON.parse( cfg.getAttribute( 'data-doses' )          || '[]' );
	var priceMatrix   = JSON.parse( cfg.getAttribute( 'data-price-matrix' )   || '{}' );
	var variationMap  = JSON.parse( cfg.getAttribute( 'data-variation-map' )  || '{}' );
	var doseAttr          = cfg.getAttribute( 'data-dose-attr' )               || 'attribute_pa_dosage';
	var doseLabels        = JSON.parse( cfg.getAttribute( 'data-dose-labels' ) || '{}' );
	var warningThreshold  = parseFloat( cfg.getAttribute( 'data-warning-threshold' ) || '10' );
	var bottleAttr    = cfg.getAttribute( 'data-bottle-attr' )                || 'attribute_pa_wm-bottle';
	var bottleSlugMap = JSON.parse( cfg.getAttribute( 'data-bottle-slug-map' ) || '{}' );

	/*
	 * WC attribute slug → term slug mapping (confirmed via WP-CLI 2026-04-21)
	 *   attribute_pa_dosage               → value from doses array (e.g. "10mg")
	 *   attribute_pa_wm-bottle            → "1-bottle" | "2-bottle" | "3-bottle"
	 *   attribute_pa_wm-subscription-plan → "1-month"  | "3-month"
	 */
	var BOTTLE_MAP   = { 1: '1-bottle', 2: '2-bottle', 3: '3-bottle' };
	var PLAN_MAP     = { 1: '1-month',  2: '1-month',  3: '3-month'  };
	var RENEW_MAP    = { 1: 'monthly',  2: 'monthly',  3: 'every 3 months' };
	var MONTH_LABELS = { 1: 'First month', 2: 'Second month', 3: 'Third month' };

	/*
	 * Per-month dose state — doses[1] is always the "primary" dose used for
	 * variation lookup and pricing. Doses 2 and 3 are stored as custom order meta.
	 * Selections persist when switching supply length so customers don't lose work.
	 */
	var state = {
		months: 1,
		doses:  { 1: doses[0] || '', 2: doses[0] || '', 3: doses[0] || '' }
	};

	var CHEVRON_SVG = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>';
	var WARNING_SVG = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';

	/* Look up the real WC price — keyed by first-month dose + supply length */
	function getPrice( dose, months ) {
		var bottle     = BOTTLE_MAP[ months ] || '1-bottle';
		var dosePrices = priceMatrix[ dose ];
		if ( dosePrices && dosePrices[ bottle ] !== undefined ) {
			return dosePrices[ bottle ];
		}
		return 0;
	}

	function weeklyMg( dose ) {
		var mg = parseFloat( dose );
		return isNaN( mg ) ? '\u2014' : ( mg / 4 ).toFixed( 2 );
	}

	/* --- Section label (updates based on supply selection) ----------------- */
	function renderDoseLabel() {
		var el = document.getElementById( 'pdp-dose-label' );
		if ( el ) {
			el.textContent = state.months > 1 ? 'Configure Your Doses' : 'Month 1 Dose';
		}
	}

	/* --- Supply button price labels (keyed off first month's dose) ---------- */
	function renderSupplyPrices() {
		Array.prototype.slice.call( cfg.querySelectorAll( '.pdp-cfg__supply' ) ).forEach( function ( btn ) {
			var m     = parseInt( btn.getAttribute( 'data-months' ), 10 );
			var price = getPrice( state.doses[1], m );
			var el    = btn.querySelector( '.pdp-cfg__supply-price' );
			if ( el ) {
				el.textContent = price
					? '$' + price.toFixed( 2 ) + ( m === 3 ? '/3mo' : '/mo' )
					: '\u2014';
			}
		} );
	}

	/* --- Dose selectors (one per month, with prev-dose badge + decrease warning) --- */
	function renderDoses() {
		var wrap = document.getElementById( 'pdp-dose' );
		if ( ! wrap || ! doses.length ) return;

		var html = '';

		for ( var m = 1; m <= state.months; m++ ) {
			var current = state.doses[ m ] || doses[0];
			var prev    = m > 1 ? ( state.doses[ m - 1 ] || doses[0] ) : null;
			var isLower = prev !== null && parseFloat( current ) < parseFloat( prev );

			var badge = m === 1
				? '<span class="pdp-cfg__dose-badge">Month 1 Dose</span>'
				: '<span class="pdp-cfg__dose-badge">Prev: ' + ( doseLabels[ prev ] || prev ) + '</span>';

			var opts = doses.map( function ( d ) {
				return '<option value="' + d + '"' + ( d === current ? ' selected' : '' ) + '>' + ( doseLabels[ d ] || d ) + '</option>';
			} ).join( '' );

			html +=
				'<div class="pdp-cfg__dose-card" data-month="' + m + '">' +
					'<div class="pdp-cfg__dose-card-header">' +
						'<span class="pdp-cfg__dose-month-label">' + MONTH_LABELS[ m ] + '</span>' +
						badge +
					'</div>' +
					'<div class="pdp-cfg__dose-select-wrap">' +
						'<select class="pdp-cfg__dose-select" data-month="' + m + '">' + opts + '</select>' +
						'<span class="pdp-cfg__dose-chevron">' + CHEVRON_SVG + '</span>' +
					'</div>' +
					'<p class="pdp-cfg__dose-detail">' +
						( doseLabels[ current ] || current ) + '/month = <strong>' + weeklyMg( current ) + ' mg/week</strong> &middot; 4 injections per month' +
					'</p>' +
					( isLower
						? '<div class="pdp-cfg__dose-warning">' +
							'<span class="pdp-cfg__dose-warning-icon" aria-hidden="true">' + WARNING_SVG + '</span>' +
							'This dose is lower than your previous month. Your provider may adjust this during review \u2014 no hard block, just a heads-up.' +
						  '</div>'
						: ''
					) +
				'</div>';
		}

		wrap.innerHTML = html;

		Array.prototype.slice.call( wrap.querySelectorAll( '.pdp-cfg__dose-select' ) ).forEach( function ( sel ) {
			sel.addEventListener( 'change', function () {
				var month = parseInt( this.getAttribute( 'data-month' ), 10 );
				state.doses[ month ] = this.value;
				renderDoses();
				renderSupplyPrices();
				renderSummary();
			} );
		} );
	}

	/* --- Order summary ------------------------------------------------------ */
	function renderSummary() {
		var wrap = document.getElementById( 'pdp-summary' );
		if ( ! wrap ) return;

		var price     = getPrice( state.doses[1], state.months );
		var planLabel = state.months === 3 ? '3-month subscription' : 'Monthly subscription';
		var priceStr  = price ? '$' + price.toFixed( 2 ) : '\u2014';
		var lastDose  = state.doses[ state.months ] || state.doses[1];

		/* Per-month dose lines with weekly breakdown */
		var doseLines = '';
		for ( var m = 1; m <= state.months; m++ ) {
			var mDose  = state.doses[ m ] || state.doses[1];
			var weekly = weeklyMg( mDose );
			doseLines +=
				'<div class="pdp-cfg__summary-line">' +
					'<span>' + MONTH_LABELS[ m ] + ' \u2014 ' + ( doseLabels[ mDose ] || mDose ) + '</span>' +
					'<span class="pdp-cfg__summary-weekly">' + weekly + '\u202fmg/week \u00d7 4 injections</span>' +
				'</div>';
		}

		/* Documentation warning \u2014 shown when month 1 dose exceeds product-specific threshold */
		var starterNote = '';
		if ( parseFloat( state.doses[1] ) > warningThreshold ) {
			starterNote =
				'<div class="pdp-cfg__summary-starter-note">' +
					'<span class="pdp-cfg__summary-starter-icon" aria-hidden="true">' +
						'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>' +
					'</span>' +
					'<span><strong>Documentation required.</strong> This dose requires proof of your current dosage. You\u2019ll be prompted to upload your provider documentation before your order is processed.</span>' +
				'</div>';
		}

		wrap.innerHTML =
			'<p class="pdp-cfg__summary-label">Order Summary</p>' +
			doseLines +
			'<div class="pdp-cfg__summary-total">' +
				'<span>Total today</span>' +
				'<span class="pdp-cfg__summary-total-price">' + priceStr + '</span>' +
			'</div>' +
			starterNote;
	}

	function render() {
		renderDoseLabel();
		renderDoses();
		renderSupplyPrices();
		renderSummary();
	}

	/* --- Supply button bindings --------------------------------------------- */
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
	   CTA — GET-based add-to-cart.
	   Variation lookup uses first-month dose (determines price tier).
	   Extra month doses are passed as custom params and saved as order meta.
	----------------------------------------------------------------------- */
	var ctaBtn = document.getElementById( 'pdp-cta' );
	if ( ctaBtn ) {
		ctaBtn.addEventListener( 'click', function () {
			var pid    = cfg.getAttribute( 'data-product-id' );
			var bottle = BOTTLE_MAP[ state.months ] || '1-bottle';
			var plan   = PLAN_MAP[ state.months ]   || '1-month';
			var dose1  = state.doses[1];

			var prevErr = document.getElementById( 'pdp-user-error' );
			if ( prevErr ) prevErr.remove();

			if ( ! pid ) {
				showUserError( 'Something went wrong. Please refresh and try again.' );
				pdpError( 'Could not determine product ID.' );
				return;
			}

			/*
			 * Resolve variation_id from our PHP-built variation map.
			 * Keyed by first-month dose → bottle → plan (or '' for products without plan attr).
			 */
			var variationId = 0;
			if ( variationMap[ dose1 ] && variationMap[ dose1 ][ bottle ] ) {
				variationId = variationMap[ dose1 ][ bottle ][ plan ] ||
				              variationMap[ dose1 ][ bottle ][ '' ]   || 0;
			}

			if ( ! variationId ) {
				/* Fallback: try WC's embedded form JSON if our map missed it */
				var wcForm = document.querySelector( '.variations_form' );
				if ( wcForm ) {
					var raw = wcForm.getAttribute( 'data-product_variations' );
					if ( raw && raw !== 'false' ) {
						try {
							var variations = JSON.parse( raw );
							var bottleWcSlugFb = bottleSlugMap[ bottle ] || bottle;
							for ( var i = 0; i < variations.length; i++ ) {
								var v = variations[ i ];
								var a = v.attributes;
								var doseOk   = ! a[ doseAttr ]                              || a[ doseAttr ]                              === dose1;
								var bottleOk = ! a[ bottleAttr ]                            || a[ bottleAttr ]                            === bottleWcSlugFb;
								var planOk   = ! a[ 'attribute_pa_wm-subscription-plan' ]   || a[ 'attribute_pa_wm-subscription-plan' ]   === plan;
								if ( doseOk && bottleOk && planOk ) {
									variationId = v.variation_id;
									break;
								}
							}
						} catch ( e ) {
							pdpWarn( 'Could not parse WC variation data: ' + e.message );
						}
					}
				}
			}

			pdpLog( 'Adding to cart \u2192 dose1: ' + dose1 + ' | bottle: ' + bottle + ' | plan: ' + plan + ' | variation_id: ' + ( variationId || 'NOT FOUND' ) );

			if ( ! variationId ) {
				pdpError( 'No matching variation: ' + dose1 + ' / ' + bottle + ' / ' + plan );
				showUserError( 'This combination is currently unavailable. Please try a different dose or supply length.' );
				return;
			}

			ctaBtn.disabled = true;
			ctaBtn.classList.add( 'pdp-cfg__cta--loading' );
			ctaBtn.textContent = 'Adding to cart\u2026';

			var bottleWcSlug = bottleSlugMap[ bottle ] || bottle;
			var url = window.location.pathname + '?add-to-cart=' + pid + '&quantity=1';
			url += '&variation_id='        + variationId;
			url += '&' + doseAttr + '='   + encodeURIComponent( dose1 );
			url += '&' + bottleAttr + '=' + encodeURIComponent( bottleWcSlug );
			if ( plan ) {
				url += '&attribute_pa_wm-subscription-plan=' + encodeURIComponent( plan );
			}
			url += '&dose_month_1='                      + encodeURIComponent( dose1 );
			if ( state.months >= 2 ) {
				url += '&dose_month_2=' + encodeURIComponent( state.doses[2] );
			}
			if ( state.months >= 3 ) {
				url += '&dose_month_3=' + encodeURIComponent( state.doses[3] );
			}

			window.location.href = url;
		} );
	}

	/* Reset to defaults on bfcache restore (user navigates back from cart) */
	window.addEventListener( 'pageshow', function ( e ) {
		if ( ! e.persisted ) return;
		state.months = 1;
		state.doses  = { 1: doses[0] || '', 2: doses[0] || '', 3: doses[0] || '' };
		Array.prototype.slice.call( cfg.querySelectorAll( '.pdp-cfg__supply' ) ).forEach( function ( btn ) {
			btn.classList.remove( 'pdp-cfg__supply--active' );
			if ( parseInt( btn.getAttribute( 'data-months' ), 10 ) === 1 ) {
				btn.classList.add( 'pdp-cfg__supply--active' );
			}
		} );
		if ( ctaBtn ) {
			ctaBtn.disabled = false;
			ctaBtn.classList.remove( 'pdp-cfg__cta--loading' );
			ctaBtn.textContent = 'Go to Checkout →';
		}
		render();
	} );

	/* Initial render */
	render();

} );
