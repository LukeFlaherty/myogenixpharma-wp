<?php
/**
 * Template for the peptides-longevity product category archive.
 * Custom layout — compact product grid with dynamic WP_Query.
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

// Display name and tagline overrides for known products — falls back to WC title for unlisted ones.
$peptide_meta = [
	4253 => [ 'name' => 'MOTSc',                    'tagline' => 'Mitochondrial support' ],
	4257 => [ 'name' => 'Epithalon',                 'tagline' => 'Longevity peptide' ],
	4249 => [ 'name' => 'BPC 3mg',                   'tagline' => 'Healing & repair' ],
	2819 => [ 'name' => 'Klow',                      'tagline' => 'Metabolic support' ],
	2803 => [ 'name' => 'Tesamorelin / Ipamorelin',  'tagline' => 'GH optimization' ],
	2619 => [ 'name' => 'CJC / Ipamorelin',          'tagline' => 'Growth hormone release' ],
	2606 => [ 'name' => 'Wolverine',                 'tagline' => 'Elite tissue recovery' ],
	1874 => [ 'name' => 'NAD+',                      'tagline' => 'Cellular energy' ],
	1871 => [ 'name' => 'Sermorelin',                'tagline' => 'Growth hormone support' ],
	1868 => [ 'name' => 'Glutathione',               'tagline' => 'Antioxidant defense' ],
];

// Dynamic query — picks up new products automatically as they're added.
$peptide_query = new WP_Query( [
	'post_type'      => 'product',
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'orderby'        => 'menu_order',
	'order'          => 'ASC',
	'tax_query'      => [ [
		'taxonomy' => 'product_cat',
		'field'    => 'slug',
		'terms'    => 'peptides-longevity',
	] ],
] );

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
			<p class="myogenix-cat__hero-label">Peptides & Longevity</p>
			<h1 class="myogenix-cat__hero-title">Performance & Longevity Peptides</h1>
			<p class="myogenix-cat__hero-desc">Compounded injectable peptides for recovery, performance, and longevity. Provider-reviewed and cold-chain shipped.</p>
		</div>
	</section>

	<!-- Compact Product Grid -->
	<section class="myogenix-cat__products myogenix-cat__products--compact">
		<div class="myogenix-pdp__container">
			<h2 class="myogenix-pdp__section-heading">Our Peptide Programs</h2>
			<p class="myogenix-pdp__section-sub">Subscription includes access to healthcare providers, medication, applicable supplies, and shipping.</p>

			<?php if ( $peptide_query->have_posts() ) : ?>
			<div class="myogenix-cat__compact-grid">
				<?php while ( $peptide_query->have_posts() ) : $peptide_query->the_post();
					$id      = get_the_ID();
					$product = wc_get_product( $id );
					if ( ! $product ) continue;

					$meta    = $peptide_meta[ $id ] ?? null;
					$name    = $meta ? $meta['name'] : get_the_title();
					$tagline = $meta ? $meta['tagline'] : wp_strip_all_tags( $product->get_short_description() );
					$tagline = mb_strimwidth( $tagline, 0, 60, '' );

					// Min price across variations; fall back to product price.
					$min_price = null;
					if ( $product->is_type( 'variable' ) || $product->is_type( 'variable-subscription' ) ) {
						foreach ( $product->get_children() as $vid ) {
							if ( 'publish' !== get_post_status( $vid ) ) continue;
							$vp = (float) get_post_meta( $vid, '_price', true );
							if ( $vp > 0 && ( null === $min_price || $vp < $min_price ) ) {
								$min_price = $vp;
							}
						}
					}
					if ( null === $min_price ) {
						$p = (float) $product->get_price();
						if ( $p > 0 ) $min_price = $p;
					}

					$img_id    = $product->get_image_id();
					$image_url = $img_id ? wp_get_attachment_image_url( $img_id, 'medium' ) : '';
					$prod_url  = get_permalink( $id );
				?>
				<a href="<?php echo esc_url( $prod_url ); ?>" class="myogenix-cat__compact-card" aria-label="<?php echo esc_attr( $name ); ?>">
					<?php if ( $image_url ) : ?>
					<img class="myogenix-cat__compact-card-img" src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $name ); ?>" loading="lazy" />
					<?php endif; ?>
					<p class="myogenix-cat__compact-card-type">Compound Injectable</p>
					<h3 class="myogenix-cat__compact-card-name"><?php echo esc_html( $name ); ?></h3>
					<?php if ( $min_price ) : ?>
					<p class="myogenix-cat__compact-card-price">$<?php echo esc_html( number_format( $min_price, 0 ) ); ?></p>
					<?php endif; ?>
					<span class="myogenix-cat__compact-card-cta pdp-cfg__cta">Get Started</span>
				</a>
				<?php endwhile; wp_reset_postdata(); ?>
			</div>
			<?php endif; ?>

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
					<p class="myogenix-pdp__how-desc">A licensed provider reviews your order within 24 hours. They confirm your protocol is clinically appropriate before it ships.</p>
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
					<h3 class="myogenix-pdp__how-title">Shipped cold-chain</h3>
					<p class="myogenix-pdp__how-desc">Your peptides ship from an FDA-registered compounding pharmacy in temperature-controlled, discreet packaging with cold-chain handling.</p>
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
					<p class="myogenix-pdp__how-desc">Your provider monitors your progress and adjusts your protocol as needed. No new consultation required for active subscribers.</p>
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
					<p class="myogenix-pdp__cq-sub">Everything you need to know about compounded peptides, administration, and our program.</p>
					<a href="#" class="myogenix-pdp__cq-btn" onclick="window.scrollTo({top:0,behavior:'smooth'});return false;">Browse peptides &rarr;</a>
				</div>

				<div class="myogenix-pdp__cq-right">
					<div class="myogenix-pdp__cq-list">

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="true" aria-controls="pl-cq-0">
								<span>What are compounded peptides?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer is-open" id="pl-cq-0">
								<p>Peptides are short chains of amino acids that act as signaling molecules in the body — influencing processes like tissue repair, hormone release, immune function, and cellular energy. Compounded peptides are prepared by licensed 503A pharmacies to precise clinical specifications. They are not FDA-approved drugs, but are legally dispensed with a valid prescription from a licensed U.S. provider.</p>
							</div>
						</div>

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="false" aria-controls="pl-cq-1">
								<span>How are peptides administered?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer" id="pl-cq-1">
								<p>Most peptides in our catalog are administered as subcutaneous (under the skin) injections using a small insulin-type needle. The injection site is typically the abdomen or thigh. Your shipment includes the vials, syringes, and all necessary supplies. Most patients find the technique straightforward — similar to a standard insulin injection.</p>
							</div>
						</div>

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="false" aria-controls="pl-cq-2">
								<span>Do I need a provider review?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer" id="pl-cq-2">
								<p>Yes — all peptide orders require a prescription from a licensed provider. After you place your order and complete the health intake, one of our licensed providers reviews your information and issues a prescription if clinically appropriate. Your card is not charged until the order is approved. There is no separate consultation fee.</p>
							</div>
						</div>

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="false" aria-controls="pl-cq-3">
								<span>Can I take multiple peptides together?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer" id="pl-cq-3">
								<p>Yes — stacking complementary peptides is common and clinically well-supported. For example, a healing stack might combine BPC-157 with TB500 (available as Wolverine), while a performance stack might pair a GHRH like Tesamorelin with a GHRP like Ipamorelin. Your provider reviews your complete protocol before any order ships, ensuring the combination is appropriate for your goals.</p>
							</div>
						</div>

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="false" aria-controls="pl-cq-4">
								<span>How should I store my peptides?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer" id="pl-cq-4">
								<p>Lyophilized (freeze-dried) peptide vials should be stored in a cool, dry place — ideally refrigerated — until reconstituted. Once reconstituted, store in the refrigerator and use within the timeframe indicated on your vial label. Your shipment includes cold packs rated for up to 72 hours in transit, so the peptides arrive at proper temperature.</p>
							</div>
						</div>

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="false" aria-controls="pl-cq-5">
								<span>Can I cancel or pause my program?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer" id="pl-cq-5">
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
				<a href="<?php echo esc_url( home_url( '/product-category/mens-health/' ) ); ?>" class="myogenix-pdp__explore-link">
					<img src="<?php echo $img_url( 'PDP Sections/mens health.png' ); ?>" alt="Men's Health" />
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
