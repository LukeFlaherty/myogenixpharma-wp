<?php
/**
 * Quest process explainer page.
 */
defined( 'ABSPATH' ) || exit;

$myo_asset = function( $path ) {
	$base  = get_stylesheet_directory_uri() . '/assets/images/grunge-redesign/';
	$parts = explode( '/', $path );
	return esc_url( $base . implode( '/', array_map( 'rawurlencode', $parts ) ) );
};

add_filter( 'body_class', function( $classes ) {
	$classes[] = 'grunge-redesign-page';
	$classes[] = 'grunge-quest-page';
	return $classes;
} );

$steps = [
	[
		'title' => 'Checkout and questionnaire',
		'body'  => 'After you pay for the TRT evaluation path, complete the medical questionnaire so your provider has your health history and goals.',
	],
	[
		'title' => 'Quest instructions arrive',
		'body'  => 'If you need bloodwork, you receive instructions for Quest after the questionnaire is complete. There is nothing to pay at Quest.',
	],
	[
		'title' => 'Create or sign in to Quest',
		'body'  => 'Use the Quest link to add your information, then enter your ZIP code to see nearby Quest locations.',
	],
	[
		'title' => 'Choose your appointment',
		'body'  => 'Select a location, date, and available time. Quest sends an automated confirmation text with the appointment details.',
	],
	[
		'title' => 'Provider review',
		'body'  => 'MyoGenix receives the lab information and your provider reviews it with your questionnaire before any TRT treatment is approved.',
	],
];

get_header();
?>

<main id="content" class="grunge-quest">
	<section class="grunge-category-hero">
		<div class="grunge-category-hero__bg" style="background-image:url('<?php echo $myo_asset( 'hero bg.png' ); ?>')" aria-hidden="true"></div>
		<div class="grunge-category-hero__shade" aria-hidden="true"></div>
		<div class="grunge-category-hero__inner">
			<div class="grunge-category-hero__copy">
				<p class="grunge-kicker">TRT labs</p>
				<h1 class="grunge-category-hero__title">
					<span class="grunge-word grunge-word--white">How Quest</span>
					<span class="grunge-word grunge-word--red">Works</span>
				</h1>
				<p class="grunge-category-hero__subtitle">A simple lab scheduling path after checkout.</p>
				<p class="grunge-category-hero__body">Quest bloodwork is part of the TRT evaluation path for patients who do not already have recent labs. MyoGenix sends the needed information so you can schedule locally.</p>
				<div class="grunge-hero__actions">
					<a class="grunge-btn grunge-btn--red" href="<?php echo esc_url( home_url( '/product/testosterone/' ) ); ?>">Return to TRT <?php echo myogenix_grunge_arrow_svg(); ?></a>
					<a class="grunge-btn grunge-btn--dark" href="<?php echo esc_url( home_url( '/reach-a-concierge/' ) ); ?>">Ask a question <?php echo myogenix_grunge_arrow_svg(); ?></a>
				</div>
			</div>
			<div class="grunge-category-hero__media">
				<img src="<?php echo $myo_asset( 'quest-logo-new.webp' ); ?>" alt="Quest Diagnostics" width="620" height="420">
			</div>
		</div>
	</section>

	<section class="grunge-section grunge-category-process">
		<div class="grunge-section__texture" style="background-image:url('<?php echo $myo_asset( 'grunge black section bg blank.png' ); ?>')" aria-hidden="true"></div>
		<div class="grunge-container">
			<div class="grunge-section__header">
				<p class="grunge-kicker">Step by step</p>
				<h2>From checkout <span class="grunge-text-red">to appointment</span></h2>
			</div>
			<div class="grunge-steps grunge-steps--quest">
				<?php foreach ( $steps as $idx => $step ) : ?>
				<article class="grunge-step">
					<span><?php echo esc_html( (string) ( $idx + 1 ) ); ?></span>
					<h3><?php echo esc_html( $step['title'] ); ?></h3>
					<p><?php echo esc_html( $step['body'] ); ?></p>
				</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="grunge-final-cta">
		<div class="grunge-final-cta__texture" style="background-image:url('<?php echo $myo_asset( 'thin section bg.png' ); ?>')" aria-hidden="true"></div>
		<div class="grunge-final-cta__inner">
			<img src="<?php echo $myo_asset( 'red and white logo.svg' ); ?>" alt="" width="176" height="54" loading="lazy">
			<h2><span class="grunge-final-cta__line">Ready for TRT?</span> <span class="grunge-text-red">Start here.</span></h2>
			<div class="grunge-final-cta__actions">
				<a class="grunge-btn grunge-btn--red" href="<?php echo esc_url( home_url( '/product/testosterone/' ) ); ?>">Return to TRT <?php echo myogenix_grunge_arrow_svg(); ?></a>
				<a class="grunge-btn grunge-btn--dark" href="<?php echo esc_url( home_url( '/reach-a-concierge/' ) ); ?>">Ask a question <?php echo myogenix_grunge_arrow_svg(); ?></a>
			</div>
		</div>
	</section>
</main>

<?php
get_footer();
