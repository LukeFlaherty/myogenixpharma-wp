<?php
/**
 * The template for displaying product content in the single-product.php template
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/content-single-product.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.6.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

/**
 * Hook: woocommerce_before_single_product.
 *
 * @hooked woocommerce_output_all_notices - 10
 */
do_action( 'woocommerce_before_single_product' );

if ( post_password_required() ) {
	echo get_the_password_form(); // WPCS: XSS ok.
	return;
}
?>
<div id="product-<?php the_ID(); ?>" <?php wc_product_class( '', $product ); ?>>

	<?php
	$product_slug = $product->get_slug();
	$weight_loss_slugs = [ 'compound-semaglutide', 'compound-tirzepatide' ];

	if ( in_array( $product_slug, $weight_loss_slugs, true ) ) :
		$hero_content = [
			'compound-semaglutide' => [
				'headline'    => 'Placeholder Headline for Semaglutide',
				'subheadline' => 'Placeholder subheadline — describe the key benefit or offer here.',
				'button_text' => 'Get Started',
				'button_url'  => '#buy',
			],
			'compound-tirzepatide' => [
				'headline'    => 'Placeholder Headline for Tirzepatide',
				'subheadline' => 'Placeholder subheadline — describe the key benefit or offer here.',
				'button_text' => 'Get Started',
				'button_url'  => '#buy',
			],
		];
		$hero = $hero_content[ $product_slug ];
		?>
		<div class="myogenix-pdp-hero">
			<h1 class="myogenix-pdp-hero__headline"><?php echo esc_html( $hero['headline'] ); ?></h1>
			<p class="myogenix-pdp-hero__subheadline"><?php echo esc_html( $hero['subheadline'] ); ?></p>
			<a href="<?php echo esc_url( $hero['button_url'] ); ?>" class="myogenix-pdp-hero__button button"><?php echo esc_html( $hero['button_text'] ); ?></a>
		</div>
	<?php endif; ?>

	<?php
	/**
	 * Hook: woocommerce_before_single_product_summary.
	 *
	 * @hooked woocommerce_show_product_sale_flash - 10
	 * @hooked woocommerce_show_product_images - 20
	 */
	do_action( 'woocommerce_before_single_product_summary' );
	?>

	<div class="summary entry-summary">
		<?php
		/**
		 * Hook: woocommerce_single_product_summary.
		 *
		 * @hooked woocommerce_template_single_title - 5
		 * @hooked woocommerce_template_single_rating - 10
		 * @hooked woocommerce_template_single_price - 10
		 * @hooked woocommerce_template_single_excerpt - 20
		 * @hooked woocommerce_template_single_add_to_cart - 30  ← variations + subscriptions attach here
		 * @hooked woocommerce_template_single_meta - 40
		 * @hooked woocommerce_template_single_sharing - 50
		 * @hooked WC_Structured_Data::generate_product_data() - 60
		 */
		do_action( 'woocommerce_single_product_summary' );
		?>
	</div>

	<?php
	/**
	 * Hook: woocommerce_after_single_product_summary.
	 *
	 * @hooked woocommerce_output_product_data_tabs - 10
	 * @hooked woocommerce_upsell_display - 15
	 * @hooked woocommerce_output_related_products - 20
	 */
	do_action( 'woocommerce_after_single_product_summary' );
	?>
</div>

<?php do_action( 'woocommerce_after_single_product' ); ?>
