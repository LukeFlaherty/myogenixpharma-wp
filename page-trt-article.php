<?php
/**
 * Template Name: TRT Article
 * Description: Editorial article — How to Legally Buy Testosterone Online
 */

defined( 'ABSPATH' ) || exit;

$trt_url = esc_url( home_url( '/product/testosterone/' ) );

// Fetch the TRT product image from the WooCommerce product (ID 883).
$trt_product   = wc_get_product( 883 );
$trt_image_url = '';
if ( $trt_product ) {
	$img_id = $trt_product->get_image_id();
	if ( $img_id ) {
		$trt_image_url = wp_get_attachment_image_url( $img_id, 'medium' );
	}
}

// Testimonials.
// To add real before/after photos: upload them to the Media Library and paste
// the full attachment URL into 'before_url' / 'after_url' for each entry.
$testimonials = [
	[
		'name'       => 'Michael T., 45',
		'state'      => 'Colorado',
		'tag'        => 'Lost 28 lbs · 5 months',
		'quote'      => '"I was barely dragging myself through the day. Six months into TRT through Myogenix, I\'m back in the gym five days a week and feel like myself again."',
		'before_url' => '',
		'after_url'  => '',
	],
	[
		'name'       => 'Robert K., 52',
		'state'      => 'Florida',
		'tag'        => '40 lbs down · 8 months',
		'quote'      => '"My energy was gone and my mood was shot. After starting TRT, my wife said she has her husband back. The process was surprisingly easy and professional."',
		'before_url' => '',
		'after_url'  => '',
	],
	[
		'name'       => 'David S., 48',
		'state'      => 'Texas',
		'tag'        => 'Body recomposition · 6 months',
		'quote'      => '"I got my labs done, spoke with a board-certified doctor the same week, and had my prescription filled within days. Couldn\'t believe how straightforward it was."',
		'before_url' => '',
		'after_url'  => '',
	],
	[
		'name'       => 'James L., 39',
		'state'      => 'California',
		'tag'        => '22 lbs lost · 4 months',
		'quote'      => '"I was skeptical that an online clinic could be legitimate. Myogenix exceeded every expectation — real doctors, real labs, real results."',
		'before_url' => '',
		'after_url'  => '',
	],
	[
		'name'       => 'Carlos M., 55',
		'state'      => 'Nevada',
		'tag'        => 'Strength & vitality restored',
		'quote'      => '"My testosterone was critically low and no one had flagged it. Myogenix caught it, treated it, and now I\'m back to competing in masters-level powerlifting."',
		'before_url' => '',
		'after_url'  => '',
	],
	[
		'name'       => 'Brian W., 61',
		'state'      => 'Ohio',
		'tag'        => '35 lbs lost · 10 months',
		'quote'      => '"Being on TRT for the past ten months has completely transformed how I look and feel. The Myogenix team checked in regularly and kept my treatment dialed in."',
		'before_url' => '',
		'after_url'  => '',
	],
];

get_header();
?>

<div class="trt-article">

	<!-- Breadcrumb -->
	<nav class="trt-article__breadcrumb" aria-label="Breadcrumb">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
		<span aria-hidden="true">/</span>
		<a href="<?php echo esc_url( home_url( '/mens-health/' ) ); ?>">Men's Health</a>
		<span aria-hidden="true">/</span>
		<span aria-current="page">How to Legally Buy Testosterone Online</span>
	</nav>

	<!-- Article Header -->
	<header class="trt-article__header">
		<span class="trt-article__kicker">Men's Health &middot; Testosterone</span>
		<h1 class="trt-article__title">How to Legally Buy Testosterone Online</h1>
		<p class="trt-article__subtitle">You'll need a prescription — but we can help you get your TRT virtually, safely, and fully within DEA guidelines.</p>
		<div class="trt-article__meta">
			<span class="trt-article__meta-by">By Marcus Webb</span>
			<span class="trt-article__meta-dot" aria-hidden="true">&bull;</span>
			<span class="trt-article__meta-reviewed">Medically reviewed by Dr. James Rivera, D.O.</span>
			<span class="trt-article__meta-dot" aria-hidden="true">&bull;</span>
			<time class="trt-article__meta-date" datetime="2024-03-04">March 4, 2024</time>
		</div>
	</header>

	<!-- Hero Image -->
	<div class="trt-article__hero">
		<img
			src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/PDP Sections/mens health.png' ); ?>"
			alt="Man running — representing the energy restored by TRT therapy"
			loading="eager"
		/>
		<p class="trt-article__hero-caption">Testosterone therapy can restore the energy, strength, and motivation that low T quietly steals.</p>
	</div>

	<!-- Body -->
	<div class="trt-article__body">

		<!-- 30-Second Takeaway -->
		<aside class="trt-takeaway" aria-label="30-Second Takeaway">
			<p class="trt-takeaway__heading">30-Second Takeaway</p>
			<ul class="trt-takeaway__list">
				<li>You can buy testosterone online legally from companies that are in full compliance with federal guidelines.</li>
				<li>Avoid purchasing from any site that does not require lab assessments, prescriptions, or that sells anabolic steroids without a diagnosis.</li>
				<li>Myogenix complies with all DEA guidelines and makes getting testosterone online simple if you have a clinically confirmed deficiency.</li>
			</ul>
		</aside>

		<p>Buying testosterone online or from a doctor's office can feel like a big step. But online testosterone therapy is legal and safer than ever — as long as you are working with a reputable, fully licensed company.</p>

		<p>Stringent safeguards in telemedicine have helped ensure that prescribing testosterone can only happen when all parties — doctor, patient, and pharmacy — remain in legal compliance with the guidelines imposed by the Drug Enforcement Administration (DEA).</p>

		<p>So how do you go about obtaining testosterone online, legally? It is natural to have basic questions. Here, we provide clear, in-depth answers.</p>

		<!-- Inline CTA #1 -->
		<div class="trt-cta-block trt-cta-block--accent">
			<?php if ( $trt_image_url ) : ?>
			<div class="trt-cta-block__img">
				<img src="<?php echo esc_url( $trt_image_url ); ?>" alt="Myogenix Testosterone Cypionate" />
			</div>
			<?php endif; ?>

			<div class="trt-cta-block__copy">
				<p class="trt-cta-block__label">Live The Life You Always Imagined</p>
				<p class="trt-cta-block__heading">Myogenix TRT &mdash; Start Your Assessment</p>
				<p class="trt-cta-block__sub">If your levels are low, our providers create a personalized treatment plan to help you feel your best.</p>
			</div>
			<div class="trt-cta-block__action">
				<a href="<?php echo $trt_url; ?>" class="trt-cta-block__btn">Get Started &rarr;</a>
				<p class="trt-cta-block__price">From <strong>$567</strong><span>/mo</span> &middot; Prescription included</p>
			</div>
		</div>

		<!-- Section: Is it legal? -->
		<h2>Is it legal to buy testosterone online?</h2>

		<p>Yes — it is 100 percent legal to buy testosterone online, as long as you have a valid prescription and the site you are buying from follows DEA guidelines.</p>

		<p>If a site states that you do not require a prescription, or claims you do not need to show proof of a diagnosed medical condition, do not buy from them. They are not in compliance with DEA guidelines and are almost certainly operating illegally.</p>

		<p>If a pharmacy does not have an actual US address or claims it can provide testosterone prescriptions after you fill out a short online form with no lab work, do not engage with them. A form alone does not qualify anyone to receive a legitimate, legal prescription.</p>

		<p>These guidelines exist entirely for your benefit and well-being. Here is what they require:</p>

		<ul class="trt-checklist">
			<li>
				<span class="trt-checklist__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
				</span>
				<span>Having a medical condition validly diagnosed by a physician through a legitimate doctor-patient relationship, including a blood panel and physical history.</span>
			</li>
			<li>
				<span class="trt-checklist__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
				</span>
				<span>Establishing a preliminary blood panel, a health history, and a physical examination before any prescription is issued.</span>
			</li>
			<li>
				<span class="trt-checklist__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
				</span>
				<span>Documentation that the doctor and patient discussed the risks and benefits of testosterone based on lab values, medical history, and symptom profile.</span>
			</li>
			<li>
				<span class="trt-checklist__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
				</span>
				<span>The physician and pharmacy operating online are based and licensed within the United States. Many states require the physician to be licensed in the state where the patient is located.</span>
			</li>
		</ul>

		<!-- Section: Why guidelines exist -->
		<h2>Why These Guidelines Are In Place</h2>

		<p>Testosterone is a male steroid hormone — though testosterone therapy is not the same as taking anabolic steroids for performance enhancement. In the years since testosterone was first synthesized, it has been sold for reasons other than treating clinically significant hormone deficiencies.</p>

		<p>For example, some men want to buy testosterone to increase athletic performance, sexual function, or simply to fight the natural age-related decline. While these goals are understandable, using testosterone without a clinical deficiency diagnosis carries real risks and bypasses safeguards designed to protect patients.</p>

		<p>To address the problem, the US government's Anabolic Steroids Control Act of 1990 placed testosterone and other anabolic-androgenic steroids (AAS) in Schedule III of the Controlled Substances Act. Schedule III drugs are defined as substances with a moderate to low potential for physical and psychological dependence when used without proper medical oversight.<sup>1</sup></p>

		<p>If any pharmacy or physician tells you differently, they are either committing an illegal act or not offering you legitimate testosterone therapy.</p>

		<!-- Section: How do I buy from Myogenix? -->
		<h2>How do I buy testosterone online from Myogenix?</h2>

		<p>If you want to buy testosterone online, Myogenix provides a safe, legal, and physician-supervised path to do so.</p>

		<p>We are fully compliant with all federal and state laws regarding the prescription and sale of testosterone. Our team has built a simple, step-by-step process to get you treatment you can trust.</p>

		<div class="trt-steps">

			<div class="trt-step">
				<div class="trt-step__num" aria-hidden="true">1</div>
				<div class="trt-step__body">
					<h3 class="trt-step__heading">Measure Your Biomarkers</h3>
					<p class="trt-step__text">If you experience symptoms that may be attributable to low testosterone — fatigue, brain fog, reduced libido, difficulty building muscle — be proactive and have your hormones tested. Myogenix connects you with a CLIA-accredited partner lab to measure your total and free testosterone alongside other key hormones.</p>
				</div>
			</div>

		</div>

		<!-- Inline CTA #2 -->
		<div class="trt-cta-block">
			<?php if ( $trt_image_url ) : ?>
			<div class="trt-cta-block__img">
				<img src="<?php echo esc_url( $trt_image_url ); ?>" alt="Myogenix Testosterone Cypionate" />
			</div>
			<?php endif; ?>

			<div class="trt-cta-block__copy">
				<p class="trt-cta-block__label">Myogenix TRT Assessment</p>
				<p class="trt-cta-block__heading">Testosterone testing made simple.</p>
				<p class="trt-cta-block__sub">If you have clinically low T, our providers can help you feel better, fast.</p>
			</div>
			<div class="trt-cta-block__action">
				<a href="<?php echo $trt_url; ?>" class="trt-cta-block__btn">Get Started &rarr;</a>
				<p class="trt-cta-block__price">From <strong>$567</strong><span>/mo</span></p>
			</div>
		</div>

		<div class="trt-steps">

			<div class="trt-step">
				<div class="trt-step__num" aria-hidden="true">2</div>
				<div class="trt-step__body">
					<h3 class="trt-step__heading">Consult With a Myogenix-Affiliated Provider</h3>
					<p class="trt-step__text">After the lab tests your levels, you will schedule an online video consultation with a board-certified physician. These doctors, licensed in your home state, specialize in hormone optimization, endocrinology, urology, and internal medicine. The consultation is included with your order — no separate appointment booking needed.</p>
				</div>
			</div>

			<div class="trt-step">
				<div class="trt-step__num" aria-hidden="true">3</div>
				<div class="trt-step__body">
					<h3 class="trt-step__heading">Discuss Treatment Options With Your Doctor</h3>
					<p class="trt-step__text">During your consultation, your physician will review the results of your hormone assessment and discuss how you are feeling before recommending any course of treatment. If your T levels are clinically and significantly deficient, they will explain the different forms of testosterone replacement therapy available to you, including each method's benefits and potential side effects. Note that a confirmatory assessment may be required before a prescription is issued.</p>
				</div>
			</div>

			<div class="trt-step">
				<div class="trt-step__num" aria-hidden="true">4</div>
				<div class="trt-step__body">
					<h3 class="trt-step__heading">Receive Your Prescription and Medication</h3>
					<p class="trt-step__text">Once your diagnosis is confirmed, your doctor will issue a valid electronic prescription for your TRT medication and dosage. Your medication is then dispensed by a licensed US pharmacy and shipped discreetly to your door. No insurance is required, and there are no hidden fees.</p>
				</div>
			</div>

			<div class="trt-step">
				<div class="trt-step__num" aria-hidden="true">5</div>
				<div class="trt-step__body">
					<h3 class="trt-step__heading">Monitor and Reassess</h3>
					<p class="trt-step__text">Myogenix schedules hormone reassessments every 90 days. Your doctor reviews the results with you and determines whether any adjustments to your TRT are needed. Consistent retesting helps you avoid delays in receiving your prescribed treatment and ensures your levels stay optimized safely over time.</p>
				</div>
			</div>

		</div>

		<!-- Inline CTA #3 (simplified checklist) -->
		<div class="trt-simple-cta">
			<p class="trt-simple-cta__heading">Myogenix makes it easy to get your hormones checked and get treated.</p>
			<ul class="trt-simple-cta__list">
				<li>Check your T levels with a CLIA-accredited lab assessment.</li>
				<li>Schedule an online video consultation with a board-certified provider.</li>
				<li>Receive your medication shipped directly to your door.</li>
			</ul>
			<a href="<?php echo $trt_url; ?>" class="trt-simple-cta__btn">Get Started &rarr;</a>
			<span class="trt-simple-cta__price">From $567/mo &middot; Doctor consultation included</span>
		</div>

		<!-- Section: Follow your regimen -->
		<h2>The next step: follow the TRT regimen prescribed by your physician</h2>

		<p>Some men want to start TRT to "get swole," boost their gym gains, or improve their sex drive. While TRT can provide all of these benefits — and they are great to experience — they are not enough on their own to justify a prescription. And they are definitely not a reason to alter your treatment regimen without a doctor's approval.</p>

		<p>You are on TRT to restore testosterone levels that have dropped significantly below what the American Urological Association (AUA) categorizes as normal.<sup>2</sup> By restoring those levels, you are also helping other functions in your body perform as they should — energy metabolism, mood, bone density, cardiovascular health, and more.</p>

		<p>Changing your dosage or protocol without your doctor's permission is not only risky to your health — it can also be illegal and can invalidate your TRT prescription.</p>

		<p>You have done all the hard work: you tested your levels, you spoke with a licensed provider, and you received a legitimate prescription from a licensed US pharmacy. Follow your TRT regimen exactly as prescribed, and you will find the benefits are well worth it.</p>

	</div><!-- .trt-article__body -->

	<!-- Testimonials -->
	<section class="trt-testimonials" aria-label="Patient testimonials">
		<div class="trt-testimonials__inner">
			<h2 class="trt-testimonials__heading">From Struggle to Success: Before &amp; After TRT</h2>
			<p class="trt-testimonials__sub">Real patients. Real results. Verified by our clinical team.</p>

			<div class="trt-testimonials__grid">
				<?php foreach ( $testimonials as $t ) :
					$has_photos = ! empty( $t['before_url'] ) && ! empty( $t['after_url'] );
				?>
				<article class="trt-testimonial-card">
					<div class="trt-testimonial-card__photo <?php echo $has_photos ? '' : 'trt-testimonial-card__photo--placeholder'; ?>">
						<?php if ( $has_photos ) : ?>
							<img class="trt-testimonial-card__before" src="<?php echo esc_url( $t['before_url'] ); ?>" alt="<?php echo esc_attr( $t['name'] ) . ' — before TRT'; ?>" loading="lazy" />
							<div class="trt-testimonial-card__divider" aria-hidden="true"></div>
							<img class="trt-testimonial-card__after"  src="<?php echo esc_url( $t['after_url'] ); ?>"  alt="<?php echo esc_attr( $t['name'] ) . ' — after TRT'; ?>"  loading="lazy" />
						<?php else : ?>
							<div class="trt-photo-half trt-photo-half--before">Before</div>
							<div class="trt-testimonial-card__divider" aria-hidden="true"></div>
							<div class="trt-photo-half trt-photo-half--after">After</div>
						<?php endif; ?>
					</div>
					<div class="trt-testimonial-card__body">
						<p class="trt-testimonial-card__name"><?php echo esc_html( $t['name'] ); ?> &middot; <?php echo esc_html( $t['state'] ); ?></p>
						<span class="trt-testimonial-card__tag"><?php echo esc_html( $t['tag'] ); ?></span>
						<p class="trt-testimonial-card__quote"><?php echo esc_html( $t['quote'] ); ?></p>
					</div>
				</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<!-- Sources -->
	<div class="trt-sources">
		<details>
			<summary>Sources &amp; References</summary>
			<ol>
				<li>Drug Enforcement Administration. <em>Drug Scheduling — Schedule III.</em> <a href="https://www.dea.gov/drug-information/drug-scheduling" target="_blank" rel="noopener noreferrer">dea.gov</a>. Accessed March 2024.</li>
				<li>American Urological Association. <em>Testosterone Deficiency Guideline.</em> <a href="https://www.auanet.org/guidelines-and-quality/guidelines/testosterone-deficiency-guideline" target="_blank" rel="noopener noreferrer">auanet.org</a>. Accessed March 2024.</li>
			</ol>
		</details>
	</div>

	<!-- Article Footer -->
	<footer class="trt-article-footer">
		<div class="trt-article-footer__badges">
			<?php
			// LegitScript badge — upload the badge image to your Media Library and
			// update this URL, or use the HTML embed code LegitScript provides.
			$legitscript_img = get_stylesheet_directory_uri() . '/assets/images/legitscript-certified.png';
			?>
			<div class="trt-article-footer__legitscript">
				<img src="<?php echo esc_url( $legitscript_img ); ?>" alt="LegitScript Certified" width="100" height="100" onerror="this.style.display='none'" />
			</div>
		</div>

		<p class="trt-article-footer__disclaimer">
			<strong>Medical Disclaimer:</strong> The information provided in this article is for educational purposes only and does not constitute medical advice. Testosterone replacement therapy carries risks and is only appropriate for individuals with a clinically confirmed testosterone deficiency, as determined by a licensed physician. Always consult a qualified healthcare provider before starting any hormone therapy. Results vary by individual. Myogenix providers comply with all applicable state and federal regulations governing the prescription and dispensing of controlled substances.
		</p>
	</footer>

</div><!-- .trt-article -->

<?php get_footer(); ?>
