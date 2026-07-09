<?php
/**
 * Template Name: Reach a Concierge
 * Description: Klaviyo landing page — prefilled contact form for concierge follow-up. Submits to GHL webhook.
 */

defined( 'ABSPATH' ) || exit;

$products = wc_get_products( [
	'status'  => 'publish',
	'limit'   => -1,
	'orderby' => 'title',
	'order'   => 'ASC',
	'return'  => 'objects',
] );

get_header();
?>

<div class="rac-page">

	<!-- ═══════════════════════════════════════════════════
	     HERO
	════════════════════════════════════════════════════ -->
	<section class="rac-hero">
		<div class="rac-hero__grid" aria-hidden="true"></div>
		<div class="rac-hero__content">
			<div class="rac-hero__pill">
				<span class="rac-hero__pill-dot" aria-hidden="true"></span>
				<span class="rac-hero__pill-text">Concierge follow-up</span>
			</div>
			<h1 class="rac-hero__headline">Let's find the right plan for you.</h1>
			<p class="rac-hero__sub">Tell us what you're interested in below. A member of our care team will personally review your request and reach out to you directly — no forms, no waiting rooms, just a real conversation about your goals.</p>
		</div>
	</section>

	<div class="rac-card">
		<form id="rac-form" novalidate>
			<label class="rac-label" for="rac-email">Email</label>
			<input type="email" id="rac-email" name="email" class="rac-input" placeholder="you@example.com" required autocomplete="email" />

			<label class="rac-label" for="rac-phone">Phone number <span class="rac-label__optional">(optional)</span></label>
			<input type="tel" id="rac-phone" name="phone" class="rac-input" placeholder="(555) 123-4567" autocomplete="tel" />

			<label class="rac-label">Products I'm curious about</label>
			<div id="rac-products" class="rac-products" role="group" aria-label="Products I'm curious about">
				<?php foreach ( $products as $product ) :
					$img_id  = $product->get_image_id();
					$img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' );
					?>
					<button type="button" class="rac-product-card" data-product="<?php echo esc_attr( $product->get_name() ); ?>" aria-pressed="false">
						<span class="rac-product-card__img-wrap">
							<img src="<?php echo esc_url( $img_url ); ?>" alt="" loading="lazy" class="rac-product-card__img" />
						</span>
						<span class="rac-product-card__name"><?php echo esc_html( $product->get_name() ); ?></span>
						<span class="rac-product-card__check" aria-hidden="true">&#10003;</span>
					</button>
				<?php endforeach; ?>
			</div>

			<label class="rac-label" for="rac-message">Additional message</label>
			<textarea id="rac-message" name="message" class="rac-input rac-textarea" rows="4" placeholder="Anything else we should know?"></textarea>

			<button type="submit" id="rac-submit" class="rac-submit">Contact Me</button>
			<div id="rac-error" class="rac-error" role="alert"></div>
		</form>

		<div id="rac-success" class="rac-success" style="display:none;">
			<div class="rac-success__check">&#10003;</div>
			<h2>We'll be in touch soon!</h2>
			<p>A member of our team will reach out to you shortly.</p>
		</div>
	</div>
</div>

<?php get_footer(); ?>
