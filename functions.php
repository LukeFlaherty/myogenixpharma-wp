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

// Enqueue PDP styles and scripts on single product pages only
add_action( 'wp_enqueue_scripts', function() {
	if ( is_singular( 'product' ) ) {
		wp_enqueue_style(
			'myogenix-pdp',
			get_stylesheet_directory_uri() . '/assets/css/pdp.css',
			[],
			'1.3.2'
		);
		wp_enqueue_script(
			'myogenix-pdp',
			get_stylesheet_directory_uri() . '/assets/js/pdp.js',
			[],
			'1.3.2',
			true
		);
	}
} );
