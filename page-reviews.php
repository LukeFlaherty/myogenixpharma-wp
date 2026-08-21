<?php
/**
 * Customer reviews page: leave-a-review form + published reviews (or a
 * product grid fallback while no reviews exist yet).
 */
defined( 'ABSPATH' ) || exit;

$myo_asset = function( $path ) {
	return function_exists( 'myogenix_grunge_asset_url' ) ? myogenix_grunge_asset_url( $path ) : '';
};

add_filter( 'body_class', function( $classes ) {
	$classes[] = 'grunge-redesign-page';
	$classes[] = 'grunge-reviews-page';
	$classes[] = 'myo-disable-purchase-popup';
	return $classes;
} );

add_filter( 'pre_get_document_title', function() {
	return 'Customer Reviews - Myogenix Pharma';
} );

add_filter( 'rank_math/frontend/title', function() {
	return 'Customer Reviews - Myogenix Pharma';
} );

$reviews = function_exists( 'myogenix_get_published_reviews' ) ? myogenix_get_published_reviews() : [];

$current_user   = is_user_logged_in() ? wp_get_current_user() : null;
$prefill_name   = $current_user ? trim( $current_user->display_name ) : '';
$prefill_email  = $current_user ? $current_user->user_email : '';

$products = [];
if ( function_exists( 'wc_get_products' ) ) {
	$wc_products = wc_get_products( [
		'status'  => 'publish',
		'limit'   => -1,
		'orderby' => 'title',
		'order'   => 'ASC',
		'return'  => 'objects',
	] );
	foreach ( $wc_products as $product ) {
		if ( ! $product || ! is_object( $product ) ) continue;
		$image = function_exists( 'myogenix_grunge_bottle_url' ) ? myogenix_grunge_bottle_url( $product ) : '';
		if ( ! $image ) {
			$image = get_the_post_thumbnail_url( $product->get_id(), 'medium' ) ?: get_the_post_thumbnail_url( $product->get_id(), 'full' );
		}
		if ( ! $image ) {
			$image = $myo_asset( 'pharma support staff tp bg.png' );
		}
		$products[] = [
			'id'    => $product->get_id(),
			'name'  => $product->get_name(),
			'url'   => $product->get_permalink(),
			'image' => $image,
		];
	}
}

get_header();
?>

<main id="content" class="grunge-reviews">
	<section class="grunge-reviews__hero">
		<div class="grunge-reviews__hero-texture" style="background-image:url('<?php echo $myo_asset( 'grunge black section bg blank.png' ); ?>')" aria-hidden="true"></div>
		<div class="grunge-container grunge-reviews__hero-inner">
			<div class="grunge-reviews__hero-copy">
				<p class="grunge-kicker">Customer feedback</p>
				<h1 class="grunge-reviews__hero-title">
					<span class="grunge-word grunge-word--white">Customer</span>
					<span class="grunge-word grunge-word--red">Reviews</span>
				</h1>
				<p class="grunge-reviews__hero-body">Real feedback from Myogenix Pharma customers. Bought something from us? We'd love to hear about your experience.</p>
				<button type="button" class="grunge-btn grunge-btn--red" id="myo-review-cta">Leave a Review <?php echo function_exists( 'myogenix_grunge_arrow_svg' ) ? myogenix_grunge_arrow_svg() : ''; ?></button>
			</div>

			<form id="myo-review-form" class="myo-review-form" hidden novalidate>
				<h2 class="myo-review-form__title">Leave a Review</h2>

				<div class="myo-review-form__row">
					<label for="myo-review-name">Your name<span aria-hidden="true">*</span></label>
					<input type="text" id="myo-review-name" name="reviewer_name" required autocomplete="name" value="<?php echo esc_attr( $prefill_name ); ?>">
				</div>

				<div class="myo-review-form__row">
					<label for="myo-review-email">Email<span aria-hidden="true">*</span></label>
					<input type="email" id="myo-review-email" name="reviewer_email" required autocomplete="email" value="<?php echo esc_attr( $prefill_email ); ?>">
				</div>

				<!-- Honeypot -->
				<div class="myo-review-form__honeypot" aria-hidden="true">
					<label for="myo-review-website">Website</label>
					<input type="text" id="myo-review-website" name="myo_review_website" tabindex="-1" autocomplete="off">
				</div>

				<div class="myo-review-form__row">
					<label>Which product(s) did you buy? Rate each one you're reviewing.<span aria-hidden="true">*</span></label>
					<div class="myo-review-form__products">
						<?php foreach ( $products as $product ) : ?>
						<div class="myo-review-product-row" data-review-product-row="<?php echo esc_attr( $product['id'] ); ?>">
							<span class="myo-review-product-row__summary">
								<span class="myo-review-product-row__image">
									<img src="<?php echo esc_url( $product['image'] ); ?>" alt="" width="56" height="56" loading="lazy">
								</span>
								<span class="myo-review-product-row__name"><?php echo esc_html( $product['name'] ); ?></span>
							</span>
							<div class="myo-star-rating" data-product-id="<?php echo esc_attr( $product['id'] ); ?>">
								<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
								<input type="radio" id="myo-star-<?php echo esc_attr( $product['id'] . '-' . $i ); ?>" name="ratings[<?php echo esc_attr( $product['id'] ); ?>]" value="<?php echo esc_attr( $i ); ?>">
								<label for="myo-star-<?php echo esc_attr( $product['id'] . '-' . $i ); ?>" title="<?php echo esc_attr( $i ); ?> star<?php echo 1 === $i ? '' : 's'; ?>">&#9733;</label>
								<?php endfor; ?>
							</div>
						</div>
						<?php endforeach; ?>
						<?php if ( empty( $products ) ) : ?>
						<p>No products are available to review right now.</p>
						<?php endif; ?>
					</div>
				</div>

				<div class="myo-review-form__row">
					<label for="myo-review-message">Additional information <span class="myo-review-form__optional">(optional)</span></label>
					<textarea id="myo-review-message" name="additional_info" rows="4" placeholder="Tell us more about your experience..."></textarea>
				</div>

				<p class="myo-review-form__error" id="myo-review-form-error" role="alert" hidden></p>

				<div class="myo-review-form__actions">
					<button type="submit" class="grunge-btn grunge-btn--red" id="myo-review-submit">Submit Review</button>
					<button type="button" class="grunge-btn grunge-btn--dark" id="myo-review-cancel">Cancel</button>
				</div>
			</form>

			<div class="myo-review-form__success" id="myo-review-success" hidden>
				<strong>Thank you!</strong>
				<span>Your review has been submitted and is pending approval.</span>
			</div>
		</div>
	</section>

	<?php if ( ! empty( $reviews ) ) : ?>
	<section class="grunge-section grunge-reviews__list-section">
		<div class="grunge-section__texture" style="background-image:url('<?php echo $myo_asset( 'red-dots-grid-background.webp' ); ?>')" aria-hidden="true"></div>
		<div class="grunge-container">
			<div class="grunge-section__header">
				<p class="grunge-kicker">What customers are saying</p>
				<h2>Read Our <span class="grunge-text-red">Reviews</span></h2>
				<p>Real customer feedback from approved Myogenix Pharma product reviews.</p>
			</div>
			<div class="myo-review-grid">
				<?php foreach ( $reviews as $review ) : ?>
				<div class="myo-review-card">
					<div class="myo-review-card__head">
						<strong class="myo-review-card__name"><?php echo esc_html( $review['name'] ); ?></strong>
						<?php if ( $review['verified'] ) : ?>
						<span class="myo-review-card__verified">Verified Purchase</span>
						<?php endif; ?>
					</div>
					<div class="myo-review-card__stars" aria-label="<?php echo esc_attr( number_format( $review['avg'], 1 ) . ' out of 5 stars' ); ?>">
						<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
						<span class="<?php echo $i <= round( $review['avg'] ) ? 'is-filled' : ''; ?>">&#9733;</span>
						<?php endfor; ?>
					</div>
					<div class="myo-review-card__products">
						<?php foreach ( $review['products'] as $p ) : ?>
						<a href="<?php echo esc_url( $p['url'] ); ?>" class="myo-review-card__product">
							<?php echo esc_html( $p['name'] ); ?> &mdash; <?php echo esc_html( str_repeat( '★', $p['rating'] ) ); ?>
						</a>
						<?php endforeach; ?>
					</div>
					<?php if ( ! empty( $review['message'] ) ) : ?>
					<p class="myo-review-card__message"><?php echo esc_html( $review['message'] ); ?></p>
					<?php endif; ?>
					<time class="myo-review-card__date"><?php echo esc_html( date_i18n( 'F j, Y', strtotime( $review['date'] ) ) ); ?></time>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php endif; ?>

	<section class="grunge-section grunge-reviews__products-section">
		<div class="grunge-section__texture" style="background-image:url('<?php echo $myo_asset( 'red-dots-grid-background.webp' ); ?>')" aria-hidden="true"></div>
		<div class="grunge-container">
			<div class="grunge-section__header grunge-reviews__products-header">
				<h2>Review Our <span class="grunge-text-red">Products</span></h2>
				<p class="grunge-reviews__products-lead">Choose a product below to share your experience, or browse product details before your next order.</p>
			</div>
			<div class="myo-review-product-grid">
				<?php foreach ( $products as $product ) : ?>
				<article class="myo-review-product-card">
					<div class="myo-review-product-card__image">
						<img src="<?php echo esc_url( $product['image'] ); ?>" alt="<?php echo esc_attr( $product['name'] ); ?>" width="240" height="240" loading="lazy">
					</div>
					<div class="myo-review-product-card__body">
						<h3><?php echo esc_html( $product['name'] ); ?></h3>
						<div class="myo-review-product-card__actions">
							<button type="button" class="grunge-btn grunge-btn--red myo-review-product-card__review" data-review-product="<?php echo esc_attr( $product['id'] ); ?>">Leave a Review <?php echo function_exists( 'myogenix_grunge_arrow_svg' ) ? myogenix_grunge_arrow_svg() : ''; ?></button>
							<a class="grunge-btn grunge-btn--dark myo-review-product-card__purchase" href="<?php echo esc_url( $product['url'] ); ?>">Purchase</a>
						</div>
					</div>
				</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
</main>

<script>
	window.myoReviewConfig = {
		ajaxUrl: <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
		nonce: <?php echo wp_json_encode( wp_create_nonce( 'myogenix_review_submit' ) ); ?>
	};
</script>

<?php
get_footer();
