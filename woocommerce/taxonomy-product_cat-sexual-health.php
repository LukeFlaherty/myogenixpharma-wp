<?php
/**
 * Template for the sexual-health product category archive.
 * Custom layout — mirrors PDP/weight-loss category design.
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

/**
 * Hook: woocommerce_before_main_content.
 * @hooked woocommerce_output_content_wrapper - 10 (outputs opening divs)
 * @hooked woocommerce_breadcrumb - 20
 */
remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
do_action( 'woocommerce_before_main_content' );

$sh_config = [
	'compound-oral-tadalafil' => [
		'badge'   => 'PDE5 Inhibitor — Long Duration',
		'title'   => 'Tadalafil',
		'desc'    => 'Daily or as-needed oral tablet for erectile dysfunction. Lasts up to 36 hours — the most flexible option for spontaneous activity.',
		'bullets' => [
			'Available as daily or as-needed dosing',
			'Up to 36-hour duration',
			'Once-daily low dose eliminates timing pressure',
			'Prescription reviewed by licensed provider',
		],
		'url' => '/product/compound-oral-tadalafil/',
	],
	'compound-sildenafil' => [
		'badge'   => 'PDE5 Inhibitor — Fast Acting',
		'title'   => 'Sildenafil',
		'desc'    => 'Fast-acting oral tablet for erectile dysfunction. Take 30–60 minutes before activity. Well-established safety and efficacy profile.',
		'bullets' => [
			'Onset in 30–60 minutes',
			'Well-established clinical safety profile',
			'Effective for the majority of patients',
			'Prescription reviewed by licensed provider',
		],
		'url' => '/product/compound-sildenafil/',
	],
];

$sh_products   = [];
$sh_min_prices = [];

foreach ( array_keys( $sh_config ) as $slug ) {
	$posts = get_posts( [
		'name'           => $slug,
		'post_type'      => 'product',
		'posts_per_page' => 1,
		'post_status'    => 'publish',
	] );
	if ( ! $posts ) continue;
	$product                = wc_get_product( $posts[0]->ID );
	$sh_products[ $slug ]   = $product;
	$min                    = null;
	foreach ( $product->get_children() as $vid ) {
		if ( 'publish' !== get_post_status( $vid ) ) continue;
		$price = (float) get_post_meta( $vid, '_price', true );
		if ( $price > 0 && ( null === $min || $price < $min ) ) {
			$min = $price;
		}
	}
	if ( null === $min ) {
		$min = (float) $product->get_price();
	}
	$sh_min_prices[ $slug ] = $min > 0 ? $min : null;
}

$img_url = function( $path ) {
	$base  = get_stylesheet_directory_uri() . '/assets/images/';
	$parts = explode( '/', $path );
	return esc_url( $base . implode( '/', array_map( 'rawurlencode', $parts ) ) );
};

$steps = [
	[
		'num'   => 'PDP Sections/1.png',
		'img'   => 'PDP Sections/form.png',
		'title' => 'Questionnaire',
		'desc'  => 'Answer a few questions and share your medical details',
	],
	[
		'num'   => 'PDP Sections/2.png',
		'img'   => 'PDP Sections/consultation.png',
		'title' => 'Review and Approved by provider',
		'desc'  => 'Discuss your goals and receive expert recommendations',
	],
	[
		'num'   => 'PDP Sections/3.png',
		'img'   => 'PDP Sections/box.png',
		'title' => 'Receive medication',
		'desc'  => 'Medication and supplies shipped straight to your door',
	],
	[
		'num'   => 'PDP Sections/4.png',
		'img'   => 'PDP Sections/calendar.png',
		'title' => 'Monthly Monitoring',
		'desc'  => 'Stay on track with regular free check-ins to ensure progress',
	],
];

// "How it works" — same design/content as the home page's home-how/hp-step section
$hp_steps = [
	[ 'num' => '01', 'img' => $img_url( 'PDP Sections/form.png' ),         'title' => 'Questionnaire',                    'desc' => 'Answer a few questions and share your medical details.'                          ],
	[ 'num' => '02', 'img' => $img_url( 'PDP Sections/consultation.png' ), 'title' => 'Reviewed & approved by provider',  'desc' => 'Discuss your goals and receive expert recommendations.'                          ],
	[ 'num' => '03', 'img' => $img_url( 'PDP Sections/box.png' ),          'title' => 'Receive medication',               'desc' => 'Medication and supplies shipped straight to your door.'                          ],
	[ 'num' => '04', 'img' => $img_url( 'PDP Sections/calendar.png' ),     'title' => 'Monthly monitoring',               'desc' => 'Stay on track with regular free check-ins to ensure progress.'                   ],
];

// FAQ — same accordion design as the PDPs (.myo-faq), category-specific content
$faqs = [
	[
		'q' => 'What is the difference between tadalafil and sildenafil?',
		'a' => 'Both are PDE5 inhibitors that work by the same mechanism — increasing blood flow to support erections. The key difference is duration: sildenafil works within 30–60 minutes and lasts 4–6 hours, making it ideal for planned activity. Tadalafil lasts up to 36 hours and is also available as a once-daily low dose, which eliminates the need to time your medication at all.',
	],
	[
		'q' => 'How quickly does each medication work?',
		'a' => 'Sildenafil typically takes effect within 30–60 minutes and should be taken on an empty stomach for fastest absorption. Tadalafil as-needed takes effect in 30–45 minutes. Daily low-dose tadalafil builds steady-state levels that provide continuous readiness without timing. A heavy meal can slow absorption of either medication.',
	],
	[
		'q' => 'Do I need a prescription?',
		'a' => 'Yes — all ED medications require a valid prescription. Our process handles this for you. After you place your order and complete the health intake, a licensed provider reviews your information and issues a prescription if clinically appropriate. Your card is not charged until the order is approved. There is no separate consultation fee.',
	],
	[
		'q' => 'Are there any contraindications I should know about?',
		'a' => 'PDE5 inhibitors should not be taken with nitrate medications (such as nitroglycerin) as this combination can cause a dangerous drop in blood pressure. They should also be used with caution in patients with certain cardiovascular conditions. Your provider reviews your health intake for these contraindications before approving any order — if your profile is not a match, they will decline the order and inform you.',
	],
	[
		'q' => 'What\'s included in my order?',
		'a' => 'Your shipment includes the compounded oral tablets in the prescribed dose and quantity. Everything ships from a licensed U.S. FDA-registered 503A compounding pharmacy in discreet packaging with no indication of contents on the outside. Free standard shipping is included on all orders.',
	],
	[
		'q' => 'Can I cancel or pause my program?',
		'a' => 'Yes. You can pause or cancel at any time through your patient portal or by contacting our support team. We ask for at least 5 business days\' notice before your next billing date. There are no long-term contracts or cancellation fees.',
	],
];
?>

<div class="myogenix-pdp myogenix-cat">

	<!-- Category Hero -->
	<section class="myogenix-cat__hero">
		<div class="myogenix-pdp__container">
			<p class="myogenix-cat__hero-label">Sexual Health</p>
			<h1 class="myogenix-cat__hero-title">Erectile Dysfunction Treatment</h1>
			<p class="myogenix-cat__hero-desc">Compounded oral ED medications, reviewed by a licensed provider. Discreet shipping, no insurance required.</p>
		</div>
	</section>

	<!-- Product Cards -->
	<section class="myogenix-cat__products">
		<div class="myogenix-pdp__container">
			<h2 class="myogenix-pdp__section-heading">Choose Your Medication</h2>
			<p class="myogenix-pdp__section-sub">Both medications are oral PDE5 inhibitors available as compounded formulations. Select the one that fits your lifestyle and clinical profile.</p>
			<div class="myogenix-cat__products-grid">

				<?php foreach ( $sh_config as $slug => $cfg ) :
					$min_price = $sh_min_prices[ $slug ] ?? null;
					$product   = $sh_products[ $slug ] ?? null;
					$image_url = '';
					if ( $product ) {
						$img_id    = $product->get_image_id();
						$image_url = $img_id ? wp_get_attachment_image_url( $img_id, 'large' ) : '';
						$prod_url  = get_permalink( $product->get_id() );
					} else {
						$prod_url = home_url( $cfg['url'] );
					}
				?>
				<div class="myogenix-cat__product-card">
					<span class="pdp-hero__badge"><?php echo esc_html( $cfg['badge'] ); ?></span>
					<h2 class="myogenix-cat__product-title"><?php echo esc_html( $cfg['title'] ); ?></h2>
					<p class="myogenix-cat__product-desc"><?php echo esc_html( $cfg['desc'] ); ?></p>
					<?php if ( $image_url ) : ?>
					<div class="myogenix-cat__product-image">
						<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $cfg['title'] ); ?>" loading="lazy" />
					</div>
					<?php endif; ?>
					<?php if ( $min_price ) : ?>
					<div class="myogenix-cat__product-price">
						<span class="myogenix-cat__product-price-label">Starting from</span>
						<strong class="myogenix-cat__product-price-value">
							$<?php echo esc_html( number_format( $min_price, 0 ) ); ?><span>/month</span>
						</strong>
					</div>
					<?php endif; ?>
					<a href="<?php echo esc_url( $prod_url ); ?>" class="pdp-cfg__cta">
						Configure Your Program &rarr;
					</a>
					<p class="pdp-cfg__disclaimer">Provider-reviewed before processing. Prescription required.</p>
				</div>
				<?php endforeach; ?>

			</div>
		</div>
	</section>

	<!-- 4 Steps Section -->
	<section class="myogenix-pdp__steps">
		<div class="myogenix-pdp__container">
			<h2 class="myogenix-pdp__section-heading">Personalized Healthcare in 4 Simple Steps</h2>
			<p class="myogenix-pdp__section-sub">Get started with no insurance required.</p>
			<div class="myogenix-pdp__steps-grid">
				<?php foreach ( $steps as $step ) : ?>
				<div class="myogenix-pdp__step-card">
					<img class="myogenix-pdp__step-num" src="<?php echo $img_url( $step['num'] ); ?>" alt="" aria-hidden="true" />
					<img class="myogenix-pdp__step-img" src="<?php echo $img_url( $step['img'] ); ?>" alt="<?php echo esc_attr( $step['title'] ); ?>" />
					<h3 class="myogenix-pdp__step-title"><?php echo esc_html( $step['title'] ); ?></h3>
					<p class="myogenix-pdp__step-desc"><?php echo esc_html( $step['desc'] ); ?></p>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<!-- How It Works Section (home page design) -->
	<section class="home-how" id="how-it-works" aria-label="How it works">
		<div class="hp-inner">
			<div class="home-how__header">
				<p class="home-how__overline">Process</p>
				<h2 class="home-how__heading">How it works</h2>
				<p class="home-how__desc">From your first order to your ongoing program — here's what to expect at every step.</p>
			</div>
			<div class="home-how__steps">
				<?php foreach ( $hp_steps as $step ) : ?>
				<div class="hp-step">
					<div class="hp-step__img-wrap">
						<img src="<?php echo esc_url( $step['img'] ); ?>" alt="<?php echo esc_attr( $step['title'] ); ?>" class="hp-step__img" loading="lazy" width="400" height="300">
					</div>
					<div class="hp-step__body">
						<p class="hp-step__num"><?php echo esc_html( $step['num'] ); ?></p>
						<h3 class="hp-step__title"><?php echo esc_html( $step['title'] ); ?></h3>
						<p class="hp-step__desc"><?php echo esc_html( $step['desc'] ); ?></p>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<!-- FAQ (PDP design) -->
	<section class="myo-faq" id="faq" aria-label="Frequently asked questions">
		<div class="myo-faq__wrap">
			<div class="myo-faq__header">
				<span class="myo-faq__eyebrow">FAQ</span>
				<h2 class="myo-faq__title">Common questions</h2>
				<p class="myo-faq__desc">Everything you need to know about ED medications, dosing, and how our program works.</p>
			</div>
			<div class="myo-faq__list">
				<?php foreach ( $faqs as $i => $faq ) :
					$panel_id   = 'sh-faq-' . $i;
					$is_first   = ( $i === 0 );
					$expanded   = $is_first ? 'true' : 'false';
					$open_class = $is_first ? ' is-open' : '';
				?>
				<div class="myo-faq__item">
					<button class="myo-faq__btn" type="button" aria-expanded="<?php echo esc_attr( $expanded ); ?>" aria-controls="<?php echo esc_attr( $panel_id ); ?>">
						<span class="myo-faq__q"><?php echo esc_html( $faq['q'] ); ?></span>
						<span class="myo-faq__icon" aria-hidden="true"><svg width="10" height="6" viewBox="0 0 10 6" fill="none"><path d="M1 1L5 5L9 1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
					</button>
					<div class="myo-faq__panel<?php echo esc_attr( $open_class ); ?>" id="<?php echo esc_attr( $panel_id ); ?>">
						<div class="myo-faq__panel-inner">
							<p><?php echo esc_html( $faq['a'] ); ?></p>
						</div>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
			<div class="myo-faq__cta">
				<a href="#" class="myo-faq__cta-btn" onclick="window.scrollTo({top:0,behavior:'smooth'});return false;">Choose your medication &rarr;</a>
			</div>
		</div>
	</section>

	<!-- Explore More Treatment Lines -->
	<section class="myogenix-pdp__explore">
		<div class="myogenix-pdp__container">
			<h2 class="myogenix-pdp__section-heading">Explore More Treatment Lines</h2>
			<p class="myogenix-pdp__section-sub">The telehealth provider of choice for holistic care.</p>
			<div class="myogenix-pdp__explore-grid">
				<a href="<?php echo esc_url( home_url( '/product-category/weight-loss/' ) ); ?>" class="myogenix-pdp__explore-link">
					<img src="<?php echo $img_url( 'PDP Sections/weight management.png' ); ?>" alt="Weight Management" />
				</a>
				<a href="<?php echo esc_url( home_url( '/product-category/mens-health/' ) ); ?>" class="myogenix-pdp__explore-link">
					<img src="<?php echo $img_url( 'PDP Sections/mens health.png' ); ?>" alt="Men's Health" />
				</a>
			</div>
		</div>
	</section>

</div>

<?php
/**
 * Hook: woocommerce_after_main_content.
 * @hooked woocommerce_output_content_wrapper_end - 10 (outputs closing divs)
 */
do_action( 'woocommerce_after_main_content' );

do_action( 'woocommerce_sidebar' );

get_footer( 'shop' );
