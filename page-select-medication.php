<?php
/**
 * Select Medication preview page.
 */
defined( 'ABSPATH' ) || exit;

$myo_asset = function( $path ) {
	return function_exists( 'myogenix_grunge_asset_url' ) ? myogenix_grunge_asset_url( $path ) : '';
};

add_filter( 'body_class', function( $classes ) {
	$classes[] = 'grunge-redesign-page';
	$classes[] = 'grunge-select-medication-page';
	return $classes;
} );

$categories = [
	[
		'title' => 'Weight Management',
		'kicker' => 'GLP-1 options',
		'body' => 'Compare semaglutide and tirzepatide paths with provider-guided dosing.',
		'url' => home_url( '/weight-management/' ),
		'image' => 'tirz-sema-category-vials.webp',
	],
	[
		'title' => 'Men\'s Health',
		'kicker' => 'TRT + hormones',
		'body' => 'Provider-reviewed testosterone therapy and men\'s health support.',
		'url' => home_url( '/mens-health/' ),
		'image' => 'trt-category-image.webp',
	],
	[
		'title' => 'Peptides',
		'kicker' => 'Recovery + longevity',
		'body' => 'Peptide options for recovery, performance, cellular support, and wellness goals.',
		'url' => home_url( '/wellness/' ),
		'image' => 'peptides-category-vials.webp',
	],
	[
		'title' => 'Sexual Health',
		'kicker' => 'Discreet ED support',
		'body' => 'Tablet-based tadalafil and sildenafil options with private provider review.',
		'url' => home_url( '/sexual-health/' ),
		'image' => 'sexual-health-products.webp',
	],
	[
		'title' => 'Women\'s Health',
		'kicker' => 'Coming soon',
		'body' => 'Concierge support can help route women\'s health questions while product options are prepared.',
		'url' => home_url( '/womens-health/' ),
		'image' => 'pharma support staff tp bg.png',
	],
];

get_header();
?>

<main id="content" class="grunge-select-medication">
	<section class="grunge-category-hero grunge-select-medication__hero">
		<div class="grunge-category-hero__bg" style="background-image:url('<?php echo $myo_asset( 'hero bg.png' ); ?>')" aria-hidden="true"></div>
		<div class="grunge-category-hero__shade" aria-hidden="true"></div>
		<div class="grunge-category-hero__inner">
			<div class="grunge-category-hero__copy">
				<p class="grunge-kicker">Choose a category</p>
				<h1 class="grunge-category-hero__title">
					<span class="grunge-word grunge-word--white">Select</span>
					<span class="grunge-word grunge-word--red">Medication</span>
				</h1>
				<p class="grunge-category-hero__subtitle">Start with the treatment area that fits your goal.</p>
				<p class="grunge-category-hero__body">This preview page keeps the current site links unchanged while you review the new category selection experience.</p>
			</div>
			<div class="grunge-category-hero__media">
				<img src="<?php echo $myo_asset( 'mgrx-hero-team.webp' ); ?>" alt="" width="620" height="520">
			</div>
		</div>
	</section>

	<section class="grunge-section grunge-select-medication__grid-section">
		<div class="grunge-section__texture" style="background-image:url('<?php echo $myo_asset( 'red-dots-grid-background.webp' ); ?>')" aria-hidden="true"></div>
		<div class="grunge-container">
			<div class="grunge-section__header">
				<p class="grunge-kicker">Program categories</p>
				<h2>Pick your <span class="grunge-text-red">starting point</span></h2>
			</div>
			<div class="grunge-select-card-grid">
				<?php foreach ( $categories as $category ) : ?>
				<a class="grunge-select-card" href="<?php echo esc_url( $category['url'] ); ?>">
					<div class="grunge-select-card__image">
						<img src="<?php echo $myo_asset( $category['image'] ); ?>" alt="" width="420" height="320" loading="lazy">
					</div>
					<div class="grunge-select-card__body">
						<p><?php echo esc_html( $category['kicker'] ); ?></p>
						<h3><?php echo esc_html( $category['title'] ); ?></h3>
						<span><?php echo esc_html( $category['body'] ); ?></span>
						<em>Select Category <?php echo myogenix_grunge_arrow_svg(); ?></em>
					</div>
				</a>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
</main>

<?php
get_footer();
