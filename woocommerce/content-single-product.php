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

/**
 * Hook: woocommerce_before_single_product.
 * @hooked woocommerce_output_all_notices - 10
 */
do_action( 'woocommerce_before_single_product' );

if ( post_password_required() ) {
	echo get_the_password_form(); // WPCS: XSS ok.
	return;
}

$slug           = $product->get_slug();
$weight_loss    = [ 'compound-semaglutide', 'compound-tirzepatide' ];
$is_weight_loss = in_array( $slug, $weight_loss, true );

if ( $is_weight_loss ) :

	// Builds an encoded image URL from a relative path (handles spaces in folder/file names)
	$img_url = function( $path ) {
		$base  = get_stylesheet_directory_uri() . '/assets/images/';
		$parts = explode( '/', $path );
		return esc_url( $base . implode( '/', array_map( 'rawurlencode', $parts ) ) );
	};

	$product_config = [
		'compound-semaglutide' => [
			'image'    => 'semaglutide/semaglutide.png',
			'headline' => 'Placeholder Headline for Semaglutide',
			'sub'      => 'Placeholder subheadline — describe the key benefit or offer here.',
			'btn_text' => 'Get Started',
			'btn_url'  => '#buy',
		],
		'compound-tirzepatide' => [
			'image'    => 'tirzepatide/tirzepatide.png',
			'headline' => 'Placeholder Headline for Tirzepatide',
			'sub'      => 'Placeholder subheadline — describe the key benefit or offer here.',
			'btn_text' => 'Get Started',
			'btn_url'  => '#buy',
		],
	];
	$cfg = $product_config[ $slug ];

	// Remove WooCommerce's default image renderer — we render our own per-product image
	remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );

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

	$faqs = [
		[
			'q' => 'What is Myogenix Pharma ?',
			'a' => 'MYOGENIX PHARMA is a modern, concierge telehealth clinic specializing in hormone optimization, weight loss, and longevity. We provide physician-guided treatments with at-home convenience, fast delivery, and ongoing support.',
		],
		[
			'q' => 'Is the process easy ?',
			'a' => 'Getting started is seamless: complete a quick online visit, and our medical team reviews your goals, symptoms, and history. If bloodwork is needed, you can schedule a lab appointment and a doctor consult in a few easy steps. A personalized treatment plan is designed, and your medications are delivered directly to your door, with ongoing support and adjustments to keep you feeling your best.',
		],
		[
			'q' => 'Do I need blood work ?',
			'a' => 'Not all treatments require blood work. But, when starting testosterone or hormone replacement therapy, we arrange your bloodwork at a local lab. The entire visit takes just a few minutes, and your provider reviews the results to design a precise, personalized treatment plan. If you have recent bloodwork, you can upload the results into the patient portal.',
		],
		[
			'q' => 'Is mobile phlebotomy available ?',
			'a' => 'If you\'re located within our service region, you can upgrade to mobile phlebotomy. We send a certified phlebotomist to your door, so your lab work is done privately, conveniently, and on your schedule.',
		],
		[
			'q' => 'Who is eligible to use Myogenix Pharma ?',
			'a' => 'Our services are for adults 18+ who have a documented medical need for treatment. Final eligibility, dosing, and medication choice are always determined by your provider after reviewing your intake, history, and goals.',
		],
		[
			'q' => 'How do Prescriptions, Refills, and Shipping Work ?',
			'a' => 'If you\'re approved for treatment, your prescription is sent to a licensed U.S. compounding pharmacy. They prepare your medication and ship directly and discreetly to your door, along with any applicable supplies. Your provider will schedule refills or follow-up evaluations as needed to continue treatment safely.',
		],
		[
			'q' => 'What if I\'m not approved or want to stop treatment ?',
			'a' => 'There\'s never a guarantee of a prescription — your provider will only recommend a medication if it\'s clinically appropriate and safe for you. If you\'re not a good candidate for a specific treatment, they may discuss alternative options. You can pause or cancel future renewals at any time according to the terms of your plan; just reach out through your Myogenix Pharma account or support team.',
		],
		[
			'q' => 'What areas do you service ?',
			'a' => 'Myogenix Pharma offers telehealth services in 48 states (not including Alaska or Mississippi).',
		],
	];
?>

<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'myogenix-pdp', $product ); ?>>

	<!-- Hero Banner — placeholder copy, replace before launch -->
	<div class="myogenix-pdp__hero-banner">
		<div class="myogenix-pdp__container">
			<h1 class="myogenix-pdp__hero-headline"><?php echo esc_html( $cfg['headline'] ); ?></h1>
			<p class="myogenix-pdp__hero-sub"><?php echo esc_html( $cfg['sub'] ); ?></p>
			<a href="<?php echo esc_url( $cfg['btn_url'] ); ?>" class="myogenix-pdp__hero-btn">
				<?php echo esc_html( $cfg['btn_text'] ); ?>
			</a>
		</div>
	</div>

	<!-- Product Section -->
	<section class="myogenix-pdp__product" id="buy">
		<div class="myogenix-pdp__container myogenix-pdp__product-inner">
			<div class="myogenix-pdp__product-image">
				<img
					src="<?php echo $img_url( $cfg['image'] ); ?>"
					alt="<?php echo esc_attr( $product->get_name() ); ?>"
				/>
			</div>
			<div class="myogenix-pdp__product-summary summary entry-summary">
				<?php
				// Sale flash and any other before_summary hooks (product images removed above)
				do_action( 'woocommerce_before_single_product_summary' );
				// Title, rating, price, excerpt, add-to-cart — variations + subscriptions attach here
				do_action( 'woocommerce_single_product_summary' );
				?>
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

	<!-- FAQ Section -->
	<section class="myogenix-pdp__faq">
		<div class="myogenix-pdp__container">
			<h2 class="myogenix-pdp__section-heading">Frequently Asked Questions</h2>
			<p class="myogenix-pdp__section-sub">Our Most Asked Questions</p>
			<div class="myogenix-pdp__faq-list">
				<?php foreach ( $faqs as $i => $faq ) : ?>
				<div class="myogenix-pdp__faq-item">
					<button
						class="myogenix-pdp__faq-question"
						aria-expanded="false"
						aria-controls="myogenix-faq-<?php echo esc_attr( $i ); ?>"
					>
						<span><?php echo esc_html( $faq['q'] ); ?></span>
						<span class="myogenix-pdp__faq-icon" aria-hidden="true">+</span>
					</button>
					<div
						class="myogenix-pdp__faq-answer"
						id="myogenix-faq-<?php echo esc_attr( $i ); ?>"
						hidden
					>
						<p><?php echo esc_html( $faq['a'] ); ?></p>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
			<p class="myogenix-pdp__faq-footnote">*If medically eligible</p>
		</div>
	</section>

	<!-- Explore More Treatment Lines -->
	<section class="myogenix-pdp__explore">
		<div class="myogenix-pdp__container">
			<h2 class="myogenix-pdp__section-heading">Explore More Treatment Lines</h2>
			<p class="myogenix-pdp__section-sub">The telehealth provider of choice for holistic care.</p>
			<div class="myogenix-pdp__explore-grid">
				<a href="https://myogenixpharma.com/mens-health/" class="myogenix-pdp__explore-link">
					<img src="<?php echo $img_url( 'PDP Sections/mens health.png' ); ?>" alt="Men's Health" />
				</a>
				<a href="https://myogenixpharma.com/womens-health/" class="myogenix-pdp__explore-link">
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
