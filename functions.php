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

// Enqueue PDP styles and scripts on single product pages only
add_action( 'wp_enqueue_scripts', function() {
	if ( is_singular( 'product' ) ) {
		wp_enqueue_style(
			'myogenix-pdp',
			get_stylesheet_directory_uri() . '/assets/css/pdp.css',
			[],
			'1.0.4'
		);
		wp_enqueue_script(
			'myogenix-pdp',
			get_stylesheet_directory_uri() . '/assets/js/pdp.js',
			[],
			'1.0.4',
			true
		);
	}
} );
