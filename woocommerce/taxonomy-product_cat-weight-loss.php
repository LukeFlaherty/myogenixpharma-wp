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
?>

<div class="myogenix-pdp myogenix-cat">

	<!-- Category Hero -->
	<section class="myogenix-cat__hero">
		<div class="myogenix-pdp__container">
			<p class="myogenix-cat__hero-label">Weight Management</p>
			<h1 class="myogenix-cat__hero-title">Medical Weight Loss Programs</h1>
			<p class="myogenix-cat__hero-desc">Provider-reviewed, compounded GLP-1 medications shipped directly to your door. No insurance required.</p>
			<div class="myogenix-cat__hero-trust">
				<div class="myogenix-cat__hero-trust-item">
					<span aria-hidden="true">🏥</span>
					<span>Licensed providers</span>
				</div>
				<div class="myogenix-cat__hero-trust-item">
					<span aria-hidden="true">✏️</span>
					<span>FDA-registered facility</span>
				</div>
				<div class="myogenix-cat__hero-trust-item">
					<span aria-hidden="true">🚚</span>
					<span>Free discreet shipping</span>
				</div>
				<div class="myogenix-cat__hero-trust-item">
					<span aria-hidden="true">💬</span>
					<span>Ongoing provider support</span>
				</div>
			</div>
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
					<ul class="myogenix-cat__product-bullets">
						<?php foreach ( $cfg['bullets'] as $bullet ) : ?>
						<li><?php echo esc_html( $bullet ); ?></li>
						<?php endforeach; ?>
					</ul>
					<hr class="myogenix-cat__product-divider" />
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

	<!-- How It Works Section -->
	<section class="myogenix-pdp__how-it-works">
		<div class="myogenix-pdp__container">
			<p class="myogenix-pdp__how-label">PROCESS</p>
			<h2 class="myogenix-pdp__how-heading">How it works</h2>
			<p class="myogenix-pdp__how-sub">From your first order to your ongoing program — here's what to expect at every step.</p>
			<div class="myogenix-pdp__how-grid">

				<div class="myogenix-pdp__how-card">
					<div class="myogenix-pdp__how-card-top">
						<div class="myogenix-pdp__how-icon" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
								<rect x="8" y="2" width="8" height="4" rx="1"/>
								<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
								<line x1="9" y1="11" x2="15" y2="11"/>
								<line x1="9" y1="15" x2="13" y2="15"/>
							</svg>
						</div>
						<span class="myogenix-pdp__how-num">01</span>
					</div>
					<h3 class="myogenix-pdp__how-title">Complete your intake</h3>
					<p class="myogenix-pdp__how-desc">Answer a short health questionnaire so our providers have the clinical context they need. Takes about 5 minutes.</p>
				</div>

				<div class="myogenix-pdp__how-card">
					<div class="myogenix-pdp__how-card-top">
						<div class="myogenix-pdp__how-icon" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
								<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
								<circle cx="12" cy="7" r="4"/>
							</svg>
						</div>
						<span class="myogenix-pdp__how-num">02</span>
					</div>
					<h3 class="myogenix-pdp__how-title">Provider reviews &amp; approves</h3>
					<p class="myogenix-pdp__how-desc">A licensed provider reviews your order within 24 hours. They confirm your dose is clinically appropriate before it ships.</p>
				</div>

				<div class="myogenix-pdp__how-card">
					<div class="myogenix-pdp__how-card-top">
						<div class="myogenix-pdp__how-icon" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
								<rect x="1" y="3" width="15" height="13" rx="1"/>
								<path d="M16 8h4l3 3v5h-7V8z"/>
								<circle cx="5.5" cy="18.5" r="2.5"/>
								<circle cx="18.5" cy="18.5" r="2.5"/>
							</svg>
						</div>
						<span class="myogenix-pdp__how-num">03</span>
					</div>
					<h3 class="myogenix-pdp__how-title">Shipped to your door</h3>
					<p class="myogenix-pdp__how-desc">Your medication ships from an FDA-registered compounding pharmacy in discreet packaging, with cold-chain handling.</p>
				</div>

				<div class="myogenix-pdp__how-card">
					<div class="myogenix-pdp__how-card-top">
						<div class="myogenix-pdp__how-icon" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
								<line x1="18" y1="20" x2="18" y2="10"/>
								<line x1="12" y1="20" x2="12" y2="4"/>
								<line x1="6" y1="20" x2="6" y2="14"/>
								<line x1="2" y1="20" x2="22" y2="20"/>
							</svg>
						</div>
						<span class="myogenix-pdp__how-num">04</span>
					</div>
					<h3 class="myogenix-pdp__how-title">Adjust as you escalate</h3>
					<p class="myogenix-pdp__how-desc">Your dose changes with you. Update your next shipment directly from your account — no new consultation required for subscribers.</p>
				</div>

			</div>
		</div>
	</section>

	<!-- Common Questions Section -->
	<section class="myogenix-pdp__cq">
		<div class="myogenix-pdp__container">
			<div class="myogenix-pdp__cq-inner">

				<div class="myogenix-pdp__cq-left">
					<p class="myogenix-pdp__cq-label">FAQ</p>
					<h2 class="myogenix-pdp__cq-heading">Common questions</h2>
					<p class="myogenix-pdp__cq-sub">Everything you need to know about GLP-1 medications, dosing, and how our program works.</p>
					<a href="#" class="myogenix-pdp__cq-btn" onclick="window.scrollTo({top:0,behavior:'smooth'});return false;">Choose your medication &rarr;</a>
				</div>

				<div class="myogenix-pdp__cq-right">
					<div class="myogenix-pdp__cq-list">

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="true" aria-controls="cat-cq-0">
								<span>What is the difference between tirzepatide and semaglutide?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer is-open" id="cat-cq-0">
								<p>Tirzepatide is a dual GIP/GLP-1 receptor agonist — it activates two metabolic pathways simultaneously. Semaglutide only activates the GLP-1 receptor. Clinical trials show tirzepatide produced significantly greater average weight loss than semaglutide across comparable doses. Semaglutide has a longer established track record and may be preferred for certain clinical profiles. Your provider will help you choose.</p>
							</div>
						</div>

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="false" aria-controls="cat-cq-1">
								<span>Am I a candidate for GLP-1 medication?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer" id="cat-cq-1">
								<p>GLP-1 medications are typically indicated for adults with a BMI of 30+ or a BMI of 27+ with at least one weight-related condition (such as type 2 diabetes, high blood pressure, or high cholesterol). A licensed provider reviews every order before it is processed — they will confirm candidacy based on your health intake and decline orders that aren't clinically appropriate.</p>
							</div>
						</div>

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="false" aria-controls="cat-cq-2">
								<span>How quickly will I see results?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer" id="cat-cq-2">
								<p>Most patients notice reduced appetite within the first few weeks. Meaningful weight loss typically becomes visible by weeks 4–8, with the most significant results occurring over months 3–6 as doses escalate. Individual results vary based on starting dose, adherence, diet, and activity level. Clinical trials show average weight loss of 15–22% body weight over 68–72 weeks at maintenance doses.</p>
							</div>
						</div>

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="false" aria-controls="cat-cq-3">
								<span>Do I need a prescription?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer" id="cat-cq-3">
								<p>Yes — all GLP-1 medications require a valid prescription. Our process handles this for you. After you place your order and complete the health intake, a licensed provider reviews your information and issues a prescription if clinically appropriate. Your card is not charged until the order is approved. There is no separate consultation fee.</p>
							</div>
						</div>

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="false" aria-controls="cat-cq-4">
								<span>What's included in my order?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer" id="cat-cq-4">
								<p>Your shipment includes the compounded medication vials, syringes, and all necessary injection supplies. Everything ships from a licensed U.S. FDA-registered 503A compounding pharmacy in temperature-controlled, discreet packaging. Free standard shipping is included on all orders.</p>
							</div>
						</div>

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="false" aria-controls="cat-cq-5">
								<span>Can I cancel or pause my program?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer" id="cat-cq-5">
								<p>Yes. You can pause or cancel at any time through your patient portal or by contacting our support team. We ask for at least 5 business days' notice before your next billing date. There are no long-term contracts or cancellation fees.</p>
							</div>
						</div>

					</div>
				</div>

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

<script>
document.querySelectorAll( '.myogenix-pdp__cq-question' ).forEach( function( btn ) {
	btn.addEventListener( 'click', function() {
		var expanded = this.getAttribute( 'aria-expanded' ) === 'true';
		this.setAttribute( 'aria-expanded', String( !expanded ) );
		var answer = document.getElementById( this.getAttribute( 'aria-controls' ) );
		if ( answer ) answer.classList.toggle( 'is-open', !expanded );
	} );
} );
</script>

<?php
/**
 * Hook: woocommerce_after_main_content.
 * @hooked woocommerce_output_content_wrapper_end - 10 (outputs closing divs)
 */
do_action( 'woocommerce_after_main_content' );

do_action( 'woocommerce_sidebar' );

get_footer( 'shop' );
