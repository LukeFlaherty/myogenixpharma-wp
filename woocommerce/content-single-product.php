<?php
/**
 * The template for displaying product content in the single-product.php template
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/content-single-product.php.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.6.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

$slug           = $product->get_slug();
$weight_loss    = [ 'compound-semaglutide', 'compound-tirzepatide' ];
$is_weight_loss = in_array( $slug, $weight_loss, true );

// Suppress the "Please choose product options" notice on our custom PDP —
// it fires from the hidden WC form and confuses customers.
if ( $is_weight_loss ) {
	remove_action( 'woocommerce_before_single_product', 'woocommerce_output_all_notices', 10 );
}

/**
 * Hook: woocommerce_before_single_product.
 * @hooked woocommerce_output_all_notices - 10
 */
do_action( 'woocommerce_before_single_product' );

if ( post_password_required() ) {
	echo get_the_password_form(); // WPCS: XSS ok.
	return;
}

if ( $is_weight_loss ) :

	// Product hero config — doses + supply_prices confirmed via WP-CLI 2026-04-21
	$hero = [
		'compound-tirzepatide' => [
			'badge'             => 'GIP/GLP-1 Receptor Agonist',
			'title'             => 'Tirzepatide',
			'desc'              => 'Tirzepatide activates both GIP and GLP-1 receptors, offering strong metabolic effects with once-weekly dosing.',
			'compare_url'       => '/product/compound-semaglutide/',
			'compare_txt'       => 'Compare with Semaglutide →',
			'doses'             => [ '10mg', '20mg', '30mg', '40mg', '50mg' ],
			'supply_prices'     => [ 399.00, 599.00, 799.00 ],
			'warning_threshold' => 10,
			'pkg_dose_slugs'    => [ 'starter' => 'months-1-3-bundle', 'continuation' => 'months-4-6-bundle' ],
			'pkg_prices'        => [ 'starter' => 989.00, 'continuation' => 1149.00 ],
		],
		'compound-semaglutide' => [
			'badge'             => 'GLP-1 Receptor Agonist',
			'title'             => 'Semaglutide',
			'desc'              => 'Semaglutide activates GLP-1 receptors to reduce appetite and improve blood sugar control with once-weekly dosing.',
			'compare_url'       => '/product/compound-tirzepatide/',
			'compare_txt'       => 'Compare with Tirzepatide →',
			'doses'             => [ '1mg', '2mg', '4mg', '6mg', '10mg' ],
			'supply_prices'     => [ 285.00, 379.00, 479.00 ],
			'warning_threshold' => 1,
			'pkg_dose_slugs'    => [ 'starter' => 'months-1-3-bundle', 'continuation' => 'months-4-6-bundle' ],
			'pkg_prices'        => [ 'starter' => 549.00, 'continuation' => 799.00 ],
		],
	];
	$h = $hero[ $slug ];

	// Detect which attributes this product uses.
	// Production uses pa_individual-dose + pa_vial for both products.
	// Staging may use pa_dosage + pa_wm-bottle (tirzepatide) or pa_vial (semaglutide).
	// We normalize bottle counts to 1-bottle/2-bottle/3-bottle throughout so JS stays consistent.
	$attrs           = $product->get_attributes();
	$dose_attr_key   = isset( $attrs['pa_individual-dose'] ) ? 'pa_individual-dose' : 'pa_dosage';
	$dose_meta_key   = 'attribute_' . $dose_attr_key;
	$bottle_attr_key = isset( $attrs['pa_wm-bottle'] ) ? 'pa_wm-bottle' : 'pa_vial';
	$bottle_meta_key = 'attribute_' . $bottle_attr_key;
	$raw_to_norm     = [
		'1-vial' => '1-bottle', '2-vial' => '2-bottle', '3-vial' => '3-bottle',
		'1-bottle' => '1-bottle', '2-bottle' => '2-bottle', '3-bottle' => '3-bottle',
	];
	// JS uses this to map normalized keys back to the real WC attribute slug for the add-to-cart URL.
	$norm_to_raw = [
		'1-bottle' => $bottle_attr_key === 'pa_vial' ? '1-vial' : '1-bottle',
		'2-bottle' => $bottle_attr_key === 'pa_vial' ? '2-vial' : '2-bottle',
		'3-bottle' => $bottle_attr_key === 'pa_vial' ? '3-vial' : '3-bottle',
	];

	// Build price matrix { "10mg": { "1-bottle": 329.95, ... } } and variation map
	// { "10mg": { "1-bottle": { "1-month": 1234 } } } from live WC variation data.
	// Skip is_purchasable() — Prescribery plugin can return false for prescription products
	// even when prices are set, which would leave both maps empty.
	$price_matrix  = [];
	$variation_map = [];
	foreach ( $product->get_children() as $vid ) {
		$v = wc_get_product( $vid );
		if ( ! $v || 'publish' !== get_post_status( $vid ) ) continue;
		// Read slugs directly from post meta — get_attribute() returns term names, not slugs
		$dose       = get_post_meta( $vid, $dose_meta_key, true );
		$bottle_raw = get_post_meta( $vid, $bottle_meta_key, true );
		$bottle     = $raw_to_norm[ $bottle_raw ] ?? null; // null if not a recognized vial count
		$plan       = get_post_meta( $vid, 'attribute_pa_wm-subscription-plan', true );
		if ( ! $dose || ! $bottle ) continue;
		$price = (float) $v->get_price();
		if ( $price > 0 && ! isset( $price_matrix[ $dose ][ $bottle ] ) ) {
			$price_matrix[ $dose ][ $bottle ] = $price;
		}
		// Store with plan key if present; always store with '' key as fallback for products without plan attr.
		$variation_map[ $dose ][ $bottle ][ $plan ?: '' ] = (int) $vid;
	}

	// Detect package variations (Starter / Continuation) by their reserved dose slugs.
	// These are priced separately and excluded from the regular dose selector.
	$pkg_dose_slugs      = $h['pkg_dose_slugs'] ?? [];
	$pkg_prices          = $h['pkg_prices']      ?? [];
	$starter_var_id      = 0;
	$starter_price       = $pkg_prices['starter']      ?? 0;
	$continuation_var_id = 0;
	$continuation_price  = $pkg_prices['continuation'] ?? 0;
	foreach ( $product->get_children() as $vid ) {
		if ( 'publish' !== get_post_status( $vid ) ) continue;
		$dose = get_post_meta( $vid, $dose_meta_key, true );
		if ( ! empty( $pkg_dose_slugs['starter'] ) && $dose === $pkg_dose_slugs['starter'] ) {
			$starter_var_id = (int) $vid;
			$live_price     = (float) get_post_meta( $vid, '_price', true );
			if ( $live_price > 0 ) $starter_price = $live_price;
		}
		if ( ! empty( $pkg_dose_slugs['continuation'] ) && $dose === $pkg_dose_slugs['continuation'] ) {
			$continuation_var_id = (int) $vid;
			$live_price          = (float) get_post_meta( $vid, '_price', true );
			if ( $live_price > 0 ) $continuation_price = $live_price;
		}
	}

	// Derive available doses from the product's attribute terms (WP Admin order),
	// filtered to doses that have at least one published variation. Overrides the
	// hardcoded list so adding/removing a dose variant in WP Admin takes effect here.
	$dosage_terms = isset( $attrs[ $dose_attr_key ] ) ? ( $attrs[ $dose_attr_key ]->get_terms() ?: [] ) : [];
	$dose_labels  = [];
	foreach ( $dosage_terms as $t ) {
		$dose_labels[ $t->slug ] = $t->name; // e.g. "10-mg" => "10 mg"
	}
	$reserved_pkg_slugs = array_filter( array_values( $pkg_dose_slugs ) );
	$wc_doses = array_values( array_filter(
		array_map( fn( $t ) => $t->slug, $dosage_terms ),
		fn( $d ) => ( isset( $variation_map[ $d ] ) || isset( $price_matrix[ $d ] ) )
		         && ! in_array( $d, $reserved_pkg_slugs, true )
	) );
	// Sort numerically so "1-mg" < "2-mg" < "10-mg" regardless of WP term menu_order.
	usort( $wc_doses, fn( $a, $b ) => (float) $a - (float) $b );
	if ( ! empty( $wc_doses ) ) {
		$h['doses'] = $wc_doses;
	}

	// Supply prices for PHP-rendered buttons — WC data takes precedence, falls back to config defaults
	$first_dose = ! empty( $h['doses'] ) ? $h['doses'][0] : '';
	$sp         = [
		$price_matrix[ $first_dose ]['1-bottle'] ?? $h['supply_prices'][0],
		$price_matrix[ $first_dose ]['2-bottle'] ?? $h['supply_prices'][1],
		$price_matrix[ $first_dose ]['3-bottle'] ?? $h['supply_prices'][2],
	];

	// Use WC product image (falls back to nothing if unset)
	$image_id  = $product->get_image_id();
	$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';

	// Keep WC images hook removed — we render the product image ourselves
	remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );

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

<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'myogenix-pdp', $product ); ?>>

	<!-- Product Hero -->
	<section class="pdp-hero" id="buy">
		<div class="pdp-hero__inner">

			<!-- Left: product info + image -->
			<div class="pdp-hero__left">
				<span class="pdp-hero__badge"><?php echo esc_html( $h['badge'] ); ?></span>
				<h1 class="pdp-hero__title"><?php echo esc_html( $h['title'] ); ?></h1>
				<p class="pdp-hero__desc"><?php echo esc_html( $h['desc'] ); ?></p>
				<ul class="pdp-hero__bullets">
					<li>Compounded · FDA-registered facility</li>
					<li>Provider-reviewed</li>
				</ul>

				<?php if ( $image_url ) : ?>
				<div class="pdp-hero__image-card">
					<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $h['title'] ); ?>" loading="lazy" />
				</div>
				<?php endif; ?>

				<div class="pdp-hero__trust-grid">
					<div class="pdp-hero__trust-item">
						<span class="pdp-hero__trust-icon" aria-hidden="true">🏥</span>
						<div class="pdp-hero__trust-text">
							<strong>Licensed providers</strong>
							<span>Board-certified MDs</span>
						</div>
					</div>
					<div class="pdp-hero__trust-item">
						<span class="pdp-hero__trust-icon" aria-hidden="true">✏️</span>
						<div class="pdp-hero__trust-text">
							<strong>Compounded in USA</strong>
							<span>FDA-registered facility</span>
						</div>
					</div>
					<div class="pdp-hero__trust-item">
						<span class="pdp-hero__trust-icon" aria-hidden="true">🚚</span>
						<div class="pdp-hero__trust-text">
							<strong>Free shipping</strong>
							<span>Discreet packaging</span>
						</div>
					</div>
					<div class="pdp-hero__trust-item">
						<span class="pdp-hero__trust-icon" aria-hidden="true">💬</span>
						<div class="pdp-hero__trust-text">
							<strong>Ongoing support</strong>
							<span>Message your care team</span>
						</div>
					</div>
				</div>
				<a href="<?php echo esc_url( $h['compare_url'] ); ?>" class="pdp-hero__compare">
					<?php echo esc_html( $h['compare_txt'] ); ?>
				</a>
			</div>

			<!-- Right: configurator + hidden WC form -->
			<div class="pdp-hero__right">

				<!-- Hidden WC form — keeps variation JS alive for any plugin hooks -->
				<div class="pdp-hero__wc-hidden" aria-hidden="true" inert>
					<?php
					do_action( 'woocommerce_before_single_product_summary' );
					do_action( 'woocommerce_single_product_summary' );
					?>
				</div>

				<!-- Custom configurator -->
				<div id="pdp-cfg" class="pdp-cfg"
					data-doses="<?php echo esc_attr( wp_json_encode( $h['doses'] ) ); ?>"
					data-price-matrix="<?php echo esc_attr( wp_json_encode( $price_matrix ) ); ?>"
					data-variation-map="<?php echo esc_attr( wp_json_encode( $variation_map ) ); ?>"
					data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
					data-dose-attr="<?php echo esc_attr( $dose_meta_key ); ?>"
					data-dose-labels="<?php echo esc_attr( wp_json_encode( $dose_labels ) ); ?>"
					data-warning-threshold="<?php echo esc_attr( $h['warning_threshold'] ); ?>"
					data-bottle-attr="<?php echo esc_attr( $bottle_meta_key ); ?>"
					data-bottle-slug-map="<?php echo esc_attr( wp_json_encode( $norm_to_raw ) ); ?>"
					data-starter-variation-id="<?php echo esc_attr( $starter_var_id ); ?>"
					data-starter-price="<?php echo esc_attr( $starter_price ); ?>"
					data-starter-dose-slug="<?php echo esc_attr( $pkg_dose_slugs['starter'] ?? '' ); ?>"
					data-continuation-variation-id="<?php echo esc_attr( $continuation_var_id ); ?>"
					data-continuation-price="<?php echo esc_attr( $continuation_price ); ?>"
					data-continuation-dose-slug="<?php echo esc_attr( $pkg_dose_slugs['continuation'] ?? '' ); ?>"
				>
					<!-- Package Type -->
					<p class="pdp-cfg__section-label">Choose Your Package</p>
					<div class="pdp-cfg__pkg-row">
						<button class="pdp-cfg__pkg pdp-cfg__pkg--active" data-pkg="custom">
							<strong>Build Your Own</strong>
							<span>Choose your supply length &amp; customize monthly doses</span>
						</button>
						<button class="pdp-cfg__pkg" data-pkg="starter">
							<strong>Starter Pack</strong>
							<span>3-month supply &middot; Doses pre-set for months 1&ndash;3</span>
						</button>
						<button class="pdp-cfg__pkg" data-pkg="continuation">
							<strong>Continuation Package</strong>
							<span>3-month supply &middot; Doses pre-set for months 4&ndash;6</span>
						</button>
					</div>

					<!-- Supply Length -->
					<p class="pdp-cfg__section-label">Supply Length</p>
					<div class="pdp-cfg__supply-row">
						<button class="pdp-cfg__supply pdp-cfg__supply--active" data-months="1">
							<strong>1 Month</strong>
							<span class="pdp-cfg__supply-price"><?php echo $sp[0] ? '$' . number_format( $sp[0], 2 ) . '/mo' : ''; ?></span>
						</button>
						<button class="pdp-cfg__supply" data-months="2">
							<strong>2 Months</strong>
							<span class="pdp-cfg__supply-price"><?php echo $sp[1] ? '$' . number_format( $sp[1], 2 ) . '/mo' : ''; ?></span>
						</button>
						<button class="pdp-cfg__supply" data-months="3">
							<span class="pdp-cfg__popular-tag">POPULAR</span>
							<strong>3 Months</strong>
							<span class="pdp-cfg__supply-price"><?php echo $sp[2] ? '$' . number_format( $sp[2], 2 ) . '/3mo' : ''; ?></span>
						</button>
					</div>

					<!-- Dose Selector -->
					<p class="pdp-cfg__section-label" id="pdp-dose-label">Month 1 Dose</p>
					<div id="pdp-dose" class="pdp-cfg__doses-wrap"></div>

					<!-- Order Summary -->
					<div id="pdp-summary" class="pdp-cfg__summary"></div>

					<!-- CTA -->
					<button id="pdp-cta" class="pdp-cfg__cta">Go to Checkout &rarr;</button>
					<p id="pdp-disclaimer" class="pdp-cfg__disclaimer">
						This is a one-time purchase. Your order will be reviewed by a licensed provider before processing.
					</p>
				</div>

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
					<p class="myogenix-pdp__cq-sub">Everything you need to know about the program, dosing, and ordering.</p>
					<a href="#buy" class="myogenix-pdp__cq-btn">Configure your program &rarr;</a>
				</div>

				<div class="myogenix-pdp__cq-right">
					<div class="myogenix-pdp__cq-list">

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="true" aria-controls="myogenix-cq-0">
								<span>What is tirzepatide and how is it different from semaglutide?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer is-open" id="myogenix-cq-0">
								<p>Tirzepatide is a dual GIP/GLP-1 receptor agonist — it activates two metabolic pathways simultaneously. Semaglutide only activates the GLP-1 receptor. Clinical trials (SURMOUNT-1) show tirzepatide produced significantly greater average weight loss than semaglutide across comparable doses.</p>
							</div>
						</div>

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="false" aria-controls="myogenix-cq-1">
								<span>How does dosing escalation work?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer" id="myogenix-cq-1">
								<p>We start most patients at 2.5 mg weekly to minimize side effects, then increase by 2.5 mg every 4 weeks based on your tolerance and progress. Typical escalation runs 2.5 → 5 → 7.5 → 10 → 12.5 → 15 mg. Your provider reviews your response at each stage and adjusts accordingly — you're never escalated faster than is clinically appropriate.</p>
							</div>
						</div>

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="false" aria-controls="myogenix-cq-2">
								<span>Do I need a new consultation every month?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer" id="myogenix-cq-2">
								<p>No. Once your treatment plan is established and your prescription is active, refills ship automatically on your chosen schedule. Your provider may request a brief check-in every few months to review progress and adjust dosing if needed — but there's no new full consultation required for standard refills.</p>
							</div>
						</div>

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="false" aria-controls="myogenix-cq-3">
								<span>What if my provider adjusts my dose?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer" id="myogenix-cq-3">
								<p>If a dose adjustment is needed, your provider will update your prescription and your next shipment will automatically reflect the new dosage. You'll be notified through your patient portal. No additional consultation or fee is required for dose changes within your active treatment plan.</p>
							</div>
						</div>

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="false" aria-controls="myogenix-cq-4">
								<span>How is this compounded and where does it ship from?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer" id="myogenix-cq-4">
								<p>Your tirzepatide is compounded by a licensed U.S. FDA-registered 503A compounding pharmacy. It's prepared as a sterile injectable solution and ships directly to your door in temperature-controlled packaging — along with syringes and all necessary supplies.</p>
							</div>
						</div>

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="false" aria-controls="myogenix-cq-5">
								<span>Can I cancel my subscription?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer" id="myogenix-cq-5">
								<p>Yes. You can pause or cancel your subscription at any time through your Myogenix Pharma patient portal or by contacting our support team. We ask for at least 5 business days' notice before your next billing date. There are no long-term contracts or cancellation fees.</p>
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

<?php do_action( 'woocommerce_after_single_product' ); ?>

<?php else : // All other products — default WooCommerce output ?>

<div id="product-<?php the_ID(); ?>" <?php wc_product_class( '', $product ); ?>>

	<?php
	/**
	 * Hook: woocommerce_before_single_product_summary.
	 * @hooked woocommerce_show_product_sale_flash - 10
	 * @hooked woocommerce_show_product_images - 20
	 */
	do_action( 'woocommerce_before_single_product_summary' );
	?>

	<div class="summary entry-summary">
		<?php
		/**
		 * Hook: woocommerce_single_product_summary.
		 * @hooked woocommerce_template_single_title - 5
		 * @hooked woocommerce_template_single_rating - 10
		 * @hooked woocommerce_template_single_price - 10
		 * @hooked woocommerce_template_single_excerpt - 20
		 * @hooked woocommerce_template_single_add_to_cart - 30  ← variations + subscriptions attach here
		 * @hooked woocommerce_template_single_meta - 40
		 * @hooked woocommerce_template_single_sharing - 50
		 * @hooked WC_Structured_Data::generate_product_data() - 60
		 */
		do_action( 'woocommerce_single_product_summary' );
		?>
	</div>

	<?php
	/**
	 * Hook: woocommerce_after_single_product_summary.
	 * @hooked woocommerce_output_product_data_tabs - 10
	 * @hooked woocommerce_upsell_display - 15
	 * @hooked woocommerce_output_related_products - 20
	 */
	do_action( 'woocommerce_after_single_product_summary' );
	?>

</div>

<?php do_action( 'woocommerce_after_single_product' ); ?>

<?php endif; ?>
