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
$weight_loss    = [ 'compound-semaglutide', 'compound-tirzepatide', 'compound-retatrutide' ];
$is_weight_loss = in_array( $slug, $weight_loss, true );

$peptide_slugs = [
	'bpc', 'motsc', 'epithalon', 'compound-injectable-nad',
	'tesamorelin-ipamorelin', 'cjc1295-ipamorelin',
	'klow-stack-bpc157-10mg-ghk-cu-50mg-tb50010mg-kpv-10mg',
	'2606', 'compound-injectable-sermorelin', 'compound-injectable-glutathione',
];
$is_peptide = in_array( $slug, $peptide_slugs, true );

$sexual_health_slugs = [ 'compound-oral-tadalafil', 'compound-sildenafil', 'testosterone', 'hcg' ];
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
			'hero_line'         => 'Dual-action metabolic care',
			'hero_accent'       => 'Tirzepatide weekly support',
			'compare_url'       => '/product/compound-semaglutide/',
			'compare_txt'       => 'Compare with Semaglutide',
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
			'hero_line'         => 'Appetite and weight support',
			'hero_accent'       => 'Semaglutide weekly care',
			'compare_url'       => '/product/compound-tirzepatide/',
			'compare_txt'       => 'Compare with Tirzepatide',
			'doses'             => [ '1mg', '2mg', '4mg', '6mg', '10mg' ],
			'supply_prices'     => [ 285.00, 379.00, 479.00 ],
			'warning_threshold' => 1,
			'pkg_dose_slugs'    => [ 'starter' => 'months-1-3-bundle', 'continuation' => 'months-4-6-bundle' ],
			'pkg_prices'        => [ 'starter' => 549.00, 'continuation' => 799.00 ],
		],
		'compound-retatrutide' => [
			'badge'             => 'GIP/GLP-1/Glucagon Triple Agonist',
			'title'             => 'Retatrutide',
			'desc'              => 'Retatrutide activates GIP, GLP-1, and glucagon receptors simultaneously for next-generation metabolic support.',
			'hero_line'         => 'Advanced metabolic support',
			'hero_accent'       => 'Retatrutide weekly care',
			'compare_url'       => '/product/compound-tirzepatide/',
			'compare_txt'       => 'Compare with Tirzepatide',
			'doses'             => [ '2mg', '4mg', '8mg', '12mg' ],
			'supply_prices'     => [ 299.00, 599.00, 899.00 ],
			'warning_threshold' => 2,
			'pkg_dose_slugs'    => [],
			'pkg_prices'        => [],
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
	$image_url = function_exists( 'myogenix_grunge_bottle_url' ) ? myogenix_grunge_bottle_url( $product ) : '';
	if ( ! $image_url ) {
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';
	}

	// Keep WC images hook removed — we render the product image ourselves
	remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );

	$img_url = function( $path ) {
		$base  = get_stylesheet_directory_uri() . '/assets/images/';
		$parts = explode( '/', $path );
		return esc_url( $base . implode( '/', array_map( 'rawurlencode', $parts ) ) );
	};

		$steps = [
			[
				'num'   => '1',
				'img'   => 'grunge-redesign/laptop-check.svg',
				'title' => 'Quick Online Intake',
				'desc'  => 'Complete your confidential medical questionnaire in minutes.',
			],
			[
				'num'   => '2',
				'img'   => 'grunge-redesign/doctor.svg',
				'title' => 'Provider Review',
				'desc'  => 'A licensed provider reviews your health history and goals.',
			],
			[
				'num'   => '3',
				'img'   => 'grunge-redesign/rx.svg',
				'title' => 'Personalized Plan',
				'desc'  => 'Your protocol is reviewed for the selected dose and supply.',
			],
			[
				'num'   => '4',
				'img'   => 'grunge-redesign/box.svg',
				'title' => 'Shipped to Your Door',
				'desc'  => 'Discreet, temperature-aware shipping direct to you.',
			],
		];

?>

<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'myogenix-pdp grunge-product-pdp', $product ); ?>>

	<!-- Product Hero -->
		<section class="pdp-hero">
			<div class="pdp-hero__inner">

			<!-- Left: product info + image -->
			<div class="pdp-hero__left">
				<span class="pdp-hero__badge"><?php echo esc_html( $h['badge'] ); ?></span>
				<h1 class="pdp-hero__title"><?php echo esc_html( $h['title'] ); ?></h1>
				<p class="pdp-hero__desc"><?php echo esc_html( $h['hero_line'] ); ?> <span><?php echo esc_html( $h['hero_accent'] ); ?></span></p>
				<div class="trt-pdp__hero-actions">
					<a class="grunge-btn grunge-btn--red" href="#buy">Select Medication <?php echo myogenix_grunge_arrow_svg(); ?></a>
					<a class="grunge-btn grunge-btn--dark" href="<?php echo esc_url( home_url( '/reach-a-concierge/' ) ); ?>">Ask a question <?php echo myogenix_grunge_arrow_svg(); ?></a>
				</div>
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
			</div>

			</div>
		</section>

		<section class="pdp-build" id="buy" aria-label="Build your plan">
			<div class="trt-pdp__section-bg" style="background-image:url('<?php echo $img_url( 'grunge-redesign/bg-genetic-wire.webp' ); ?>')" aria-hidden="true"></div>
			<div class="pdp-build__inner">
				<!-- Configurator + hidden WC form -->
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
					<div class="pdp-cfg__builder">
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
					</div>

					<div class="pdp-cfg__side">
					<!-- Order Summary -->
					<div id="pdp-summary" class="pdp-cfg__summary"></div>

					<!-- CTA -->
					<button id="pdp-cta" class="pdp-cfg__cta">Go to Checkout</button>

					<div class="peptide-cfg__includes">
						<p class="peptide-cfg__includes-title">What's included</p>
						<ul class="peptide-cfg__includes-list">
							<li class="peptide-cfg__includes-item"><span class="peptide-cfg__includes-icon" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>Provider-reviewed treatment plan</li>
							<li class="peptide-cfg__includes-item"><span class="peptide-cfg__includes-icon" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>Personalized monthly dosing</li>
							<li class="peptide-cfg__includes-item"><span class="peptide-cfg__includes-icon" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>Prescription required if approved</li>
							<li class="peptide-cfg__includes-item"><span class="peptide-cfg__includes-icon" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>Concierge support</li>
						</ul>
					</div>

					<p id="pdp-disclaimer" class="pdp-cfg__disclaimer">
						This is a one-time purchase. Your order will be reviewed by a licensed provider before processing.
					</p>
					</div>
				</div>

				</div>
			</div>
		</section>

	<section class="trt-pdp__trust-strip" aria-label="Care features">
		<div class="trt-pdp__trust-strip-inner">
			<?php
			$pdp_trust = [
				[ 'img' => 'grunge-redesign/doctor.svg',       'label' => 'Physician-Guided Care' ],
				[ 'img' => 'grunge-redesign/laptop-check.svg', 'label' => 'Online Intake' ],
				[ 'img' => 'grunge-redesign/rx.svg',           'label' => 'Personalized Dosing' ],
				[ 'img' => 'grunge-redesign/box.svg',          'label' => 'Shipped to Your Door' ],
				[ 'img' => 'grunge-redesign/headphones.svg',   'label' => 'Concierge Support' ],
			];
			foreach ( $pdp_trust as $item ) :
			?>
			<div class="trt-pdp__trust-cell">
				<img src="<?php echo $img_url( $item['img'] ); ?>" alt="" loading="lazy" width="36" height="36">
				<span><?php echo esc_html( $item['label'] ); ?></span>
			</div>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="trt-pdp__plans" aria-label="Choose your starting point">
		<div class="trt-pdp__section-bg" style="background-image:url('<?php echo $img_url( 'grunge-redesign/section bg 4.png' ); ?>')" aria-hidden="true"></div>
		<div class="trt-pdp__plans-inner">
			<div class="trt-pdp__plans-copy">
				<p class="grunge-kicker">Choose your starting point</p>
				<h2><span class="grunge-word grunge-word--white"><?php echo esc_html( $h['title'] ); ?></span><span class="grunge-word grunge-word--red"><?php echo esc_html( $dose_labels[ $first_dose ] ?? $first_dose ); ?></span></h2>
				<p><?php echo esc_html( $h['desc'] ); ?></p>
			</div>
			<div class="trt-pdp__plan-grid">
				<?php
				$wm_plan_cards = [
					[ 'title' => '1 Month',  'supply' => 'starter supply', 'price' => $sp[0], 'meta' => '1 vial' ],
					[ 'title' => '2 Months', 'supply' => 'expanded supply', 'price' => $sp[1], 'meta' => '2 vials', 'popular' => true ],
					[ 'title' => '3 Months', 'supply' => 'best value', 'price' => $sp[2], 'meta' => '3 vials' ],
				];
				foreach ( $wm_plan_cards as $plan ) :
				?>
				<article class="trt-pdp__plan-card">
					<?php if ( ! empty( $plan['popular'] ) ) : ?><span class="trt-pdp__popular">Popular</span><?php endif; ?>
					<h3><?php echo esc_html( $plan['title'] ); ?></h3>
					<p><?php echo esc_html( $plan['supply'] ); ?></p>
					<ul>
						<li>Provider-reviewed</li>
						<li>Personalized dosing</li>
						<li>Once-weekly injections</li>
						<li>Concierge support</li>
					</ul>
					<dl>
						<div><dt>Starting dose</dt><dd><?php echo esc_html( $dose_labels[ $first_dose ] ?? $first_dose ); ?></dd></div>
						<div><dt>Supply</dt><dd><?php echo esc_html( $plan['meta'] ); ?></dd></div>
						<div><dt>Price</dt><dd><?php echo $plan['price'] ? '$' . esc_html( number_format( $plan['price'], 0 ) ) : 'Custom'; ?></dd></div>
					</dl>
					<a class="trt-pdp__select" href="#buy" data-pdp-months="<?php echo esc_attr( (int) $plan['title'] ); ?>">Select Plan</a>
				</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<!-- How It Works Section -->
	<section class="home-how" aria-label="How it works">
		<div class="hp-inner">
			<div class="home-how__header">
					<p class="home-how__overline">How it works</p>
					<h2 class="home-how__heading"><span class="grunge-word grunge-word--white">Getting started</span><span class="grunge-word grunge-word--red">is simple</span></h2>
					<p class="home-how__desc">From your intake to provider review, each step is built for clear, guided care.</p>
			</div>
			<div class="home-how__steps">
				<?php foreach ( $steps as $step ) : ?>
				<div class="hp-step">
					<div class="hp-step__img-wrap">
						<img src="<?php echo $img_url( $step['img'] ); ?>" alt="<?php echo esc_attr( $step['title'] ); ?>" class="hp-step__img" loading="lazy" width="400" height="300">
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

	<!-- Common Questions Section -->
	<?php myogenix_render_product_faq( $product->get_id() ); ?>

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
			'hero_line' => 'Tissue and joint support',
			'hero_accent' => 'BPC-157 recovery care',
			'spec'  => 'BPC-157 · 3mg/ml · 5ml per vial',
		],
		'motsc' => [
			'name'  => 'MOTSc',
			'badge' => 'Mitochondrial Peptide',
			'desc'  => 'MOTSc is a mitochondrial-derived peptide that activates AMPK pathways, supporting energy metabolism, insulin sensitivity, and cellular resilience.',
			'hero_line' => 'Energy metabolism support',
			'hero_accent' => 'MOTSc peptide care',
			'spec'  => 'MOTSc · 2mg/ml · 5ml per vial',
		],
		'epithalon' => [
			'name'  => 'Epithalon',
			'badge' => 'Longevity Peptide',
			'desc'  => 'Epithalon is a tetrapeptide that stimulates telomerase activity and regulates the pineal gland, supporting healthy aging and cellular longevity.',
			'hero_line' => 'Healthy aging support',
			'hero_accent' => 'Epithalon longevity care',
			'spec'  => 'Epithalon · 2mg/ml · 5ml per vial',
		],
		'compound-injectable-nad' => [
			'name'  => 'NAD+',
			'badge' => 'Cellular Energy Support',
			'desc'  => 'NAD+ is a critical coenzyme involved in cellular energy production, DNA repair, and sirtuin activation — supporting cognitive function, metabolism, and anti-aging pathways.',
			'hero_line' => 'Cellular energy support',
			'hero_accent' => 'NAD+ injectable care',
			'spec'  => 'NAD+ · 100mg/ml · 10ml per vial',
		],
		'tesamorelin-ipamorelin' => [
			'name'  => 'Tesamorelin / Ipamorelin',
			'badge' => 'GH Secretagogue Blend',
			'desc'  => 'A dual-action blend combining Tesamorelin (a GHRH analogue) with Ipamorelin (a GHRP), designed to pulse growth hormone release naturally and support lean body composition.',
			'hero_line' => 'Lean body support',
			'hero_accent' => 'Tesamorelin / Ipamorelin blend',
			'spec'  => 'Tesamorelin 3mg + Ipamorelin 2mg · 5ml per vial',
		],
		'cjc1295-ipamorelin' => [
			'name'  => 'CJC-1295 / Ipamorelin',
			'badge' => 'GH Secretagogue Blend',
			'desc'  => 'CJC-1295 extends the half-life of natural GH pulses while Ipamorelin provides a clean GH release — a popular stack for muscle recovery, fat loss, and sleep quality.',
			'hero_line' => 'Recovery and sleep support',
			'hero_accent' => 'CJC-1295 / Ipamorelin blend',
			'spec'  => 'CJC-1295 1.2mg + Ipamorelin 2mg · 5ml per vial',
		],
		'klow-stack-bpc157-10mg-ghk-cu-50mg-tb50010mg-kpv-10mg' => [
			'name'  => 'KLOW Stack',
			'badge' => 'Recovery Peptide Stack',
			'desc'  => 'The KLOW Stack combines BPC-157, GHK-Cu, TB-500, and KPV in a single vial — a comprehensive recovery peptide blend targeting tissue repair, inflammation, and systemic healing.',
			'hero_line' => 'Comprehensive recovery support',
			'hero_accent' => 'KLOW peptide stack',
			'spec'  => 'BPC-157 3mg / GHK-Cu 10mg / TB-500 3mg / KPV 3mg · 5ml per vial',
		],
		'2606' => [
			'name'  => 'Wolverine Stack',
			'badge' => 'Recovery Peptide Stack',
			'desc'  => 'The Wolverine Stack pairs BPC-157 with TB-500 for accelerated recovery and tissue regeneration — a go-to protocol for musculoskeletal injuries and chronic inflammation.',
			'hero_line' => 'Tissue recovery support',
			'hero_accent' => 'Wolverine peptide stack',
			'spec'  => 'BPC-157 3mg + TB-500 3mg · 5ml per vial',
		],
		'compound-injectable-sermorelin' => [
			'name'  => 'Sermorelin',
			'badge' => 'Growth Hormone Secretagogue',
			'desc'  => 'Sermorelin is a synthetic analogue of GHRH that stimulates natural growth hormone production, supporting sleep quality, lean mass, recovery, and metabolic health.',
			'hero_line' => 'Recovery and sleep support',
			'hero_accent' => 'Sermorelin peptide care',
			'spec'  => 'Sermorelin · 10mg per vial',
		],
		'compound-injectable-glutathione' => [
			'name'  => 'Glutathione',
			'badge' => 'Master Antioxidant Therapy',
			'desc'  => 'Glutathione is the body\'s master antioxidant, critical for oxidative stress management, immune function, and liver detoxification. Delivered as a sterile injectable for maximum bioavailability.',
			'hero_line' => 'Antioxidant defense support',
			'hero_accent' => 'Glutathione injectable care',
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
	$image_url = function_exists( 'myogenix_grunge_bottle_url' ) ? myogenix_grunge_bottle_url( $product ) : '';
	if ( ! $image_url ) {
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';
	}

	remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );

	$img_url = function( $path ) {
		$base  = get_stylesheet_directory_uri() . '/assets/images/';
		$parts = explode( '/', $path );
		return esc_url( $base . implode( '/', array_map( 'rawurlencode', $parts ) ) );
	};

		$steps = [
			[ 'num' => '1', 'img' => 'grunge-redesign/laptop-check.svg', 'title' => 'Quick Online Intake',  'desc' => 'Complete your confidential medical questionnaire in minutes.' ],
			[ 'num' => '2', 'img' => 'grunge-redesign/doctor.svg',       'title' => 'Provider Review',      'desc' => 'A licensed provider reviews your health history and goals.' ],
			[ 'num' => '3', 'img' => 'grunge-redesign/rx.svg',           'title' => 'Personalized Plan',    'desc' => 'Your protocol is reviewed for the selected dose and supply.' ],
			[ 'num' => '4', 'img' => 'grunge-redesign/box.svg',          'title' => 'Shipped to Your Door', 'desc' => 'Discreet, temperature-aware shipping direct to you.' ],
		];

?>

<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'myogenix-pdp grunge-product-pdp peptide-pdp', $product ); ?>>

	<!-- Product Hero -->
		<section class="pdp-hero">
			<div class="pdp-hero__inner">

			<div class="pdp-hero__left">
				<span class="pdp-hero__badge"><?php echo $pcfg['badge']; ?></span>
				<h1 class="pdp-hero__title"><?php echo esc_html( $pcfg['name'] ); ?></h1>
				<p class="pdp-hero__desc"><?php echo esc_html( $pcfg['hero_line'] ); ?> <span><?php echo esc_html( $pcfg['hero_accent'] ); ?></span></p>
				<div class="trt-pdp__hero-actions">
					<a class="grunge-btn grunge-btn--red" href="#buy">Select Medication <?php echo myogenix_grunge_arrow_svg(); ?></a>
					<a class="grunge-btn grunge-btn--dark" href="<?php echo esc_url( home_url( '/reach-a-concierge/' ) ); ?>">Ask a question <?php echo myogenix_grunge_arrow_svg(); ?></a>
				</div>
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

			</div>
		</section>

		<section class="pdp-build" id="buy" aria-label="Build your plan">
			<div class="trt-pdp__section-bg" style="background-image:url('<?php echo $img_url( 'grunge-redesign/bg-genetic-wire.webp' ); ?>')" aria-hidden="true"></div>
			<div class="pdp-build__inner">
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
					<div class="pdp-cfg__builder">
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
					</div>

					<div class="peptide-cfg__includes peptide-cfg__includes--inline">
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

					<div class="pdp-cfg__side">
					<div id="peptide-summary" class="pdp-cfg__summary"></div>

					<button id="pdp-cta" class="pdp-cfg__cta">Go to Checkout</button>
					<p id="pdp-disclaimer" class="pdp-cfg__disclaimer">
						One-time purchase. Order reviewed by a licensed provider before processing.
					</p>
					</div>

				</div>
				</div>
			</div>
		</section>

	<section class="trt-pdp__trust-strip" aria-label="Care features">
		<div class="trt-pdp__trust-strip-inner">
			<?php
			$pdp_trust = [
				[ 'img' => 'grunge-redesign/doctor.svg',       'label' => 'Physician-Guided Care' ],
				[ 'img' => 'grunge-redesign/laptop-check.svg', 'label' => 'Online Intake' ],
				[ 'img' => 'grunge-redesign/rx.svg',           'label' => 'Personalized Dosing' ],
				[ 'img' => 'grunge-redesign/box.svg',          'label' => 'Shipped to Your Door' ],
				[ 'img' => 'grunge-redesign/headphones.svg',   'label' => 'Concierge Support' ],
			];
			foreach ( $pdp_trust as $item ) :
			?>
			<div class="trt-pdp__trust-cell">
				<img src="<?php echo $img_url( $item['img'] ); ?>" alt="" loading="lazy" width="36" height="36">
				<span><?php echo esc_html( $item['label'] ); ?></span>
			</div>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="trt-pdp__plans" aria-label="Choose your starting point">
		<div class="trt-pdp__section-bg" style="background-image:url('<?php echo $img_url( 'grunge-redesign/section bg 4.png' ); ?>')" aria-hidden="true"></div>
		<div class="trt-pdp__plans-inner">
			<div class="trt-pdp__plans-copy">
				<p class="grunge-kicker">Choose your starting point</p>
				<h2><span class="grunge-word grunge-word--white"><?php echo esc_html( $pcfg['name'] ); ?></span><span class="grunge-word grunge-word--red"><?php echo esc_html( $single_supply_price ? '$' . number_format( $single_supply_price, 0 ) : 'Program' ); ?></span></h2>
				<p><?php echo esc_html( $pcfg['desc'] ); ?></p>
			</div>
			<div class="trt-pdp__plan-grid">
				<?php
				$plan_index = 0;
				foreach ( $supply_map as $s_slug => $s_entry ) :
					$plan_index++;
				?>
				<article class="trt-pdp__plan-card">
					<?php if ( $plan_index === 2 && count( $supply_map ) > 1 ) : ?><span class="trt-pdp__popular">Popular</span><?php endif; ?>
					<h3><?php echo esc_html( $s_entry['label'] ); ?></h3>
					<p><?php echo esc_html( '~' . ( 30 * (int) $s_entry['qty'] ) . ' day supply' ); ?></p>
					<ul>
						<li>Provider-reviewed</li>
						<li>Syringes and needles</li>
						<li>Dosing protocol card</li>
						<li>Concierge support</li>
					</ul>
					<dl>
						<div><dt>Formula</dt><dd><?php echo esc_html( $pcfg['spec'] ); ?></dd></div>
						<div><dt>Supply</dt><dd><?php echo esc_html( $s_entry['label'] ); ?></dd></div>
						<div><dt>Price</dt><dd><?php echo '$' . esc_html( number_format( (float) $s_entry['price'], 0 ) ); ?></dd></div>
					</dl>
					<a class="trt-pdp__select" href="#buy" data-pdp-supply="<?php echo esc_attr( $s_slug ); ?>">Select Plan</a>
				</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<!-- How It Works Section -->
	<section class="home-how" aria-label="How it works">
		<div class="hp-inner">
			<div class="home-how__header">
					<p class="home-how__overline">How it works</p>
					<h2 class="home-how__heading"><span class="grunge-word grunge-word--white">Getting started</span><span class="grunge-word grunge-word--red">is simple</span></h2>
					<p class="home-how__desc">From your intake to provider review, each step is built for clear, guided care.</p>
			</div>
			<div class="home-how__steps">
				<?php foreach ( $steps as $step ) : ?>
				<div class="hp-step">
					<div class="hp-step__img-wrap">
						<img src="<?php echo $img_url( $step['img'] ); ?>" alt="<?php echo esc_attr( $step['title'] ); ?>" class="hp-step__img" loading="lazy" width="400" height="300">
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

	<!-- Common Questions -->
	<?php myogenix_render_product_faq( $product->get_id() ); ?>

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
			'hero_line'       => 'Long-lasting performance support',
			'hero_accent'     => 'Tadalafil sexual health care',
			'includes'        => [
				'90 compounded oral tablets',
				'Dosing protocol card',
			],
			'primary_attr'    => 'pa_dosage',
			'primary_label'   => 'Select Dosage',
			'secondary_attr'  => null,
			'secondary_label' => null,
			'fixed_attrs'     => [ 'attribute_pa_tablets' => '90-tablets' ],
			'cta_label'       => 'Go to Checkout',
			'disclaimer'      => 'One-time purchase. Order reviewed by a licensed provider before processing.',
			'flat_fee_price'  => 0,
			'flat_fee_label'  => '',
		],
		'compound-sildenafil' => [
			'name'            => 'Sildenafil',
			'badge'           => 'Sexual Health',
			'desc'            => 'Sildenafil (generic Viagra) is a PDE5 inhibitor that increases blood flow to support erections when sexually stimulated. Fast-acting, widely studied, and available in multiple strengths.',
			'hero_line'       => 'Fast-acting performance support',
			'hero_accent'     => 'Sildenafil sexual health care',
			'includes'        => [
				'Compounded oral sildenafil tablets',
				'Dosing protocol card',
			],
			'primary_attr'    => 'pa_dosage',
			'primary_label'   => 'Select Dosage',
			'secondary_attr'  => 'pa_tablets',
			'secondary_label' => 'Supply Length',
			'fixed_attrs'     => [],
			'cta_label'       => 'Go to Checkout',
			'disclaimer'      => 'One-time purchase. Order reviewed by a licensed provider before processing.',
			'flat_fee_price'  => 0,
			'flat_fee_label'  => '',
		],
		'testosterone' => [
			'name'            => 'Testosterone Therapy',
			'badge'           => 'Provider-reviewed men\'s health',
			'desc'            => 'Testosterone Cypionate is a long-acting injectable testosterone used to treat hypogonadism (low T). It supports energy levels, muscle mass, libido, mood, and overall wellbeing.',
			'hero_line'       => 'Provider-managed TRT',
			'hero_accent'     => 'Testosterone Cypionate',
			'includes'        => [
				'Testosterone cypionate',
				'Labwork',
				'Dr. consultations',
				'Syringes',
				'Alcohol swabs',
				'Estrogen support if necessary',
				'Shipped to your door',
			],
			'primary_attr'    => 'pa_subscription-plan',
			'primary_label'   => '2. Quantity',
			'secondary_attr'  => null,
			'secondary_label' => null,
			'fixed_attrs'     => [],
			'cta_label'       => 'Go to Checkout',
			'disclaimer'      => 'Order reviewed by a licensed provider before processing.',
			'flat_fee_price'  => 165,
			'flat_fee_label'  => 'Male Hormone Panel & Initial Doctor Consult',
			'flat_fee_price_own_labs' => 65,
			'flat_fee_label_own_labs' => 'Initial Doctor Consult (labs provided by you)',
		],
		'hcg' => [
			'name'            => 'HCG',
			'badge'           => 'Men\'s Health',
			'desc'            => 'HCG (Human Chorionic Gonadotropin) is a physician-prescribed injectable used to support natural testosterone production and testicular function, often alongside a personalized hormone optimization plan.',
			'hero_line'       => 'Hormone function support',
			'hero_accent'     => 'HCG men\'s health care',
			'includes'        => [
				'HCG injectable &middot; 10,000 IU vial',
				'Syringes &amp; needles',
				'Alcohol prep pads',
				'Dosing protocol card',
			],
			'primary_attr'    => 'pa_dosage',
			'primary_label'   => 'Strength',
			'secondary_attr'  => null,
			'secondary_label' => null,
			'fixed_attrs'     => [],
			'cta_label'       => 'Go to Checkout',
			'disclaimer'      => 'One-time purchase. Order reviewed by a licensed provider before processing.',
			'flat_fee_price'  => 0,
			'flat_fee_label'  => '',
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

	// Sort dosage-like attributes low to high; fall back to term menu_order for non-numeric plans.
	$primary_order = array_keys( $primary_labels );
	$variant_sort_value = function( $slug, $labels ) {
		$label = $labels[ $slug ] ?? $slug;
		if ( preg_match( '/\d+(?:\.\d+)?/', $label, $m ) || preg_match( '/\d+(?:\.\d+)?/', $slug, $m ) ) {
			return (float) $m[0];
		}
		return null;
	};
	uksort( $variation_matrix, function ( $a, $b ) use ( $primary_order, $primary_labels, $variant_sort_value ) {
		$a_numeric = $variant_sort_value( $a, $primary_labels );
		$b_numeric = $variant_sort_value( $b, $primary_labels );
		if ( null !== $a_numeric && null !== $b_numeric && $a_numeric !== $b_numeric ) {
			return $a_numeric <=> $b_numeric;
		}
		return array_search( $a, $primary_order, true ) - array_search( $b, $primary_order, true );
	} );

	$primary_keys   = array_keys( $variation_matrix );
	$secondary_keys = $secondary_attr_key && ! empty( $primary_keys )
		? array_keys( $variation_matrix[ $primary_keys[0] ] )
		: [];
	if ( ! empty( $secondary_keys ) ) {
		usort( $secondary_keys, function( $a, $b ) use ( $secondary_labels, $variant_sort_value ) {
			$a_numeric = $variant_sort_value( $a, $secondary_labels );
			$b_numeric = $variant_sort_value( $b, $secondary_labels );
			if ( null !== $a_numeric && null !== $b_numeric && $a_numeric !== $b_numeric ) {
				return $a_numeric <=> $b_numeric;
			}
			return strcmp( $a, $b );
		} );
	}
	$last_secondary = ! empty( $secondary_keys ) ? end( $secondary_keys ) : '';

	// ─── Image ────────────────────────────────────────────────────────────────
	$image_id  = $product->get_image_id();
	$image_url = function_exists( 'myogenix_grunge_bottle_url' ) ? myogenix_grunge_bottle_url( $product ) : '';
	if ( ! $image_url ) {
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';
	}

	remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );

	$img_url = function( $path ) {
		$base  = get_stylesheet_directory_uri() . '/assets/images/';
		$parts = explode( '/', $path );
		return esc_url( $base . implode( '/', array_map( 'rawurlencode', $parts ) ) );
	};

		$steps = $slug === 'testosterone' ? [
			[ 'num' => '1', 'img' => 'grunge-redesign/laptop-check.svg', 'title' => 'Checkout',              'desc' => 'Choose whether you need bloodwork or already have recent labs, then complete checkout.' ],
			[ 'num' => '2', 'img' => 'grunge-redesign/vial.svg',         'title' => 'Questionnaire & labs',  'desc' => 'Complete the medical questionnaire. If bloodwork is needed, you receive Quest scheduling instructions by email.' ],
			[ 'num' => '3', 'img' => 'grunge-redesign/doctor.svg',       'title' => 'Provider review',       'desc' => 'Your provider reviews your questionnaire and lab information before treatment is approved.' ],
			[ 'num' => '4', 'img' => 'grunge-redesign/box.svg',          'title' => 'TRT shipped',           'desc' => 'If approved, your TRT supplies are prepared and shipped to your door.' ],
		] : [
			[ 'num' => '1', 'img' => 'grunge-redesign/laptop-check.svg', 'title' => 'Quick Online Intake',  'desc' => 'Complete your confidential medical questionnaire in minutes.' ],
			[ 'num' => '2', 'img' => 'grunge-redesign/doctor.svg',       'title' => 'Provider Review',      'desc' => 'A licensed provider reviews your health history and goals.' ],
			[ 'num' => '3', 'img' => 'grunge-redesign/rx.svg',           'title' => 'Personalized Plan',    'desc' => 'Your protocol is reviewed for the selected dose and supply.' ],
			[ 'num' => '4', 'img' => 'grunge-redesign/box.svg',          'title' => 'Shipped to Your Door', 'desc' => 'Discreet, temperature-aware shipping direct to you.' ],
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

<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'myogenix-pdp grunge-product-pdp sexual-health-pdp' . ( $slug === 'testosterone' ? ' trt-grunge-pdp' : '' ), $product ); ?>>

	<!-- Product Hero -->
		<section class="pdp-hero">
		<?php if ( $slug === 'testosterone' ) : ?>
		<div class="trt-pdp__hero-bg" style="background-image:url('<?php echo $img_url( 'grunge-redesign/hero bg.png' ); ?>')" aria-hidden="true"></div>
		<?php endif; ?>
		<div class="pdp-hero__inner">

			<div class="pdp-hero__left">
				<span class="pdp-hero__badge"><?php echo $shcfg['badge']; ?></span>
				<?php if ( $slug === 'testosterone' ) : ?>
				<h1 class="pdp-hero__title">
					<span class="grunge-word grunge-word--red">Testosterone</span>
					<span class="grunge-word grunge-word--white">Therapy</span>
				</h1>
				<p class="pdp-hero__desc">Provider-managed TRT <span>Testosterone Cypionate</span></p>
				<div class="trt-pdp__hero-actions">
					<a class="grunge-btn grunge-btn--red" href="#build-plan">Continue to Checkout <?php echo myogenix_grunge_arrow_svg(); ?></a>
					<a class="grunge-btn grunge-btn--dark" href="<?php echo esc_url( home_url( '/reach-a-concierge/' ) ); ?>">Ask a question <?php echo myogenix_grunge_arrow_svg(); ?></a>
				</div>
					<?php else : ?>
					<h1 class="pdp-hero__title"><?php echo esc_html( $shcfg['name'] ); ?></h1>
					<p class="pdp-hero__desc"><?php echo esc_html( $shcfg['hero_line'] ); ?> <span><?php echo esc_html( $shcfg['hero_accent'] ); ?></span></p>
					<div class="trt-pdp__hero-actions">
						<a class="grunge-btn grunge-btn--red" href="#buy">Select Medication <?php echo myogenix_grunge_arrow_svg(); ?></a>
						<a class="grunge-btn grunge-btn--dark" href="<?php echo esc_url( home_url( '/reach-a-concierge/' ) ); ?>">Ask a question <?php echo myogenix_grunge_arrow_svg(); ?></a>
					</div>
					<?php endif; ?>
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

			</div>
		</section>

		<section class="pdp-build" id="<?php echo $slug === 'testosterone' ? 'build-plan' : 'buy'; ?>" aria-label="Build your plan">
			<div class="trt-pdp__section-bg" style="background-image:url('<?php echo $img_url( 'grunge-redesign/bg-genetic-wire.webp' ); ?>')" aria-hidden="true"></div>
			<div class="pdp-build__inner">
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
					data-monthly-billing="<?php echo $slug === 'testosterone' ? '1' : '0'; ?>"
					data-flat-fee-price="<?php echo esc_attr( $shcfg['flat_fee_price'] ); ?>"
					data-flat-fee-label="<?php echo esc_attr( $shcfg['flat_fee_label'] ); ?>"
					<?php if ( $slug === 'testosterone' ) : ?>
					data-checkout-url="<?php echo esc_url( wc_get_checkout_url() ); ?>"
					data-trt-allowed-states="<?php echo esc_attr( wp_json_encode( $trt_allowed_states ) ); ?>"
					data-flat-fee-price-own-labs="<?php echo esc_attr( $shcfg['flat_fee_price_own_labs'] ); ?>"
					data-flat-fee-label-own-labs="<?php echo esc_attr( $shcfg['flat_fee_label_own_labs'] ); ?>"
					<?php endif; ?>
				>
					<div class="pdp-cfg__builder">
					<?php if ( $slug === 'testosterone' ) : ?>
					<div class="trt-pdp__requirements">
						<p class="pdp-cfg__section-label">What's required to get started</p>
						<div class="trt-pdp__requirement-grid">
							<div><strong>State eligibility</strong><span>Confirm TRT is available where you live.</span></div>
							<div><strong>Labs</strong><span>Use recent labs or schedule bloodwork after checkout.</span></div>
						</div>
					</div>
					<?php endif; ?>

					<!-- Primary selector (dosage or plan) -->
					<?php if ( $slug !== 'testosterone' ) : ?>
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
					<?php endif; ?>

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

					<?php if ( $slug === 'testosterone' ) : ?>
					<!-- TRT: state eligibility gate -->
					<div class="trt-pdp__eligibility">
						<p class="pdp-cfg__section-label trt-pdp__eligibility-label">
							Eligibility
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
								Contact us about future availability
							</a>
						</div>
					</div>
					<?php endif; ?>

					<?php if ( $slug === 'testosterone' ) : ?>
					<!-- TRT: own-labs discount toggle -->
					<div class="trt-pdp__labs-choice">
						<p class="pdp-cfg__section-label">Labs</p>
						<div class="trt-pdp__labs-grid" role="group" aria-label="TRT lab option">
							<button class="trt-pdp__labs-option trt-pdp__labs-option--active" type="button" data-trt-own-labs="0" aria-pressed="true">
								<strong>Without labs <span>$165</span></strong>
								<em>Schedule Bloodwork</em>
							</button>
							<button class="trt-pdp__labs-option" type="button" data-trt-own-labs="1" aria-pressed="false">
								<strong>With labs <span>$65</span></strong>
								<em>Schedule Doctor Consult + Upload Labs after payment</em>
							</button>
						</div>
						<input type="checkbox" id="pdp-own-labs" class="pdp-cfg__own-labs-checkbox" hidden />
						<p class="pdp-cfg__own-labs-note">You'll confirm lab details during your intake after checkout.</p>
					</div>
					<?php endif; ?>
					</div>

					<!-- What's included -->
						<?php
						$sh_config_option_count = 1 + ( $secondary_attr_key && ! empty( $secondary_keys ) ? 1 : 0 );
						if ( 'testosterone' === $slug ) {
							$sh_config_option_count = 3;
						}
						?>
						<?php if ( $sh_config_option_count < 2 ) : ?>
						<div class="peptide-cfg__includes peptide-cfg__includes--inline">
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
					<?php endif; ?>

					<div class="pdp-cfg__side">
					<div id="sh-summary" class="pdp-cfg__summary"></div>

					<button id="pdp-cta" class="pdp-cfg__cta"><?php echo esc_html( $shcfg['cta_label'] ); ?></button>
					<?php if ( $sh_config_option_count >= 2 ) : ?>
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
					<?php endif; ?>
					<p id="pdp-disclaimer" class="pdp-cfg__disclaimer">
						<?php echo esc_html( $shcfg['disclaimer'] ); ?>
					</p>
					</div>

				</div>
				</div>
			</div>
		</section>

	<section class="trt-pdp__trust-strip" aria-label="Care features">
		<div class="trt-pdp__trust-strip-inner">
			<?php
			$trt_trust = [
				[ 'img' => 'grunge-redesign/doctor.svg',       'label' => 'Physician-Guided Care' ],
				[ 'img' => 'grunge-redesign/laptop-check.svg', 'label' => 'Online Intake' ],
				[ 'img' => 'grunge-redesign/rx.svg',           'label' => 'Personalized Dosing' ],
				[ 'img' => 'grunge-redesign/box.svg',          'label' => 'Shipped to Your Door' ],
				[ 'img' => 'grunge-redesign/headphones.svg',   'label' => 'Concierge Support' ],
			];
			foreach ( $trt_trust as $item ) :
			?>
			<div class="trt-pdp__trust-cell">
				<img src="<?php echo $img_url( $item['img'] ); ?>" alt="" loading="lazy" width="36" height="36">
				<span><?php echo esc_html( $item['label'] ); ?></span>
			</div>
			<?php endforeach; ?>
		</div>
	</section>

	<?php if ( $slug === 'testosterone' ) : ?>
	<section class="trt-pdp__symptoms" aria-label="Common low testosterone symptoms">
		<div class="trt-pdp__section-bg" style="background-image:url('<?php echo $img_url( 'grunge-redesign/section bg 2.png' ); ?>')" aria-hidden="true"></div>
		<div class="trt-pdp__plans-inner">
			<div class="trt-pdp__plans-copy">
				<p class="grunge-kicker">TRT care path</p>
				<h2><span class="grunge-word grunge-word--white">Common low T</span><span class="grunge-word grunge-word--red">signals</span></h2>
				<p>Symptoms and labs are reviewed together before a provider determines whether TRT is clinically appropriate.</p>
			</div>
			<ul class="grunge-symptom-grid grunge-check-list trt-pdp__symptom-list">
				<?php foreach ( [ 'Low energy', 'Brain fog', 'Loss of strength', 'Increased body fat', 'Low libido', 'Poor recovery', 'Mood changes', 'Poor sleep' ] as $symptom ) : ?>
				<li><?php echo esc_html( $symptom ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>
	<?php endif; ?>

	<section class="trt-pdp__plans" aria-label="<?php echo $slug === 'testosterone' ? 'TRT checkout options' : 'Choose your starting point'; ?>">
		<div class="trt-pdp__section-bg" style="background-image:url('<?php echo $img_url( 'grunge-redesign/section bg 4.png' ); ?>')" aria-hidden="true"></div>
		<div class="trt-pdp__plans-inner">
			<div class="trt-pdp__plans-copy">
				<?php if ( $slug === 'testosterone' ) : ?>
				<p class="grunge-kicker">Customization to order</p>
				<h2><span class="grunge-word grunge-word--white">TRT checkout</span><span class="grunge-word grunge-word--red">lab path</span></h2>
				<p>TRT starts with state eligibility, lab status, checkout, questionnaire completion, and provider review before any treatment is approved.</p>
				<?php else : ?>
				<p class="grunge-kicker">Choose your starting point</p>
				<h2><span class="grunge-word grunge-word--white"><?php echo esc_html( $shcfg['name'] ); ?></span><span class="grunge-word grunge-word--red"><?php echo esc_html( $shcfg['primary_label'] ); ?></span></h2>
				<p><?php echo esc_html( $shcfg['desc'] ); ?></p>
				<?php endif; ?>
			</div>
			<div class="trt-pdp__plan-grid">
				<?php
				if ( $slug === 'testosterone' ) :
						$trt_plan_cards = [
							[ 'title' => 'Without labs', 'price' => '$165', 'supply' => 'Schedule Bloodwork', 'own_labs' => '0', 'popular' => true, 'items' => [ 'Medical questionnaire after checkout', 'Quest instructions by email', 'Schedule a nearby Quest appointment', 'Nothing to pay at Quest' ] ],
							[ 'title' => 'With labs', 'price' => '$65', 'supply' => 'Schedule Doctor Consult + Upload Labs after payment', 'own_labs' => '1', 'items' => [ 'Medical questionnaire after checkout', 'Upload recent labwork', 'Provider reviews labs and intake', 'Consult path without new Quest bloodwork' ] ],
						];
					foreach ( $trt_plan_cards as $plan ) :
				?>
				<article class="trt-pdp__plan-card">
					<?php if ( ! empty( $plan['popular'] ) ) : ?><span class="trt-pdp__popular">Popular</span><?php endif; ?>
					<h3><?php echo esc_html( $plan['title'] ); ?></h3>
					<p><?php echo esc_html( $plan['supply'] ); ?></p>
					<ul>
						<?php foreach ( $plan['items'] as $item ) : ?>
						<li><?php echo esc_html( $item ); ?></li>
						<?php endforeach; ?>
					</ul>
					<dl>
						<div><dt>Due today</dt><dd><?php echo esc_html( $plan['price'] ); ?></dd></div>
						<div><dt>Next step</dt><dd><?php echo esc_html( $plan['supply'] ); ?></dd></div>
					</dl>
					<a class="trt-pdp__select" href="#build-plan" data-trt-own-labs="<?php echo esc_attr( $plan['own_labs'] ); ?>">Select Option</a>
					</article>
				<?php
					endforeach;
				else :
					$plan_index = 0;
					foreach ( $primary_keys as $p_slug ) :
						$plan_index++;
						$entry = $secondary_attr_key && ! empty( $secondary_keys )
							? ( $variation_matrix[ $p_slug ][ $secondary_keys[0] ] ?? null )
							: ( $variation_matrix[ $p_slug ] ?? null );
						if ( ! $entry ) continue;
				?>
				<article class="trt-pdp__plan-card">
					<?php if ( $plan_index === 2 && count( $primary_keys ) > 1 ) : ?><span class="trt-pdp__popular">Popular</span><?php endif; ?>
					<h3><?php echo esc_html( $primary_labels[ $p_slug ] ?? $p_slug ); ?></h3>
					<p><?php echo esc_html( $shcfg['badge'] ); ?></p>
					<ul>
						<li>Provider-reviewed</li>
						<li>Prescription required</li>
						<li>Discreet shipping</li>
						<li>Concierge support</li>
					</ul>
					<dl>
						<div><dt>Option</dt><dd><?php echo esc_html( $primary_labels[ $p_slug ] ?? $p_slug ); ?></dd></div>
						<?php if ( $secondary_attr_key && ! empty( $secondary_keys ) ) : ?>
						<div><dt>Supply</dt><dd><?php echo esc_html( $secondary_labels[ $secondary_keys[0] ] ?? $secondary_keys[0] ); ?></dd></div>
						<?php endif; ?>
						<div><dt>Price</dt><dd><?php echo '$' . esc_html( number_format( (float) $entry['price'], 0 ) ); ?></dd></div>
					</dl>
						<a class="trt-pdp__select" href="#build-plan" data-pdp-primary="<?php echo esc_attr( $p_slug ); ?>"<?php echo $secondary_attr_key && ! empty( $secondary_keys ) ? ' data-pdp-secondary="' . esc_attr( $secondary_keys[0] ) . '"' : ''; ?>>Select Plan</a>
					</article>
				<?php
					endforeach;
				endif;
				?>
			</div>
		</div>
	</section>

	<!-- How It Works Section -->
	<section class="home-how" aria-label="How it works">
		<?php if ( $slug === 'testosterone' ) : ?>
		<div class="trt-pdp__section-bg" style="background-image:url('<?php echo $img_url( 'grunge-redesign/section bg 2.png' ); ?>')" aria-hidden="true"></div>
		<?php endif; ?>
		<div class="hp-inner">
			<div class="home-how__header">
					<p class="home-how__overline">How it works</p>
						<h2 class="home-how__heading"><span class="grunge-word grunge-word--white">Getting started</span><span class="grunge-word grunge-word--red">is simple</span></h2>
					<p class="home-how__desc">From your intake to provider review, each step is built for clear, guided care.</p>
			</div>
			<div class="home-how__steps">
				<?php foreach ( $steps as $step ) : ?>
				<div class="hp-step">
					<div class="hp-step__img-wrap">
						<img src="<?php echo $img_url( $step['img'] ); ?>" alt="<?php echo esc_attr( $step['title'] ); ?>" class="hp-step__img" loading="lazy" width="400" height="300">
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

	<!-- Common Questions -->
	<?php if ( $slug === 'testosterone' ) : ?>
	<section class="myo-faq" id="faq">
		<div class="trt-pdp__section-bg" style="background-image:url('<?php echo $img_url( 'grunge-redesign/thin section bg.png' ); ?>')" aria-hidden="true"></div>
		<div class="myo-faq__wrap">
			<div class="myo-faq__header">
				<span class="myo-faq__eyebrow">Support</span>
				<h2 class="myo-faq__title">
					<span class="grunge-word grunge-word--white">Fast answers.</span>
					<span class="grunge-word grunge-word--red">Guided support.</span>
				</h2>
				<p class="myo-faq__desc">We are here to guide you through every step and answer questions before, during, and after your treatment.</p>
			</div>
			<div class="myo-faq__list">
				<?php
				$trt_faqs = [
					[ 'q' => 'What happens after I pay?', 'a' => 'After checkout, complete the medical questionnaire. If you need bloodwork, you receive Quest instructions by email so you can schedule locally.' ],
					[ 'q' => 'How does Quest scheduling work?', 'a' => 'You create or sign in to Quest, enter your ZIP code, choose a nearby location, and select an available date and time. There is nothing to pay at Quest.' ],
					[ 'q' => 'Can I use my own labs?', 'a' => 'Yes. Choose the with-labs option if you already have recent labs. You will upload them after payment so your provider can review them with your questionnaire.' ],
					[ 'q' => 'When is testosterone shipped?', 'a' => 'Only after your questionnaire and lab information are reviewed and a licensed provider determines treatment is appropriate.' ],
				];
				foreach ( $trt_faqs as $idx => $item ) :
					$panel_id   = 'trt-pdp-faq-' . $idx;
					$is_first   = ( $idx === 0 );
					$expanded   = $is_first ? 'true' : 'false';
					$open_class = $is_first ? ' is-open' : '';
				?>
				<div class="myo-faq__item">
					<button class="myo-faq__btn" type="button" aria-expanded="<?php echo esc_attr( $expanded ); ?>" aria-controls="<?php echo esc_attr( $panel_id ); ?>">
						<span class="myo-faq__q"><?php echo esc_html( $item['q'] ); ?></span>
						<span class="myo-faq__icon" aria-hidden="true">+</span>
					</button>
					<div class="myo-faq__panel<?php echo esc_attr( $open_class ); ?>" id="<?php echo esc_attr( $panel_id ); ?>">
						<div class="myo-faq__panel-inner">
							<p><?php echo esc_html( $item['a'] ); ?></p>
						</div>
					</div>
				</div>
				<?php endforeach; ?>
				<div class="myo-faq__cta">
					<a href="#build-plan" class="myo-faq__cta-btn">Continue to checkout</a>
					<a href="<?php echo esc_url( home_url( '/quest-faqs/' ) ); ?>" class="myo-faq__cta-btn myo-faq__cta-btn--dark">Quest FAQs</a>
					<a href="<?php echo esc_url( home_url( '/reach-a-concierge/' ) ); ?>" class="myo-faq__cta-btn myo-faq__cta-btn--dark">Ask a question</a>
				</div>
				<p class="trt-pdp__faq-disclaimer">Prescription required if approved. Plan review by licensed provider.</p>
			</div>
		</div>
	</section>
	<?php else : ?>
	<?php myogenix_render_product_faq( $product->get_id() ); ?>
	<?php endif; ?>

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
