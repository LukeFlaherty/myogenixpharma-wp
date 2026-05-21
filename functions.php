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

// Replace cart/checkout item title with full multi-dose Rx name
// e.g. TIRZEPATIDE - 10mg, 20mg, 40mg, 3 Bottle, 3 month
add_filter( 'woocommerce_cart_item_name', function ( $name, $cart_item, $cart_item_key ) {
	$parent_slug = get_post_field( 'post_name', $cart_item['product_id'] ?? 0 );
	if ( ! in_array( $parent_slug, [ 'compound-tirzepatide', 'compound-semaglutide' ], true ) ) {
		return $name;
	}

	$drug = strtoupper( str_replace( 'compound-', '', $parent_slug ) );

	$variation  = $cart_item['variation'] ?? [];
	$bottle_raw = $variation['attribute_pa_wm-bottle'] ?? $variation['attribute_pa_vial'] ?? '';
	$bottle_num = (int) preg_replace( '/[^0-9]/', '', $bottle_raw );

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
	$parent_slug = get_post_field( 'post_name', $values['product_id'] ?? 0 );
	if ( in_array( $parent_slug, [ 'compound-tirzepatide', 'compound-semaglutide' ], true ) ) {
		$drug = ucfirst( str_replace( 'compound-', '', $parent_slug ) );

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
	$wm_slugs = [ 'compound-tirzepatide', 'compound-semaglutide' ];
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

// Enqueue PDP styles and scripts on single product pages and the weight-loss category
add_action( 'wp_enqueue_scripts', function() {
	$is_pdp = is_singular( 'product' );
	$is_wm_cat = is_tax( 'product_cat', 'weight-loss' );

	if ( $is_pdp || $is_wm_cat ) {
		wp_enqueue_style(
			'myogenix-pdp',
			get_stylesheet_directory_uri() . '/assets/css/pdp.css',
			[],
			'1.3.4'
		);
	}
	if ( $is_pdp ) {
		wp_enqueue_script(
			'myogenix-pdp',
			get_stylesheet_directory_uri() . '/assets/js/pdp.js',
			[],
			'1.3.4',
			true
		);
	}
} );

// Google Places address autocomplete on billing and shipping address_1 fields.
// Works with both Classic WooCommerce checkout and WC Blocks (React-based).
// Replace MYOGENIX_GOOGLE_MAPS_KEY with your real API key (Places API must be enabled).
define( 'MYOGENIX_GOOGLE_MAPS_KEY', 'YOUR_GOOGLE_MAPS_API_KEY' );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_checkout() ) return;

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
