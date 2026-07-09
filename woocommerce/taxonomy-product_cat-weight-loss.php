<?php
/**
 * Template for the weight-loss product category archive.
 * Custom layout — mirrors PDP design with product comparison cards.
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

// Load both weight management products by slug
$wm_config = [
	'compound-tirzepatide' => [
		'badge'       => 'GIP/GLP-1 Receptor Agonist',
		'title'       => 'Tirzepatide',
		'desc'        => 'Activates both GIP and GLP-1 receptors, delivering stronger metabolic effects than GLP-1 alone. Once-weekly injection.',
		'bullets'     => [
			'Dual receptor mechanism',
			'Greater average weight loss vs. semaglutide in clinical trials',
			'Once-weekly self-injection',
			'Compounded in FDA-registered facility',
		],
		'url'      => '/product/compound-tirzepatide/',
		'img_path' => 'tirzepatide/tirzepatide.png',
	],
	'compound-semaglutide' => [
		'badge'       => 'GLP-1 Receptor Agonist',
		'title'       => 'Semaglutide',
		'desc'        => 'Activates GLP-1 receptors to reduce appetite and improve blood sugar control. Well-established safety profile. Once-weekly injection.',
		'bullets'     => [
			'Well-established clinical safety profile',
			'Clinically proven appetite suppression',
			'Once-weekly self-injection',
			'Compounded in FDA-registered facility',
		],
		'url'      => '/product/compound-semaglutide/',
		'img_path' => 'semaglutide/semaglutide.png',
	],
];

// Fetch live WC products and their starting prices
$wm_products   = [];
$wm_min_prices = [];

foreach ( array_keys( $wm_config ) as $slug ) {
	$posts = get_posts( [
		'name'           => $slug,
		'post_type'      => 'product',
		'posts_per_page' => 1,
		'post_status'    => 'publish',
	] );
	if ( ! $posts ) continue;
	$product                = wc_get_product( $posts[0]->ID );
	$wm_products[ $slug ]   = $product;
	$min                    = null;
	foreach ( $product->get_children() as $vid ) {
		if ( 'publish' !== get_post_status( $vid ) ) continue;
		$price = (float) get_post_meta( $vid, '_price', true );
		if ( $price > 0 && ( null === $min || $price < $min ) ) {
			$min = $price;
		}
	}
	$wm_min_prices[ $slug ] = $min;
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
		'q' => 'What is the difference between tirzepatide and semaglutide?',
		'a' => 'Tirzepatide is a dual GIP/GLP-1 receptor agonist — it activates two metabolic pathways simultaneously. Semaglutide only activates the GLP-1 receptor. Clinical trials show tirzepatide produced significantly greater average weight loss than semaglutide across comparable doses. Semaglutide has a longer established track record and may be preferred for certain clinical profiles. Your provider will help you choose.',
	],
	[
		'q' => 'Am I a candidate for GLP-1 medication?',
		'a' => 'GLP-1 medications are typically indicated for adults with a BMI of 30+ or a BMI of 27+ with at least one weight-related condition (such as type 2 diabetes, high blood pressure, or high cholesterol). A licensed provider reviews every order before it is processed — they will confirm candidacy based on your health intake and decline orders that aren\'t clinically appropriate.',
	],
	[
		'q' => 'How quickly will I see results?',
		'a' => 'Most patients notice reduced appetite within the first few weeks. Meaningful weight loss typically becomes visible by weeks 4–8, with the most significant results occurring over months 3–6 as doses escalate. Individual results vary based on starting dose, adherence, diet, and activity level. Clinical trials show average weight loss of 15–22% body weight over 68–72 weeks at maintenance doses.',
	],
	[
		'q' => 'Do I need a prescription?',
		'a' => 'Yes — all GLP-1 medications require a valid prescription. Our process handles this for you. After you place your order and complete the health intake, a licensed provider reviews your information and issues a prescription if clinically appropriate. Your card is not charged until the order is approved. There is no separate consultation fee.',
	],
	[
		'q' => 'What\'s included in my order?',
		'a' => 'Your shipment includes the compounded medication vials, syringes, and all necessary injection supplies. Everything ships from a licensed U.S. FDA-registered 503A compounding pharmacy in temperature-controlled, discreet packaging. Free standard shipping is included on all orders.',
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
			<p class="myogenix-cat__hero-label">Weight Management</p>
			<h1 class="myogenix-cat__hero-title">Medical Weight Loss Programs</h1>
			<p class="myogenix-cat__hero-desc">Compounded GLP-1 medications, reviewed by a licensed provider. No insurance required.</p>
		</div>
	</section>

	<!-- Product Cards -->
	<section class="myogenix-cat__products">
		<div class="myogenix-pdp__container">
			<h2 class="myogenix-pdp__section-heading">Choose Your Medication</h2>
			<p class="myogenix-pdp__section-sub">Both medications are once-weekly injectables available as compounded formulations. Select the one that fits your clinical profile.</p>
			<div class="myogenix-cat__products-grid">

				<?php foreach ( $wm_config as $slug => $cfg ) :
					$min_price = $wm_min_prices[ $slug ] ?? null;
					$product   = $wm_products[ $slug ] ?? null;
					$image_url = '';
					if ( $product ) {
						$img_id    = $product->get_image_id();
						$image_url = $img_id ? wp_get_attachment_image_url( $img_id, 'large' ) : '';
					}
					if ( ! $image_url ) {
						$image_url = $img_url( $cfg['img_path'] );
					}
				?>
				<div class="myogenix-cat__product-card">
					<span class="pdp-hero__badge"><?php echo esc_html( $cfg['badge'] ); ?></span>
					<h2 class="myogenix-cat__product-title"><?php echo esc_html( $cfg['title'] ); ?></h2>
					<p class="myogenix-cat__product-desc"><?php echo esc_html( $cfg['desc'] ); ?></p>
					<div class="myogenix-cat__product-image">
						<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $cfg['title'] ); ?>" loading="lazy" />
					</div>
					<?php if ( $min_price ) : ?>
					<div class="myogenix-cat__product-price">
						<span class="myogenix-cat__product-price-label">Starting from</span>
						<strong class="myogenix-cat__product-price-value">
							$<?php echo esc_html( number_format( $min_price, 0 ) ); ?><span>/month</span>
						</strong>
					</div>
					<?php endif; ?>
					<a href="<?php echo esc_url( home_url( $cfg['url'] ) ); ?>" class="pdp-cfg__cta">
						Configure Your Program &rarr;
					</a>
					<p class="pdp-cfg__disclaimer">Provider-reviewed before processing. One-time purchase.</p>
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
				<p class="myo-faq__desc">Everything you need to know about GLP-1 medications, dosing, and how our program works.</p>
			</div>
			<div class="myo-faq__list">
				<?php foreach ( $faqs as $i => $faq ) :
					$panel_id   = 'wm-faq-' . $i;
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
				<a href="<?php echo esc_url( home_url( '/mens-health/' ) ); ?>" class="myogenix-pdp__explore-link">
					<img src="<?php echo $img_url( 'PDP Sections/mens health.png' ); ?>" alt="Men's Health" />
				</a>
				<a href="<?php echo esc_url( home_url( '/womens-health/' ) ); ?>" class="myogenix-pdp__explore-link">
					<img src="<?php echo $img_url( 'PDP Sections/womens health.png' ); ?>" alt="Women's Health" />
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
