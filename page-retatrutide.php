<?php
/**
 * Template Name: Retatrutide PDP
 * Description: Password-gated product page for Retatrutide
 */

defined( 'ABSPATH' ) || exit;

// ─── Password gate ────────────────────────────────────────────────────────────
// Passwords are keyed by location slug → display name.
// Add entries here to grant access to new clinic locations.
$gate_passwords  = [
	'legacytrainingcenter' => 'Legacy Training Center',
];
$gate_cookie = 'mgx_rtd_access';

$authenticated = false;
if ( ! empty( $_COOKIE[ $gate_cookie ] ) ) {
	foreach ( array_keys( $gate_passwords ) as $pw ) {
		if ( hash_equals( wp_hash( $pw ), $_COOKIE[ $gate_cookie ] ) ) {
			$authenticated = true;
			break;
		}
	}
}

if ( ! $authenticated ) {
	$gate_error = ! empty( $GLOBALS['retatrutide_gate_error'] );
	?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Retatrutide — Provider Access Required</title>
<?php wp_head(); ?>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}
body{background:#080d18;color:#fff;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh}
.rtd-gate{width:100%;max-width:420px;padding:0 24px}
.rtd-gate__logo{display:block;margin:0 auto 40px;text-align:center}
.rtd-gate__logo img{max-width:160px;height:auto}
.rtd-gate__logo-text{font-size:22px;font-weight:700;letter-spacing:.04em;color:#fff}
.rtd-gate__badge{display:inline-block;background:rgba(99,179,237,.15);border:1px solid rgba(99,179,237,.35);color:#63b3ed;font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;padding:4px 10px;border-radius:20px;margin-bottom:24px}
.rtd-gate__heading{font-size:28px;font-weight:700;line-height:1.2;margin-bottom:10px}
.rtd-gate__sub{font-size:15px;color:#8a9bbb;line-height:1.6;margin-bottom:32px}
.rtd-gate__label{display:block;font-size:12px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#8a9bbb;margin-bottom:8px}
.rtd-gate__input{width:100%;background:#0f1829;border:1px solid #1e2d47;border-radius:8px;padding:14px 16px;font-size:15px;color:#fff;outline:none;transition:border-color .2s}
.rtd-gate__input:focus{border-color:#3b7dd8}
.rtd-gate__input::placeholder{color:#3a4a64}
.rtd-gate__error{margin-top:10px;font-size:13px;color:#fc8181;display:flex;align-items:center;gap:6px}
.rtd-gate__btn{display:block;width:100%;margin-top:20px;padding:15px;background:#3b7dd8;border:none;border-radius:8px;font-size:15px;font-weight:600;color:#fff;cursor:pointer;transition:background .2s,transform .1s}
.rtd-gate__btn:hover{background:#2f6bc2}
.rtd-gate__btn:active{transform:scale(.98)}
.rtd-gate__footer{margin-top:28px;font-size:13px;color:#4a5a7a;text-align:center}
@media(max-width:480px){.rtd-gate__heading{font-size:24px}}
</style>
</head>
<body>
<div class="rtd-gate">

	<div class="rtd-gate__logo">
		<?php
		$logo_html = get_custom_logo();
		if ( $logo_html ) {
			echo $logo_html;
		} else {
			echo '<span class="rtd-gate__logo-text">' . esc_html( get_bloginfo( 'name' ) ) . '</span>';
		}
		?>
	</div>

	<span class="rtd-gate__badge">Provider Access Required</span>
	<h1 class="rtd-gate__heading">Restricted Treatment Program</h1>
	<p class="rtd-gate__sub">This page is for patients referred by a licensed provider partner. Enter the access code provided by your clinic to continue.</p>

	<form method="post" action="">
		<?php wp_nonce_field( 'retatrutide_gate', 'retatrutide_nonce' ); ?>
		<label class="rtd-gate__label" for="rtd-pw">Access Code</label>
		<input id="rtd-pw" class="rtd-gate__input" type="password" name="retatrutide_pw"
			placeholder="Enter your clinic access code" autocomplete="current-password" required>
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
<?php wp_footer(); ?>
</body>
</html>
	<?php
	return;
}

// ─── PDP (authenticated) ──────────────────────────────────────────────────────
$rtd_products = wc_get_products( [
	'slug'   => 'compound-retatrutide',
	'status' => 'publish',
	'limit'  => 1,
] );

if ( empty( $rtd_products ) ) {
	get_header();
	echo '<div style="padding:80px 24px;text-align:center"><p>This product is not yet available. Please check back soon.</p></div>';
	get_footer();
	return;
}

$product = $rtd_products[0];

// Build price matrix and variation map (mirrors content-single-product.php logic)
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

get_header();

// Suppress the WC "Please choose product options" notice
remove_action( 'woocommerce_before_single_product', 'woocommerce_output_all_notices', 10 );
do_action( 'woocommerce_before_single_product' );

?>

<div id="product-<?php echo esc_attr( $product->get_id() ); ?>" class="myogenix-pdp retatrutide-pdp">

	<!-- Product Hero -->
	<section class="pdp-hero" id="buy">
		<div class="pdp-hero__inner">

			<!-- Left: product info + image -->
			<div class="pdp-hero__left">
				<span class="pdp-hero__badge">GIP/GLP-1/Glucagon Triple Agonist</span>
				<h1 class="pdp-hero__title">Retatrutide</h1>
				<p class="pdp-hero__desc">Retatrutide is the first triple agonist — simultaneously activating GIP, GLP-1, and glucagon receptors for powerful metabolic effects with once-weekly dosing.</p>
				<ul class="pdp-hero__bullets">
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

			<!-- Right: configurator -->
			<div class="pdp-hero__right">

				<!-- Hidden WC hooks — preserves plugin integrations -->
				<div style="display:none" aria-hidden="true" inert>
					<?php
					global $post;
					$prev_post = $post;
					$post      = get_post( $product->get_id() );
					setup_postdata( $post );
					do_action( 'woocommerce_before_single_product_summary' );
					do_action( 'woocommerce_single_product_summary' );
					wp_reset_postdata();
					$post = $prev_post;
					?>
				</div>

				<!-- Custom configurator — drives pdp.js -->
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
					<!-- Supply Length (BYO only — no package type selector) -->
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

					<!-- Dose reference table -->
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
								<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
								<line x1="9" y1="11" x2="15" y2="11"/><line x1="9" y1="15" x2="13" y2="15"/>
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
								<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
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
								<rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 3v5h-7V8z"/>
								<circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
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
								<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/>
								<line x1="6" y1="20" x2="6" y2="14"/><line x1="2" y1="20" x2="22" y2="20"/>
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

	<!-- Common Questions -->
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
								<p>Retatrutide is the first triple agonist, simultaneously activating three receptors: GIP, GLP-1, and glucagon. Semaglutide targets only GLP-1; tirzepatide targets GIP and GLP-1. By adding glucagon receptor activation, retatrutide further increases energy expenditure on top of the appetite suppression provided by the other two pathways — making it one of the most potent metabolic agents in clinical development.</p>
							</div>
						</div>

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="false" aria-controls="rtd-cq-1">
								<span>How does dosing escalation work for retatrutide?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer" id="rtd-cq-1">
								<p>A typical escalation starts at 1–2 mg per week and increases gradually every 4 weeks based on tolerance and clinical response. Doses range from 1 mg/week up to 12 mg/week. Each vial contains a 4-week supply at the selected weekly dose. Your provider reviews your progress at each stage and adjusts accordingly.</p>
							</div>
						</div>

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="false" aria-controls="rtd-cq-2">
								<span>Why is access to this program restricted?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer" id="rtd-cq-2">
								<p>Retatrutide is a newer compound with a higher potency profile. We currently offer it exclusively through verified clinic partner referrals to ensure every patient has appropriate clinical oversight. This allows us to provide tighter monitoring during dose escalation and maintain the highest standard of care.</p>
							</div>
						</div>

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="false" aria-controls="rtd-cq-3">
								<span>Do I need a new consultation for refills?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer" id="rtd-cq-3">
								<p>No. Once your treatment plan is established, refills ship automatically on your chosen schedule. Your provider may request a brief check-in every few months to review progress and adjust dosing if needed — but there's no new full consultation required for standard refills.</p>
							</div>
						</div>

						<div class="myogenix-pdp__cq-item">
							<button class="myogenix-pdp__cq-question" aria-expanded="false" aria-controls="rtd-cq-4">
								<span>How is this compounded and where does it ship from?</span>
								<span class="myogenix-pdp__cq-icon" aria-hidden="true">+</span>
							</button>
							<div class="myogenix-pdp__cq-answer" id="rtd-cq-4">
								<p>Your retatrutide is compounded by a licensed U.S. FDA-registered 503A compounding pharmacy as a sterile injectable solution. It ships directly to your door in temperature-controlled packaging along with syringes and all necessary supplies.</p>
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
