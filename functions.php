<?php
/**
 * Myogenix Theme functions
 */

// Enqueue parent theme styles
add_action( 'wp_enqueue_scripts', function() {
	wp_enqueue_style(
		'hello-elementor-style',
		get_template_directory_uri() . '/style.css'
	);
} );

// ─── Site-wide navbar + home page assets ──────────────────────────────────────
// home.css contains both navbar styles (used on all pages) and home-page section
// styles (.home-hero, .home-categories, etc.) that only render on the front page.
add_action( 'wp_enqueue_scripts', function() {
	wp_enqueue_style(
		'myogenix-home',
		get_stylesheet_directory_uri() . '/assets/css/home.css',
		[],
		'1.5.0'
	);
	wp_enqueue_script(
		'myogenix-home',
		get_stylesheet_directory_uri() . '/assets/js/home.js',
		[],
		'1.5.0',
		true
	);
} );

// ─── TRT Article page styles ─────────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', function() {
	if ( ! is_page_template( 'page-trt-article.php' ) ) return;
	wp_enqueue_style(
		'myogenix-trt-article',
		get_stylesheet_directory_uri() . '/assets/css/trt-article.css',
		[ 'myogenix-home' ],
		'1.3.0'
	);
} );

// ─── Reach a Concierge page styles/script ────────────────────────────────────
add_action( 'wp_enqueue_scripts', function() {
	if ( ! is_page_template( 'page-reach-a-concierge.php' ) ) return;
	wp_enqueue_style(
		'myogenix-reach-a-concierge',
		get_stylesheet_directory_uri() . '/assets/css/reach-a-concierge.css',
		[],
		'1.6.0'
	);
	wp_enqueue_script(
		'myogenix-reach-a-concierge',
		get_stylesheet_directory_uri() . '/assets/js/reach-a-concierge.js',
		[],
		'1.6.0',
		true
	);
} );

// NOTE: Elementor Pro's Header template (#898) is excluded from this page via an
// "exclude/singular/page/4757" condition set directly in the database (both
// _elementor_conditions postmeta on post 898 and the elementor_pro_theme_builder_conditions
// option cache — see CLAUDE.md "Elementor Conditions Management"). That's the only
// thing that actually stops its CSS/JS from loading here: Elementor Pro force-prints
// theme-builder assets via direct wp_styles()->do_item() calls that ignore
// wp_dequeue_style()/wp_deregister_style() entirely, so PHP-side removal doesn't work —
// the page has to be excluded from the template's match conditions instead.

// ─── Retatrutide password gate ────────────────────────────────────────────────
add_action( 'template_redirect', function () {
	if ( ! is_page( 'retatrutide' ) ) return;

	$passwords = [ 'legacy' => 'Legacy Training Center' ];

	// POST: verify password, then PRG to avoid browser resubmit alert.
	if ( ! empty( $_POST['retatrutide_pw'] ) ) {
		if ( ! isset( $_POST['retatrutide_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['retatrutide_nonce'] ), 'retatrutide_gate' ) ) return;
		$pw = sanitize_text_field( wp_unslash( $_POST['retatrutide_pw'] ) );
		if ( isset( $passwords[ $pw ] ) ) {
			// Short-lived signed token (90 s) — password required every page load,
			// but redirect avoids the "Confirm resubmission" browser dialog.
			$expires = time() + 90;
			$token   = hash_hmac( 'sha256', $expires . '|' . $pw, NONCE_SALT );
			wp_safe_redirect( add_query_arg( [ 'rtd' => $token, 'rtd_t' => $expires ], get_permalink() ) );
			exit;
		}
		$GLOBALS['retatrutide_gate_error'] = true;
		return;
	}

	// GET: validate short-lived token issued by the POST redirect above.
	if ( ! empty( $_GET['rtd'] ) && ! empty( $_GET['rtd_t'] ) ) {
		$t = (int) $_GET['rtd_t'];
		if ( $t >= time() ) {
			foreach ( array_keys( $passwords ) as $pw ) {
				if ( hash_equals( hash_hmac( 'sha256', $t . '|' . $pw, NONCE_SALT ), sanitize_text_field( wp_unslash( $_GET['rtd'] ) ) ) ) {
					$GLOBALS['retatrutide_authenticated'] = true;
					break;
				}
			}
		}
	}
} );


// Convert a dose term slug (e.g. "10-mg") to its display name (e.g. "10 mg").
// Checks pa_individual-dose first (production), then pa_dosage (staging).
function myogenix_dose_display( $slug ) {
	foreach ( [ 'pa_individual-dose', 'pa_dosage' ] as $tax ) {
		$term = get_term_by( 'slug', $slug, $tax );
		if ( $term ) return $term->name;
	}
	return $slug;
}

// Dose escalation — capture per-month doses from add-to-cart URL and attach to cart item
add_filter( 'woocommerce_add_cart_item_data', function ( $cart_item_data, $product_id, $variation_id ) {
	foreach ( [ 'dose_month_1', 'dose_month_2', 'dose_month_3' ] as $key ) {
		if ( ! empty( $_REQUEST[ $key ] ) ) {
			$cart_item_data[ $key ] = sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) );
		}
	}
	return $cart_item_data;
}, 10, 3 );

// Display dose schedule in cart and checkout review with weekly breakdown.
// Uses separate entries so WC Blocks checkout renders each as its own row.
add_filter( 'woocommerce_get_item_data', function ( $item_data, $cart_item ) {
	$months = [
		'dose_month_1' => 'Month 1',
		'dose_month_2' => 'Month 2',
		'dose_month_3' => 'Month 3',
	];
	foreach ( $months as $key => $label ) {
		if ( ! empty( $cart_item[ $key ] ) ) {
			$slug    = $cart_item[ $key ];
			$display = myogenix_dose_display( $slug );
			$mg      = (float) $slug;
			$weekly  = $mg > 0
				? ' (' . rtrim( rtrim( number_format( $mg / 4, 2 ), '0' ), '.' ) . ' mg/week)'
				: '';
			$item_data[] = [
				'key'   => $label,
				'value' => esc_html( $display . $weekly ),
			];
		}
	}
	return $item_data;
}, 10, 2 );

// Replace cart/checkout item title for weight-management and peptide products.
add_filter( 'woocommerce_cart_item_name', function ( $name, $cart_item, $cart_item_key ) {
	$parent_slug = get_post_field( 'post_name', $cart_item['product_id'] ?? 0 );

	$wm_names = [
		'compound-tirzepatide' => 'TIRZEPATIDE',
		'compound-semaglutide' => 'SEMAGLUTIDE',
		'compound-retatrutide' => 'RETATRUTIDE',
	];
	$peptide_names = [
		'bpc'                                                    => 'BPC-157',
		'motsc'                                                  => 'MOTSc',
		'epithalon'                                              => 'Epithalon',
		'compound-injectable-nad'                                => 'NAD+',
		'tesamorelin-ipamorelin'                                 => 'Tesamorelin / Ipamorelin',
		'cjc1295-ipamorelin'                                     => 'CJC-1295 / Ipamorelin',
		'klow-stack-bpc157-10mg-ghk-cu-50mg-tb50010mg-kpv-10mg' => 'KLOW Stack',
		'2606'                                                   => 'Wolverine Stack',
		'compound-injectable-sermorelin'                         => 'Sermorelin',
		'compound-injectable-glutathione'                        => 'Glutathione',
	];

	if ( isset( $wm_names[ $parent_slug ] ) ) {
		$drug      = $wm_names[ $parent_slug ];
		$variation  = $cart_item['variation'] ?? [];
		$bottle_raw = $variation['attribute_pa_wm-bottle'] ?? $variation['attribute_pa_vial'] ?? '';
		$bottle_num = (int) preg_replace( '/[^0-9]/', '', $bottle_raw );
		if ( $bottle_num === 0 ) {
			$bottle_num = count( array_filter( [
				$cart_item['dose_month_1'] ?? '',
				$cart_item['dose_month_2'] ?? '',
				$cart_item['dose_month_3'] ?? '',
			] ) );
		}
		$dose_fields = array_values( array_filter( array_map(
			'myogenix_dose_display',
			array_filter( [
				$cart_item['dose_month_1'] ?? '',
				$cart_item['dose_month_2'] ?? '',
				$cart_item['dose_month_3'] ?? '',
			] )
		) ) );
		$dose_str = ! empty( $dose_fields )
			? ( count( array_unique( $dose_fields ) ) === 1 ? $dose_fields[0] : implode( ', ', $dose_fields ) )
			: '';
		$parts = array_filter( [
			$dose_str,
			$bottle_num ? $bottle_num . ' Bottle' : '',
			$bottle_num ? $bottle_num . ' month'  : '',
		] );
		if ( ! $parts ) return $name;
		$custom = esc_html( $drug . ' - ' . implode( ', ', $parts ) );

	} elseif ( isset( $peptide_names[ $parent_slug ] ) ) {
		$drug       = $peptide_names[ $parent_slug ];
		$variation  = $cart_item['variation'] ?? [];
		$supply_raw = $variation['attribute_pa_vial-wellness'] ?? $variation['attribute_pa_bottle'] ?? '';
		$supply_map = [
			'1-vial' => '1 vial', '2-vial' => '2 vials', '3-vial' => '3 vials',
			'1-bottle' => '1 vial', '2-bottle' => '2 vials', '3-bottle' => '3 vials',
		];
		$supply_str = $supply_map[ $supply_raw ] ?? '';
		$custom = esc_html( $drug . ( $supply_str ? ' - ' . $supply_str : '' ) );

	} else {
		$sexual_health_names = [
			'compound-oral-tadalafil' => 'Tadalafil',
			'compound-sildenafil'     => 'Sildenafil',
			'testosterone'            => 'Testosterone Cypionate',
		];
		if ( isset( $sexual_health_names[ $parent_slug ] ) ) {
			$drug      = $sexual_health_names[ $parent_slug ];
			$variation = $cart_item['variation'] ?? [];
			$dosage    = $variation['attribute_pa_dosage']             ?? '';
			$days      = $variation['attribute_pa_days']               ?? '';
			$tablets   = $variation['attribute_pa_tablets']            ?? '';
			$plan      = $variation['attribute_pa_subscription-plan']  ?? '';
			$parts     = array_filter( [ $dosage, $days ?: $tablets ?: $plan ] );
			$custom    = esc_html( $drug . ( $parts ? ' - ' . implode( ', ', $parts ) : '' ) );
		} else {
			return $name;
		}
	}

	// Preserve the <a> link wrapper if WooCommerce already added one
	if ( strpos( $name, '<a ' ) !== false ) {
		return preg_replace( '/(<a[^>]+>).*?(<\/a>)/s', '$1' . $custom . '$2', $name );
	}
	return $custom;
}, 10, 3 );

// Save dose schedule to order line item meta so it appears on the order and in emails
add_action( 'woocommerce_checkout_create_order_line_item', function ( $item, $cart_item_key, $values, $order ) {
	$labels = [
		'dose_month_1' => 'Month 1 Dose',
		'dose_month_2' => 'Month 2 Dose',
		'dose_month_3' => 'Month 3 Dose',
	];
	foreach ( $labels as $key => $label ) {
		if ( ! empty( $values[ $key ] ) ) {
			$item->add_meta_data( $label, esc_html( myogenix_dose_display( $values[ $key ] ) ) );
		}
	}

	// Rx Summary for Prescribery — backend only, not shown to customer
	// Format: Tirzepatide - 10mg(2.5mg/wk), 20mg(5mg/wk), 30mg(7.5mg/wk)
	$slug_to_drug_rx = [
		'compound-tirzepatide' => 'Tirzepatide',
		'compound-semaglutide' => 'Semaglutide',
		'compound-retatrutide' => 'Retatrutide',
	];
	$parent_slug = get_post_field( 'post_name', $values['product_id'] ?? 0 );
	if ( isset( $slug_to_drug_rx[ $parent_slug ] ) ) {
		$drug = $slug_to_drug_rx[ $parent_slug ];

		$variation = $values['variation'] ?? [];

		// Build per-dose strings with inline weekly breakdown
		$dose_strings = [];
		foreach ( [ 'dose_month_1', 'dose_month_2', 'dose_month_3' ] as $key ) {
			$slug = $values[ $key ] ?? '';
			if ( empty( $slug ) ) continue;
			$mg = (float) $slug;
			if ( $mg > 0 ) {
				$mg_str         = rtrim( rtrim( number_format( $mg, 2 ), '0' ), '.' );
				$weekly         = rtrim( rtrim( number_format( $mg / 4, 2 ), '0' ), '.' );
				$dose_strings[] = $mg_str . 'mg(' . $weekly . 'mg/wk)';
			} else {
				$dose_strings[] = myogenix_dose_display( $slug );
			}
		}

		// Fallback for orders placed without dose_month_* URL params (standard add-to-cart).
		// Read the dose directly from the selected variation attribute.
		if ( empty( $dose_strings ) ) {
			$var_dose = $variation['attribute_pa_individual-dose'] ?? $variation['attribute_pa_dosage'] ?? '';
			if ( ! empty( $var_dose ) ) {
				$mg = (float) $var_dose;
				if ( $mg > 0 ) {
					$mg_str         = rtrim( rtrim( number_format( $mg, 2 ), '0' ), '.' );
					$weekly         = rtrim( rtrim( number_format( $mg / 4, 2 ), '0' ), '.' );
					$dose_strings[] = $mg_str . 'mg(' . $weekly . 'mg/wk)';
				}
			}
		}

		if ( empty( $dose_strings ) ) return;

		$is_same_dose = count( array_unique( $dose_strings ) ) === 1;
		$dose_str     = $is_same_dose
			? $dose_strings[0]
			: implode( ', ', $dose_strings );

		// For same-dose orders the vial count isn't implied by the dose string,
		// so Prescribery can't distinguish 1-vial from 3-vial at the same dose.
		// Mixed-dose orders already convey supply size via number of dose entries.
		if ( $is_same_dose ) {
			$bottle_raw = $variation['attribute_pa_wm-bottle'] ?? $variation['attribute_pa_vial'] ?? '';
			$bottle_num = (int) preg_replace( '/[^0-9]/', '', $bottle_raw );
			if ( $bottle_num > 1 ) {
				$dose_str .= ', ' . $bottle_num . ' vial';
			}
		}

		$rx_name = $drug . ' - ' . $dose_str;

		$item->add_meta_data( 'Rx Summary', $rx_name );
		$item->set_name( $rx_name );
		return;
	}

	// Rx Summary for peptide products — supply count only, no dose escalation
	$peptide_drug_names = [
		'bpc'                                                    => 'BPC-157',
		'motsc'                                                  => 'MOTSc',
		'epithalon'                                              => 'Epithalon',
		'compound-injectable-nad'                                => 'NAD+',
		'tesamorelin-ipamorelin'                                 => 'Tesamorelin / Ipamorelin',
		'cjc1295-ipamorelin'                                     => 'CJC-1295 / Ipamorelin',
		'klow-stack-bpc157-10mg-ghk-cu-50mg-tb50010mg-kpv-10mg' => 'KLOW Stack',
		'2606'                                                   => 'Wolverine Stack',
		'compound-injectable-sermorelin'                         => 'Sermorelin',
		'compound-injectable-glutathione'                        => 'Glutathione',
	];
	if ( isset( $peptide_drug_names[ $parent_slug ] ) ) {
		$drug      = $peptide_drug_names[ $parent_slug ];
		$variation = $values['variation'] ?? [];

		$supply_slug = $variation['attribute_pa_vial-wellness'] ?? $variation['attribute_pa_bottle'] ?? '';
		$supply_display = [
			'1-vial' => '1 vial', '2-vial' => '2 vials', '3-vial' => '3 vials',
			'1-bottle' => '1 vial', '2-bottle' => '2 vials', '3-bottle' => '3 vials',
		];
		$supply_str = $supply_display[ $supply_slug ] ?? $supply_slug;

		if ( $supply_str ) {
			$rx_name = $drug . ' - ' . $supply_str;
			$item->add_meta_data( 'Rx Summary', $rx_name );
			$item->set_name( $rx_name );
		}
		return;
	}

	$sexual_health_drug_names = [
		'compound-oral-tadalafil' => 'Tadalafil',
		'compound-sildenafil'     => 'Sildenafil',
		'testosterone'            => 'Testosterone Cypionate',
	];
	if ( isset( $sexual_health_drug_names[ $parent_slug ] ) ) {
		$drug      = $sexual_health_drug_names[ $parent_slug ];
		$variation = $values['variation'] ?? [];
		$dosage    = $variation['attribute_pa_dosage']            ?? '';
		$days      = $variation['attribute_pa_days']              ?? '';
		$tablets   = $variation['attribute_pa_tablets']           ?? '';
		$plan      = $variation['attribute_pa_subscription-plan'] ?? '';
		$parts     = array_filter( [ $dosage, $days ?: $tablets ?: $plan ] );
		if ( $parts ) {
			$rx_name = $drug . ' - ' . implode( ', ', $parts );
			$item->add_meta_data( 'Rx Summary', $rx_name );
			$item->set_name( $rx_name );
		}
	}
}, 10, 4 );

// Look up the 1-vial price for a given dose slug on a weight management product.
// Used by the cart price override below.
function myogenix_get_1vial_price( $parent_id, $dose_slug ) {
	$parent = wc_get_product( $parent_id );
	if ( ! $parent ) return 0;
	$attrs           = $parent->get_attributes();
	$dose_meta_key   = isset( $attrs['pa_individual-dose'] ) ? 'attribute_pa_individual-dose' : 'attribute_pa_dosage';
	$bottle_meta_key = isset( $attrs['pa_wm-bottle'] )       ? 'attribute_pa_wm-bottle'       : 'attribute_pa_vial';
	$one_vial        = [ '1-vial', '1-bottle' ];
	foreach ( $parent->get_children() as $vid ) {
		if ( 'publish' !== get_post_status( $vid ) ) continue;
		if ( get_post_meta( $vid, $dose_meta_key, true ) !== $dose_slug ) continue;
		if ( ! in_array( get_post_meta( $vid, $bottle_meta_key, true ), $one_vial, true ) ) continue;
		$price = (float) get_post_meta( $vid, '_price', true );
		if ( $price > 0 ) return $price;
	}
	return 0;
}

// Override cart price for mixed-dose BYO orders.
// When monthly doses differ, charge the sum of each month's 1-vial price instead of
// the variation's N-vial bulk rate. Package variations (Starter/Continuation) are
// detected by their non-numeric dose attribute and are left at their flat rate.
add_action( 'woocommerce_before_calculate_totals', function ( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
	$wm_slugs = [ 'compound-tirzepatide', 'compound-semaglutide', 'compound-retatrutide' ];
	foreach ( $cart->get_cart() as $cart_item ) {
		$parent_slug = get_post_field( 'post_name', $cart_item['product_id'] ?? 0 );
		if ( ! in_array( $parent_slug, $wm_slugs, true ) ) continue;

		// Package variations have a non-numeric dose slug (e.g. "months-1-3-bundle") — skip them.
		$variation    = $cart_item['variation'] ?? [];
		$var_dose_raw = $variation['attribute_pa_individual-dose'] ?? $variation['attribute_pa_dosage'] ?? '';
		if ( ! empty( $var_dose_raw ) && (float) $var_dose_raw === 0.0 ) continue;

		$doses = array_values( array_filter( [
			$cart_item['dose_month_1'] ?? '',
			$cart_item['dose_month_2'] ?? '',
			$cart_item['dose_month_3'] ?? '',
		] ) );

		// Same dose every month → variation's N-vial bulk price is correct, leave it alone.
		if ( count( $doses ) < 2 || count( array_unique( $doses ) ) === 1 ) continue;

		// Mixed doses → sum each month's 1-vial price.
		$total = 0;
		foreach ( $doses as $dose_slug ) {
			$total += myogenix_get_1vial_price( $cart_item['product_id'], $dose_slug );
		}
		if ( $total > 0 ) {
			$cart_item['data']->set_price( $total );
		}
	}
}, 10 );

// For variable subscriptions whose cheapest variation bills every N months (N > 1),
// show the per-month equivalent instead of the lump-sum total so archive/home-page
// tiles display consistently with the PDP plan boxes (e.g. $567/3mo → $189/month).
add_filter( 'woocommerce_variable_subscription_price_html', function ( $price_html, $product ) {
	if ( ! class_exists( 'WC_Subscriptions_Product' ) ) return $price_html;

	$min_variation_id = $product->get_meta( '_min_price_variation_id' );
	if ( ! $min_variation_id ) return $price_html;

	$variation = wc_get_product( $min_variation_id );
	if ( ! $variation ) return $price_html;

	$interval = (int) WC_Subscriptions_Product::get_interval( $variation );
	if ( $interval <= 1 ) return $price_html;

	$period = WC_Subscriptions_Product::get_period( $variation );
	if ( 'month' !== $period ) return $price_html;

	$price = (float) WC_Subscriptions_Product::get_price( $variation );
	if ( $price <= 0 ) return $price_html;

	return wcs_price_string( [
		'recurring_amount'      => $price / $interval,
		'subscription_interval' => 1,
		'subscription_period'   => 'month',
		'subscription_length'   => 0,
		'trial_length'          => 0,
	] );
}, 10, 2 );

// In WC Blocks checkout (Store API context), hide the confusing WC Subscriptions
// price string ("$599 → $0.00/month") and sale badge ("Save $599/month") for
// Prescribery-synced products. The "Expected Product Charge After Approval" row
// already shows the correct charge. Scoped to /wc/store/ requests only so PDPs,
// admin, and WC REST API v3 are unaffected.
add_filter( 'woocommerce_get_price_html', function ( $price_html, $product ) {
	if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) return $price_html;
	if ( strpos( $_SERVER['REQUEST_URI'] ?? '', '/wc/store/' ) === false ) return $price_html;
	$product_id = $product->get_parent_id() ?: $product->get_id();
	if ( strtolower( trim( get_post_meta( $product_id, '_pre_woo_sync', true ) ) ) === 'yes' ) return '';
	return $price_html;
}, 10, 2 );

// Also zero regular_price in Store API context for synced products so WC Blocks
// does not render a "Save $X/month" sale badge (badge fires when regular > current).
add_filter( 'woocommerce_product_get_regular_price', 'myogenix_synced_regular_price_store_api', 99, 2 );
add_filter( 'woocommerce_product_variation_get_regular_price', 'myogenix_synced_regular_price_store_api', 99, 2 );
function myogenix_synced_regular_price_store_api( $price, $product ) {
	if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) return $price;
	if ( strpos( $_SERVER['REQUEST_URI'] ?? '', '/wc/store/' ) === false ) return $price;
	$product_id = $product->get_parent_id() ?: $product->get_id();
	if ( strtolower( trim( get_post_meta( $product_id, '_pre_woo_sync', true ) ) ) === 'yes' ) {
		return $product->get_price(); // regular = current ($0) → no sale badge
	}
	return $price;
}

// Cart/checkout: ensure variation meta dd elements stack as blocks so <br> line breaks render
add_action( 'wp_enqueue_scripts', function() {
	if ( is_cart() || is_checkout() ) {
		wp_add_inline_style( 'hello-elementor-style',
			'.variation dt, .variation dd { display: block; float: none; margin: 0; }' .
			'.variation dt { font-weight: 600; margin-top: 6px; }' .
			'.variation dd { margin-bottom: 2px; }'
		);
	}
}, 20 );

// Enqueue PDP styles and scripts on single product pages, category pages, and retatrutide page
add_action( 'wp_enqueue_scripts', function() {
	$is_pdp    = is_singular( 'product' );
	$is_wm_cat = is_tax( 'product_cat', 'weight-loss' );
	$is_mh_cat = is_tax( 'product_cat', 'mens-health' );
	$is_sh_cat = is_tax( 'product_cat', 'sexual-health' );
	$is_pl_cat = is_tax( 'product_cat', 'peptides-longevity' );
	$is_rtd    = is_page( 'retatrutide' );

	if ( $is_pdp || $is_wm_cat || $is_mh_cat || $is_sh_cat || $is_pl_cat || $is_rtd ) {
		wp_enqueue_style(
			'myogenix-pdp',
			get_stylesheet_directory_uri() . '/assets/css/pdp.css',
			[],
			'1.9.7'
		);
	}

	if ( $is_pdp || $is_rtd ) {
		$product_slug = $is_pdp ? get_post_field( 'post_name', get_the_ID() ) : '';

		$wm_slugs = [ 'compound-tirzepatide', 'compound-semaglutide', 'compound-retatrutide' ];
		if ( $is_rtd || in_array( $product_slug, $wm_slugs, true ) ) {
			wp_enqueue_script(
				'myogenix-pdp',
				get_stylesheet_directory_uri() . '/assets/js/pdp.js',
				[],
				'1.5.0',
				true
			);
		}

		$peptide_slugs = [
			'bpc', 'motsc', 'epithalon', 'compound-injectable-nad',
			'tesamorelin-ipamorelin', 'cjc1295-ipamorelin',
			'klow-stack-bpc157-10mg-ghk-cu-50mg-tb50010mg-kpv-10mg',
			'2606', 'compound-injectable-sermorelin', 'compound-injectable-glutathione',
		];
		if ( in_array( $product_slug, $peptide_slugs, true ) ) {
			wp_enqueue_script(
				'myogenix-peptide-pdp',
				get_stylesheet_directory_uri() . '/assets/js/peptide-pdp.js',
				[],
				'1.4.1',
				true
			);
		}

		$sexual_health_slugs = [ 'compound-oral-tadalafil', 'compound-sildenafil', 'testosterone' ];
		if ( in_array( $product_slug, $sexual_health_slugs, true ) ) {
			wp_enqueue_script(
				'myogenix-sexual-health-pdp',
				get_stylesheet_directory_uri() . '/assets/js/sexual-health-pdp.js',
				[],
				'1.2.1',
				true
			);
		}

		if ( $product_slug === 'testosterone' ) {
			wp_enqueue_script(
				'myogenix-trt-pdp',
				get_stylesheet_directory_uri() . '/assets/js/trt-pdp.js',
				[],
				'1.1.0',
				true
			);
		}
	}
} );

/**
 * Render product category scrollers (hp-catbox pattern from the home page).
 *
 * @param string[] $category_slugs  Keys: 'mens-health', 'weight-loss', 'peptides'.
 * @param int      $exclude_id      WC product ID to omit (avoids listing the current PDP).
 */
function myogenix_render_product_scrollers( array $category_slugs, int $exclude_id = 0 ): void {
	$all_ids = [
		'tirzepatide'  => 4063,
		'semaglutide'  => 4041,
		'wolverine'    => 2606,
		'tesamorelin'  => 2803,
		'klow'         => 2819,
		'glow'         => 1868,
		'bpc'          => 4249,
		'motsc'        => 4253,
		'epithalon'    => 4257,
		'tadalafil'    => 1886,
		'sildenafil'   => 1883,
		'testosterone' => 883,
	];
	$all_meta = [
		'tirzepatide'  => [ 'name' => 'Tirzepatide',   'tagline' => 'Dual-action GLP-1 therapy',  'unit' => '/mo'   ],
		'semaglutide'  => [ 'name' => 'Semaglutide',   'tagline' => 'Proven GLP-1 therapy',       'unit' => '/mo'   ],
		'wolverine'    => [ 'name' => 'Wolverine',      'tagline' => 'Elite tissue recovery',      'unit' => '/vial' ],
		'tesamorelin'  => [ 'name' => 'Tesamorelin',    'tagline' => 'GH optimization',            'unit' => '/vial' ],
		'klow'         => [ 'name' => 'Klow',           'tagline' => 'Metabolic support',          'unit' => '/vial' ],
		'glow'         => [ 'name' => 'Glow',           'tagline' => 'Longevity & renewal',        'unit' => '/vial' ],
		'bpc'          => [ 'name' => 'BPC-157',        'tagline' => 'Healing & repair',           'unit' => '/vial' ],
		'motsc'        => [ 'name' => 'MOTSc',          'tagline' => 'Mitochondrial health',       'unit' => '/vial' ],
		'epithalon'    => [ 'name' => 'Epithalon',      'tagline' => 'Longevity peptide',          'unit' => '/vial' ],
		'tadalafil'    => [ 'name' => 'Tadalafil',      'tagline' => 'Daily ED support',           'unit' => '/tablet', 'tablets_supply' => 90 ],
		'sildenafil'   => [ 'name' => 'Sildenafil',     'tagline' => 'Fast-acting ED treatment',   'unit' => '/mo'   ],
		'testosterone' => [ 'name' => 'Testosterone',   'tagline' => 'Hormone optimization',       'unit' => '/mo'   ],
	];
	$all_categories = [
		'mens-health' => [
			'title'    => 'Mens Health',
			'shop_url' => '/product-category/mens-health/',
			'products' => [ 'testosterone', 'tadalafil', 'sildenafil' ],
		],
		'weight-loss' => [
			'title'    => 'Weight Loss',
			'shop_url' => '/product-category/weight-loss/',
			'products' => [ 'tirzepatide', 'semaglutide' ],
		],
		'peptides' => [
			'title'    => 'Peptides',
			'shop_url' => '/product-category/peptides-longevity/',
			'products' => [ 'wolverine', 'tesamorelin', 'klow', 'glow', 'bpc', 'motsc', 'epithalon' ],
			'full'     => true,
		],
	];

	// Collect all product keys needed across the requested categories.
	$needed_keys = [];
	foreach ( $category_slugs as $cat_slug ) {
		if ( isset( $all_categories[ $cat_slug ] ) ) {
			foreach ( $all_categories[ $cat_slug ]['products'] as $pkey ) {
				$needed_keys[ $pkey ] = true;
			}
		}
	}

	// Load WC product data once.
	$products = [];
	foreach ( array_keys( $needed_keys ) as $key ) {
		$id = $all_ids[ $key ] ?? 0;
		if ( ! $id || $id === $exclude_id ) continue;
		$wc = wc_get_product( $id );
		if ( ! $wc ) continue;
		$raw_price = (float) $wc->get_price();
		// Normalise variable-subscription lump-sum to per-month
		if ( $wc->is_type( 'variable-subscription' ) && class_exists( 'WC_Subscriptions_Product' ) ) {
			$min_var_id = $wc->get_meta( '_min_price_variation_id' );
			if ( $min_var_id ) {
				$interval = (int) WC_Subscriptions_Product::get_interval( $min_var_id );
				if ( $interval > 1 && 'month' === WC_Subscriptions_Product::get_period( $min_var_id ) ) {
					$raw_price = $raw_price / $interval;
				}
			}
		}
		// Sildenafil: derive lowest per-month price across tablet-count variations
		if ( $key === 'sildenafil' && $wc->is_type( 'variable' ) ) {
			$min_per_month = PHP_FLOAT_MAX;
			foreach ( $wc->get_children() as $vid ) {
				$v = wc_get_product( $vid );
				if ( ! $v || 'publish' !== get_post_status( $vid ) ) continue;
				$price     = (float) $v->get_price();
				if ( $price <= 0 ) continue;
				$tab_slug  = get_post_meta( $vid, 'attribute_pa_tablets', true );
				$tab_count = (int) $tab_slug;
				$months    = $tab_count > 0 ? $tab_count / 30 : 1;
				$per_month = $price / $months;
				if ( $per_month < $min_per_month ) $min_per_month = $per_month;
			}
			if ( $min_per_month < PHP_FLOAT_MAX ) $raw_price = $min_per_month;
		}
		$months = isset( $all_meta[ $key ]['months_supply'] ) ? (int) $all_meta[ $key ]['months_supply'] : 1;
		if ( $months > 1 ) $raw_price = $raw_price / $months;
		$tablets_supply = isset( $all_meta[ $key ]['tablets_supply'] ) ? (int) $all_meta[ $key ]['tablets_supply'] : 0;
		if ( $tablets_supply > 0 ) $raw_price = $raw_price / $tablets_supply;
		$products[ $key ] = [
			'price' => $raw_price,
			'url'   => $wc->get_permalink(),
			'image' => get_the_post_thumbnail_url( $id, 'large' ) ?: get_the_post_thumbnail_url( $id, 'full' ) ?: '',
		];
	}

	// Render scrollers.
	echo '<div class="home-categories__grid">';
	foreach ( $category_slugs as $cat_slug ) {
		if ( ! isset( $all_categories[ $cat_slug ] ) ) continue;
		$cat        = $all_categories[ $cat_slug ];
		$full_class = ( ! empty( $cat['full'] ) && count( $category_slugs ) !== 2 ) ? ' hp-catbox--full' : '';
		echo '<div class="hp-catbox' . $full_class . '">';
		echo '<div class="hp-catbox__header">';
		echo '<h3 class="hp-catbox__title">' . esc_html( $cat['title'] ) . '</h3>';
		echo '<a href="' . esc_url( home_url( $cat['shop_url'] ) ) . '" class="hp-catbox__shopall">Shop all →</a>';
		echo '</div>';
		echo '<div class="hp-catbox__scroll-wrap"><div class="hp-catbox__scroll">';
		$rendered = 0;
		foreach ( $cat['products'] as $pkey ) {
			if ( empty( $products[ $pkey ] ) ) continue;
			$p       = $products[ $pkey ];
			$m       = $all_meta[ $pkey ];
			$decimals = ( '/tablet' === $m['unit'] ) ? 2 : 0;
			$price   = '$' . number_format( $p['price'], $decimals );
			printf(
				'<a href="%s" class="hp-card" aria-label="%s">
					<div class="hp-card__img-wrap">
						<img src="%s" alt="%s" class="hp-card__img" loading="lazy" width="176" height="176">
					</div>
					<div class="hp-card__body">
						<div class="hp-card__name">%s</div>
						<div class="hp-card__tag">%s</div>
						<div class="hp-card__foot">
							<span class="hp-card__price">%s<span class="hp-card__unit">%s</span></span>
							<span class="hp-card__btn" aria-hidden="true">Shop →</span>
						</div>
					</div>
				</a>',
				esc_url( $p['url'] ),
				esc_attr( $m['name'] ),
				esc_url( $p['image'] ),
				esc_attr( $m['name'] ),
				esc_html( $m['name'] ),
				esc_html( $m['tagline'] ),
				esc_html( $price ),
				esc_html( $m['unit'] )
			);
			$rendered++;
		}
		for ( $i = $rendered; $i < 3; $i++ ) {
			echo '<div class="hp-card hp-card--coming-soon" aria-label="Coming soon">
				<div class="hp-card__img-wrap">
					<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 48 48" aria-hidden="true" width="48" height="48">
						<circle cx="24" cy="24" r="16" stroke="#d4d4d8" stroke-width="1.5" stroke-dasharray="4 3"/>
						<path d="M24 16v8M24 28v2" stroke="#d4d4d8" stroke-width="1.5" stroke-linecap="round"/>
					</svg>
				</div>
				<div class="hp-card__body">
					<div class="hp-card__name" style="color:#a1a1aa;">More coming</div>
					<div class="hp-card__tag">New products may be on the way</div>
					<div class="hp-card__foot"><span class="hp-card__coming-tag">Soon</span></div>
				</div>
			</div>';
		}
		echo '</div>'; // .hp-catbox__scroll
		echo '<div class="hp-catbox__fade" aria-hidden="true"></div>';
		echo '</div>'; // .hp-catbox__scroll-wrap
		echo '</div>'; // .hp-catbox
	}
	echo '</div>'; // .home-categories__grid
}

// Google Places address autocomplete on billing and shipping address_1 fields.
// Works with both Classic WooCommerce checkout and WC Blocks (React-based).
// Define MYOGENIX_GOOGLE_MAPS_KEY in wp-config.php (preferred) or here.
// The Places API must be enabled on the key.
if ( ! defined( 'MYOGENIX_GOOGLE_MAPS_KEY' ) ) {
	define( 'MYOGENIX_GOOGLE_MAPS_KEY', '' );
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_checkout() ) return;
	if ( ! MYOGENIX_GOOGLE_MAPS_KEY ) return; // skip if key not configured

	// Our script must load first so window.initCheckoutAutocomplete is defined
	// before Google Maps calls it as its async callback.
	wp_enqueue_script(
		'myogenix-checkout-autocomplete',
		get_stylesheet_directory_uri() . '/assets/js/checkout-autocomplete.js',
		[],
		'1.0.0',
		true
	);

	wp_enqueue_script(
		'google-maps-places',
		'https://maps.googleapis.com/maps/api/js?key=' . MYOGENIX_GOOGLE_MAPS_KEY . '&libraries=places&callback=initCheckoutAutocomplete',
		[ 'myogenix-checkout-autocomplete' ],
		null, // no version — Google manages its own versioning
		true
	);
}, 20 );

// Remove WooCommerce breadcrumb from all product PDPs.
add_action( 'wp', function() {
	if ( is_product() ) {
		remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
	}
} );

// Apartment / suite field (address_2) is optional.
// The WC admin option woocommerce_checkout_address_2_field controls this,
// but this filter guards against it being reset to 'required' again.
add_filter( 'woocommerce_default_address_fields', function ( $fields ) {
	if ( isset( $fields['address_2'] ) ) {
		$fields['address_2']['required'] = false;
	}
	return $fields;
} );

/* ==========================================================================
   Product FAQ — meta box + render helper
   ========================================================================== */

add_action( 'add_meta_boxes', function() {
	add_meta_box(
		'myogenix_product_faq',
		'Product FAQ (6 Questions)',
		'myogenix_faq_meta_box_html',
		'product',
		'normal',
		'default'
	);
} );

function myogenix_faq_meta_box_html( $post ) {
	$faqs = get_post_meta( $post->ID, '_product_faq', true );
	if ( ! is_array( $faqs ) ) {
		$faqs = [];
	}
	while ( count( $faqs ) < 6 ) {
		$faqs[] = [ 'q' => '', 'a' => '' ];
	}
	wp_nonce_field( 'myogenix_faq_save', 'myogenix_faq_nonce' );
	echo '<p style="color:#666;font-size:13px;margin:0 0 16px;">These 6 questions appear in the FAQ accordion on the product page. Plain text only — no HTML.</p>';
	for ( $i = 0; $i < 6; $i++ ) :
		$border = $i > 0 ? 'border-top:1px solid #eee;padding-top:16px;' : '';
		?>
		<div style="<?= $border ?>margin-bottom:16px;">
			<p style="margin:0 0 6px;font-weight:600;font-size:13px;color:#1d2327;">Q<?= $i + 1 ?></p>
			<input type="text" name="product_faq[<?= $i ?>][q]"
				   value="<?= esc_attr( $faqs[ $i ]['q'] ?? '' ) ?>"
				   style="width:100%;margin-bottom:6px;" placeholder="Question..." />
			<textarea name="product_faq[<?= $i ?>][a]" rows="3"
					  style="width:100%;resize:vertical;" placeholder="Answer..."><?= esc_textarea( $faqs[ $i ]['a'] ?? '' ) ?></textarea>
		</div>
		<?php
	endfor;
}

add_action( 'save_post_product', function( $post_id ) {
	if ( ! isset( $_POST['myogenix_faq_nonce'] ) ) return;
	if ( ! wp_verify_nonce( $_POST['myogenix_faq_nonce'], 'myogenix_faq_save' ) ) return;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;

	$raw  = isset( $_POST['product_faq'] ) ? (array) $_POST['product_faq'] : [];
	$faqs = [];
	foreach ( $raw as $item ) {
		$faqs[] = [
			'q' => sanitize_text_field( $item['q'] ?? '' ),
			'a' => sanitize_textarea_field( $item['a'] ?? '' ),
		];
	}
	update_post_meta( $post_id, '_product_faq', array_slice( $faqs, 0, 6 ) );
} );

function myogenix_render_product_faq( $product_id ) {
	$faqs = get_post_meta( $product_id, '_product_faq', true );
	if ( ! is_array( $faqs ) ) return;
	$faqs = array_values( array_filter( $faqs, fn( $f ) => ! empty( $f['q'] ) ) );
	if ( empty( $faqs ) ) return;
	?>
	<section class="myo-faq">
		<div class="myo-faq__wrap">
			<div class="myo-faq__header">
				<span class="myo-faq__eyebrow">FAQ</span>
				<h2 class="myo-faq__title">Common questions</h2>
				<p class="myo-faq__desc">Everything you need to know about the program, ordering, and what to expect before and after you start.</p>
			</div>
			<div class="myo-faq__list">
				<?php foreach ( $faqs as $idx => $item ) :
					$panel_id   = 'pdp-faq-' . intval( $product_id ) . '-' . $idx;
					$is_first   = ( $idx === 0 );
					$expanded   = $is_first ? 'true' : 'false';
					$open_class = $is_first ? ' is-open' : '';
				?>
				<div class="myo-faq__item">
					<button class="myo-faq__btn" type="button" aria-expanded="<?= $expanded ?>" aria-controls="<?= esc_attr( $panel_id ) ?>">
						<span class="myo-faq__q"><?= esc_html( $item['q'] ) ?></span>
						<span class="myo-faq__icon" aria-hidden="true"><svg width="10" height="6" viewBox="0 0 10 6" fill="none"><path d="M1 1L5 5L9 1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
					</button>
					<div class="myo-faq__panel<?= $open_class ?>" id="<?= esc_attr( $panel_id ) ?>">
						<div class="myo-faq__panel-inner">
							<p><?= nl2br( esc_html( $item['a'] ) ) ?></p>
						</div>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
			<div class="myo-faq__cta">
				<a href="#buy" class="myo-faq__cta-btn">Get started &rarr;</a>
			</div>
		</div>
	</section>
	<?php
}

// ─── TRT checkout notice styles ──────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', function() {
	if ( ! is_checkout() ) return;
	wp_enqueue_style(
		'myogenix-checkout-trt',
		get_stylesheet_directory_uri() . '/assets/css/checkout-trt.css',
		[],
		'1.0.0'
	);
} );

/**
 * TRT checkout notice: the $165 due today only covers the bloodwork panel and
 * initial doctor consult. If approved for treatment, the patient is then
 * billed $567 quarterly ($189/mo x 3) for the medication itself. Shown only
 * when a testosterone item is in the cart, on the checkout page.
 */
add_action( 'woocommerce_review_order_before_payment', function () {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) return;

	$has_trt = false;
	foreach ( WC()->cart->get_cart() as $cart_item ) {
		$parent_id = $cart_item['product_id'] ?? 0;
		$product   = wc_get_product( $parent_id );
		if ( $product && 'testosterone' === $product->get_slug() ) {
			$has_trt = true;
			break;
		}
	}
	if ( ! $has_trt ) return;
	?>
	<div class="myo-trt-checkout-notice">
		<p class="myo-trt-checkout-notice__title">After the Doctor Consult, if you are approved for treatment</p>
		<div class="myo-trt-checkout-notice__price"><?php echo wc_price( 189 ); ?><span>/month</span></div>
		<div class="myo-trt-checkout-notice__rows">
			<div class="myo-trt-checkout-notice__row"><span>Month 1</span><span><?php echo wc_price( 189 ); ?></span></div>
			<div class="myo-trt-checkout-notice__row"><span>Month 2</span><span><?php echo wc_price( 189 ); ?></span></div>
			<div class="myo-trt-checkout-notice__row"><span>Month 3</span><span><?php echo wc_price( 189 ); ?></span></div>
		</div>
		<p class="myo-trt-checkout-notice__note">Full amount charged quarterly &mdash; not split into monthly payments.</p>
		<p class="myo-trt-checkout-notice__note">Processed only after approved for treatment.</p>
	</div>
	<?php
} );
