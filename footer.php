<?php
/**
 * Site footer — overrides Hello Elementor's footer.php.
 *
 * Intentionally does NOT call elementor_theme_do_location('footer'),
 * which means Elementor's footer template (#914) is bypassed on all pages
 * and our custom footer (same design as the home page) renders instead.
 * Mirrors the approach used in header.php for the navbar (#898).
 */
defined( 'ABSPATH' ) || exit;

get_template_part( 'template-parts/site-footer' );
?>

<?php wp_footer(); ?>

</body>
</html>
