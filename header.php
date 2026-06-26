<?php
/**
 * Site header — overrides Hello Elementor's header.php.
 *
 * Intentionally does NOT call elementor_theme_do_location('header'),
 * which means Elementor's header template (#898) is bypassed on all pages
 * and our custom navbar renders instead. The <body> tag is left open here —
 * wp_footer() and </body></html> come from the parent theme's footer.php.
 */
defined( 'ABSPATH' ) || exit;

$viewport_content = apply_filters( 'hello_elementor_viewport_content', 'width=device-width, initial-scale=1' );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="<?php echo esc_attr( $viewport_content ); ?>">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#content"><?php esc_html_e( 'Skip to content', 'hello-elementor' ); ?></a>
<?php get_template_part( 'template-parts/site-header' ); ?>
