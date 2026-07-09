<?php
/**
 * Front page template — custom home page (bypasses Elementor).
 * To revert: delete this file and push. Elementor re-takes control immediately.
 */
defined( 'ABSPATH' ) || exit;

// ── Product data ──────────────────────────────────────────────────────────────
$hp_ids = [
	'tirzepatide'  => 4063,
	'semaglutide'  => 4041,
	'wolverine'    => 2606,
	'tesamorelin'  => 2803,
	'klow'         => 2819,
	'glow'         => 1868,
	'bpc'          => 4249,
	'motsc'        => 4253,
	'epithalon'    => 4257,
	'tadalafil'    => 1886,
	'sildenafil'   => 1883,
	'testosterone' => 883,
];

$hp_meta = [
	'tirzepatide'  => [ 'name' => 'Tirzepatide',   'tagline' => 'Dual-action GLP-1 therapy',    'unit' => '/mo'   ],
	'semaglutide'  => [ 'name' => 'Semaglutide',   'tagline' => 'Proven GLP-1 therapy',         'unit' => '/mo'   ],
	'wolverine'    => [ 'name' => 'Wolverine',      'tagline' => 'Elite tissue recovery',        'unit' => '/vial' ],
	'tesamorelin'  => [ 'name' => 'Tesamorelin',    'tagline' => 'GH optimization',              'unit' => '/vial' ],
	'klow'         => [ 'name' => 'Klow',           'tagline' => 'Metabolic support',            'unit' => '/vial' ],
	'glow'         => [ 'name' => 'Glow',           'tagline' => 'Longevity & renewal',          'unit' => '/vial' ],
	'bpc'          => [ 'name' => 'BPC-157',        'tagline' => 'Healing & repair',             'unit' => '/vial' ],
	'motsc'        => [ 'name' => 'MOTSc',          'tagline' => 'Mitochondrial health',         'unit' => '/vial' ],
	'epithalon'    => [ 'name' => 'Epithalon',      'tagline' => 'Longevity peptide',            'unit' => '/vial' ],
	'tadalafil'    => [ 'name' => 'Tadalafil',      'tagline' => 'Daily ED support',             'unit' => '/tablet', 'tablets_supply' => 90 ],
	'sildenafil'   => [ 'name' => 'Sildenafil',     'tagline' => 'Fast-acting ED treatment',     'unit' => '/mo'   ],
	'testosterone' => [ 'name' => 'Testosterone',   'tagline' => 'Hormone optimization',         'unit' => '/mo'   ],
];

$hp_products = [];
foreach ( $hp_ids as $key => $id ) {
	$wc = wc_get_product( $id );
	if ( ! $wc ) continue;
	$raw_price = (float) $wc->get_price();
	// Variable subscriptions billed every N months carry a lump-sum price; normalise to per-month.
	if ( $wc->is_type( 'variable-subscription' ) && class_exists( 'WC_Subscriptions_Product' ) ) {
		$min_var_id = $wc->get_meta( '_min_price_variation_id' );
		if ( $min_var_id ) {
			$interval = (int) WC_Subscriptions_Product::get_interval( $min_var_id );
			if ( $interval > 1 && 'month' === WC_Subscriptions_Product::get_period( $min_var_id ) ) {
				$raw_price = $raw_price / $interval;
			}
		}
	}
	// Tablet-count products (e.g. Sildenafil): each variant is N tablets = N/30 months.
	// Find the minimum per-month price across all variations rather than using the overall minimum.
	if ( $key === 'sildenafil' && $wc->is_type( 'variable' ) ) {
		$min_per_month = PHP_FLOAT_MAX;
		foreach ( $wc->get_children() as $vid ) {
			$v = wc_get_product( $vid );
			if ( ! $v || 'publish' !== get_post_status( $vid ) ) continue;
			$price      = (float) $v->get_price();
			if ( $price <= 0 ) continue;
			$tab_slug   = get_post_meta( $vid, 'attribute_pa_tablets', true );
			$tab_count  = (int) $tab_slug;
			$var_months = $tab_count > 0 ? $tab_count / 30 : 1;
			$per_month  = $price / $var_months;
			if ( $per_month < $min_per_month ) $min_per_month = $per_month;
		}
		if ( $min_per_month < PHP_FLOAT_MAX ) $raw_price = $min_per_month;
	}

	// Non-subscription products sold as a multi-month supply use months_supply in $hp_meta.
	$months = isset( $hp_meta[ $key ]['months_supply'] ) ? (int) $hp_meta[ $key ]['months_supply'] : 1;
	if ( $months > 1 ) {
		$raw_price = $raw_price / $months;
	}
	// Non-subscription products sold as a fixed tablet count use tablets_supply in $hp_meta.
	$tablets_supply = isset( $hp_meta[ $key ]['tablets_supply'] ) ? (int) $hp_meta[ $key ]['tablets_supply'] : 0;
	if ( $tablets_supply > 0 ) {
		$raw_price = $raw_price / $tablets_supply;
	}
	$hp_products[ $key ] = [
		'price' => $raw_price,
		'url'   => $wc->get_permalink(),
		'image' => get_the_post_thumbnail_url( $id, 'large' ) ?: get_the_post_thumbnail_url( $id, 'full' ) ?: '',
	];
}

// ── Helpers ───────────────────────────────────────────────────────────────────
if ( ! function_exists( 'hp_product_card' ) ) {
	function hp_product_card( string $key, array $products, array $meta ): string {
		if ( empty( $products[ $key ] ) ) return '';
		$p        = $products[ $key ];
		$m        = $meta[ $key ];
		$decimals = ( '/tablet' === $m['unit'] ) ? 2 : 0;
		$price    = '$' . number_format( $p['price'], $decimals );
		return sprintf(
			'<a href="%s" class="hp-card" aria-label="%s">
				<div class="hp-card__img-wrap">
					<img src="%s" alt="%s" class="hp-card__img" loading="lazy" width="176" height="176">
				</div>
				<div class="hp-card__body">
					<div class="hp-card__name">%s</div>
					<div class="hp-card__tag">%s</div>
					<div class="hp-card__foot">
						<span class="hp-card__price">%s<span class="hp-card__unit">%s</span></span>
						<span class="hp-card__btn" aria-hidden="true">Shop →</span>
					</div>
				</div>
			</a>',
			esc_url( $p['url'] ),
			esc_attr( $m['name'] ),
			esc_url( $p['image'] ),
			esc_attr( $m['name'] ),
			esc_html( $m['name'] ),
			esc_html( $m['tagline'] ),
			esc_html( $price ),
			esc_html( $m['unit'] )
		);
	}
}

// ── Category boxes ─────────────────────────────────────────────────────────────
$hp_categories = [
	[
		'title'    => 'Mens Health',
		'shop_url' => '/product-category/mens-health/',
		'products' => [ 'testosterone', 'tadalafil', 'sildenafil' ],
	],
	[
		'title'    => 'Weight Loss',
		'shop_url' => '/product-category/weight-loss/',
		'products' => [ 'tirzepatide', 'semaglutide' ],
	],
	[
		'title'    => 'Peptides',
		'shop_url' => '/product-category/peptides-longevity/',
		'products' => [ 'wolverine', 'tesamorelin', 'klow', 'glow', 'bpc', 'motsc', 'epithalon' ],
		'full'     => true,
	],
];

// ── New arrivals ───────────────────────────────────────────────────────────────
$hp_arrivals = [
	[
		'key'     => 'wolverine',
		'generic' => 'Recovery Peptide Blend',
		'benefit' => 'Accelerated healing, joint & tendon repair, anti-inflammatory.',
	],
	[
		'key'     => 'tesamorelin',
		'generic' => 'GHRH Analogue',
		'benefit' => 'Visceral fat reduction, GH optimization, lean mass support.',
	],
	[
		'key'     => 'glow',
		'generic' => 'Longevity & Renewal Blend',
		'benefit' => 'Cellular regeneration, skin health, antioxidant defense, longevity.',
	],
];

// ── How it works steps ─────────────────────────────────────────────────────────
$hp_img = get_stylesheet_directory_uri() . '/assets/images/PDP Sections/';
$hp_steps = [
	[ 'num' => '01', 'img' => $hp_img . 'form.png',         'title' => 'Questionnaire',                    'desc' => 'Answer a few questions and share your medical details.'                               ],
	[ 'num' => '02', 'img' => $hp_img . 'consultation.png', 'title' => 'Reviewed & approved by provider',  'desc' => 'Discuss your goals and receive expert recommendations.'                              ],
	[ 'num' => '03', 'img' => $hp_img . 'box.png',          'title' => 'Receive medication',               'desc' => 'Medication and supplies shipped straight to your door.'                             ],
	[ 'num' => '04', 'img' => $hp_img . 'calendar.png',     'title' => 'Monthly monitoring',               'desc' => 'Stay on track with regular free check-ins to ensure progress.'                     ],
];

// ── FAQ ────────────────────────────────────────────────────────────────────────
$hp_faqs = [
	[
		'q' => 'What is tirzepatide and how is it different from semaglutide?',
		'a' => 'Tirzepatide is a dual GIP/GLP-1 receptor agonist — it activates two metabolic pathways simultaneously. Semaglutide only activates the GLP-1 receptor. Clinical trials (SURMOUNT-1) show tirzepatide produced significantly greater average weight loss than semaglutide across comparable doses.',
	],
	[
		'q' => 'How does dosing escalation work?',
		'a' => 'You start at 10 mg/month and increase in 10 mg steps based on tolerance and provider guidance. Most patients step up every 4 weeks. Our configurator lets you plan your escalation upfront, and your provider confirms each step is appropriate before it ships.',
	],
	[
		'q' => 'Do I need a new consultation every month?',
		'a' => 'Subscribers don\'t. Your initial approval covers your configured escalation program. A new consultation is only required for one-time purchases, or if you request a dose change outside your original program. Consult fees are $79.',
	],
	[
		'q' => 'What if my provider adjusts my dose?',
		'a' => 'If your provider determines a different dose is more appropriate, they\'ll contact you before fulfilling the order. You\'re never charged for a dose that wasn\'t approved.',
	],
	[
		'q' => 'How is this compounded and where does it ship from?',
		'a' => 'Your medication is compounded at an FDA-registered 503A pharmacy in the United States. It ships refrigerated in discreet packaging with a cold pack valid for up to 72 hours in transit.',
	],
	[
		'q' => 'Can I cancel my subscription?',
		'a' => 'Yes — anytime. Cancel before your renewal date and you won\'t be charged for the next cycle. There\'s no minimum commitment and no cancellation fee.',
	],
];

// ── Shared values ──────────────────────────────────────────────────────────────
$logo_url = wp_get_attachment_image_url( 16, 'full' ) ?: '';
$logo_alt = get_bloginfo( 'name' );
$year     = (int) date( 'Y' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'myogenix-home-page' ); ?>>
<?php wp_body_open(); ?>

<?php get_template_part( 'template-parts/site-header' ); ?>

<main>

<!-- ═══════════════════════════════════════════════════
     HERO
════════════════════════════════════════════════════ -->
<section class="home-hero">
	<div class="home-hero__grid" aria-hidden="true"></div>
	<div class="home-hero__content">
		<div class="home-hero__pill">
			<span class="home-hero__pill-dot" aria-hidden="true"></span>
			<span class="home-hero__pill-text">Provider-reviewed · FDA-registered compounding</span>
		</div>
		<h1 class="home-hero__headline">Clinical programs for every health goal.</h1>
		<p class="home-hero__sub">Compounded weight management, men's health, sexual health, and performance peptides — reviewed by a licensed provider before every shipment.</p>
	</div>
</section>

<!-- ═══════════════════════════════════════════════════
     CATEGORY BOXES
════════════════════════════════════════════════════ -->
<section class="home-categories" aria-label="Programs">
	<div class="home-categories__grid">
		<?php foreach ( $hp_categories as $cat ) : ?>
		<div class="hp-catbox<?php echo ! empty( $cat['full'] ) ? ' hp-catbox--full' : ''; ?>">
			<div class="hp-catbox__header">
				<h2 class="hp-catbox__title"><?php echo esc_html( $cat['title'] ); ?></h2>
				<a href="<?php echo esc_url( home_url( $cat['shop_url'] ) ); ?>" class="hp-catbox__shopall">Shop all →</a>
			</div>
			<div class="hp-catbox__scroll-wrap">
				<div class="hp-catbox__scroll">
					<?php
					$rendered = 0;
					foreach ( $cat['products'] as $pkey ) {
						$card = hp_product_card( $pkey, $hp_products, $hp_meta );
						if ( $card ) { echo $card; $rendered++; }
					}
					// Pad to minimum 3 with "coming soon" placeholders
					for ( $i = $rendered; $i < 3; $i++ ) : ?>
					<div class="hp-card hp-card--coming-soon" aria-label="Coming soon">
						<div class="hp-card__img-wrap">
							<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 48 48" aria-hidden="true" width="48" height="48">
								<circle cx="24" cy="24" r="16" stroke="#d4d4d8" stroke-width="1.5" stroke-dasharray="4 3"/>
								<path d="M24 16v8M24 28v2" stroke="#d4d4d8" stroke-width="1.5" stroke-linecap="round"/>
							</svg>
						</div>
						<div class="hp-card__body">
							<div class="hp-card__name" style="color:#a1a1aa;">More coming</div>
							<div class="hp-card__tag">New products may be on the way</div>
							<div class="hp-card__foot">
								<span class="hp-card__coming-tag">Soon</span>
							</div>
						</div>
					</div>
					<?php endfor; ?>
				</div>
				<div class="hp-catbox__fade" aria-hidden="true"></div>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
</section>

<!-- ═══════════════════════════════════════════════════
     NEW ARRIVALS
════════════════════════════════════════════════════ -->
<section class="home-arrivals" aria-label="New arrivals">
	<div class="home-arrivals__inner">
		<div class="home-arrivals__header">
			<div class="home-arrivals__left">
				<p class="home-arrivals__overline">New arrivals</p>
				<h2 class="home-arrivals__heading">3 new peptides</h2>
				<p class="home-arrivals__desc">The latest additions to our compounded peptide line. Provider-reviewed and shipped cold-chain from our FDA-registered facility.</p>
			</div>
			<a href="<?php echo esc_url( home_url( '/product-category/peptides-longevity/' ) ); ?>" class="home-arrivals__viewall">View all peptides →</a>
		</div>

		<div class="home-arrivals__grid">
			<?php foreach ( $hp_arrivals as $arr ) :
				$key = $arr['key'];
				if ( empty( $hp_products[ $key ] ) ) continue;
				$p   = $hp_products[ $key ];
				$m   = $hp_meta[ $key ];
				$price = '$' . number_format( $p['price'], 0 );
			?>
			<a href="<?php echo esc_url( $p['url'] ); ?>" class="hp-arrival-card" aria-label="<?php echo esc_attr( $m['name'] ); ?>">
				<div class="hp-arrival-card__img-wrap">
					<img src="<?php echo esc_url( $p['image'] ); ?>" alt="<?php echo esc_attr( $m['name'] ); ?>" loading="lazy" width="400" height="400">
				</div>
				<div class="hp-arrival-card__body">
					<div class="hp-arrival-card__top">
						<h3 class="hp-arrival-card__name"><?php echo esc_html( $m['name'] ); ?></h3>
						<p class="hp-arrival-card__benefit"><?php echo esc_html( $arr['benefit'] ); ?></p>
					</div>
					<div class="hp-arrival-card__foot">
						<div class="hp-arrival-card__price-block">
							<span class="hp-arrival-card__starting">Starting at</span>
							<span class="hp-arrival-card__price"><?php echo esc_html( $price ); ?><span class="hp-arrival-card__unit"><?php echo esc_html( $m['unit'] ); ?></span></span>
						</div>
						<span class="hp-arrival-card__order-btn" aria-hidden="true">Order →</span>
					</div>
				</div>
			</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<!-- ═══════════════════════════════════════════════════
     HOW IT WORKS
════════════════════════════════════════════════════ -->
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

<!-- ═══════════════════════════════════════════════════
     FAQ
════════════════════════════════════════════════════ -->
<section class="home-faq" id="faq" aria-label="Frequently asked questions">
	<div class="hp-inner">
		<div class="home-faq__layout">

			<div class="home-faq__sidebar">
				<p class="home-faq__overline">FAQ</p>
				<h2 class="home-faq__heading">Common questions</h2>
				<p class="home-faq__sub">Everything you need to know about the programs, dosing, and ordering.</p>
				<a href="<?php echo esc_url( home_url( '/product-category/weight-loss/' ) ); ?>" class="home-faq__cta">Configure your program →</a>
			</div>

			<div class="home-faq__accordion" role="list">
				<?php foreach ( $hp_faqs as $i => $faq ) : ?>
				<div class="hp-faq-item" role="listitem">
					<button class="hp-faq-btn" aria-expanded="<?php echo $i === 0 ? 'true' : 'false'; ?>">
						<span class="hp-faq-question"><?php echo esc_html( $faq['q'] ); ?></span>
						<span class="hp-faq-icon" aria-hidden="true">+</span>
					</button>
					<div class="hp-faq-answer">
						<p class="hp-faq-answer-inner"><?php echo esc_html( $faq['a'] ); ?></p>
					</div>
				</div>
				<?php endforeach; ?>
			</div>

		</div>
	</div>
</section>

</main>

<!-- ═══════════════════════════════════════════════════
     FOOTER
════════════════════════════════════════════════════ -->
<footer class="home-footer" role="contentinfo">
	<div class="home-footer__inner">
		<div class="home-footer__grid">

			<div class="home-footer__brand">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( $logo_alt ); ?> home">
					<?php if ( $logo_url ) : ?>
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $logo_alt ); ?>" class="home-footer__logo" width="auto" height="32">
					<?php else : ?>
						<span style="font-size:16px;font-weight:800;color:#000;"><?php echo esc_html( $logo_alt ); ?></span>
					<?php endif; ?>
				</a>
				<p class="home-footer__tagline">Configured for your protocol. FDA-registered compounding.</p>
			</div>

			<div>
				<p class="home-footer__col-heading">Programs</p>
				<ul class="home-footer__links">
					<li><a href="<?php echo esc_url( home_url( '/product-category/weight-loss/' ) ); ?>">Weight Management</a></li>
					<li><a href="<?php echo esc_url( home_url( '/product-category/mens-health/' ) ); ?>">Mens Health</a></li>
					<li><a href="<?php echo esc_url( home_url( '/product-category/sexual-health/' ) ); ?>">Sexual Health</a></li>
					<li><a href="<?php echo esc_url( home_url( '/product-category/peptides-longevity/' ) ); ?>">Peptides &amp; Longevity</a></li>
				</ul>
			</div>

			<div>
				<p class="home-footer__col-heading">Company</p>
				<ul class="home-footer__links">
					<li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">About</a></li>
					<li><a href="#how-it-works">How it works</a></li>
					<li><a href="#faq">FAQ</a></li>
					<li><a href="<?php echo esc_url( home_url( '/affiliates/' ) ); ?>">Affiliate Program</a></li>
				</ul>
			</div>

			<div>
				<p class="home-footer__col-heading">Legal</p>
				<ul class="home-footer__links">
					<li><a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>">Privacy Policy</a></li>
					<li><a href="<?php echo esc_url( home_url( '/terms-of-service/' ) ); ?>">Terms of Service</a></li>
					<li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact</a></li>
				</ul>
			</div>

		</div>

		<div class="home-footer__bottom">
			<p class="home-footer__copy">© <?php echo $year; ?> MyoGenix Pharma. For informational purposes only. Not medical advice.</p>
			<p class="home-footer__disclaimer">Compounded medications are not FDA-approved.</p>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
