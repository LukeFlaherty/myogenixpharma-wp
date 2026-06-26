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
do_action( 'woocommerce_before_main_content' );

$mh_config = [
	'testosterone' => [
		'badge'   => 'Testosterone Replacement Therapy',
		'title'   => 'Testosterone Cypionate',
		'desc'    => 'Clinically dosed injectable testosterone to restore optimal hormonal levels. Weekly or bi-weekly self-injection with ongoing provider monitoring.',
		'bullets' => [
			'Provider-reviewed TRT program',
			'Weekly or bi-weekly self-injection',
			'Ongoing hormone monitoring included',
			'Compounded in FDA-registered facility',
		],
		'url' => '/product/testosterone/',
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
		if ( $price > 0 && ( null === $min || $price < $min ) ) {
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
			<p class="myogenix-cat__hero-label">Men's Health</p>
			<h1 class="myogenix-cat__hero-title">Testosterone Replacement Therapy</h1>
			<p class="myogenix-cat__hero-desc">Provider-reviewed TRT program with compounded testosterone shipped directly to your door. No insurance required.</p>
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
			<h2 class="myogenix-pdp__section-heading">Your TRT Program</h2>
			<p class="myogenix-pdp__section-sub">Provider-reviewed testosterone replacement with supplies and ongoing monitoring included.</p>
			<div class="myogenix-cat__products-grid myogenix-cat__products-grid--single">

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
					<h3 class="myogenix-pdp__how-title">Ongoing monitoring</h3>
					<p class="myogenix-pdp__how-desc">Your provider monitors your hormone levels and adjusts your dose as needed. No new consultation required for active subscribers.</p>
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
					<p class="myogenix-pdp__cq-sub">Everything you need to know about testosterone therapy, dosing, and how our program works.</p>
					<a href="<?php echo esc_url( home_url( '/product/testosterone/' ) ); ?>" class="myogenix-pdp__cq-btn">Configure your program &rarr;</a>
				</div>

				<div class="myogenix-pdp__cq-right">
					<div class="myogenix-pdp__cq-list">

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="true" aria-controls="mh-cq-0">
								<span>Is TRT right for me?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer is-open" id="mh-cq-0">
								<p>TRT is typically indicated for adult men with clinically low testosterone levels confirmed by lab work, accompanied by symptoms such as fatigue, low libido, reduced muscle mass, or mood changes. A licensed provider reviews every order before it is processed — they confirm candidacy based on your health intake and decline orders that aren't clinically appropriate.</p>
							</div>
						</div>

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="false" aria-controls="mh-cq-1">
								<span>How is testosterone administered?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer" id="mh-cq-1">
								<p>Testosterone cypionate is administered as a subcutaneous or intramuscular injection, typically once or twice per week depending on your prescribed dose and protocol. Your shipment includes the vials and all necessary injection supplies. Most patients find the injection simple to perform at home after a brief demonstration.</p>
							</div>
						</div>

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="false" aria-controls="mh-cq-2">
								<span>Do I need labs or blood work?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer" id="mh-cq-2">
								<p>Baseline lab work establishing low testosterone is required before starting TRT. Your provider may request recent lab results as part of your intake. Ongoing monitoring — typically every 3–6 months — helps ensure your dose remains appropriate and your hormone levels stay in the optimal range. Monitoring check-ins are included in your subscription.</p>
							</div>
						</div>

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="false" aria-controls="mh-cq-3">
								<span>How quickly will I see results?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer" id="mh-cq-3">
								<p>Most patients notice improvements in energy, mood, and libido within 3–6 weeks of starting TRT. Muscle mass and body composition changes typically become noticeable after 2–3 months at therapeutic doses. Optimal results are usually seen after 4–6 months of consistent therapy with appropriate dose titration.</p>
							</div>
						</div>

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="false" aria-controls="mh-cq-4">
								<span>Is this a prescription medication?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer" id="mh-cq-4">
								<p>Yes — testosterone is a controlled substance that requires a valid prescription. Our process handles this for you. After you place your order and complete the health intake, a licensed provider reviews your information and issues a prescription if clinically appropriate. Your card is not charged until the order is approved.</p>
							</div>
						</div>

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="false" aria-controls="mh-cq-5">
								<span>Can I pause or cancel my program?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer" id="mh-cq-5">
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
				<a href="<?php echo esc_url( home_url( '/product-category/weight-loss/' ) ); ?>" class="myogenix-pdp__explore-link">
					<img src="<?php echo $img_url( 'PDP Sections/weight management.png' ); ?>" alt="Weight Management" />
				</a>
				<a href="<?php echo esc_url( home_url( '/product-category/sexual-health/' ) ); ?>" class="myogenix-pdp__explore-link">
					<img src="<?php echo $img_url( 'PDP Sections/sexual health.png' ); ?>" alt="Sexual Health" />
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
