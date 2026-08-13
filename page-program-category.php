<?php
/**
 * Coded grunge program category landing page.
 */
defined( 'ABSPATH' ) || exit;

$myo_asset = function( $path ) {
	$base  = get_stylesheet_directory_uri() . '/assets/images/grunge-redesign/';
	$parts = explode( '/', $path );
	return esc_url( $base . implode( '/', array_map( 'rawurlencode', $parts ) ) );
};

$current_slug = get_post_field( 'post_name', get_queried_object_id() );
if ( function_exists( 'is_product_category' ) && is_product_category() ) {
	$term = get_queried_object();
	$term_slug_map = [
		'weight-loss'          => 'weight-management',
		'mens-health'          => 'mens-health',
		'sexual-health'        => 'sexual-health',
		'peptides-longevity'   => 'wellness',
		'womens-health'        => 'womens-health',
		'uncategorized'        => 'uncategorized',
	];
	if ( $term && ! empty( $term->slug ) && isset( $term_slug_map[ $term->slug ] ) ) {
		$current_slug = $term_slug_map[ $term->slug ];
	}
}

$product_ids = [
	'tirzepatide'  => 4063,
	'semaglutide'  => 4041,
	'retatrutide'  => 4537,
	'testosterone' => 883,
	'hcg'          => 4779,
	'tadalafil'    => 1886,
	'sildenafil'   => 1883,
	'bpc'          => 4249,
	'motsc'        => 4253,
	'epithalon'    => 4257,
	'nad'          => 1874,
	'sermorelin'   => 1871,
	'glutathione'  => 1868,
	'tesamorelin'  => 2803,
	'cjc'          => 2619,
	'klow'         => 2819,
	'wolverine'    => 2606,
];

$product_meta = [
	'tirzepatide'  => [ 'name' => 'Tirzepatide',  'tagline' => 'Dual-action GLP-1 therapy', 'unit' => '/mo' ],
	'semaglutide'  => [ 'name' => 'Semaglutide',  'tagline' => 'Proven GLP-1 therapy', 'unit' => '/mo' ],
	'retatrutide'  => [ 'name' => 'Retatrutide',  'tagline' => 'Triple-action weight support', 'unit' => '/mo', 'url' => home_url( '/retatrutide/' ) ],
	'testosterone' => [ 'name' => 'Testosterone Therapy', 'tagline' => 'TRT evaluation and care', 'unit' => '', 'price_label' => 'From $65 today', 'price_prefix' => 'Evaluation' ],
	'hcg'          => [ 'name' => 'HCG',          'tagline' => 'Natural testosterone support', 'unit' => '' ],
	'tadalafil'    => [ 'name' => 'Tadalafil',    'tagline' => 'Daily ED support', 'unit' => '/tablet', 'tablets_supply' => 90 ],
	'sildenafil'   => [ 'name' => 'Sildenafil',   'tagline' => 'Fast-acting ED treatment', 'unit' => '/mo' ],
	'bpc'          => [ 'name' => 'BPC-157',      'tagline' => 'Recovery and repair support', 'unit' => '/vial' ],
	'motsc'        => [ 'name' => 'MOTSc',        'tagline' => 'Mitochondrial performance', 'unit' => '/vial' ],
	'epithalon'    => [ 'name' => 'Epithalon',    'tagline' => 'Longevity peptide', 'unit' => '/vial' ],
	'nad'          => [ 'name' => 'NAD+',         'tagline' => 'Cellular energy support', 'unit' => '/vial' ],
	'sermorelin'   => [ 'name' => 'Sermorelin',   'tagline' => 'GH optimization support', 'unit' => '/vial' ],
	'glutathione'  => [ 'name' => 'Glutathione',  'tagline' => 'Antioxidant and renewal support', 'unit' => '/vial' ],
	'tesamorelin'  => [ 'name' => 'Tesamorelin / Ipamorelin', 'tagline' => 'GH optimization support', 'unit' => '/vial' ],
	'cjc'          => [ 'name' => 'CJC-1295 / Ipamorelin', 'tagline' => 'Recovery and performance support', 'unit' => '/vial' ],
	'klow'         => [ 'name' => 'KLOW',         'tagline' => 'Metabolic support', 'unit' => '/vial' ],
	'wolverine'    => [ 'name' => 'Wolverine',    'tagline' => 'Elite tissue recovery', 'unit' => '/vial' ],
];

$programs = [
	'weight-management' => [
		'eyebrow'       => 'Physician-guided care',
		'title'         => 'Weight Loss',
		'accent'        => 'Weight',
		'subtitle'      => 'Physician-guided care, built around your goals.',
		'body'          => 'Online intake, provider review, personalized treatment options, and concierge support.',
		'option_label'  => 'weight loss',
		'hero_image'    => 'tirz-sema-category-vials.webp',
		'hero_cta'      => home_url( '/product/compound-tirzepatide/' ),
		'products'      => [ 'tirzepatide', 'semaglutide' ],
	],
	'mens-health' => [
		'eyebrow'       => 'Hormone optimization',
		'title'         => "Men's Health",
		'accent'        => "Men's",
		'subtitle'      => 'Provider-managed TRT, built around your labs and goals.',
		'body'          => 'Online intake, provider review, personalized treatment options, and concierge support.',
		'option_label'  => "men's health",
		'hero_image'    => 'trt-category-image.webp',
		'hero_cta'      => home_url( '/product/testosterone/' ),
		'products'      => [ 'testosterone', 'hcg', 'tadalafil', 'sildenafil' ],
		'collage'       => [ 'testosterone', 'hcg', 'tadalafil', 'sildenafil' ],
	],
	'sexual-health' => [
		'eyebrow'       => 'Discreet provider review',
		'title'         => 'Sexual Health',
		'accent'        => 'Sexual',
		'subtitle'      => 'Provider-guided ED support, shipped discreetly to your door.',
		'body'          => 'Online intake, licensed provider review, personalized options, and private shipping.',
		'option_label'  => 'sexual health',
		'hero_image'    => 'sexual-health-products.webp',
		'hero_cta'      => home_url( '/product/compound-oral-tadalafil/' ),
		'products'      => [ 'tadalafil', 'sildenafil' ],
		'collage'       => [ 'tadalafil', 'sildenafil' ],
	],
	'wellness' => [
		'eyebrow'       => 'Compounded peptides',
		'title'         => 'Peptides',
		'accent'        => 'Peptides',
		'subtitle'      => 'Physician-guided support for recovery, performance, and health goals.',
		'body'          => 'Online intake, provider review, personalized options, and concierge support.',
		'option_label'  => 'peptide',
		'hero_image'    => 'peptides-category-vials.webp',
		'hero_cta'      => home_url( '/product/bpc/' ),
		'products'      => [ 'bpc', 'motsc', 'epithalon', 'nad', 'sermorelin', 'glutathione', 'tesamorelin', 'cjc', 'klow', 'wolverine' ],
	],
	'womens-health' => [
		'eyebrow'       => 'Personalized care',
		'title'         => "Women's Health",
		'accent'        => "Women's",
		'subtitle'      => 'Provider-guided support, built around your goals.',
		'body'          => 'Online intake, provider review, personalized options, and concierge support.',
		'option_label'  => "women's health",
		'hero_image'    => 'pharma support staff tp bg.png',
		'hero_cta'      => home_url( '/reach-a-concierge/' ),
		'products'      => [],
	],
	'uncategorized' => [
		'eyebrow'       => 'Concierge support',
		'title'         => 'Treatment Options',
		'accent'        => 'Treatment',
		'subtitle'      => 'Provider-guided care, routed by a real support team.',
		'body'          => 'Online intake, provider review, personalized options, and concierge support for the right next step.',
		'option_label'  => 'treatment',
		'hero_image'    => 'pharma support staff tp bg.png',
		'hero_cta'      => home_url( '/reach-a-concierge/' ),
		'products'      => [],
	],
];

$program = $programs[ $current_slug ] ?? $programs['weight-management'];

$care_features = [
	[ 'label' => 'Physician-guided care', 'icon' => 'doctor.svg' ],
	[ 'label' => 'Online intake', 'icon' => 'laptop-check.svg' ],
	[ 'label' => 'Personalized dosing', 'icon' => 'rx.svg' ],
	[ 'label' => 'Shipped to your door', 'icon' => 'box.svg' ],
	[ 'label' => 'Concierge support', 'icon' => 'headphones.svg' ],
];

$process_steps = [
	[ 'number' => '1', 'title' => 'Quick online intake', 'body' => 'Complete your confidential medical questionnaire in minutes.', 'icon' => 'laptop-check.svg' ],
	[ 'number' => '2', 'title' => 'Provider review', 'body' => 'A licensed provider reviews your health history and goals.', 'icon' => 'doctor.svg' ],
	[ 'number' => '3', 'title' => 'Personalized plan', 'body' => 'Your protocol is reviewed for the selected dose and supply.', 'icon' => 'rx.svg' ],
	[ 'number' => '4', 'title' => 'Shipped to your door', 'body' => 'Discreet, temperature-aware shipping direct to you.', 'icon' => 'box.svg' ],
];

$get_product = function( $key ) use ( $product_ids, $product_meta ) {
	$id      = $product_ids[ $key ] ?? 0;
	$product = ( $id && function_exists( 'wc_get_product' ) ) ? wc_get_product( $id ) : null;
	if ( ! $product ) return null;

	$raw_price = (float) $product->get_price();
	if ( $product->is_type( 'variable-subscription' ) && class_exists( 'WC_Subscriptions_Product' ) ) {
		$min_var_id = $product->get_meta( '_min_price_variation_id' );
		if ( $min_var_id ) {
			$interval = (int) WC_Subscriptions_Product::get_interval( $min_var_id );
			if ( $interval > 1 && 'month' === WC_Subscriptions_Product::get_period( $min_var_id ) ) {
				$raw_price = $raw_price / $interval;
			}
		}
	}

	$meta = $product_meta[ $key ] ?? [];
	if ( ! empty( $meta['tablets_supply'] ) ) {
		$raw_price = $raw_price / max( 1, (int) $meta['tablets_supply'] );
	}
	$decimals    = ( ( $meta['unit'] ?? '' ) === '/tablet' ) ? 2 : 0;
	$price_label = $meta['price_label'] ?? ( '$' . number_format( max( 0, $raw_price ), $decimals ) );

	return [
		'name'    => $meta['name'] ?? $product->get_name(),
		'tagline' => $meta['tagline'] ?? '',
		'unit'    => $meta['unit'] ?? '',
		'price'   => $price_label,
		'price_prefix' => $meta['price_prefix'] ?? 'Starting at',
		'url'     => $meta['url'] ?? $product->get_permalink(),
		'image'   => get_the_post_thumbnail_url( $id, 'large' ) ?: get_the_post_thumbnail_url( $id, 'full' ) ?: '',
	];
};

add_filter( 'body_class', function( $classes ) use ( $current_slug ) {
	$classes[] = 'grunge-redesign-page';
	$classes[] = 'grunge-program-page';
	$classes[] = 'grunge-program-page--' . sanitize_html_class( $current_slug );
	return $classes;
} );

get_header();
?>

<main id="content" class="grunge-category">
	<section class="grunge-category-hero">
		<div class="grunge-category-hero__bg" style="background-image:url('<?php echo $myo_asset( 'hero bg.png' ); ?>')" aria-hidden="true"></div>
		<div class="grunge-category-hero__shade" aria-hidden="true"></div>
		<div class="grunge-category-hero__inner">
			<div class="grunge-category-hero__copy">
				<p class="grunge-kicker"><?php echo esc_html( $program['eyebrow'] ); ?></p>
				<h1 class="grunge-category-hero__title">
					<?php
					$title_parts = explode( $program['accent'], $program['title'], 2 );
					if ( 2 === count( $title_parts ) ) :
						if ( $title_parts[0] ) :
					?>
						<span class="grunge-word grunge-word--white"><?php echo esc_html( trim( $title_parts[0] ) ); ?></span>
					<?php endif; ?>
						<span class="grunge-word grunge-word--red"><?php echo esc_html( $program['accent'] ); ?></span>
					<?php if ( trim( $title_parts[1] ) ) : ?>
						<span class="grunge-word grunge-word--white"><?php echo esc_html( trim( $title_parts[1] ) ); ?></span>
					<?php endif; else : ?>
						<span class="grunge-word grunge-word--white"><?php echo esc_html( $program['title'] ); ?></span>
					<?php endif; ?>
				</h1>
				<p class="grunge-category-hero__subtitle"><?php echo esc_html( $program['subtitle'] ); ?></p>
				<p class="grunge-category-hero__body"><?php echo esc_html( $program['body'] ); ?></p>
				<div class="grunge-hero__actions">
					<a class="grunge-btn grunge-btn--red" href="<?php echo esc_url( $program['hero_cta'] ); ?>">Learn about this treatment <?php echo myogenix_grunge_arrow_svg(); ?></a>
					<a class="grunge-btn grunge-btn--dark" href="<?php echo esc_url( home_url( '/reach-a-concierge/' ) ); ?>">Ask a question <?php echo myogenix_grunge_arrow_svg(); ?></a>
				</div>
			</div>
			<div class="grunge-category-hero__media">
				<img src="<?php echo $myo_asset( $program['hero_image'] ); ?>" alt="" width="620" height="520">
				<?php if ( ! empty( $program['collage'] ) ) : ?>
				<div class="grunge-category-hero__collage" aria-hidden="true">
					<?php foreach ( $program['collage'] as $collage_key ) :
						$collage_product = $get_product( $collage_key );
						if ( ! $collage_product || empty( $collage_product['image'] ) ) continue;
					?>
					<img src="<?php echo esc_url( $collage_product['image'] ); ?>" alt="" width="160" height="160" loading="lazy">
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<section class="grunge-care-strip" aria-label="Care features">
		<div class="grunge-care-strip__texture" style="background-image:url('<?php echo $myo_asset( 'thin section bg.png' ); ?>')" aria-hidden="true"></div>
		<div class="grunge-care-strip__inner">
			<?php foreach ( $care_features as $feature ) : ?>
			<div class="grunge-care">
				<img src="<?php echo $myo_asset( $feature['icon'] ); ?>" alt="" width="52" height="52" loading="lazy">
				<p><?php echo esc_html( $feature['label'] ); ?></p>
			</div>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="grunge-section grunge-category-options">
		<div class="grunge-section__texture" style="background-image:url('<?php echo $myo_asset( 'red-dots-grid-background.webp' ); ?>')" aria-hidden="true"></div>
		<div class="grunge-container">
			<div class="grunge-section__header">
				<p class="grunge-kicker">Choose your <?php echo esc_html( strtolower( $program['option_label'] ) ); ?> option</p>
				<h2>Choose your <span class="grunge-text-red"><?php echo esc_html( $program['option_label'] ); ?> option</span></h2>
			</div>
			<?php if ( ! empty( $program['products'] ) ) : ?>
			<div class="grunge-category-product-grid">
				<?php foreach ( $program['products'] as $key ) :
					$product = $get_product( $key );
					if ( ! $product ) continue;
				?>
				<a class="grunge-category-product-card" href="<?php echo esc_url( $product['url'] ); ?>">
					<?php if ( $product['image'] ) : ?>
					<img src="<?php echo esc_url( $product['image'] ); ?>" alt="<?php echo esc_attr( $product['name'] ); ?>" width="320" height="260" loading="lazy">
					<?php endif; ?>
					<div class="grunge-category-product-card__body">
						<p><?php echo esc_html( $product['tagline'] ); ?></p>
						<h3><?php echo esc_html( $product['name'] ); ?></h3>
						<span><?php echo esc_html( $product['price_prefix'] ); ?></span>
						<strong><?php echo esc_html( $product['price'] ); ?><small><?php echo esc_html( $product['unit'] ); ?></small></strong>
						<em>Learn More <?php echo myogenix_grunge_arrow_svg(); ?></em>
					</div>
				</a>
				<?php endforeach; ?>
			</div>
			<?php else : ?>
			<div class="grunge-category-empty">
				<p>Product options are coming soon. Our concierge team can help route you to the right next step.</p>
				<a class="grunge-btn grunge-btn--red" href="<?php echo esc_url( home_url( '/reach-a-concierge/' ) ); ?>">Ask a question <?php echo myogenix_grunge_arrow_svg(); ?></a>
			</div>
			<?php endif; ?>
		</div>
	</section>

	<section class="grunge-section grunge-category-process">
		<div class="grunge-section__texture" style="background-image:url('<?php echo $myo_asset( 'grunge black section bg blank.png' ); ?>')" aria-hidden="true"></div>
		<div class="grunge-container">
			<div class="grunge-section__header">
				<p class="grunge-kicker">How it works</p>
				<h2>Getting started <span class="grunge-text-red">is simple</span></h2>
			</div>
			<div class="grunge-steps grunge-steps--icons">
				<?php foreach ( $process_steps as $step ) : ?>
				<article class="grunge-step">
					<span><?php echo esc_html( $step['number'] ); ?></span>
					<img src="<?php echo $myo_asset( $step['icon'] ); ?>" alt="" width="84" height="84" loading="lazy">
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
			<img src="<?php echo $myo_asset( 'red and white logo.svg' ); ?>" alt="MyoGenix Pharma" width="176" height="54" loading="lazy">
			<h2>Ready to start? <span class="grunge-text-red">We are here to help.</span></h2>
			<div class="grunge-final-cta__actions">
				<a class="grunge-btn grunge-btn--red" href="<?php echo esc_url( $program['hero_cta'] ); ?>">Learn about this treatment <?php echo myogenix_grunge_arrow_svg(); ?></a>
				<a class="grunge-btn grunge-btn--dark" href="<?php echo esc_url( home_url( '/reach-a-concierge/' ) ); ?>">Ask a question <?php echo myogenix_grunge_arrow_svg(); ?></a>
			</div>
		</div>
	</section>
</main>

<?php
get_footer();
