<?php
/**
 * Template for the mens-health product category archive.
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

$mh_config = [
	'testosterone' => [
		'badge'       => 'Testosterone Replacement Therapy',
		'title'       => 'Testosterone Cypionate',
		'desc'        => 'Clinically dosed injectable testosterone to restore optimal hormonal levels. Weekly or bi-weekly self-injection with ongoing provider monitoring.',
		'bullets'     => [
			'Provider-reviewed TRT program',
			'Weekly or bi-weekly self-injection',
			'Ongoing hormone monitoring included',
			'Compounded in FDA-registered facility',
		],
		'url'         => '/product/testosterone/',
		'price_label' => 'Starting from',
		'price_unit'  => '/month',
		'cta_label'   => 'Configure Your Program',
	],
	'hcg' => [
		'badge'       => "Men's Health",
		'title'       => 'HCG',
		'desc'        => 'Physician-prescribed HCG to support natural testosterone production and testicular function, often used alongside a hormone optimization plan.',
		'bullets'     => [
			'Provider-reviewed before shipping',
			'Subcutaneous self-injection',
			'Syringes & supplies included',
			'Compounded in FDA-registered facility',
		],
		'url'         => '/product/hcg/',
		'price_label' => 'One-time purchase',
		'price_unit'  => '',
		'cta_label'   => 'Go to Checkout',
	],
];

$mh_products   = [];
$mh_min_prices = [];

foreach ( array_keys( $mh_config ) as $slug ) {
	$posts = get_posts( [
		'name'           => $slug,
		'post_type'      => 'product',
		'posts_per_page' => 1,
		'post_status'    => 'publish',
	] );
	if ( ! $posts ) continue;
	$product                = wc_get_product( $posts[0]->ID );
	$mh_products[ $slug ]   = $product;
	$min                    = null;
	foreach ( $product->get_children() as $vid ) {
		if ( 'publish' !== get_post_status( $vid ) ) continue;
		$price = (float) get_post_meta( $vid, '_price', true );
		if ( $price <= 0 ) continue;

		// Subscription variations bill every N months (N > 1) as a lump sum;
		// normalize to per-month so "Starting from" matches the PDP/home-page
		// figure instead of showing the full multi-month charge (e.g. TRT's
		// $567/3mo -> $189/month).
		if ( class_exists( 'WC_Subscriptions_Product' ) ) {
			$interval = (int) WC_Subscriptions_Product::get_interval( $vid );
			$period   = WC_Subscriptions_Product::get_period( $vid );
			if ( $interval > 1 && 'month' === $period ) {
				$price = $price / $interval;
			}
		}

		if ( null === $min || $price < $min ) {
			$min = $price;
		}
	}
	if ( null === $min ) {
		$min = (float) $product->get_price();
	}
	$mh_min_prices[ $slug ] = $min > 0 ? $min : null;
}

$img_url = function( $path ) {
	$base  = get_stylesheet_directory_uri() . '/assets/images/';
	$parts = explode( '/', $path );
	return esc_url( $base . implode( '/', array_map( 'rawurlencode', $parts ) ) );
};

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
		'q' => 'Is TRT right for me?',
		'a' => 'TRT is typically indicated for adult men with clinically low testosterone levels confirmed by lab work, accompanied by symptoms such as fatigue, low libido, reduced muscle mass, or mood changes. A licensed provider reviews every order before it is processed — they confirm candidacy based on your health intake and decline orders that aren\'t clinically appropriate.',
	],
	[
		'q' => 'How is testosterone administered?',
		'a' => 'Testosterone cypionate is administered as a subcutaneous or intramuscular injection, typically once or twice per week depending on your prescribed dose and protocol. Your shipment includes the vials and all necessary injection supplies. Most patients find the injection simple to perform at home after a brief demonstration.',
	],
	[
		'q' => 'Do I need labs or blood work?',
		'a' => 'Baseline lab work establishing low testosterone is required before starting TRT. Your provider may request recent lab results as part of your intake. Ongoing monitoring — typically every 3–6 months — helps ensure your dose remains appropriate and your hormone levels stay in the optimal range. Monitoring check-ins are included in your subscription.',
	],
	[
		'q' => 'How quickly will I see results?',
		'a' => 'Most patients notice improvements in energy, mood, and libido within 3–6 weeks of starting TRT. Muscle mass and body composition changes typically become noticeable after 2–3 months at therapeutic doses. Optimal results are usually seen after 4–6 months of consistent therapy with appropriate dose titration.',
	],
	[
		'q' => 'Is this a prescription medication?',
		'a' => 'Yes — testosterone is a controlled substance that requires a valid prescription. Our process handles this for you. After you place your order and complete the health intake, a licensed provider reviews your information and issues a prescription if clinically appropriate. Your card is not charged until the order is approved.',
	],
	[
		'q' => 'Can I pause or cancel my program?',
		'a' => 'Yes. You can pause or cancel at any time through your patient portal or by contacting our support team. We ask for at least 5 business days\' notice before your next billing date. There are no long-term contracts or cancellation fees.',
	],
	[
		'q' => 'What is HCG used for?',
		'a' => 'HCG (human chorionic gonadotropin) supports natural testosterone production and testicular function, often alongside a TRT program or as part of a broader hormone optimization plan.',
	],
	[
		'q' => 'Is HCG a subscription?',
		'a' => 'No — HCG is a one-time purchase. You can reorder whenever you need your next vial, with no recurring subscription or commitment.',
	],
];
?>

<div class="myogenix-pdp myogenix-cat">

	<!-- Category Hero -->
	<section class="myogenix-cat__hero">
		<div class="myogenix-pdp__container">
			<p class="myogenix-cat__hero-label">Men's Health</p>
			<h1 class="myogenix-cat__hero-title">Men's Health Treatments</h1>
			<p class="myogenix-cat__hero-desc">Compounded hormone therapy and support, reviewed by a licensed provider. No insurance required.</p>
		</div>
	</section>

	<!-- Product Cards -->
	<section class="myogenix-cat__products">
		<div class="myogenix-pdp__container">
			<h2 class="myogenix-pdp__section-heading">Men's Health Programs</h2>
			<p class="myogenix-pdp__section-sub">Provider-reviewed treatments with supplies and ongoing support included.</p>
			<div class="myogenix-cat__products-grid<?php echo count( $mh_config ) === 1 ? ' myogenix-cat__products-grid--single' : ''; ?>">

				<?php foreach ( $mh_config as $slug => $cfg ) :
					$min_price = $mh_min_prices[ $slug ] ?? null;
					$product   = $mh_products[ $slug ] ?? null;
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
						<span class="myogenix-cat__product-price-label"><?php echo esc_html( $cfg['price_label'] ); ?></span>
						<strong class="myogenix-cat__product-price-value">
							$<?php echo esc_html( number_format( $min_price, 0 ) ); ?><span><?php echo esc_html( $cfg['price_unit'] ); ?></span>
						</strong>
					</div>
					<?php endif; ?>
					<a href="<?php echo esc_url( $prod_url ); ?>" class="pdp-cfg__cta">
						<?php echo esc_html( $cfg['cta_label'] ); ?> &rarr;
					</a>
					<p class="pdp-cfg__disclaimer">Provider-reviewed before processing. Prescription required.</p>
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
				<p class="myo-faq__desc">Everything you need to know about our men's health treatments, dosing, and how our program works.</p>
			</div>
			<div class="myo-faq__list">
				<?php foreach ( $faqs as $i => $faq ) :
					$panel_id   = 'mh-faq-' . $i;
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
				<a href="<?php echo esc_url( home_url( '/product-category/mens-health/' ) ); ?>" class="myo-faq__cta-btn">Explore men's health treatments &rarr;</a>
			</div>
		</div>
	</section>

	<!-- Explore More Treatment Lines -->
	<section class="myogenix-pdp__explore">
		<div class="myogenix-pdp__container">
			<h2 class="myogenix-pdp__section-heading">Explore More Treatment Lines</h2>
			<p class="myogenix-pdp__section-sub">Provider-reviewed programs for every health goal.</p>
			<?php myogenix_render_product_scrollers( [ 'weight-loss', 'peptides' ] ); ?>
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
