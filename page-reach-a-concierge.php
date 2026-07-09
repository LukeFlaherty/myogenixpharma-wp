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
	<div class="rac-card">
		<h1 class="rac-title">Talk to a Concierge</h1>
		<p class="rac-subtitle">Tell us what you're interested in and a member of our team will reach out shortly.</p>

		<form id="rac-form" novalidate>
			<label class="rac-label" for="rac-email">Email</label>
			<input type="email" id="rac-email" name="email" class="rac-input" placeholder="you@example.com" required autocomplete="email" />

			<label class="rac-label" for="rac-products">Products I'm curious about</label>
			<select id="rac-products" name="products" class="rac-input rac-select" multiple size="6">
				<?php foreach ( $products as $product ) : ?>
					<option value="<?php echo esc_attr( $product->get_name() ); ?>"><?php echo esc_html( $product->get_name() ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="rac-hint">Hold Cmd (Mac) or Ctrl (Windows) to select multiple.</p>

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
