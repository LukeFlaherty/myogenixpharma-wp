<?php
/**
 * Front page grunge redesign.
 */
defined( 'ABSPATH' ) || exit;

$myo_asset = function( $path ) {
	$base  = get_stylesheet_directory_uri() . '/assets/images/grunge-redesign/';
	$parts = explode( '/', $path );
	return esc_url( $base . implode( '/', array_map( 'rawurlencode', $parts ) ) );
};

$product_ids = [
	'testosterone'  => 883,
	'hcg'           => 4779,
	'tirzepatide'   => 4063,
	'semaglutide'   => 4041,
	'bpc'           => 4249,
	'motsc'         => 4253,
	'epithalon'     => 4257,
	'nad'           => 1874,
	'sermorelin'    => 1871,
	'glutathione'   => 1868,
	'tadalafil'     => 1886,
	'sildenafil'    => 1883,
];

$product_meta = [
	'testosterone' => [ 'name' => 'Testosterone', 'line' => 'Hormone optimization', 'unit' => '/mo' ],
	'hcg'          => [ 'name' => 'HCG', 'line' => 'Natural testosterone support', 'unit' => '' ],
	'tirzepatide'  => [ 'name' => 'Tirzepatide', 'line' => 'Dual-action GLP-1 protocol', 'unit' => '/mo' ],
	'semaglutide'  => [ 'name' => 'Semaglutide', 'line' => 'Provider-guided GLP-1 care', 'unit' => '/mo' ],
	'bpc'          => [ 'name' => 'BPC-157', 'line' => 'Recovery and repair support', 'unit' => '/vial' ],
	'motsc'        => [ 'name' => 'MOTSc', 'line' => 'Mitochondrial performance', 'unit' => '/vial' ],
	'epithalon'    => [ 'name' => 'Epithalon', 'line' => 'Longevity peptide', 'unit' => '/vial' ],
	'nad'          => [ 'name' => 'NAD+', 'line' => 'Cellular energy support', 'unit' => '/vial' ],
	'sermorelin'   => [ 'name' => 'Sermorelin', 'line' => 'GH optimization support', 'unit' => '/vial' ],
	'glutathione'  => [ 'name' => 'Glutathione', 'line' => 'Antioxidant and renewal support', 'unit' => '/vial' ],
	'tadalafil'    => [ 'name' => 'Tadalafil', 'line' => 'Daily ED support', 'unit' => '/tablet', 'tablets_supply' => 90 ],
	'sildenafil'   => [ 'name' => 'Sildenafil', 'line' => 'Fast-acting ED treatment', 'unit' => '/mo' ],
];

$products = [];
foreach ( $product_ids as $key => $id ) {
	$product = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
	if ( ! $product ) {
		continue;
	}

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

	if ( ! empty( $product_meta[ $key ]['tablets_supply'] ) ) {
		$raw_price = $raw_price / max( 1, (int) $product_meta[ $key ]['tablets_supply'] );
	}
	$decimals = ( isset( $product_meta[ $key ]['unit'] ) && '/tablet' === $product_meta[ $key ]['unit'] ) ? 2 : 0;
	$products[ $key ] = [
		'name'  => $product_meta[ $key ]['name'],
		'line'  => $product_meta[ $key ]['line'],
		'unit'  => $product_meta[ $key ]['unit'],
		'price' => '$' . number_format( max( 0, $raw_price ), $decimals ),
		'url'   => $product->get_permalink(),
		'image' => get_the_post_thumbnail_url( $id, 'large' ) ?: get_the_post_thumbnail_url( $id, 'full' ) ?: '',
	];
}

$care_features = [
	[ 'label' => 'Physician-guided care', 'icon' => 'doctor.svg' ],
	[ 'label' => 'Online evaluation', 'icon' => 'laptop-check.svg' ],
	[ 'label' => 'Personalized treatment plans', 'icon' => 'rx.svg' ],
	[ 'label' => 'Medication shipped to your door', 'icon' => 'box.svg' ],
	[ 'label' => 'Dedicated concierge support', 'icon' => 'headphones.svg' ],
];

$steps = [
	[ 'number' => '1', 'title' => 'Choose a treatment line', 'body' => 'Explore the program or medication that fits your goals.' ],
	[ 'number' => '2', 'title' => 'Complete your online intake', 'body' => 'Share your health history so a provider can review next steps.' ],
	[ 'number' => '3', 'title' => 'Provider review', 'body' => 'A licensed provider reviews your order before treatment moves forward.' ],
	[ 'number' => '4', 'title' => 'Discreet fulfillment', 'body' => 'If approved, your medication is prepared and shipped to your door.' ],
];

$programs = [
	[
		'title' => 'Weight Management',
		'text'  => 'Provider-reviewed GLP-1 programs configured for your protocol.',
		'url'   => home_url( '/weight-management/' ),
		'image' => 'weight-loss-category-vials.webp',
	],
	[
		'title' => 'Mens Health',
		'text'  => 'TRT, HCG, and performance support guided by labs and symptoms.',
		'url'   => home_url( '/mens-health/' ),
		'image' => 'trt-category-image.webp',
	],
	[
		'title' => 'Peptides',
		'text'  => 'Recovery, longevity, and cellular performance protocols.',
		'url'   => home_url( '/wellness/' ),
		'image' => 'peptides-category-vials.webp',
	],
	[
		'title' => 'Sexual Health',
		'text'  => 'Discreet ED support with provider-reviewed treatment options.',
		'url'   => home_url( '/sexual-health/' ),
		'image' => 'sexual-health-products.webp',
	],
];

$featured_keys = [ 'tirzepatide', 'semaglutide', 'tadalafil', 'sildenafil', 'bpc', 'nad' ];

$symptoms = [
	'Low energy',
	'Brain fog',
	'Loss of strength',
	'Increased body fat',
	'Low libido',
	'Poor recovery',
	'Mood changes',
	'Poor sleep',
];

$trust_items = [
	[ 'title' => 'Built for athletes', 'body' => 'Performance roots. Clinical standards.', 'icon' => 'muscle-icon.webp' ],
	[ 'title' => 'Physician-guided care', 'body' => 'Licensed medical oversight.', 'icon' => 'hospital-staff.webp' ],
	[ 'title' => 'Discreet delivery', 'body' => 'Care shipped directly to your door.', 'icon' => 'box.svg' ],
	[ 'title' => 'Concierge follow-up', 'body' => 'Real support. Always here.', 'icon' => 'headphones.svg' ],
];

$faqs = [
	[
		'q' => 'How do I start?',
		'a' => 'Start by choosing the treatment line or medication you want to learn more about. Each product page explains the next step before checkout.',
	],
	[
		'q' => 'Do I need labs?',
		'a' => 'Some programs require labs before treatment can be approved. The product page will explain what is required for that medication.',
	],
	[
		'q' => 'Who reviews my results?',
		'a' => 'A licensed provider reviews your intake and lab results before a treatment plan is approved.',
	],
	[
		'q' => 'How long does it take?',
		'a' => 'Timing depends on lab completion and provider review, but many patients can complete the evaluation process in one to two weeks.',
	],
	[
		'q' => 'Can I ask questions first?',
		'a' => 'Yes. You can reach out before starting, and our team can help you understand the care journey before you submit an evaluation.',
	],
];

add_filter( 'body_class', function( $classes ) {
	$classes[] = 'myogenix-home-page';
	$classes[] = 'grunge-redesign-page';
	return $classes;
} );

get_header();
?>

<main id="content" class="grunge-home">
	<section class="grunge-hero">
		<div class="grunge-hero__bg" style="background-image:url('<?php echo $myo_asset( 'hero bg.png' ); ?>')" aria-hidden="true"></div>
		<div class="grunge-hero__shade" aria-hidden="true"></div>
		<div class="grunge-hero__inner">
			<div class="grunge-hero__copy">
				<p class="grunge-kicker">25+ years of performance</p>
				<h1 class="grunge-hero__title">
					<span class="grunge-word grunge-word--red">MyoGenix</span>
					<span class="grunge-word grunge-word--white">Pharma</span>
				</h1>
				<p class="grunge-hero__lead">Concierge telehealth for performance and wellness <span>Care guided by real humans.</span></p>
				<div class="grunge-hero__actions">
					<a class="grunge-btn grunge-btn--red" href="<?php echo esc_url( home_url( '/weight-management/' ) ); ?>">Explore treatments <?php echo myogenix_grunge_arrow_svg(); ?></a>
					<a class="grunge-btn grunge-btn--dark" href="<?php echo esc_url( home_url( '/reach-a-concierge/' ) ); ?>">Ask a question <?php echo myogenix_grunge_arrow_svg(); ?></a>
				</div>
			</div>
			<div class="grunge-hero__media">
				<img src="<?php echo $myo_asset( 'mgrx-hero-team.webp' ); ?>" alt="MyoGenix care team" width="740" height="760">
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

	<section class="grunge-section grunge-how" id="how-it-works">
		<div class="grunge-section__texture" style="background-image:url('<?php echo $myo_asset( 'grunge black section bg blank.png' ); ?>')" aria-hidden="true"></div>
		<div class="grunge-container">
			<div class="grunge-section__header">
				<p class="grunge-kicker">How it works</p>
				<h2>Getting evaluated <span class="grunge-text-red">is easier than ever</span></h2>
				<p>Many patients complete the process in as little as <strong>1-2 weeks</strong></p>
			</div>
			<div class="grunge-steps">
				<?php foreach ( $steps as $step ) : ?>
				<article class="grunge-step">
					<span><?php echo esc_html( $step['number'] ); ?></span>
					<h3><?php echo esc_html( $step['title'] ); ?></h3>
					<p><?php echo esc_html( $step['body'] ); ?></p>
				</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="grunge-section grunge-performance">
		<div class="grunge-programs__bg" style="background-image:url('<?php echo $myo_asset( 'bg-genetic-wire.webp' ); ?>')" aria-hidden="true"></div>
		<div class="grunge-container grunge-performance__grid">
			<div class="grunge-performance__media">
				<img src="<?php echo $myo_asset( 'mgrx-phone-care-journey.webp' ); ?>" alt="MyoGenix care journey" width="620" height="620" loading="lazy">
			</div>
			<div class="grunge-performance__copy">
				<p class="grunge-kicker">Treatment lines</p>
				<h2>Medication options <span class="grunge-text-red">built around goals</span></h2>
				<p><span class="grunge-text-red">Provider-reviewed</span> care across weight management, men's health, peptides, and sexual health.</p>
				<ul class="grunge-check-list">
					<li>GLP-1 weight-management options</li>
					<li>TRT, HCG, and hormone support</li>
					<li>Recovery and longevity peptides</li>
					<li>Discreet sexual health medications</li>
					<li>Human concierge support before you choose</li>
				</ul>
				<a class="grunge-btn grunge-btn--red grunge-inline-cta" href="<?php echo esc_url( home_url( '/weight-management/' ) ); ?>">Explore programs <?php echo myogenix_grunge_arrow_svg(); ?></a>
			</div>
		</div>
	</section>

	<section class="grunge-symptoms">
		<div class="grunge-symptoms__image" style="background-image:url('<?php echo $myo_asset( 'section bg 2.png' ); ?>')" aria-hidden="true"></div>
		<div class="grunge-symptoms__shade" aria-hidden="true"></div>
		<div class="grunge-container grunge-symptoms__content">
			<div class="grunge-symptoms__intro">
				<img class="grunge-symptoms__person" src="<?php echo $myo_asset( 'guy-sad.webp' ); ?>" alt="" width="360" height="420" loading="lazy">
				<h2>Support for <span class="grunge-text-red">real goals</span></h2>
			</div>
			<ul class="grunge-symptom-grid grunge-check-list">
				<li>Weight management</li>
				<li>Metabolic support</li>
				<li>Hormone optimization</li>
				<li>Sexual performance</li>
				<li>Recovery support</li>
				<li>Longevity-focused care</li>
				<li>Energy and consistency</li>
				<li>Private, provider-guided treatment</li>
			</ul>
			<a class="grunge-btn grunge-btn--red grunge-inline-cta" href="<?php echo esc_url( home_url( '/reach-a-concierge/' ) ); ?>">Get product guidance <?php echo myogenix_grunge_arrow_svg(); ?></a>
		</div>
	</section>

	<section class="grunge-section grunge-approach">
		<div class="grunge-section__texture" style="background-image:url('<?php echo $myo_asset( 'red-dots-grid-background.webp' ); ?>')" aria-hidden="true"></div>
		<div class="grunge-container grunge-approach__grid">
			<div class="grunge-section__header">
					<p class="grunge-kicker">Our approach</p>
					<h2>Not a hard sell. <span class="grunge-text-red">A human hand holder.</span></h2>
			</div>
			<div class="grunge-approach__person">
				<img src="<?php echo $myo_asset( 'guy-helping-2.webp' ); ?>" alt="" width="420" height="480" loading="lazy">
			</div>
			<div class="grunge-support-card">
				<div class="grunge-support-card__brand">MyoGenix Pharma</div>
				<div class="grunge-support-card__rows">
					<div>
						<img src="<?php echo $myo_asset( 'headphones.svg' ); ?>" alt="" width="25" height="25" loading="lazy">
						<p><span>Fast answers</span><small>Guided support</small></p>
					</div>
					<div>
						<img src="<?php echo $myo_asset( 'doctor.svg' ); ?>" alt="" width="25" height="25" loading="lazy">
						<p><span>Ask questions</span><small>We're here to help</small></p>
					</div>
					<div>
						<img src="<?php echo $myo_asset( 'hospital-staff.webp' ); ?>" alt="" width="25" height="25" loading="lazy">
						<p><span>Your care team</span><small>Real people</small></p>
					</div>
				</div>
				<a class="grunge-btn grunge-btn--dark" href="<?php echo esc_url( home_url( '/reach-a-concierge/' ) ); ?>">Ask a question <?php echo myogenix_grunge_arrow_svg(); ?></a>
			</div>
		</div>
	</section>

	<section class="grunge-section grunge-trust">
		<div class="grunge-container">
			<div class="grunge-section__header">
				<p class="grunge-kicker">Why athletes trust us</p>
				<h2>25+ years of <span class="grunge-text-red">performance</span></h2>
			</div>
			<div class="grunge-trust__grid">
				<?php foreach ( $trust_items as $item ) : ?>
				<article class="grunge-trust-card">
					<img src="<?php echo $myo_asset( $item['icon'] ); ?>" alt="" width="82" height="82" loading="lazy">
					<h3><?php echo esc_html( $item['title'] ); ?></h3>
					<p><?php echo esc_html( $item['body'] ); ?></p>
				</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="myo-faq grunge-faq" id="faq" aria-label="Frequently asked questions">
		<div class="grunge-faq__bg" style="background-image:url('<?php echo $myo_asset( 'grunge black section bg blank.png' ); ?>')" aria-hidden="true"></div>
		<div class="myo-faq__wrap grunge-faq__wrap">
			<div class="myo-faq__header grunge-section__header">
				<span class="myo-faq__eyebrow grunge-kicker">FAQ</span>
				<h2 class="myo-faq__title">Your questions. <span class="grunge-text-red">Answered.</span></h2>
				<p class="myo-faq__desc"><a class="grunge-btn grunge-btn--dark" href="<?php echo esc_url( home_url( '/reach-a-concierge/' ) ); ?>">Ask a question <?php echo myogenix_grunge_arrow_svg(); ?></a></p>
			</div>
			<div class="myo-faq__list">
				<?php foreach ( $faqs as $i => $faq ) :
					$panel_id = 'home-faq-' . $i;
					$is_open  = 0 === $i;
				?>
				<div class="myo-faq__item">
					<button class="myo-faq__btn" type="button" aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $panel_id ); ?>">
						<span class="myo-faq__q"><?php echo esc_html( $faq['q'] ); ?></span>
						<span class="myo-faq__icon" aria-hidden="true">+</span>
					</button>
					<div class="myo-faq__panel<?php echo $is_open ? ' is-open' : ''; ?>" id="<?php echo esc_attr( $panel_id ); ?>">
						<div class="myo-faq__panel-inner">
							<p><?php echo esc_html( $faq['a'] ); ?></p>
						</div>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="grunge-final-cta">
		<div class="grunge-final-cta__texture" style="background-image:url('<?php echo $myo_asset( 'thin section bg.png' ); ?>')" aria-hidden="true"></div>
		<div class="grunge-final-cta__inner">
			<img src="<?php echo $myo_asset( 'red and white logo.svg' ); ?>" alt="" width="176" height="54" loading="lazy">
			<h2><span class="grunge-final-cta__line">Concierge care</span> <span class="grunge-text-red">is live</span></h2>
			<div class="grunge-final-cta__actions">
				<a class="grunge-btn grunge-btn--red" href="<?php echo esc_url( home_url( '/weight-management/' ) ); ?>">Explore treatments <?php echo myogenix_grunge_arrow_svg(); ?></a>
				<a class="grunge-btn grunge-btn--dark" href="<?php echo esc_url( home_url( '/reach-a-concierge/' ) ); ?>">Ask a question <?php echo myogenix_grunge_arrow_svg(); ?></a>
			</div>
		</div>
	</section>
</main>

<?php
get_footer();
