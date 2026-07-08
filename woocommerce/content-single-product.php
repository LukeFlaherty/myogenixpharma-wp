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

$peptide_slugs = [
	'bpc', 'motsc', 'epithalon', 'compound-injectable-nad',
	'tesamorelin-ipamorelin', 'cjc1295-ipamorelin',
	'klow-stack-bpc157-10mg-ghk-cu-50mg-tb50010mg-kpv-10mg',
	'2606', 'compound-injectable-sermorelin', 'compound-injectable-glutathione',
];
$is_peptide = in_array( $slug, $peptide_slugs, true );

$sexual_health_slugs = [ 'compound-oral-tadalafil', 'compound-sildenafil', 'testosterone' ];
$is_sexual_health    = in_array( $slug, $sexual_health_slugs, true );

// Suppress the "Please choose product options" notice on our custom PDPs —
// it fires from the hidden WC form and confuses customers.
if ( $is_weight_loss || $is_peptide || $is_sexual_health ) {
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
	<section class="myo-faq">
		<div class="myo-faq__wrap">

			<div class="myo-faq__header">
				<p class="myo-faq__eyebrow">FAQ</p>
				<h2 class="myo-faq__title">Common questions</h2>
				<p class="myo-faq__desc">Everything you need to know about the program, dosing, and ordering.</p>
			</div>

			<div class="myo-faq__list">

				<div class="myo-faq__item">
					<button class="myo-faq__btn" type="button" aria-expanded="false" aria-controls="wm-faq-0">
						<span class="myo-faq__q">What is tirzepatide and how is it different from semaglutide?</span>
						<span class="myo-faq__icon" aria-hidden="true"></span>
					</button>
					<div class="myo-faq__panel" id="wm-faq-0">
						<div class="myo-faq__panel-inner">
							<p>Tirzepatide is a dual GIP/GLP-1 receptor agonist — it activates two metabolic pathways simultaneously. Semaglutide only activates the GLP-1 receptor. Clinical trials (SURMOUNT-1) show tirzepatide produced significantly greater average weight loss than semaglutide across comparable doses.</p>
						</div>
					</div>
				</div>

				<div class="myo-faq__item">
					<button class="myo-faq__btn" type="button" aria-expanded="false" aria-controls="wm-faq-1">
						<span class="myo-faq__q">How does dosing escalation work?</span>
						<span class="myo-faq__icon" aria-hidden="true"></span>
					</button>
					<div class="myo-faq__panel" id="wm-faq-1">
						<div class="myo-faq__panel-inner">
							<p>We start most patients at 2.5 mg weekly to minimize side effects, then increase by 2.5 mg every 4 weeks based on your tolerance and progress. Typical escalation runs 2.5 → 5 → 7.5 → 10 → 12.5 → 15 mg. Your provider reviews your response at each stage and adjusts accordingly — you're never escalated faster than is clinically appropriate.</p>
						</div>
					</div>
				</div>

				<div class="myo-faq__item">
					<button class="myo-faq__btn" type="button" aria-expanded="false" aria-controls="wm-faq-2">
						<span class="myo-faq__q">Do I need a new consultation every month?</span>
						<span class="myo-faq__icon" aria-hidden="true"></span>
					</button>
					<div class="myo-faq__panel" id="wm-faq-2">
						<div class="myo-faq__panel-inner">
							<p>No. Once your treatment plan is established and your prescription is active, refills ship automatically on your chosen schedule. Your provider may request a brief check-in every few months to review progress and adjust dosing if needed — but there's no new full consultation required for standard refills.</p>
						</div>
					</div>
				</div>

				<div class="myo-faq__item">
					<button class="myo-faq__btn" type="button" aria-expanded="false" aria-controls="wm-faq-3">
						<span class="myo-faq__q">What if my provider adjusts my dose?</span>
						<span class="myo-faq__icon" aria-hidden="true"></span>
					</button>
					<div class="myo-faq__panel" id="wm-faq-3">
						<div class="myo-faq__panel-inner">
							<p>If a dose adjustment is needed, your provider will update your prescription and your next shipment will automatically reflect the new dosage. You'll be notified through your patient portal. No additional consultation or fee is required for dose changes within your active treatment plan.</p>
						</div>
					</div>
				</div>

				<div class="myo-faq__item">
					<button class="myo-faq__btn" type="button" aria-expanded="false" aria-controls="wm-faq-4">
						<span class="myo-faq__q">How is this compounded and where does it ship from?</span>
						<span class="myo-faq__icon" aria-hidden="true"></span>
					</button>
					<div class="myo-faq__panel" id="wm-faq-4">
						<div class="myo-faq__panel-inner">
							<p>Your tirzepatide is compounded by a licensed U.S. FDA-registered 503A compounding pharmacy. It's prepared as a sterile injectable solution and ships directly to your door in temperature-controlled packaging — along with syringes and all necessary supplies.</p>
						</div>
					</div>
				</div>

				<div class="myo-faq__item">
					<button class="myo-faq__btn" type="button" aria-expanded="false" aria-controls="wm-faq-5">
						<span class="myo-faq__q">Can I cancel my subscription?</span>
						<span class="myo-faq__icon" aria-hidden="true"></span>
					</button>
					<div class="myo-faq__panel" id="wm-faq-5">
						<div class="myo-faq__panel-inner">
							<p>Yes. You can pause or cancel your subscription at any time through your Myogenix Pharma patient portal or by contacting our support team. We ask for at least 5 business days' notice before your next billing date. There are no long-term contracts or cancellation fees.</p>
						</div>
					</div>
				</div>

			</div>

			<div class="myo-faq__cta">
				<a href="#buy" class="myo-faq__cta-btn">Configure your program &rarr;</a>
			</div>

		</div>
	</section>

	<!-- Explore More Treatment Lines -->
	<section class="myogenix-pdp__explore">
		<div class="myogenix-pdp__container">
			<h2 class="myogenix-pdp__section-heading">Explore More Treatment Lines</h2>
			<p class="myogenix-pdp__section-sub">Provider-reviewed programs for every health goal.</p>
			<?php myogenix_render_product_scrollers( [ 'weight-loss', 'peptides' ], $product->get_id() ); ?>
		</div>
	</section>

</div>

<?php do_action( 'woocommerce_after_single_product' ); ?>

<?php elseif ( $is_peptide ) :

	// ─── Per-product config ────────────────────────────────────────────────────
	$peptide_config = [
		'bpc' => [
			'name'  => 'BPC-157',
			'badge' => 'Tissue &amp; Joint Recovery',
			'desc'  => 'BPC-157 is a synthetic peptide derived from a protective stomach protein, studied for its regenerative effects on tissue repair, joint health, and gut function.',
			'spec'  => 'BPC-157 · 3mg/ml · 5ml per vial',
		],
		'motsc' => [
			'name'  => 'MOTSc',
			'badge' => 'Mitochondrial Peptide',
			'desc'  => 'MOTSc is a mitochondrial-derived peptide that activates AMPK pathways, supporting energy metabolism, insulin sensitivity, and cellular resilience.',
			'spec'  => 'MOTSc · 2mg/ml · 5ml per vial',
		],
		'epithalon' => [
			'name'  => 'Epithalon',
			'badge' => 'Longevity Peptide',
			'desc'  => 'Epithalon is a tetrapeptide that stimulates telomerase activity and regulates the pineal gland, supporting healthy aging and cellular longevity.',
			'spec'  => 'Epithalon · 2mg/ml · 5ml per vial',
		],
		'compound-injectable-nad' => [
			'name'  => 'NAD+',
			'badge' => 'Cellular Energy Support',
			'desc'  => 'NAD+ is a critical coenzyme involved in cellular energy production, DNA repair, and sirtuin activation — supporting cognitive function, metabolism, and anti-aging pathways.',
			'spec'  => 'NAD+ · 100mg/ml · 10ml per vial',
		],
		'tesamorelin-ipamorelin' => [
			'name'  => 'Tesamorelin / Ipamorelin',
			'badge' => 'GH Secretagogue Blend',
			'desc'  => 'A dual-action blend combining Tesamorelin (a GHRH analogue) with Ipamorelin (a GHRP), designed to pulse growth hormone release naturally and support lean body composition.',
			'spec'  => 'Tesamorelin 3mg + Ipamorelin 2mg · 5ml per vial',
		],
		'cjc1295-ipamorelin' => [
			'name'  => 'CJC-1295 / Ipamorelin',
			'badge' => 'GH Secretagogue Blend',
			'desc'  => 'CJC-1295 extends the half-life of natural GH pulses while Ipamorelin provides a clean GH release — a popular stack for muscle recovery, fat loss, and sleep quality.',
			'spec'  => 'CJC-1295 1.2mg + Ipamorelin 2mg · 5ml per vial',
		],
		'klow-stack-bpc157-10mg-ghk-cu-50mg-tb50010mg-kpv-10mg' => [
			'name'  => 'KLOW Stack',
			'badge' => 'Recovery Peptide Stack',
			'desc'  => 'The KLOW Stack combines BPC-157, GHK-Cu, TB-500, and KPV in a single vial — a comprehensive recovery peptide blend targeting tissue repair, inflammation, and systemic healing.',
			'spec'  => 'BPC-157 3mg / GHK-Cu 10mg / TB-500 3mg / KPV 3mg · 5ml per vial',
		],
		'2606' => [
			'name'  => 'Wolverine Stack',
			'badge' => 'Recovery Peptide Stack',
			'desc'  => 'The Wolverine Stack pairs BPC-157 with TB-500 for accelerated recovery and tissue regeneration — a go-to protocol for musculoskeletal injuries and chronic inflammation.',
			'spec'  => 'BPC-157 3mg + TB-500 3mg · 5ml per vial',
		],
		'compound-injectable-sermorelin' => [
			'name'  => 'Sermorelin',
			'badge' => 'Growth Hormone Secretagogue',
			'desc'  => 'Sermorelin is a synthetic analogue of GHRH that stimulates natural growth hormone production, supporting sleep quality, lean mass, recovery, and metabolic health.',
			'spec'  => 'Sermorelin · 10mg per vial',
		],
		'compound-injectable-glutathione' => [
			'name'  => 'Glutathione',
			'badge' => 'Master Antioxidant Therapy',
			'desc'  => 'Glutathione is the body\'s master antioxidant, critical for oxidative stress management, immune function, and liver detoxification. Delivered as a sterile injectable for maximum bioavailability.',
			'spec'  => 'Glutathione · 200mg/ml · 10ml per vial',
		],
	];
	$pcfg = $peptide_config[ $slug ];

	// ─── Build supply map from live WC variation data ─────────────────────────
	// Detect which supply attribute this product uses (pa_vial-wellness or pa_bottle).
	$attrs           = $product->get_attributes();
	$supply_attr_key = isset( $attrs['pa_vial-wellness'] ) ? 'pa_vial-wellness' : 'pa_bottle';
	$supply_meta_key = 'attribute_' . $supply_attr_key;

	$supply_label_map = [
		'1-vial'   => '1 Vial',    '2-vial'   => '2 Vials',    '3-vial'   => '3 Vials',
		'1-bottle' => '1 Bottle',  '2-bottle' => '2 Bottles',  '3-bottle' => '3 Bottles',
	];
	$supply_qty_map = [
		'1-vial' => 1, '1-bottle' => 1,
		'2-vial' => 2, '2-bottle' => 2,
		'3-vial' => 3, '3-bottle' => 3,
	];
	$supply_order = [
		'1-vial' => 1, '1-bottle' => 1,
		'2-vial' => 2, '2-bottle' => 2,
		'3-vial' => 3, '3-bottle' => 3,
	];

	$supply_map = [];
	foreach ( $product->get_children() as $vid ) {
		$v = wc_get_product( $vid );
		if ( ! $v || 'publish' !== get_post_status( $vid ) ) continue;
		$supply_slug = get_post_meta( $vid, $supply_meta_key, true );
		if ( ! $supply_slug ) continue;
		$price = (float) $v->get_price();
		if ( $price > 0 && ! isset( $supply_map[ $supply_slug ] ) ) {
			$supply_map[ $supply_slug ] = [
				'id'    => (int) $vid,
				'price' => $price,
				'label' => $supply_label_map[ $supply_slug ] ?? $supply_slug,
				'qty'   => $supply_qty_map[ $supply_slug ] ?? 1,
			];
		}
	}
	uksort( $supply_map, fn( $a, $b ) => ( $supply_order[ $a ] ?? 99 ) - ( $supply_order[ $b ] ?? 99 ) );

	$supply_keys         = array_keys( $supply_map );
	$last_supply         = ! empty( $supply_keys ) ? end( $supply_keys ) : '';
	$single_supply_price = ! empty( $supply_keys ) ? ( $supply_map[ $supply_keys[0] ]['price'] ?? 0 ) : 0;

	// ─── Image ────────────────────────────────────────────────────────────────
	$image_id  = $product->get_image_id();
	$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';

	remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );

	$img_url = function( $path ) {
		$base  = get_stylesheet_directory_uri() . '/assets/images/';
		$parts = explode( '/', $path );
		return esc_url( $base . implode( '/', array_map( 'rawurlencode', $parts ) ) );
	};

	$steps = [
		[ 'num' => 'PDP Sections/1.png', 'img' => 'PDP Sections/form.png',         'title' => 'Questionnaire',                  'desc' => 'Answer a few questions and share your medical details' ],
		[ 'num' => 'PDP Sections/2.png', 'img' => 'PDP Sections/consultation.png', 'title' => 'Review and Approved by provider',  'desc' => 'Discuss your goals and receive expert recommendations' ],
		[ 'num' => 'PDP Sections/3.png', 'img' => 'PDP Sections/box.png',          'title' => 'Receive medication',               'desc' => 'Medication and supplies shipped straight to your door' ],
		[ 'num' => 'PDP Sections/4.png', 'img' => 'PDP Sections/calendar.png',     'title' => 'Monthly Monitoring',               'desc' => 'Stay on track with regular free check-ins to ensure progress' ],
	];

?>

<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'myogenix-pdp peptide-pdp', $product ); ?>>

	<!-- Product Hero -->
	<section class="pdp-hero" id="buy">
		<div class="pdp-hero__inner">

			<div class="pdp-hero__left">
				<span class="pdp-hero__badge"><?php echo $pcfg['badge']; ?></span>
				<h1 class="pdp-hero__title"><?php echo esc_html( $pcfg['name'] ); ?></h1>
				<p class="pdp-hero__desc"><?php echo esc_html( $pcfg['desc'] ); ?></p>
				<ul class="pdp-hero__bullets">
					<li>Compounded &middot; FDA-registered facility</li>
					<li>Provider-reviewed &middot; Prescription required</li>
				</ul>

				<?php if ( $image_url ) : ?>
				<div class="pdp-hero__image-card">
					<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $pcfg['name'] ); ?>" loading="lazy" />
				</div>
				<?php endif; ?>

				<div class="pdp-hero__trust-grid">
					<div class="pdp-hero__trust-item">
						<span class="pdp-hero__trust-icon" aria-hidden="true">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
						</span>
						<div class="pdp-hero__trust-text"><strong>Licensed providers</strong><span>Board-certified MDs</span></div>
					</div>
					<div class="pdp-hero__trust-item">
						<span class="pdp-hero__trust-icon" aria-hidden="true">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/></svg>
						</span>
						<div class="pdp-hero__trust-text"><strong>Compounded in USA</strong><span>FDA-registered facility</span></div>
					</div>
					<div class="pdp-hero__trust-item">
						<span class="pdp-hero__trust-icon" aria-hidden="true">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
						</span>
						<div class="pdp-hero__trust-text"><strong>Free shipping</strong><span>Discreet packaging</span></div>
					</div>
					<div class="pdp-hero__trust-item">
						<span class="pdp-hero__trust-icon" aria-hidden="true">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
						</span>
						<div class="pdp-hero__trust-text"><strong>Ongoing support</strong><span>Message your care team</span></div>
					</div>
				</div>
			</div>

			<div class="pdp-hero__right">

				<!-- Hidden WC form — keeps variation hooks alive for plugins -->
				<div class="pdp-hero__wc-hidden" aria-hidden="true" inert>
					<?php
					do_action( 'woocommerce_before_single_product_summary' );
					do_action( 'woocommerce_single_product_summary' );
					?>
				</div>

				<!-- Peptide configurator -->
				<div id="pdp-cfg" class="pdp-cfg"
					data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
					data-supply-map="<?php echo esc_attr( wp_json_encode( $supply_map ) ); ?>"
					data-supply-attr="<?php echo esc_attr( $supply_meta_key ); ?>"
				>
					<p class="pdp-cfg__section-label">Supply</p>
					<div class="pdp-cfg__supply-row">
						<?php
						$is_first = true;
						foreach ( $supply_map as $s_slug => $s_entry ) :
						?>
						<button class="pdp-cfg__supply<?php echo $is_first ? ' pdp-cfg__supply--active' : ''; ?>"
							data-supply="<?php echo esc_attr( $s_slug ); ?>">
							<?php if ( $s_slug === $last_supply && count( $supply_map ) > 1 ) : ?>
							<span class="pdp-cfg__popular-tag">POPULAR</span>
							<?php endif; ?>
							<strong><?php echo esc_html( $s_entry['label'] ); ?></strong>
						</button>
						<?php
						$is_first = false;
						endforeach;
						?>
					</div>

					<div class="peptide-cfg__includes">
						<p class="peptide-cfg__includes-title">What's included</p>
						<ul class="peptide-cfg__includes-list">
							<li class="peptide-cfg__includes-item">
								<span class="peptide-cfg__includes-icon" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
								<?php echo esc_html( $pcfg['spec'] ); ?>
							</li>
							<li class="peptide-cfg__includes-item">
								<span class="peptide-cfg__includes-icon" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
								Syringes &amp; needles
							</li>
							<li class="peptide-cfg__includes-item">
								<span class="peptide-cfg__includes-icon" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
								Alcohol prep pads
							</li>
							<li class="peptide-cfg__includes-item">
								<span class="peptide-cfg__includes-icon" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
								Dosing protocol card
							</li>
						</ul>
					</div>

					<div id="peptide-summary" class="pdp-cfg__summary"></div>

					<button id="pdp-cta" class="pdp-cfg__cta">Go to Checkout &rarr;</button>
					<p id="pdp-disclaimer" class="pdp-cfg__disclaimer">
						One-time purchase. Order reviewed by a licensed provider before processing.
					</p>

				</div>
			</div>
		</div>
	</section>

	<!-- 4 Steps -->
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

	<!-- Common Questions -->
	<section class="myo-faq">
		<div class="myo-faq__wrap">

			<div class="myo-faq__header">
				<p class="myo-faq__eyebrow">FAQ</p>
				<h2 class="myo-faq__title">Common questions</h2>
				<p class="myo-faq__desc">Everything you need to know about peptide therapy, ordering, and what to expect.</p>
			</div>

			<div class="myo-faq__list">

				<div class="myo-faq__item">
					<button class="myo-faq__btn" type="button" aria-expanded="false" aria-controls="pep-faq-0">
						<span class="myo-faq__q">How is this peptide administered?</span>
						<span class="myo-faq__icon" aria-hidden="true"></span>
					</button>
					<div class="myo-faq__panel" id="pep-faq-0">
						<div class="myo-faq__panel-inner">
							<p>Your peptide is formulated as a sterile injectable solution and administered subcutaneously (under the skin) using a small insulin-style syringe. All necessary supplies — syringes, bacteriostatic water where required, and alcohol swabs — are included with your order.</p>
						</div>
					</div>
				</div>

				<div class="myo-faq__item">
					<button class="myo-faq__btn" type="button" aria-expanded="false" aria-controls="pep-faq-1">
						<span class="myo-faq__q">How long until I see results?</span>
						<span class="myo-faq__icon" aria-hidden="true"></span>
					</button>
					<div class="myo-faq__panel" id="pep-faq-1">
						<div class="myo-faq__panel-inner">
							<p>Results vary by compound and individual. Tissue-repair peptides like BPC-157 and the Wolverine Stack often show faster response (2–4 weeks). Longer-acting agents like MOTSc, Epithalon, and Sermorelin may require 4–8 weeks of consistent use for the full effect to become apparent.</p>
						</div>
					</div>
				</div>

				<div class="myo-faq__item">
					<button class="myo-faq__btn" type="button" aria-expanded="false" aria-controls="pep-faq-2">
						<span class="myo-faq__q">Why is a provider review required before my order ships?</span>
						<span class="myo-faq__icon" aria-hidden="true"></span>
					</button>
					<div class="myo-faq__panel" id="pep-faq-2">
						<div class="myo-faq__panel-inner">
							<p>We operate as a licensed telehealth clinic, not a supplement retailer. Every order is reviewed by a board-certified provider who confirms the compound and dose are appropriate for you before anything ships. This protects your safety and ensures you receive the correct protocol.</p>
						</div>
					</div>
				</div>

				<div class="myo-faq__item">
					<button class="myo-faq__btn" type="button" aria-expanded="false" aria-controls="pep-faq-3">
						<span class="myo-faq__q">How do I store my peptide vials?</span>
						<span class="myo-faq__icon" aria-hidden="true"></span>
					</button>
					<div class="myo-faq__panel" id="pep-faq-3">
						<div class="myo-faq__panel-inner">
							<p>Lyophilized (dry) peptides can be stored at room temperature short-term or refrigerated for longer shelf life. After reconstitution with bacteriostatic water, store refrigerated at 2–8&deg;C and use within 28 days. Never freeze reconstituted peptides — freezing degrades peptide integrity.</p>
						</div>
					</div>
				</div>

				<div class="myo-faq__item">
					<button class="myo-faq__btn" type="button" aria-expanded="false" aria-controls="pep-faq-4">
						<span class="myo-faq__q">How is this compounded and where does it ship from?</span>
						<span class="myo-faq__icon" aria-hidden="true"></span>
					</button>
					<div class="myo-faq__panel" id="pep-faq-4">
						<div class="myo-faq__panel-inner">
							<p>Your peptide is compounded by a licensed U.S. FDA-registered 503A compounding pharmacy as a sterile injectable solution. It ships directly to your door in temperature-controlled, discreet packaging with all necessary supplies included.</p>
						</div>
					</div>
				</div>

			</div>

			<div class="myo-faq__cta">
				<a href="#buy" class="myo-faq__cta-btn">Order now &rarr;</a>
			</div>

		</div>
	</section>

	<!-- Explore More Treatment Lines -->
	<section class="myogenix-pdp__explore">
		<div class="myogenix-pdp__container">
			<h2 class="myogenix-pdp__section-heading">Explore More Treatment Lines</h2>
			<p class="myogenix-pdp__section-sub">Provider-reviewed programs for every health goal.</p>
			<?php myogenix_render_product_scrollers( [ 'peptides' ], $product->get_id() ); ?>
		</div>
	</section>

</div>

<?php do_action( 'woocommerce_after_single_product' ); ?>

<?php elseif ( $is_sexual_health ) :

	// ─── Per-product config ────────────────────────────────────────────────────
	$sexual_health_config = [
		'compound-oral-tadalafil' => [
			'name'            => 'Tadalafil',
			'badge'           => 'Sexual Health',
			'desc'            => 'Tadalafil (generic Cialis) is a PDE5 inhibitor prescribed for erectile dysfunction and benign prostatic hyperplasia. It provides long-lasting support — up to 36 hours — and is available as a lower-dose daily option.',
			'includes'        => [
				'90 compounded oral tablets',
				'Dosing protocol card',
			],
			'primary_attr'    => 'pa_dosage',
			'primary_label'   => 'Select Dosage',
			'secondary_attr'  => null,
			'secondary_label' => null,
			'fixed_attrs'     => [ 'attribute_pa_tablets' => '90-tablets' ],
		],
		'compound-sildenafil' => [
			'name'            => 'Sildenafil',
			'badge'           => 'Sexual Health',
			'desc'            => 'Sildenafil (generic Viagra) is a PDE5 inhibitor that increases blood flow to support erections when sexually stimulated. Fast-acting, widely studied, and available in multiple strengths.',
			'includes'        => [
				'Compounded oral sildenafil tablets',
				'Dosing protocol card',
			],
			'primary_attr'    => 'pa_dosage',
			'primary_label'   => 'Select Dosage',
			'secondary_attr'  => 'pa_tablets',
			'secondary_label' => 'Supply Length',
			'fixed_attrs'     => [],
		],
		'testosterone' => [
			'name'            => 'Testosterone Cypionate',
			'badge'           => 'Hormone Therapy',
			'desc'            => 'Testosterone Cypionate is a long-acting injectable testosterone used to treat hypogonadism (low T). It supports energy levels, muscle mass, libido, mood, and overall wellbeing.',
			'includes'        => [
				'Testosterone Cypionate injectable &middot; multi-dose vial',
				'Syringes &amp; needles',
				'Alcohol prep pads',
				'Dosing protocol card',
			],
			'primary_attr'    => 'pa_subscription-plan',
			'primary_label'   => 'Subscription Plan',
			'secondary_attr'  => null,
			'secondary_label' => null,
			'fixed_attrs'     => [],
		],
	];
	$shcfg = $sexual_health_config[ $slug ];

	// ─── Build variation matrix from live WC data ──────────────────────────────
	$attrs            = $product->get_attributes();
	$primary_attr_key   = 'attribute_' . $shcfg['primary_attr'];
	$secondary_attr_key = $shcfg['secondary_attr'] ? 'attribute_' . $shcfg['secondary_attr'] : null;

	// Build label maps from WC attribute terms
	$primary_labels   = [];
	$secondary_labels = [];
	if ( isset( $attrs[ $shcfg['primary_attr'] ] ) ) {
		foreach ( $attrs[ $shcfg['primary_attr'] ]->get_terms() ?: [] as $t ) {
			$primary_labels[ $t->slug ] = $t->name;
		}
	}
	if ( $shcfg['secondary_attr'] && isset( $attrs[ $shcfg['secondary_attr'] ] ) ) {
		foreach ( $attrs[ $shcfg['secondary_attr'] ]->get_terms() ?: [] as $t ) {
			$secondary_labels[ $t->slug ] = $t->name;
		}
	}

	// Build variation matrix: 1D { primary_slug: {id, price} } or 2D { primary_slug: { secondary_slug: {id, price} } }
	$variation_matrix = [];
	foreach ( $product->get_children() as $vid ) {
		$v = wc_get_product( $vid );
		if ( ! $v || 'publish' !== get_post_status( $vid ) ) continue;
		$primary_slug = get_post_meta( $vid, $primary_attr_key, true );
		if ( ! $primary_slug ) continue;
		$price = (float) $v->get_price();
		if ( $price <= 0 ) continue;

		if ( $secondary_attr_key ) {
			$secondary_slug = get_post_meta( $vid, $secondary_attr_key, true );
			if ( ! $secondary_slug ) continue;
			if ( ! isset( $variation_matrix[ $primary_slug ][ $secondary_slug ] ) ) {
				$variation_matrix[ $primary_slug ][ $secondary_slug ] = [
					'id'    => (int) $vid,
					'price' => $price,
				];
			}
		} else {
			if ( ! isset( $variation_matrix[ $primary_slug ] ) ) {
				$variation_matrix[ $primary_slug ] = [
					'id'    => (int) $vid,
					'price' => $price,
				];
			}
		}
	}

	// Sort primary keys by term menu_order (preserves WP Admin ordering)
	$primary_order = array_keys( $primary_labels );
	uksort( $variation_matrix, function ( $a, $b ) use ( $primary_order ) {
		return array_search( $a, $primary_order ) - array_search( $b, $primary_order );
	} );

	$primary_keys   = array_keys( $variation_matrix );
	$secondary_keys = $secondary_attr_key && ! empty( $primary_keys )
		? array_keys( $variation_matrix[ $primary_keys[0] ] )
		: [];
	$last_secondary = ! empty( $secondary_keys ) ? end( $secondary_keys ) : '';

	// ─── Image ────────────────────────────────────────────────────────────────
	$image_id  = $product->get_image_id();
	$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';

	remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );

	$img_url = function( $path ) {
		$base  = get_stylesheet_directory_uri() . '/assets/images/';
		$parts = explode( '/', $path );
		return esc_url( $base . implode( '/', array_map( 'rawurlencode', $parts ) ) );
	};

	$steps = [
		[ 'num' => 'PDP Sections/1.png', 'img' => 'PDP Sections/form.png',         'title' => 'Questionnaire',                  'desc' => 'Answer a few questions and share your medical details' ],
		[ 'num' => 'PDP Sections/2.png', 'img' => 'PDP Sections/consultation.png', 'title' => 'Review and Approved by provider',  'desc' => 'Discuss your goals and receive expert recommendations' ],
		[ 'num' => 'PDP Sections/3.png', 'img' => 'PDP Sections/box.png',          'title' => 'Receive medication',               'desc' => 'Medication and supplies shipped straight to your door' ],
		[ 'num' => 'PDP Sections/4.png', 'img' => 'PDP Sections/calendar.png',     'title' => 'Monthly Monitoring',               'desc' => 'Stay on track with regular free check-ins to ensure progress' ],
	];

	// ─── TRT state gating — 48 states per Myogenix service policy ───────────────
	// Alaska (AK) and Mississippi (MS) excluded. Update this list as coverage changes.
	$trt_allowed_states = [
		'AL',       'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA',
		'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD',
		'MA', 'MI', 'MN',       'MO', 'MT', 'NE', 'NV', 'NH', 'NJ',
		'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC',
		'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY',
	];
	$all_us_states = [
		'AL' => 'Alabama',        'AK' => 'Alaska',         'AZ' => 'Arizona',       'AR' => 'Arkansas',
		'CA' => 'California',     'CO' => 'Colorado',       'CT' => 'Connecticut',   'DE' => 'Delaware',
		'FL' => 'Florida',        'GA' => 'Georgia',        'HI' => 'Hawaii',        'ID' => 'Idaho',
		'IL' => 'Illinois',       'IN' => 'Indiana',        'IA' => 'Iowa',          'KS' => 'Kansas',
		'KY' => 'Kentucky',       'LA' => 'Louisiana',      'ME' => 'Maine',         'MD' => 'Maryland',
		'MA' => 'Massachusetts',  'MI' => 'Michigan',       'MN' => 'Minnesota',     'MS' => 'Mississippi',
		'MO' => 'Missouri',       'MT' => 'Montana',        'NE' => 'Nebraska',      'NV' => 'Nevada',
		'NH' => 'New Hampshire',  'NJ' => 'New Jersey',     'NM' => 'New Mexico',    'NY' => 'New York',
		'NC' => 'North Carolina', 'ND' => 'North Dakota',   'OH' => 'Ohio',          'OK' => 'Oklahoma',
		'OR' => 'Oregon',         'PA' => 'Pennsylvania',   'RI' => 'Rhode Island',  'SC' => 'South Carolina',
		'SD' => 'South Dakota',   'TN' => 'Tennessee',      'TX' => 'Texas',         'UT' => 'Utah',
		'VT' => 'Vermont',        'VA' => 'Virginia',       'WA' => 'Washington',    'WV' => 'West Virginia',
		'WI' => 'Wisconsin',      'WY' => 'Wyoming',
	];

?>

<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'myogenix-pdp sexual-health-pdp', $product ); ?>>

	<!-- Product Hero -->
	<section class="pdp-hero" id="buy">
		<div class="pdp-hero__inner">

			<div class="pdp-hero__left">
				<span class="pdp-hero__badge"><?php echo $shcfg['badge']; ?></span>
				<h1 class="pdp-hero__title"><?php echo esc_html( $shcfg['name'] ); ?></h1>
				<p class="pdp-hero__desc"><?php echo esc_html( $shcfg['desc'] ); ?></p>
				<ul class="pdp-hero__bullets">
					<li>Compounded &middot; FDA-registered facility</li>
					<li>Provider-reviewed &middot; Prescription required</li>
				</ul>

				<?php if ( $image_url ) : ?>
				<div class="pdp-hero__image-card">
					<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $shcfg['name'] ); ?>" loading="lazy" />
				</div>
				<?php endif; ?>

				<div class="pdp-hero__trust-grid">
					<div class="pdp-hero__trust-item">
						<span class="pdp-hero__trust-icon" aria-hidden="true">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
						</span>
						<div class="pdp-hero__trust-text"><strong>Licensed providers</strong><span>Board-certified MDs</span></div>
					</div>
					<div class="pdp-hero__trust-item">
						<span class="pdp-hero__trust-icon" aria-hidden="true">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/></svg>
						</span>
						<div class="pdp-hero__trust-text"><strong>Compounded in USA</strong><span>FDA-registered facility</span></div>
					</div>
					<div class="pdp-hero__trust-item">
						<span class="pdp-hero__trust-icon" aria-hidden="true">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
						</span>
						<div class="pdp-hero__trust-text"><strong>Free shipping</strong><span>Discreet packaging</span></div>
					</div>
					<div class="pdp-hero__trust-item">
						<span class="pdp-hero__trust-icon" aria-hidden="true">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
						</span>
						<div class="pdp-hero__trust-text"><strong>Ongoing support</strong><span>Message your care team</span></div>
					</div>
				</div>
			</div>

			<div class="pdp-hero__right">

				<!-- Hidden WC form — keeps variation hooks alive for plugins -->
				<div class="pdp-hero__wc-hidden" aria-hidden="true" inert>
					<?php
					do_action( 'woocommerce_before_single_product_summary' );
					do_action( 'woocommerce_single_product_summary' );
					?>
				</div>

				<!-- Sexual health configurator -->
				<div id="pdp-cfg" class="pdp-cfg"
					data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
					data-variation-matrix="<?php echo esc_attr( wp_json_encode( $variation_matrix ) ); ?>"
					data-primary-attr="<?php echo esc_attr( $primary_attr_key ); ?>"
					data-secondary-attr="<?php echo esc_attr( $secondary_attr_key ?? '' ); ?>"
					data-fixed-attrs="<?php echo esc_attr( wp_json_encode( $shcfg['fixed_attrs'] ) ); ?>"
					data-primary-labels="<?php echo esc_attr( wp_json_encode( $primary_labels ) ); ?>"
					data-secondary-labels="<?php echo esc_attr( wp_json_encode( $secondary_labels ) ); ?>"
					<?php if ( $slug === 'testosterone' ) : ?>
					data-trt-allowed-states="<?php echo esc_attr( wp_json_encode( $trt_allowed_states ) ); ?>"
					<?php endif; ?>
				>

					<?php if ( $slug === 'testosterone' ) : ?>
					<!-- TRT: state eligibility gate -->
					<p class="pdp-cfg__section-label">
						Your State
						<span class="trt-state__required">Required</span>
					</p>
					<div class="trt-state__picker" id="trt-state-picker">
						<button class="trt-state__trigger" id="trt-state-trigger" type="button"
							aria-haspopup="listbox" aria-expanded="false" aria-label="Select your state">
							<span id="trt-state-display" class="trt-state__trigger-text trt-state__trigger-text--placeholder">Select your state&hellip;</span>
							<span class="trt-state__chevron" aria-hidden="true">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
							</span>
						</button>
						<ul class="trt-state__options" id="trt-state-options" role="listbox" aria-label="Select your state">
							<?php foreach ( $all_us_states as $code => $name ) : ?>
							<li class="trt-state__option" role="option" data-value="<?php echo esc_attr( $code ); ?>" aria-selected="false"><?php echo esc_html( $name ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
					<input type="hidden" id="trt-state-value" value="">
					<p id="trt-state-status" class="trt-state__status"></p>
					<div id="trt-state-error" class="trt-state__unavailable" style="display:none;">
						<div class="trt-state__unavailable-header">
							<div class="trt-state__unavailable-icon" aria-hidden="true">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
							</div>
							<p class="trt-state__unavailable-title">Not available in your state</p>
						</div>
						<p class="trt-state__unavailable-desc">
							We currently offer TRT services in 48 states. We&rsquo;re not yet licensed to prescribe in your area, but we&rsquo;re actively expanding coverage.
						</p>
						<a href="mailto:support@myogenixpharma.com" class="trt-state__unavailable-link">
							Contact us about future availability &rarr;
						</a>
					</div>
					<?php endif; ?>

					<!-- Primary selector (dosage or plan) -->
					<p class="pdp-cfg__section-label"><?php echo esc_html( $shcfg['primary_label'] ); ?></p>
					<div class="pdp-cfg__supply-row">
						<?php
						$is_first = true;
						foreach ( $primary_keys as $p_slug ) :
						?>
						<button class="pdp-cfg__supply sh-pdp__primary-btn<?php echo $is_first ? ' pdp-cfg__supply--active' : ''; ?>"
							data-primary="<?php echo esc_attr( $p_slug ); ?>">
							<strong><?php echo esc_html( $primary_labels[ $p_slug ] ?? $p_slug ); ?></strong>
						</button>
						<?php
						$is_first = false;
						endforeach;
						?>
					</div>

					<?php if ( $secondary_attr_key && ! empty( $secondary_keys ) ) : ?>
					<!-- Secondary selector (tablets / supply length) -->
					<p class="pdp-cfg__section-label"><?php echo esc_html( $shcfg['secondary_label'] ); ?></p>
					<div class="pdp-cfg__supply-row">
						<?php
						$is_first = true;
						foreach ( $secondary_keys as $s_slug ) :
						?>
						<button class="pdp-cfg__supply sh-pdp__secondary-btn<?php echo $is_first ? ' pdp-cfg__supply--active' : ''; ?>"
							data-secondary="<?php echo esc_attr( $s_slug ); ?>">
							<?php if ( $s_slug === $last_secondary && count( $secondary_keys ) > 1 ) : ?>
							<span class="pdp-cfg__popular-tag">POPULAR</span>
							<?php endif; ?>
							<strong><?php echo esc_html( $secondary_labels[ $s_slug ] ?? $s_slug ); ?></strong>
						</button>
						<?php
						$is_first = false;
						endforeach;
						?>
					</div>
					<?php endif; ?>

					<!-- What's included -->
					<div class="peptide-cfg__includes">
						<p class="peptide-cfg__includes-title">What's included</p>
						<ul class="peptide-cfg__includes-list">
							<?php foreach ( $shcfg['includes'] as $include_item ) : ?>
							<li class="peptide-cfg__includes-item">
								<span class="peptide-cfg__includes-icon" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
								<?php echo $include_item; ?>
							</li>
							<?php endforeach; ?>
						</ul>
					</div>

					<div id="sh-summary" class="pdp-cfg__summary"></div>

					<button id="pdp-cta" class="pdp-cfg__cta">Go to Checkout &rarr;</button>
					<p id="pdp-disclaimer" class="pdp-cfg__disclaimer">
						One-time purchase. Order reviewed by a licensed provider before processing.
					</p>

				</div>
			</div>
		</div>
	</section>

	<!-- 4 Steps -->
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

	<!-- Common Questions -->
	<section class="myo-faq">
		<div class="myo-faq__wrap">

			<div class="myo-faq__header">
				<p class="myo-faq__eyebrow">FAQ</p>
				<h2 class="myo-faq__title">Common questions</h2>
				<p class="myo-faq__desc">What you need to know about sexual health and hormone therapy ordering.</p>
			</div>

			<div class="myo-faq__list">

				<div class="myo-faq__item">
					<button class="myo-faq__btn" type="button" aria-expanded="false" aria-controls="sh-faq-0">
						<span class="myo-faq__q">How is this medication prescribed and delivered?</span>
						<span class="myo-faq__icon" aria-hidden="true"></span>
					</button>
					<div class="myo-faq__panel" id="sh-faq-0">
						<div class="myo-faq__panel-inner">
							<p>You complete a short health questionnaire online. A licensed provider reviews your order and, if appropriate, issues a prescription through our telehealth platform. Your medication is then compounded and shipped directly to your door in discreet packaging — no pharmacy visit required.</p>
						</div>
					</div>
				</div>

				<div class="myo-faq__item">
					<button class="myo-faq__btn" type="button" aria-expanded="false" aria-controls="sh-faq-1">
						<span class="myo-faq__q">How quickly does it start working?</span>
						<span class="myo-faq__icon" aria-hidden="true"></span>
					</button>
					<div class="myo-faq__panel" id="sh-faq-1">
						<div class="myo-faq__panel-inner">
							<p>Onset time varies by compound. Sildenafil typically takes effect within 30–60 minutes and lasts 4–6 hours. Tadalafil has a longer onset of 1–2 hours but can remain effective for up to 36 hours, making it suitable for daily low-dose use. Testosterone Cypionate builds to therapeutic levels over 2–4 weeks of consistent treatment.</p>
						</div>
					</div>
				</div>

				<div class="myo-faq__item">
					<button class="myo-faq__btn" type="button" aria-expanded="false" aria-controls="sh-faq-2">
						<span class="myo-faq__q">Why is a provider review required before my order ships?</span>
						<span class="myo-faq__icon" aria-hidden="true"></span>
					</button>
					<div class="myo-faq__panel" id="sh-faq-2">
						<div class="myo-faq__panel-inner">
							<p>We operate as a licensed telehealth clinic, not a supplement retailer. Every order is reviewed by a board-certified provider who confirms the compound, dose, and any contraindications before anything ships. This protects your safety and ensures you receive the correct protocol for your specific situation.</p>
						</div>
					</div>
				</div>

				<div class="myo-faq__item">
					<button class="myo-faq__btn" type="button" aria-expanded="false" aria-controls="sh-faq-3">
						<span class="myo-faq__q">Do I need a new consultation for every refill?</span>
						<span class="myo-faq__icon" aria-hidden="true"></span>
					</button>
					<div class="myo-faq__panel" id="sh-faq-3">
						<div class="myo-faq__panel-inner">
							<p>No. Once your prescription is established, refills are straightforward. Your provider may request a brief check-in every few months to review your response and adjust dosing if needed — but there is no full consultation required for standard refills within an active treatment plan.</p>
						</div>
					</div>
				</div>

				<div class="myo-faq__item">
					<button class="myo-faq__btn" type="button" aria-expanded="false" aria-controls="sh-faq-4">
						<span class="myo-faq__q">How is this compounded and where does it ship from?</span>
						<span class="myo-faq__icon" aria-hidden="true"></span>
					</button>
					<div class="myo-faq__panel" id="sh-faq-4">
						<div class="myo-faq__panel-inner">
							<p>All medications are compounded by a licensed U.S. FDA-registered 503A compounding pharmacy. Orders ship directly to your door in temperature-controlled, discreet packaging. All required supplies (syringes, prep pads, or dosing guides, depending on the product) are included.</p>
						</div>
					</div>
				</div>

			</div>

			<div class="myo-faq__cta">
				<a href="#buy" class="myo-faq__cta-btn">Order now &rarr;</a>
			</div>

		</div>
	</section>

	<!-- Explore More Treatment Lines -->
	<section class="myogenix-pdp__explore">
		<div class="myogenix-pdp__container">
			<h2 class="myogenix-pdp__section-heading">Explore More Treatment Lines</h2>
			<p class="myogenix-pdp__section-sub">Provider-reviewed programs for every health goal.</p>
			<?php myogenix_render_product_scrollers( [ 'mens-health', 'peptides' ], $product->get_id() ); ?>
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
