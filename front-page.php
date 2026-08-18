<?php
/**
 * Front page grunge redesign.
 */
defined( 'ABSPATH' ) || exit;

$myo_asset = function( $path ) {
	return function_exists( 'myogenix_grunge_asset_url' ) ? myogenix_grunge_asset_url( $path ) : '';
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
	'testosterone' => [ 'name' => 'Testosterone', 'line' => 'Hormone optimization', 'unit' => '' ],
	'hcg'          => [ 'name' => 'HCG', 'line' => 'Natural testosterone support', 'unit' => '' ],
	'tirzepatide'  => [ 'name' => 'Tirzepatide', 'line' => 'Dual-action GLP-1 protocol', 'unit' => '/mo' ],
	'semaglutide'  => [ 'name' => 'Semaglutide', 'line' => 'Provider-guided GLP-1 care', 'unit' => '/mo' ],
	'bpc'          => [ 'name' => 'BPC-157', 'line' => 'Recovery and repair support', 'unit' => '/vial' ],
	'motsc'        => [ 'name' => 'MOTSc', 'line' => 'Mitochondrial performance', 'unit' => '/vial' ],
	'epithalon'    => [ 'name' => 'Epithalon', 'line' => 'Longevity peptide', 'unit' => '/vial' ],
	'nad'          => [ 'name' => 'NAD+', 'line' => 'Cellular energy support', 'unit' => '/vial' ],
	'sermorelin'   => [ 'name' => 'Sermorelin', 'line' => 'GH optimization support', 'unit' => '/vial' ],
	'glutathione'  => [ 'name' => 'Glutathione', 'line' => 'Antioxidant and renewal support', 'unit' => '/vial' ],
	'tadalafil'    => [ 'name' => 'Tadalafil', 'line' => 'Daily ED support', 'unit' => '/tablet' ],
	'sildenafil'   => [ 'name' => 'Sildenafil', 'line' => 'Fast-acting ED treatment', 'unit' => '/tablet' ],
];

$products = [];
foreach ( $product_ids as $key => $id ) {
	$product = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
	if ( ! $product ) {
		continue;
	}

	$decimals = ( isset( $product_meta[ $key ]['unit'] ) && '/tablet' === $product_meta[ $key ]['unit'] ) ? 2 : 0;
	$raw_price = function_exists( 'myogenix_get_lowest_product_price' )
		? myogenix_get_lowest_product_price( $product, $product_meta[ $key ]['unit'] )
		: (float) $product->get_price();
	$image_url = function_exists( 'myogenix_grunge_bottle_url' ) ? myogenix_grunge_bottle_url( $product ) : '';
	if ( null === $raw_price || ! $image_url ) continue;

	$products[ $key ] = [
		'name'  => $product_meta[ $key ]['name'],
		'line'  => $product_meta[ $key ]['line'],
		'unit'  => $product_meta[ $key ]['unit'],
		'price' => '$' . number_format( max( 0, $raw_price ), $decimals ),
		'url'   => $product->get_permalink(),
		'image' => $image_url,
	];
}

$care_features = [
	[ 'label' => 'Physician-guided care', 'icon' => 'doctor.svg' ],
	[ 'label' => 'Lab testing with Quest', 'icon' => 'vial.svg' ],
	[ 'label' => 'Personalized treatment plans', 'icon' => 'rx.svg' ],
	[ 'label' => 'Medication shipped to your door', 'icon' => 'box.svg' ],
	[ 'label' => 'Dedicated concierge support', 'icon' => 'headphones.svg' ],
];

$steps = [
	[ 'number' => '1', 'title' => 'Quick online intake', 'body' => 'Complete your medical questionnaire in minutes.' ],
	[ 'number' => '2', 'title' => 'Quest lab testing', 'body' => 'Get diagnostic labs at a trusted local Quest.' ],
	[ 'number' => '3', 'title' => 'Physician review', 'body' => 'A licensed provider reviews your results and health history.' ],
	[ 'number' => '4', 'title' => 'Personalized plan', 'body' => 'Your dose is built for your goals, symptoms, and markers.' ],
	[ 'number' => '5', 'title' => 'Shipped to your door', 'body' => 'Medication arrives discreetly with ongoing concierge care.' ],
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

$featured_keys = [ 'testosterone', 'tirzepatide', 'semaglutide', 'tadalafil', 'sildenafil', 'bpc', 'nad' ];

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
	[ 'title' => 'Quest diagnostics', 'body' => 'Industry-leading lab partner.', 'icon' => 'quest-logo-new.webp' ],
	[ 'title' => 'Concierge follow-up', 'body' => 'Real support. Always here.', 'icon' => 'headphones.svg' ],
];

$faqs = [
	[
		'q' => 'How do I start?',
		'a' => 'Start with the online evaluation. We collect your goals, medical history, and the details a provider needs to determine next steps.',
	],
	[
		'q' => 'Do I need labs?',
		'a' => 'For TRT, labs are part of the care path. We use diagnostics to help guide eligibility, treatment planning, and follow-up.',
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
				<p class="grunge-hero__lead">Concierge telehealth for TRT <span>Performance care, guided by humans.</span></p>
				<div class="grunge-hero__actions">
					<a class="grunge-btn grunge-btn--red" href="<?php echo esc_url( home_url( '/product-category/mens-health/' ) ); ?>">Select Medication <?php echo myogenix_grunge_arrow_svg(); ?></a>
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

	<section class="grunge-section grunge-products">
		<div class="grunge-section__texture" style="background-image:url('<?php echo $myo_asset( 'red-dots-grid-background.webp' ); ?>')" aria-hidden="true"></div>
		<div class="grunge-container">
			<div class="grunge-section__header grunge-section__header--split">
				<div>
					<p class="grunge-kicker">Treatment options</p>
					<h2>Select the <span class="grunge-text-red">medication path</span></h2>
				</div>
				<a class="grunge-btn grunge-btn--dark" href="<?php echo esc_url( home_url( '/medications/' ) ); ?>">View all <?php echo myogenix_grunge_arrow_svg(); ?></a>
			</div>
			<div class="grunge-product-grid">
				<?php foreach ( $featured_keys as $key ) :
					if ( empty( $products[ $key ] ) ) continue;
					$item = $products[ $key ];
				?>
				<a class="grunge-product-card" href="<?php echo esc_url( $item['url'] ); ?>">
					<img src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['name'] ); ?>" width="180" height="180" loading="lazy">
					<div>
						<p><?php echo esc_html( $item['line'] ); ?></p>
						<h3><?php echo esc_html( $item['name'] ); ?></h3>
						<strong><?php echo esc_html( $item['price'] ); ?><span><?php echo esc_html( $item['unit'] ); ?></span></strong>
					</div>
				</a>
				<?php endforeach; ?>
			</div>
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
				<p class="grunge-kicker">TRT care path</p>
				<h2>Concierge telehealth <span class="grunge-text-red">for TRT</span></h2>
				<p><span class="grunge-text-red">Physician-guided</span> treatment. Human support.</p>
				<ul class="grunge-check-list">
					<li>Online enrollment</li>
					<li>Licensed providers</li>
					<li>Personalized dosing</li>
					<li>Doorstep delivery</li>
					<li>Human concierge support</li>
				</ul>
				<a class="grunge-btn grunge-btn--red grunge-inline-cta" href="<?php echo esc_url( home_url( '/product/testosterone/' ) ); ?>">Start TRT <?php echo myogenix_grunge_arrow_svg(); ?></a>
			</div>
		</div>
	</section>

	<section class="grunge-section grunge-weight-compare">
		<div class="grunge-section__texture" style="background-image:url('<?php echo $myo_asset( 'section bg 5.png' ); ?>')" aria-hidden="true"></div>
		<div class="grunge-container">
			<div class="grunge-section__header grunge-section__header--split">
				<div>
					<p class="grunge-kicker">Weight management</p>
					<h2>Compare your <span class="grunge-text-red">GLP-1 options</span></h2>
				</div>
				<a class="grunge-btn grunge-btn--dark" href="<?php echo esc_url( home_url( '/weight-management/' ) ); ?>">View weight loss <?php echo myogenix_grunge_arrow_svg(); ?></a>
			</div>
			<div class="grunge-compare-grid">
				<?php
				$compare_cards = [
					[ 'key' => 'semaglutide', 'best' => 'Established GLP-1 support', 'points' => [ 'Once-weekly injection', 'Lower your appetite', 'Lower starting price' ] ],
					[ 'key' => 'tirzepatide', 'best' => 'Dual-action metabolic care', 'points' => [ 'GIP + GLP-1 activity', 'Once-weekly injection', 'Stronger effects' ] ],
				];
				foreach ( $compare_cards as $card ) :
					if ( empty( $products[ $card['key'] ] ) ) continue;
					$item = $products[ $card['key'] ];
				?>
				<a class="grunge-compare-card" href="<?php echo esc_url( $item['url'] ); ?>">
					<img src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['name'] ); ?>" width="260" height="260" loading="lazy">
					<div>
						<p><?php echo esc_html( $card['best'] ); ?></p>
						<h3><?php echo esc_html( $item['name'] ); ?></h3>
						<strong><?php echo esc_html( $item['price'] ); ?><span><?php echo esc_html( $item['unit'] ); ?></span></strong>
						<ul>
							<?php foreach ( $card['points'] as $point ) : ?>
							<li><?php echo esc_html( $point ); ?></li>
							<?php endforeach; ?>
						</ul>
						<em>Select <?php echo esc_html( $item['name'] ); ?> <?php echo myogenix_grunge_arrow_svg(); ?></em>
					</div>
				</a>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="grunge-symptoms">
		<div class="grunge-symptoms__image" style="background-image:url('<?php echo $myo_asset( 'section bg 4.png' ); ?>')" aria-hidden="true"></div>
		<div class="grunge-symptoms__shade" aria-hidden="true"></div>
		<div class="grunge-container grunge-symptoms__content">
			<div class="grunge-symptoms__intro">
				<img class="grunge-symptoms__person" src="<?php echo $myo_asset( 'peptides-category-vials.webp' ); ?>" alt="" width="360" height="420" loading="lazy">
				<h2>Peptides for <span class="grunge-text-red">recovery and longevity</span></h2>
			</div>
			<ul class="grunge-symptom-grid grunge-check-list">
				<?php foreach ( [ 'BPC-157', 'NAD+', 'Sermorelin', 'Glutathione', 'CJC-1295', 'MOTSc', 'Epithalon', 'Wolverine' ] as $symptom ) : ?>
				<li><?php echo esc_html( $symptom ); ?></li>
				<?php endforeach; ?>
			</ul>
			<a class="grunge-btn grunge-btn--red grunge-inline-cta" href="<?php echo esc_url( home_url( '/wellness/' ) ); ?>">Select Medication <?php echo myogenix_grunge_arrow_svg(); ?></a>
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
				<a class="grunge-btn grunge-btn--red" href="<?php echo esc_url( home_url( '/product-category/mens-health/' ) ); ?>">Select Medication <?php echo myogenix_grunge_arrow_svg(); ?></a>
				<a class="grunge-btn grunge-btn--dark" href="<?php echo esc_url( home_url( '/reach-a-concierge/' ) ); ?>">Ask a question <?php echo myogenix_grunge_arrow_svg(); ?></a>
			</div>
		</div>
	</section>
</main>

<?php
get_footer();
