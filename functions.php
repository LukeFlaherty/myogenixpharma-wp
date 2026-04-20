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
});
