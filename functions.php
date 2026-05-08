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

// Display dose schedule in cart and checkout review
add_filter( 'woocommerce_get_item_data', function ( $item_data, $cart_item ) {
	$labels = [
		'dose_month_1' => 'Month 1 Dose',
		'dose_month_2' => 'Month 2 Dose',
		'dose_month_3' => 'Month 3 Dose',
	];
	foreach ( $labels as $key => $label ) {
		if ( ! empty( $cart_item[ $key ] ) ) {
			$item_data[] = [
				'key'   => $label,
				'value' => esc_html( myogenix_dose_display( $cart_item[ $key ] ) ),
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

	// Consolidated Rx Summary for backend processing
	// Format: TIRZEPATIDE - 10mg, 20mg, 30mg, 3 Bottle, 3 month
	$parent_slug = get_post_field( 'post_name', $values['product_id'] ?? 0 );
	if ( in_array( $parent_slug, [ 'compound-tirzepatide', 'compound-semaglutide' ], true ) ) {
		$drug = strtoupper( str_replace( 'compound-', '', $parent_slug ) );

		// Bottle count from whichever attribute this product uses
		$variation  = $values['variation'] ?? [];
		$bottle_raw = $variation['attribute_pa_wm-bottle'] ?? $variation['attribute_pa_vial'] ?? '';
		$bottle_num = (int) preg_replace( '/[^0-9]/', '', $bottle_raw );

		// Collect non-empty per-month doses; show all if different, just one if all same
		$dose_fields = array_values( array_filter( array_map(
			'myogenix_dose_display',
			array_filter( [
				$values['dose_month_1'] ?? '',
				$values['dose_month_2'] ?? '',
				$values['dose_month_3'] ?? '',
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
		if ( $parts ) {
			$item->add_meta_data( 'Rx Summary', $drug . ' - ' . implode( ', ', $parts ) );
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
}, 99 );

// Enqueue PDP styles and scripts on single product pages only
add_action( 'wp_enqueue_scripts', function() {
	if ( is_singular( 'product' ) ) {
		wp_enqueue_style(
			'myogenix-pdp',
			get_stylesheet_directory_uri() . '/assets/css/pdp.css',
			[],
			'1.3.3'
		);
		wp_enqueue_script(
			'myogenix-pdp',
			get_stylesheet_directory_uri() . '/assets/js/pdp.js',
			[],
			'1.3.3',
			true
		);
	}
} );
