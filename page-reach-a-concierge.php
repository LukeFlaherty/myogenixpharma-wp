<?php
/**
 * Template Name: Reach a Concierge
 * Description: Klaviyo landing page — contact form for concierge follow-up. Submits to GHL webhook.
 */

defined( 'ABSPATH' ) || exit;

// Four main categories, each shown as a single representative bottle — not a
// card per product. mens-health -> Testosterone Cypionate, weight-loss ->
// Semaglutide, sexual-health -> Tadalafil, peptides-longevity -> BPC-157
// (most-ordered peptide).
$rac_categories = [
	[ 'label' => 'TRT',           'product_id' => 883 ],
	[ 'label' => 'Weight Loss',   'product_id' => 4041 ],
	[ 'label' => 'Sexual Health', 'product_id' => 1886 ],
	[ 'label' => 'Peptides',      'product_id' => 4249 ],
];
foreach ( $rac_categories as &$rac_cat ) {
	$rac_cat_product   = wc_get_product( $rac_cat['product_id'] );
	$rac_cat['image']  = $rac_cat_product ? wp_get_attachment_image_url( $rac_cat_product->get_image_id(), 'medium' ) : wc_placeholder_img_src( 'medium' );
}
unset( $rac_cat );

get_header();
?>

<div class="rac-page">
	<div class="rac-page__grid" aria-hidden="true"></div>

	<!-- ═══════════════════════════════════════════════════
	     HERO
	════════════════════════════════════════════════════ -->
	<section class="rac-hero">
		<div class="rac-hero__content">
			<div class="rac-hero__pill">
				<span class="rac-hero__pill-dot" aria-hidden="true"></span>
				<span class="rac-hero__pill-text">Get 1-on-1 Guidance</span>
			</div>
			<h1 class="rac-hero__headline">Get More Info</h1>
			<p class="rac-hero__sub">Tell us what you're interested in below. A member of our care team will personally review your request and reach out to you directly.</p>
		</div>
	</section>

	<div class="rac-card">
		<form id="rac-form" novalidate>
			<div class="rac-field">
				<label class="rac-label" for="rac-email">Email</label>
				<input type="email" id="rac-email" name="email" class="rac-input" placeholder="you@example.com" required autocomplete="email" />

				<label class="rac-label" for="rac-phone">Phone number <span class="rac-label__optional">(optional)</span></label>
				<input type="tel" id="rac-phone" name="phone" class="rac-input" placeholder="(555) 123-4567" autocomplete="tel" />

				<label class="rac-label">What are you interested in?</label>
			</div>
			<div id="rac-products" class="rac-products" role="group" aria-label="What are you interested in?">
				<?php foreach ( $rac_categories as $rac_cat ) : ?>
					<button type="button" class="rac-product-card" data-product="<?php echo esc_attr( $rac_cat['label'] ); ?>" aria-pressed="false">
						<span class="rac-product-card__img-wrap">
							<img src="<?php echo esc_url( $rac_cat['image'] ); ?>" alt="" loading="lazy" class="rac-product-card__img" />
						</span>
						<span class="rac-product-card__name"><?php echo esc_html( $rac_cat['label'] ); ?></span>
						<span class="rac-product-card__check" aria-hidden="true">&#10003;</span>
					</button>
				<?php endforeach; ?>
			</div>

			<div class="rac-field">
				<label class="rac-label rac-label--message" for="rac-message">Additional message</label>
				<textarea id="rac-message" name="message" class="rac-input rac-textarea" rows="4" placeholder="Anything else we should know?"></textarea>

				<button type="submit" id="rac-submit" class="rac-submit">Contact Me</button>
				<div id="rac-error" class="rac-error" role="alert"></div>
			</div>
		</form>

		<div id="rac-success" class="rac-success" style="display:none;">
			<div class="rac-success__check">&#10003;</div>
			<h2>We'll be in touch soon!</h2>
			<p>A member of our team will reach out to you shortly.</p>
		</div>
	</div>
</div>

<?php get_footer(); ?>
