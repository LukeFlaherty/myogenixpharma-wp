<?php
/**
 * Template Name: Retatrutide PDP
 * Description: Password-gated product page for Retatrutide
 */

defined( 'ABSPATH' ) || exit;

// ─── Password gate ────────────────────────────────────────────────────────────
// Authentication is request-scoped only — no cookie, password required every load.
// The template_redirect hook in functions.php verifies the POST and sets this global.
$authenticated = ! empty( $GLOBALS['retatrutide_authenticated'] );

// ─── Load product by slug (direct post query avoids catalog-visibility filter) ─
$rtd_post   = get_page_by_path( 'compound-retatrutide', OBJECT, 'product' );
$rtd_prod_id = $rtd_post ? (int) $rtd_post->ID : 0;

// ─── Gate view ────────────────────────────────────────────────────────────────
if ( ! $authenticated ) {
	$gate_error = ! empty( $GLOBALS['retatrutide_gate_error'] );
	get_header();
	?>
	<div class="rtd-gate-section">
		<div class="rtd-gate-card">
			<span class="rtd-gate__badge">Provider-Referred Patients Only</span>
			<h1 class="rtd-gate__heading">Access Required</h1>
			<p class="rtd-gate__sub">This treatment program is available exclusively to patients referred by a licensed clinic partner. Enter the access code provided by your provider to continue.</p>

			<form class="rtd-gate__form" method="post" action="">
				<?php wp_nonce_field( 'retatrutide_gate', 'retatrutide_nonce' ); ?>
				<label class="rtd-gate__label" for="rtd-pw">Clinic Access Code</label>
				<input id="rtd-pw" class="rtd-gate__input" type="password" name="retatrutide_pw"
					placeholder="Enter your access code" autocomplete="current-password" required>
				<?php if ( $gate_error ) : ?>
				<p class="rtd-gate__error">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
					Incorrect access code. Please try again.
				</p>
				<?php endif; ?>
				<button class="rtd-gate__btn" type="submit">Continue &rarr;</button>
			</form>

			<p class="rtd-gate__footer">Not a referred patient? Contact your healthcare provider to receive access.</p>
		</div>
	</div>
	<?php
	get_footer();
	return;
}

// ─── PDP (authenticated) ──────────────────────────────────────────────────────
if ( ! $rtd_prod_id ) {
	get_header();
	echo '<div style="padding:80px 24px;text-align:center"><p>This product is not yet available. Please check back soon.</p></div>';
	get_footer();
	return;
}

get_header();

// Re-fetch after get_header() — WC/Elementor hooks inside the header can
// overwrite the global $product variable; re-loading here keeps us safe.
$product = wc_get_product( $rtd_prod_id );

// Bundle simple products
$step_up_post = get_page_by_path( 'compound-retatrutide-step-up', OBJECT, 'product' );
$step_up_id   = $step_up_post ? (int) $step_up_post->ID : 0;
$phase2_post  = get_page_by_path( 'compound-retatrutide-phase-2', OBJECT, 'product' );
$phase2_id    = $phase2_post ? (int) $phase2_post->ID : 0;

$step_up_url = $step_up_id ? esc_url( add_query_arg( [
	'add-to-cart'  => $step_up_id,
	'quantity'     => 1,
	'dose_month_1' => '4-mg',
	'dose_month_2' => '8-mg',
	'dose_month_3' => '16-mg',
], wc_get_cart_url() ) ) : '#';

$phase2_url = $phase2_id ? esc_url( add_query_arg( [
	'add-to-cart'  => $phase2_id,
	'quantity'     => 1,
	'dose_month_1' => '24-mg',
	'dose_month_2' => '32-mg',
], wc_get_cart_url() ) ) : '#';

if ( ! $product ) {
	echo '<div style="padding:80px 24px;text-align:center"><p>Product could not be loaded. Please try again.</p></div>';
	get_footer();
	return;
}

// Build price matrix and variation map
$attrs           = $product->get_attributes();
$dose_attr_key   = isset( $attrs['pa_individual-dose'] ) ? 'pa_individual-dose' : 'pa_dosage';
$dose_meta_key   = 'attribute_' . $dose_attr_key;
$bottle_attr_key = isset( $attrs['pa_wm-bottle'] ) ? 'pa_wm-bottle' : 'pa_vial';
$bottle_meta_key = 'attribute_' . $bottle_attr_key;
$raw_to_norm     = [
	'1-vial' => '1-bottle', '2-vial' => '2-bottle', '3-vial' => '3-bottle',
	'1-bottle' => '1-bottle', '2-bottle' => '2-bottle', '3-bottle' => '3-bottle',
];
$norm_to_raw = [
	'1-bottle' => $bottle_attr_key === 'pa_vial' ? '1-vial' : '1-bottle',
	'2-bottle' => $bottle_attr_key === 'pa_vial' ? '2-vial' : '2-bottle',
	'3-bottle' => $bottle_attr_key === 'pa_vial' ? '3-vial' : '3-bottle',
];

$price_matrix  = [];
$variation_map = [];
foreach ( $product->get_children() as $vid ) {
	$v = wc_get_product( $vid );
	if ( ! $v || 'publish' !== get_post_status( $vid ) ) continue;
	$dose       = get_post_meta( $vid, $dose_meta_key, true );
	$bottle_raw = get_post_meta( $vid, $bottle_meta_key, true );
	$bottle     = $raw_to_norm[ $bottle_raw ] ?? null;
	$plan       = get_post_meta( $vid, 'attribute_pa_wm-subscription-plan', true );
	if ( ! $dose || ! $bottle ) continue;
	$price = (float) $v->get_price();
	if ( $price > 0 && ! isset( $price_matrix[ $dose ][ $bottle ] ) ) {
		$price_matrix[ $dose ][ $bottle ] = $price;
	}
	$variation_map[ $dose ][ $bottle ][ $plan ?: '' ] = (int) $vid;
}

$dosage_terms = isset( $attrs[ $dose_attr_key ] ) ? ( $attrs[ $dose_attr_key ]->get_terms() ?: [] ) : [];
$dose_labels  = [];
foreach ( $dosage_terms as $t ) {
	$dose_labels[ $t->slug ] = $t->name;
}
$wc_doses = array_values( array_filter(
	array_map( fn( $t ) => $t->slug, $dosage_terms ),
	fn( $d ) => isset( $variation_map[ $d ] ) || isset( $price_matrix[ $d ] )
) );
usort( $wc_doses, fn( $a, $b ) => (float) $a - (float) $b );

$first_dose = ! empty( $wc_doses ) ? $wc_doses[0] : '';
$sp         = [
	$price_matrix[ $first_dose ]['1-bottle'] ?? 299.00,
	$price_matrix[ $first_dose ]['2-bottle'] ?? 449.00,
	$price_matrix[ $first_dose ]['3-bottle'] ?? 599.00,
];

$image_id  = $product->get_image_id();
$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';

$img_url = function( $path ) {
	$base  = get_stylesheet_directory_uri() . '/assets/images/';
	$parts = explode( '/', $path );
	return esc_url( $base . implode( '/', array_map( 'rawurlencode', $parts ) ) );
};

$steps = [
	[ 'num' => 'PDP Sections/1.png', 'img' => 'PDP Sections/form.png',         'title' => 'Questionnaire',                'desc' => 'Answer a few questions and share your medical details' ],
	[ 'num' => 'PDP Sections/2.png', 'img' => 'PDP Sections/consultation.png', 'title' => 'Review and Approved by provider', 'desc' => 'Discuss your goals and receive expert recommendations' ],
	[ 'num' => 'PDP Sections/3.png', 'img' => 'PDP Sections/box.png',          'title' => 'Receive medication',             'desc' => 'Medication and supplies shipped straight to your door' ],
	[ 'num' => 'PDP Sections/4.png', 'img' => 'PDP Sections/calendar.png',     'title' => 'Monthly Monitoring',             'desc' => 'Stay on track with regular free check-ins to ensure progress' ],
];

remove_action( 'woocommerce_before_single_product', 'woocommerce_output_all_notices', 10 );
do_action( 'woocommerce_before_single_product' );
?>

<div id="product-<?php echo esc_attr( $product->get_id() ); ?>" class="myogenix-pdp retatrutide-pdp">

	<!-- Product Hero -->
	<section class="pdp-hero" id="buy">
		<div class="pdp-hero__inner">

			<div class="pdp-hero__left">
				<span class="pdp-hero__badge">GIP/GLP-1/Glucagon Triple Agonist</span>
				<h1 class="pdp-hero__title">Retatrutide</h1>
				<p class="pdp-hero__desc">Retatrutide is the first triple agonist — simultaneously activating GIP, GLP-1, and glucagon receptors for powerful metabolic effects with once-weekly dosing.</p>
				<ul class="pdp-hero__bullets retatrutide-pdp__bullets">
					<li>Compounded &middot; FDA-registered facility</li>
					<li>Provider-reviewed &middot; Clinic-referred patients only</li>
				</ul>

				<?php if ( $image_url ) : ?>
				<div class="pdp-hero__image-card">
					<img src="<?php echo esc_url( $image_url ); ?>" alt="Retatrutide" loading="lazy" />
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

				<div id="pdp-cfg" class="pdp-cfg"
					data-doses="<?php echo esc_attr( wp_json_encode( $wc_doses ) ); ?>"
					data-price-matrix="<?php echo esc_attr( wp_json_encode( $price_matrix ) ); ?>"
					data-variation-map="<?php echo esc_attr( wp_json_encode( $variation_map ) ); ?>"
					data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
					data-dose-attr="<?php echo esc_attr( $dose_meta_key ); ?>"
					data-dose-labels="<?php echo esc_attr( wp_json_encode( $dose_labels ) ); ?>"
					data-warning-threshold="4"
					data-bottle-attr="<?php echo esc_attr( $bottle_meta_key ); ?>"
					data-bottle-slug-map="<?php echo esc_attr( wp_json_encode( $norm_to_raw ) ); ?>"
					data-starter-variation-id="0"
					data-starter-price="0"
					data-starter-dose-slug=""
					data-continuation-variation-id="0"
					data-continuation-price="0"
					data-continuation-dose-slug=""
				>

					<!-- ── Program type selector ── -->
					<p class="pdp-cfg__section-label">Program</p>
					<div class="rtd-pkg-row">
						<button class="rtd-pkg rtd-pkg--active" data-pkg="step-up">
							<strong>Step Up Bundle</strong>
							<span>Mos 1&ndash;3 &middot; $950</span>
						</button>
						<button class="rtd-pkg" data-pkg="phase-2">
							<strong>Phase 2</strong>
							<span>Mos 4&ndash;5 &middot; $1,175</span>
						</button>
						<button class="rtd-pkg" data-pkg="byo">
							<strong>Build Your Own</strong>
							<span>Choose dose &amp; supply</span>
						</button>
					</div>

					<!-- ── Step Up Bundle panel ── -->
					<div id="rtd-panel-step-up" class="rtd-bundle-panel">
						<p class="rtd-bundle-panel__title">Starter escalation protocol — 3 vials</p>
						<div class="rtd-bundle-panel__months">
							<div class="rtd-bundle-panel__month">
								<span class="rtd-bundle-panel__month-label">Month 1</span>
								<span class="rtd-bundle-panel__month-dose">4 mg vial</span>
								<span class="rtd-bundle-panel__month-weekly">1 mg / week</span>
							</div>
							<div class="rtd-bundle-panel__month">
								<span class="rtd-bundle-panel__month-label">Month 2</span>
								<span class="rtd-bundle-panel__month-dose">8 mg vial</span>
								<span class="rtd-bundle-panel__month-weekly">2 mg / week</span>
							</div>
							<div class="rtd-bundle-panel__month">
								<span class="rtd-bundle-panel__month-label">Month 3</span>
								<span class="rtd-bundle-panel__month-dose">16 mg vial</span>
								<span class="rtd-bundle-panel__month-weekly">4 mg / week</span>
							</div>
						</div>
						<div class="rtd-bundle-panel__price">$950 <span>/ 3 months</span></div>
						<?php if ( $step_up_id ) : ?>
						<a href="<?php echo $step_up_url; ?>" class="pdp-cfg__cta rtd-bundle-atc">Go to Checkout &rarr;</a>
						<?php else : ?>
						<p class="pdp-cfg__disclaimer">Bundle temporarily unavailable — please use Build Your Own.</p>
						<?php endif; ?>
						<p class="pdp-cfg__disclaimer">One-time purchase. Order reviewed by a licensed provider before processing.</p>
					</div>

					<!-- ── Phase 2 Bundle panel ── -->
					<div id="rtd-panel-phase-2" class="rtd-bundle-panel" hidden>
						<p class="rtd-bundle-panel__title">Continued escalation — 2 vials</p>
						<div class="rtd-bundle-panel__months">
							<div class="rtd-bundle-panel__month">
								<span class="rtd-bundle-panel__month-label">Month 4</span>
								<span class="rtd-bundle-panel__month-dose">24 mg vial</span>
								<span class="rtd-bundle-panel__month-weekly">6 mg / week</span>
							</div>
							<div class="rtd-bundle-panel__month">
								<span class="rtd-bundle-panel__month-label">Month 5</span>
								<span class="rtd-bundle-panel__month-dose">32 mg vial</span>
								<span class="rtd-bundle-panel__month-weekly">8 mg / week</span>
							</div>
						</div>
						<div class="rtd-bundle-panel__price">$1,175 <span>/ 2 months</span></div>
						<?php if ( $phase2_id ) : ?>
						<a href="<?php echo $phase2_url; ?>" class="pdp-cfg__cta rtd-bundle-atc">Go to Checkout &rarr;</a>
						<?php else : ?>
						<p class="pdp-cfg__disclaimer">Bundle temporarily unavailable — please use Build Your Own.</p>
						<?php endif; ?>
						<p class="pdp-cfg__disclaimer">One-time purchase. Order reviewed by a licensed provider before processing.</p>
					</div>

					<!-- ── BYO section (hidden by default) ── -->
					<div id="rtd-byo-section" hidden>
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

						<p class="pdp-cfg__section-label" id="pdp-dose-label">Month 1 Dose</p>
						<div id="pdp-dose" class="pdp-cfg__doses-wrap"></div>

						<div class="rtd-dose-ref">
							<p class="rtd-dose-ref__label">Dose Reference</p>
							<table class="rtd-dose-ref__table">
								<thead><tr><th>Vial</th><th>Weekly Dose</th></tr></thead>
								<tbody>
									<tr><td>4 mg</td><td>1 mg / week</td></tr>
									<tr><td>8 mg</td><td>2 mg / week</td></tr>
									<tr><td>16 mg</td><td>4 mg / week</td></tr>
									<tr><td>24 mg</td><td>6 mg / week</td></tr>
									<tr><td>32 mg</td><td>8 mg / week</td></tr>
									<tr><td>48 mg</td><td>12 mg / week</td></tr>
								</tbody>
							</table>
						</div>

						<div id="pdp-summary" class="pdp-cfg__summary"></div>
						<button id="pdp-cta" class="pdp-cfg__cta">Go to Checkout &rarr;</button>
						<p id="pdp-disclaimer" class="pdp-cfg__disclaimer">
							This is a one-time purchase. Your order will be reviewed by a licensed provider before processing.
						</p>
					</div>

				</div>

				<script>
				(function () {
					var pkgBtns  = document.querySelectorAll( '.rtd-pkg' );
					var panels   = {
						'step-up': document.getElementById( 'rtd-panel-step-up' ),
						'phase-2': document.getElementById( 'rtd-panel-phase-2' ),
						'byo':     document.getElementById( 'rtd-byo-section' ),
					};

					function activate( pkg ) {
						pkgBtns.forEach( function ( b ) {
							b.classList.toggle( 'rtd-pkg--active', b.dataset.pkg === pkg );
						} );
						Object.keys( panels ).forEach( function ( key ) {
							panels[ key ].hidden = ( key !== pkg );
						} );
					}

					pkgBtns.forEach( function ( btn ) {
						btn.addEventListener( 'click', function () { activate( this.dataset.pkg ); } );
					} );

					// Default: Step Up Bundle
					activate( 'step-up' );
				}());
				</script>

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

	<!-- FAQ -->
	<section class="myogenix-pdp__cq">
		<div class="myogenix-pdp__container">
			<div class="myogenix-pdp__cq-inner">

				<div class="myogenix-pdp__cq-left">
					<p class="myogenix-pdp__cq-label">FAQ</p>
					<h2 class="myogenix-pdp__cq-heading">Common questions</h2>
					<p class="myogenix-pdp__cq-sub">Everything you need to know about retatrutide, dosing, and ordering.</p>
					<a href="#buy" class="myogenix-pdp__cq-btn">Configure your program &rarr;</a>
				</div>

				<div class="myogenix-pdp__cq-right">
					<div class="myogenix-pdp__cq-list">

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="true" aria-controls="rtd-cq-0">
								<span>What is retatrutide and how does it differ from semaglutide and tirzepatide?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer is-open" id="rtd-cq-0">
								<p>Retatrutide is the first triple agonist, simultaneously activating GIP, GLP-1, and glucagon receptors. Semaglutide targets only GLP-1; tirzepatide targets GIP and GLP-1. By adding glucagon receptor activation, retatrutide further increases energy expenditure — making it one of the most potent metabolic agents in clinical development.</p>
							</div>
						</div>

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="false" aria-controls="rtd-cq-1">
								<span>How does dosing escalation work for retatrutide?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer" id="rtd-cq-1">
								<p>Typical escalation starts at 1–2 mg/week and increases gradually every 4 weeks based on tolerance. Doses range from 1 mg/week up to 12 mg/week. Each vial contains a 4-week supply at the selected weekly dose. Your provider reviews progress at each stage and adjusts accordingly.</p>
							</div>
						</div>

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="false" aria-controls="rtd-cq-2">
								<span>Why is access to this program restricted?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer" id="rtd-cq-2">
								<p>Retatrutide is a newer compound with a higher potency profile. We currently offer it exclusively through verified clinic partner referrals to ensure every patient has appropriate clinical oversight and tighter monitoring during dose escalation.</p>
							</div>
						</div>

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="false" aria-controls="rtd-cq-3">
								<span>Do I need a new consultation for refills?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer" id="rtd-cq-3">
								<p>No. Once your treatment plan is established, refills ship on your chosen schedule. Your provider may request a brief check-in every few months, but no new full consultation is required for standard refills.</p>
							</div>
						</div>

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="false" aria-controls="rtd-cq-4">
								<span>How is this compounded and where does it ship from?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer" id="rtd-cq-4">
								<p>Your retatrutide is compounded by a licensed U.S. FDA-registered 503A pharmacy as a sterile injectable solution. It ships in temperature-controlled packaging with syringes and all necessary supplies.</p>
							</div>
						</div>

					</div>
				</div>
			</div>
		</div>
	</section>

	<!-- Explore More -->
	<section class="myogenix-pdp__explore">
		<div class="myogenix-pdp__container">
			<h2 class="myogenix-pdp__section-heading">Explore More Treatment Lines</h2>
			<p class="myogenix-pdp__section-sub">The telehealth provider of choice for holistic care.</p>
			<div class="myogenix-pdp__explore-grid">
				<a href="<?php echo esc_url( home_url( '/weight-loss/' ) ); ?>" class="myogenix-pdp__explore-link">
					<img src="<?php echo $img_url( 'PDP Sections/mens health.png' ); ?>" alt="Weight Management" />
				</a>
				<a href="<?php echo esc_url( home_url( '/womens-health/' ) ); ?>" class="myogenix-pdp__explore-link">
					<img src="<?php echo $img_url( 'PDP Sections/womens health.png' ); ?>" alt="Women's Health" />
				</a>
			</div>
		</div>
	</section>

</div>

<?php do_action( 'woocommerce_after_single_product' ); ?>

<?php
get_footer();
