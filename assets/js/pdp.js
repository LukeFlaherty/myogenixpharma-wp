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
	   FAQ accordion is handled site-wide by home.js (enqueued on every page) —
	   don't duplicate it here, two listeners on the same button cancel each
	   other's toggle out.
	----------------------------------------------------------------------- */

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

	/* Package variation IDs and prices — populated by PHP from WC variation data */
	var starterVariationId      = parseInt(  cfg.getAttribute( 'data-starter-variation-id' )      || '0', 10 );
	var starterPrice            = parseFloat( cfg.getAttribute( 'data-starter-price' )             || '0' );
	var starterDoseSlug         = cfg.getAttribute( 'data-starter-dose-slug' )                     || '';
	var continuationVariationId = parseInt(  cfg.getAttribute( 'data-continuation-variation-id' ) || '0', 10 );
	var continuationPrice       = parseFloat( cfg.getAttribute( 'data-continuation-price' )        || '0' );
	var continuationDoseSlug    = cfg.getAttribute( 'data-continuation-dose-slug' )                || '';
	/* Locked month count for each package type. Defaults to 3; override via data-continuation-months. */
	var continuationMonths      = parseInt(  cfg.getAttribute( 'data-continuation-months' )        || '3', 10 );

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
	 *
	 * packageType: 'custom' | 'starter' | 'continuation'
	 *   - 'custom'       → Build Your Own (all supply lengths, editable doses)
	 *   - 'starter'      → locked to 3 months, doses[0-2] pre-set
	 *   - 'continuation' → locked to 3 months, doses[3-5] pre-set (repeats last if fewer than 6)
	 */
	var state = {
		months:      1,
		doses:       { 1: doses[0] || '', 2: doses[0] || '', 3: doses[0] || '' },
		packageType: 'custom'
	};
	var defaultPkg = 'custom';

	var CHEVRON_SVG = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>';
	var WARNING_SVG = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
	var LOCK_SVG    = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';

	/* Look up the real WC price — keyed by dose + supply length (N-vial bulk rate) */
	function getPrice( dose, months ) {
		var bottle     = BOTTLE_MAP[ months ] || '1-bottle';
		var dosePrices = priceMatrix[ dose ];
		if ( dosePrices && dosePrices[ bottle ] !== undefined ) {
			return dosePrices[ bottle ];
		}
		return 0;
	}

	/*
	 * BYO total price for a given supply length:
	 *   - All months same dose → use the N-vial bulk rate (discounted)
	 *   - Mixed doses         → sum each month's 1-vial price
	 */
	function getBYOTotalFor( months ) {
		var allSame = true;
		for ( var i = 2; i <= months; i++ ) {
			if ( ( state.doses[ i ] || state.doses[1] ) !== state.doses[1] ) {
				allSame = false;
				break;
			}
		}
		if ( allSame ) {
			return getPrice( state.doses[1], months );
		}
		var total = 0;
		for ( var i = 1; i <= months; i++ ) {
			total += getPrice( state.doses[ i ] || state.doses[1], 1 );
		}
		return total;
	}

	function getBYOTotal() {
		return getBYOTotalFor( state.months );
	}

	function weeklyMg( dose ) {
		var mg = parseFloat( dose );
		return isNaN( mg ) ? '—' : ( mg / 4 ).toFixed( 2 );
	}

	/*
	 * Returns the pre-set doses for starter / continuation packages.
	 * Starter:      doses 0,1,2 (months 1-3 of treatment)
	 * Continuation: doses 3,4,5 (months 4-6 of treatment) — repeats last dose if fewer than 6 exist
	 */
	function getLockedDoses() {
		var last = doses[ doses.length - 1 ] || '';
		if ( state.packageType === 'starter' ) {
			return {
				1: doses[0] || '',
				2: doses[1] || doses[0] || '',
				3: doses[2] || doses[1] || doses[0] || ''
			};
		}
		if ( state.packageType === 'continuation' ) {
			return {
				1: doses[3] || last,
				2: doses[4] || last,
				3: doses[5] || last
			};
		}
		return null;
	}

	/* Read initial package selection from DOM (PHP sets the --active class) */
	( function () {
		var activeBtn = cfg.querySelector( '.pdp-cfg__pkg.pdp-cfg__pkg--active' );
		defaultPkg = activeBtn ? activeBtn.getAttribute( 'data-pkg' ) : 'custom';
		if ( defaultPkg === 'starter' || defaultPkg === 'continuation' ) {
			state.packageType = defaultPkg;
			state.months      = defaultPkg === 'continuation' ? continuationMonths : 3;
			var locked = getLockedDoses();
			if ( locked ) {
				state.doses[1] = locked[1];
				state.doses[2] = locked[2];
				state.doses[3] = locked[3];
			}
		}
	}() );

	/* --- Section label (updates based on supply / package selection) -------- */
	function renderDoseLabel() {
		var el = document.getElementById( 'pdp-dose-label' );
		if ( ! el ) return;
		if ( state.packageType !== 'custom' ) {
			el.textContent = 'Your Monthly Doses';
		} else {
			el.textContent = state.months > 1 ? 'Configure Your Doses' : 'Month 1 Dose';
		}
	}

	/* --- Supply button price labels (keyed off first month's dose) ---------- */
	function renderSupplyPrices() {
		Array.prototype.slice.call( cfg.querySelectorAll( '.pdp-cfg__supply' ) ).forEach( function ( btn ) {
			var m  = parseInt( btn.getAttribute( 'data-months' ), 10 );
			var el = btn.querySelector( '.pdp-cfg__supply-price' );
			if ( ! el ) return;

			if ( state.packageType === 'starter' && m === 3 ) {
				el.textContent = starterPrice ? '$' + starterPrice.toFixed( 2 ) + '/3mo' : '—';
			} else if ( state.packageType === 'continuation' && m === continuationMonths ) {
				el.textContent = continuationPrice ? '$' + continuationPrice.toFixed( 2 ) + '/' + continuationMonths + 'mo' : '—';
			} else {
				var price = getBYOTotalFor( m );
				el.textContent = price ? '$' + price.toFixed( 2 ) + ( m === 3 ? '/3mo' : '/mo' ) : '—';
			}
		} );
	}

	/*
	 * When packageType is starter or continuation, disable the 1- and 2-month
	 * supply buttons and force the 3-month button active.
	 */
	function renderSupplyVisibility() {
		var isLocked = state.packageType !== 'custom';
		Array.prototype.slice.call( cfg.querySelectorAll( '.pdp-cfg__supply' ) ).forEach( function ( btn ) {
			var m = parseInt( btn.getAttribute( 'data-months' ), 10 );
			btn.classList.toggle( 'pdp-cfg__supply--disabled', isLocked && m !== state.months );
			if ( isLocked ) {
				btn.classList.toggle( 'pdp-cfg__supply--active', m === state.months );
			}
		} );
	}

	/* --- Dose selectors (one per month, with prev-dose badge + decrease warning) --- */
	function renderDoses() {
		var wrap = document.getElementById( 'pdp-dose' );
		if ( ! wrap || ! doses.length ) return;

		var isLocked = state.packageType !== 'custom';
		var html = '';

		for ( var m = 1; m <= state.months; m++ ) {
			var current = state.doses[ m ] || doses[0];
			var prev    = m > 1 ? ( state.doses[ m - 1 ] || doses[0] ) : null;
			var isLower = prev !== null && parseFloat( current ) < parseFloat( prev );

			var badge = m === 1
				? '<span class="pdp-cfg__dose-badge">Month 1 Dose</span>'
				: '<span class="pdp-cfg__dose-badge">Prev: ' + ( doseLabels[ prev ] || prev ) + '</span>';

			var inputHtml;
			if ( isLocked ) {
				inputHtml =
					'<div class="pdp-cfg__dose-static">' +
						'<span class="pdp-cfg__dose-static-value">' + ( doseLabels[ current ] || current ) + '</span>' +
						'<span class="pdp-cfg__dose-lock-icon" aria-hidden="true">' + LOCK_SVG + '</span>' +
					'</div>';
			} else {
				var opts = doses.map( function ( d ) {
					return '<option value="' + d + '"' + ( d === current ? ' selected' : '' ) + '>' + ( doseLabels[ d ] || d ) + '</option>';
				} ).join( '' );
				inputHtml =
					'<div class="pdp-cfg__dose-select-wrap">' +
						'<select class="pdp-cfg__dose-select" data-month="' + m + '">' + opts + '</select>' +
						'<span class="pdp-cfg__dose-chevron">' + CHEVRON_SVG + '</span>' +
					'</div>';
			}

			html +=
				'<div class="pdp-cfg__dose-card' + ( isLocked ? ' pdp-cfg__dose-card--locked' : '' ) + '" data-month="' + m + '">' +
					'<div class="pdp-cfg__dose-card-header">' +
						'<span class="pdp-cfg__dose-month-label">' + MONTH_LABELS[ m ] + '</span>' +
						badge +
					'</div>' +
					inputHtml +
					'<p class="pdp-cfg__dose-detail">' +
						( doseLabels[ current ] || current ) + '/month = <strong>' + weeklyMg( current ) + ' mg/week</strong> &middot; 4 injections per month' +
					'</p>' +
					( isLower
						? '<div class="pdp-cfg__dose-warning">' +
							'<span class="pdp-cfg__dose-warning-icon" aria-hidden="true">' + WARNING_SVG + '</span>' +
							'This dose is lower than your previous month. Your provider may adjust this during review — no hard block, just a heads-up.' +
						  '</div>'
						: ''
					) +
				'</div>';
		}

		wrap.innerHTML = html;

		if ( ! isLocked ) {
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
	}

	/* --- Order summary ------------------------------------------------------ */
	function renderSummary() {
		var wrap = document.getElementById( 'pdp-summary' );
		if ( ! wrap ) return;

		var price;
		if ( state.packageType === 'starter' ) {
			price = starterPrice;
		} else if ( state.packageType === 'continuation' ) {
			price = continuationPrice;
		} else {
			price = getBYOTotal();
		}
		var planLabel = state.months === 3 ? '3-month subscription' : 'Monthly subscription';
		var priceStr  = price ? '$' + price.toFixed( 2 ) : '—';
		var lastDose  = state.doses[ state.months ] || state.doses[1];

		/* Per-month dose lines with weekly breakdown */
		var doseLines = '';
		for ( var m = 1; m <= state.months; m++ ) {
			var mDose  = state.doses[ m ] || state.doses[1];
			var weekly = weeklyMg( mDose );
			doseLines +=
				'<div class="pdp-cfg__summary-line">' +
					'<span>' + MONTH_LABELS[ m ] + ' — ' + ( doseLabels[ mDose ] || mDose ) + '</span>' +
					'<span class="pdp-cfg__summary-weekly">' + weekly + ' mg/week × 4 injections</span>' +
				'</div>';
		}

		/* Documentation warning — shown when month 1 dose exceeds product-specific threshold */
		var starterNote = '';
		if ( parseFloat( state.doses[1] ) > warningThreshold ) {
			starterNote =
				'<div class="pdp-cfg__summary-starter-note">' +
					'<span class="pdp-cfg__summary-starter-icon" aria-hidden="true">' +
						'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>' +
					'</span>' +
					'<span><strong>Documentation required.</strong> This dose requires proof of your current dosage. You’ll be prompted to upload your provider documentation before your order is processed.</span>' +
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
		renderSupplyVisibility();
		renderSummary();
		/* Hide the static dose reference table when a bundle is selected (locked cards already show the info) */
		var doseRef = document.getElementById( 'rtd-dose-ref' );
		if ( doseRef ) doseRef.hidden = ( state.packageType !== 'custom' );
		/* Retatrutide-style layout: compact bundle panels vs. full BYO section */
		var bundleStarterEl = document.getElementById( 'rtd-bundle-starter' );
		var bundleContEl    = document.getElementById( 'rtd-bundle-continuation' );
		var byoSectionEl    = document.getElementById( 'rtd-byo-section' );
		if ( bundleStarterEl ) bundleStarterEl.hidden = ( state.packageType !== 'starter' );
		if ( bundleContEl )    bundleContEl.hidden    = ( state.packageType !== 'continuation' );
		if ( byoSectionEl )    byoSectionEl.hidden    = ( state.packageType !== 'custom' );
	}

	/* --- Supply button bindings --------------------------------------------- */
	Array.prototype.slice.call( cfg.querySelectorAll( '.pdp-cfg__supply' ) ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			/* Locked packages always use 3 months — ignore clicks on other options */
			if ( state.packageType !== 'custom' ) return;

			state.months = parseInt( this.getAttribute( 'data-months' ), 10 );
			Array.prototype.slice.call( cfg.querySelectorAll( '.pdp-cfg__supply' ) ).forEach( function ( b ) {
				b.classList.remove( 'pdp-cfg__supply--active' );
			} );
			this.classList.add( 'pdp-cfg__supply--active' );
			render();
		} );
	} );

	/* --- Package type button bindings --------------------------------------- */
	Array.prototype.slice.call( cfg.querySelectorAll( '.pdp-cfg__pkg' ) ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var pkg = this.getAttribute( 'data-pkg' );
			state.packageType = pkg;

			Array.prototype.slice.call( cfg.querySelectorAll( '.pdp-cfg__pkg' ) ).forEach( function ( b ) {
				b.classList.remove( 'pdp-cfg__pkg--active' );
			} );
			this.classList.add( 'pdp-cfg__pkg--active' );

			if ( pkg === 'starter' || pkg === 'continuation' ) {
				state.months = pkg === 'continuation' ? continuationMonths : 3;
				var locked = getLockedDoses();
				state.doses[1] = locked[1];
				state.doses[2] = locked[2];
				state.doses[3] = locked[3];
			}

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
			var pid = cfg.getAttribute( 'data-product-id' );

			var prevErr = document.getElementById( 'pdp-user-error' );
			if ( prevErr ) prevErr.remove();

			if ( ! pid ) {
				showUserError( 'Something went wrong. Please refresh and try again.' );
				pdpError( 'Could not determine product ID.' );
				return;
			}

			/* ---- Package (Starter / Continuation) add-to-cart ---- */
			if ( state.packageType !== 'custom' ) {
				var pkgVarId   = state.packageType === 'starter' ? starterVariationId   : continuationVariationId;
				var pkgDoseSlg = state.packageType === 'starter' ? starterDoseSlug      : continuationDoseSlug;

				if ( ! pkgVarId ) {
					showUserError( 'This package is currently unavailable. Please try Build Your Own.' );
					pdpError( 'Package variation ID not found for: ' + state.packageType );
					return;
				}

				pdpLog( 'Adding package to cart → type:' + state.packageType + ' | variation_id:' + pkgVarId );

				ctaBtn.disabled = true;
				ctaBtn.classList.add( 'pdp-cfg__cta--loading' );
				ctaBtn.textContent = 'Adding to cart…';

				var pkgBottleKey = BOTTLE_MAP[ state.months ] || '3-bottle';
				var pkgBottle    = bottleSlugMap[ pkgBottleKey ] || pkgBottleKey.replace( 'bottle', 'vial' );
				var url = window.location.pathname + '?add-to-cart=' + pid + '&quantity=1';
				url += '&variation_id='        + pkgVarId;
				url += '&' + doseAttr + '='   + encodeURIComponent( pkgDoseSlg );
				url += '&' + bottleAttr + '=' + encodeURIComponent( pkgBottle );
				url += '&dose_month_1='        + encodeURIComponent( state.doses[1] );
				if ( state.months >= 2 ) { url += '&dose_month_2=' + encodeURIComponent( state.doses[2] ); }
				if ( state.months >= 3 ) { url += '&dose_month_3=' + encodeURIComponent( state.doses[3] ); }

				window.location.href = url;
				return;
			}

			/* ---- Build Your Own add-to-cart ---- */
			var bottle = BOTTLE_MAP[ state.months ] || '1-bottle';
			var plan   = PLAN_MAP[ state.months ]   || '1-month';
			var dose1  = state.doses[1];

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

			pdpLog( 'Adding to cart → dose1: ' + dose1 + ' | bottle: ' + bottle + ' | plan: ' + plan + ' | variation_id: ' + ( variationId || 'NOT FOUND' ) );

			if ( ! variationId ) {
				pdpError( 'No matching variation: ' + dose1 + ' / ' + bottle + ' / ' + plan );
				showUserError( 'This combination is currently unavailable. Please try a different dose or supply length.' );
				return;
			}

			ctaBtn.disabled = true;
			ctaBtn.classList.add( 'pdp-cfg__cta--loading' );
			ctaBtn.textContent = 'Adding to cart…';

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
		state.packageType = defaultPkg;
		if ( defaultPkg === 'starter' || defaultPkg === 'continuation' ) {
			state.months = defaultPkg === 'continuation' ? continuationMonths : 3;
			var resetLocked = getLockedDoses();
			state.doses = resetLocked
				? { 1: resetLocked[1], 2: resetLocked[2], 3: resetLocked[3] }
				: { 1: doses[0] || '', 2: doses[0] || '', 3: doses[0] || '' };
		} else {
			state.months = 1;
			state.doses  = { 1: doses[0] || '', 2: doses[0] || '', 3: doses[0] || '' };
		}

		Array.prototype.slice.call( cfg.querySelectorAll( '.pdp-cfg__supply' ) ).forEach( function ( btn ) {
			btn.classList.remove( 'pdp-cfg__supply--active' );
			if ( parseInt( btn.getAttribute( 'data-months' ), 10 ) === state.months ) {
				btn.classList.add( 'pdp-cfg__supply--active' );
			}
		} );

		Array.prototype.slice.call( cfg.querySelectorAll( '.pdp-cfg__pkg' ) ).forEach( function ( btn ) {
			btn.classList.remove( 'pdp-cfg__pkg--active' );
			if ( btn.getAttribute( 'data-pkg' ) === defaultPkg ) {
				btn.classList.add( 'pdp-cfg__pkg--active' );
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
