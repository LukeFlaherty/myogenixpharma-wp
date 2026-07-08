<?php
/**
 * Template Name: TRT Article
 * Description: Editorial article — How to Legally Buy Testosterone Online
 */

defined( 'ABSPATH' ) || exit;

$trt_url  = esc_url( home_url( '/product/testosterone/' ) );
$img_base = get_stylesheet_directory_uri() . '/assets/images/testimonials/';

// Fetch TRT product image from WooCommerce (product ID 883).
$trt_product   = wc_get_product( 883 );
$trt_image_url = '';
if ( $trt_product ) {
	$img_id = $trt_product->get_image_id();
	if ( $img_id ) {
		$trt_image_url = wp_get_attachment_image_url( $img_id, 'medium' );
	}
}

// Each entry maps to one combined before/after photo.
$testimonials = [
	[ 'img' => 'trt-1.webp',  'name' => 'James R., 44',   'state' => 'Colorado',    'tag' => 'Lost 30 lbs in 5 months',        'quote' => '"I lost 130 pounds and have a new outlook on life. TRT was the missing piece — within weeks I finally had the drive to get back in the gym and stick with it."' ],
	[ 'img' => 'trt-2.webp',  'name' => 'Derek M., 51',   'state' => 'Florida',     'tag' => 'TRT helped him regain 50 lbs of lean muscle', 'quote' => '"After getting my levels checked I realized I had been dealing with clinically low testosterone for years. After starting TRT I put on 50 lbs of muscle."' ],
	[ 'img' => 'trt-3.webp',  'name' => 'Kenneth S., 58', 'state' => 'Ohio',        'tag' => 'Lost 130 lbs in 6 months',       'quote' => '"I lost 130 pounds and look and feel completely different. Myogenix made the process simple — real doctors, real labs, real results."' ],
	[ 'img' => 'trt-4.webp',  'name' => 'Chris P., 36',   'state' => 'Georgia',     'tag' => 'Lost 40 lbs',                    'quote' => '"My energy was shot and I had zero motivation. TRT brought everything back — better sleep, better workouts, better mood."' ],
	[ 'img' => 'trt-5.webp',  'name' => 'Marcus W., 49',  'state' => 'Nevada',      'tag' => 'Strength & vitality restored',   'quote' => '"I was skeptical about doing this online but the physician was thorough and professional. I had my prescription in less than a week."' ],
	[ 'img' => 'trt-6.webp',  'name' => 'Tony G., 53',    'state' => 'Arizona',     'tag' => '45 lbs lost',                    'quote' => '"Starting TRT was the best health decision I have made. I went from barely getting off the couch to working out six days a week."' ],
	[ 'img' => 'trt-7.webp',  'name' => 'Ryan B., 42',    'state' => 'Texas',       'tag' => 'Body recomposition',             'quote' => '"The entire process took about a week from labs to prescription. Medication shipped to my door — no trips to the pharmacy, no hassle."' ],
	[ 'img' => 'trt-8.webp',  'name' => 'Carlos V., 55',  'state' => 'California',  'tag' => 'Veterans & Low T program',       'quote' => '"As a veteran I was used to things being complicated. Myogenix made TRT straightforward. My provider answered every question and got me started fast."' ],
	[ 'img' => 'trt-9.webp',  'name' => 'Nathan L., 40',  'state' => 'Washington',  'tag' => 'Confidence and drive restored',  'quote' => '"I was 40 and felt 70. Three months on TRT and I feel stronger, leaner, and sharper than I did in my 30s."' ],
	[ 'img' => 'trt-10.webp', 'name' => 'Mike D., 47',    'state' => 'Illinois',    'tag' => 'Lost 35 lbs',                    'quote' => '"The difference between who I was before TRT and who I am now is night and day. I only wish I had started sooner."' ],
	[ 'img' => 'trt-11.webp', 'name' => 'Steve K., 61',   'state' => 'Minnesota',   'tag' => 'Active retirement',              'quote' => '"I thought slowing down was just part of getting older. My doctor at Myogenix showed me my levels were critically low. Six months later I feel incredible."' ],
	[ 'img' => 'trt-12.webp', 'name' => 'Eric F., 38',    'state' => 'Pennsylvania','tag' => 'Lean muscle gain',               'quote' => '"I went from skinny-fat with no drive to being the most muscular I have ever been. TRT with Myogenix changed everything."' ],
];

get_header();
?>

<div class="trt-article">

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
			<time class="trt-article__meta-date" datetime="2026-07-06">July 6, 2026</time>
		</div>
	</header>

	<!-- Body -->
	<div class="trt-article__body">

		<!-- 30-Second Takeaway -->
		<aside class="trt-takeaway" aria-label="30-Second Takeaway">
			<p class="trt-takeaway__heading">30-Second Takeaway</p>
			<ul class="trt-takeaway__list">
				<li>You can buy testosterone online legally from companies that are in full compliance with federal guidelines.</li>
				<li>Avoid purchasing from any site that does not require lab work, prescriptions, or that sells anabolic steroids without a diagnosis.</li>
				<li>Myogenix complies with all DEA guidelines and makes starting TRT simple — lab testing, physician consult, and medication shipped to your door.</li>
			</ul>
		</aside>

		<p>Getting started on testosterone replacement therapy online is legal and safer than ever — as long as you work with a reputable, fully licensed company. Thousands of men are reclaiming their energy, strength, and quality of life through physician-supervised TRT without ever leaving their home.</p>

		<p>Stringent safeguards in telemedicine ensure that prescribing testosterone can only happen when all parties — doctor, patient, and pharmacy — remain in legal compliance with the Drug Enforcement Administration (DEA).</p>

		<p>So what does it actually take to get started? Here is everything you need to know.</p>

		<!-- Inline CTA #1 -->
		<div class="trt-cta-block trt-cta-block--accent">
			<?php if ( $trt_image_url ) : ?>
			<div class="trt-cta-block__img">
				<img src="<?php echo esc_url( $trt_image_url ); ?>" alt="Myogenix Testosterone Cypionate" />
			</div>
			<?php endif; ?>
			<div class="trt-cta-block__copy">
				<p class="trt-cta-block__label">Ready to Get Started?</p>
				<p class="trt-cta-block__heading">Start TRT with Myogenix Today</p>
				<p class="trt-cta-block__sub">Labs, physician consultation, and medication — all in one streamlined process. No insurance needed.</p>
			</div>
			<div class="trt-cta-block__action">
				<a href="<?php echo $trt_url; ?>" class="trt-cta-block__btn">Start Now &rarr;</a>
				<p class="trt-cta-block__price">From <strong>$567</strong>/mo &middot; Consultation included</p>
			</div>
		</div>

		<h2>Is it legal to buy testosterone online?</h2>

		<p>Yes — it is 100 percent legal to buy testosterone online, as long as you have a valid prescription and the site you are buying from follows DEA guidelines.</p>

		<p>If a site states that you do not require a prescription, or claims you do not need to show proof of a diagnosed medical condition, do not buy from them. They are not in compliance with DEA guidelines and are almost certainly operating illegally.</p>

		<p>If a pharmacy does not have an actual US address or claims it can provide prescriptions after you fill out a short form with no lab work, do not engage with them. A form alone does not qualify anyone to receive a legitimate, legal prescription.</p>

		<p>These guidelines exist entirely for your benefit. Here is what they require:</p>

		<ul class="trt-checklist">
			<li>
				<span class="trt-checklist__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
				</span>
				<span>A medical condition validly diagnosed by a physician through a legitimate doctor-patient relationship — including a blood panel and health history.</span>
			</li>
			<li>
				<span class="trt-checklist__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
				</span>
				<span>A preliminary blood panel, health history, and physical examination before any prescription is written.</span>
			</li>
			<li>
				<span class="trt-checklist__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
				</span>
				<span>Documentation that the doctor discussed the risks and benefits of testosterone based on your lab values, medical history, and symptoms.</span>
			</li>
			<li>
				<span class="trt-checklist__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
				</span>
				<span>The physician and pharmacy operating online are based and licensed within the United States, and licensed in your state.</span>
			</li>
		</ul>

		<h2>Why These Guidelines Are In Place</h2>

		<p>Testosterone is a male steroid hormone — though testosterone replacement therapy is not the same as taking anabolic steroids for performance enhancement. Since testosterone was first synthesized, bad actors have attempted to sell it without appropriate medical oversight.</p>

		<p>The US government's Anabolic Steroids Control Act of 1990 placed testosterone and other anabolic-androgenic steroids (AAS) in Schedule III of the Controlled Substances Act — substances with a moderate to low potential for dependence when used outside of proper medical care.</p>

		<p>Legitimate providers follow all of these rules. If any pharmacy or physician tells you a prescription isn't required, walk away.</p>

		<!-- Inline CTA #2 -->
		<div class="trt-cta-block">
			<?php if ( $trt_image_url ) : ?>
			<div class="trt-cta-block__img">
				<img src="<?php echo esc_url( $trt_image_url ); ?>" alt="Myogenix Testosterone Cypionate" />
			</div>
			<?php endif; ?>
			<div class="trt-cta-block__copy">
				<p class="trt-cta-block__label">Myogenix TRT</p>
				<p class="trt-cta-block__heading">Physician-supervised. DEA compliant. Shipped to your door.</p>
				<p class="trt-cta-block__sub">Our board-certified providers follow every required guideline — and make the process as simple as possible for you.</p>
			</div>
			<div class="trt-cta-block__action">
				<a href="<?php echo $trt_url; ?>" class="trt-cta-block__btn">Start Now &rarr;</a>
				<p class="trt-cta-block__price">From <strong>$567</strong>/mo</p>
			</div>
		</div>

		<h2>How do I get started with TRT through Myogenix?</h2>

		<p>Myogenix is fully compliant with all federal and state laws governing the prescription and sale of testosterone. Here is exactly how our process works.</p>

		<div class="trt-steps">

			<div class="trt-step">
				<div class="trt-step__num" aria-hidden="true">1</div>
				<div class="trt-step__body">
					<h3 class="trt-step__heading">Measure Your Biomarkers</h3>
					<p class="trt-step__text">If you experience symptoms that may be attributable to low testosterone — fatigue, brain fog, reduced libido, difficulty building muscle — start by getting your hormones tested. Myogenix connects you with a CLIA-accredited partner lab to measure your total and free testosterone alongside other key hormones. This gives your provider the clinical data they need to make a real diagnosis.</p>
				</div>
			</div>

			<div class="trt-step">
				<div class="trt-step__num" aria-hidden="true">2</div>
				<div class="trt-step__body">
					<h3 class="trt-step__heading">Consult With a Myogenix-Affiliated Provider</h3>
					<p class="trt-step__text">After the lab processes your results, you will schedule an online video consultation with a board-certified physician licensed in your home state. These providers specialize in hormone optimization, endocrinology, urology, and internal medicine. Your doctor consultation is included — no separate booking or extra fees.</p>
				</div>
			</div>

		</div>

		<!-- Inline CTA #3 -->
		<div class="trt-cta-block trt-cta-block--accent">
			<?php if ( $trt_image_url ) : ?>
			<div class="trt-cta-block__img">
				<img src="<?php echo esc_url( $trt_image_url ); ?>" alt="Myogenix Testosterone Cypionate" />
			</div>
			<?php endif; ?>
			<div class="trt-cta-block__copy">
				<p class="trt-cta-block__label">Start Your TRT Journey</p>
				<p class="trt-cta-block__heading">Get matched with a Myogenix provider this week</p>
				<p class="trt-cta-block__sub">Board-certified physicians. CLIA-accredited labs. Medication shipped discreetly to your door.</p>
			</div>
			<div class="trt-cta-block__action">
				<a href="<?php echo $trt_url; ?>" class="trt-cta-block__btn">Get Started &rarr;</a>
				<p class="trt-cta-block__price">From <strong>$567</strong>/mo &middot; Consultation included</p>
			</div>
		</div>

		<div class="trt-steps">

			<div class="trt-step">
				<div class="trt-step__num" aria-hidden="true">3</div>
				<div class="trt-step__body">
					<h3 class="trt-step__heading">Discuss Treatment Options With Your Doctor</h3>
					<p class="trt-step__text">Your physician will review your lab results and discuss your symptoms and goals before recommending any treatment. If your T levels are clinically deficient, they will explain the different forms of testosterone replacement available — including each option's benefits, dosing, and potential side effects. A confirmatory assessment may be required before your prescription is finalized.</p>
				</div>
			</div>

			<div class="trt-step">
				<div class="trt-step__num" aria-hidden="true">4</div>
				<div class="trt-step__body">
					<h3 class="trt-step__heading">Receive Your Prescription and Medication</h3>
					<p class="trt-step__text">Once your diagnosis is confirmed, your doctor issues a valid electronic prescription. Your medication is dispensed by a licensed US pharmacy — compounded to your exact prescribed dose — and shipped discreetly to your door. No insurance required. No hidden fees.</p>
				</div>
			</div>

			<div class="trt-step">
				<div class="trt-step__num" aria-hidden="true">5</div>
				<div class="trt-step__body">
					<h3 class="trt-step__heading">Monitor and Reassess Every 90 Days</h3>
					<p class="trt-step__text">Myogenix schedules hormone reassessments every 90 days. Your doctor reviews your updated labs with you and adjusts your protocol as needed. Consistent monitoring keeps your levels optimized, your prescription current, and your treatment on track.</p>
				</div>
			</div>

		</div>

		<!-- Inline CTA #4 -->
		<div class="trt-simple-cta">
			<p class="trt-simple-cta__heading">Everything you need to start TRT — in one place.</p>
			<ul class="trt-simple-cta__list">
				<li>CLIA-accredited lab testing to confirm your levels</li>
				<li>Online consultation with a board-certified physician</li>
				<li>Medication shipped directly to your door</li>
				<li>90-day reassessments to keep your treatment optimized</li>
			</ul>
			<a href="<?php echo $trt_url; ?>" class="trt-simple-cta__btn">Start Now &rarr;</a>
			<span class="trt-simple-cta__price">From $567/mo &middot; Doctor consultation included</span>
		</div>

		<h2>Follow the TRT regimen prescribed by your physician</h2>

		<p>Some men start TRT hoping to get leaner, build muscle faster, or boost their sex drive. While TRT can deliver all of these benefits, they are not enough on their own to justify a prescription — and they are definitely not a reason to modify your regimen without your doctor's approval.</p>

		<p>You are on TRT to restore testosterone levels that have dropped significantly below what the American Urological Association (AUA) categorizes as normal. By restoring those levels, you help your entire body function the way it should — energy, mood, bone density, cardiovascular health, and more.</p>

		<p>Adjusting your dose or protocol without your doctor's sign-off is not only risky to your health — it can be illegal and invalidate your prescription entirely.</p>

		<p>You have done the hard work: tested your levels, consulted with a licensed provider, and received a legitimate prescription from a licensed US pharmacy. Stick to your protocol and the benefits will follow.</p>

		<!-- Inline CTA #5 -->
		<div class="trt-cta-block trt-cta-block--accent">
			<?php if ( $trt_image_url ) : ?>
			<div class="trt-cta-block__img">
				<img src="<?php echo esc_url( $trt_image_url ); ?>" alt="Myogenix Testosterone Cypionate" />
			</div>
			<?php endif; ?>
			<div class="trt-cta-block__copy">
				<p class="trt-cta-block__label">Take the First Step</p>
				<p class="trt-cta-block__heading">Start your TRT protocol with Myogenix</p>
				<p class="trt-cta-block__sub">Join thousands of men who have reclaimed their energy, strength, and quality of life with physician-supervised TRT.</p>
			</div>
			<div class="trt-cta-block__action">
				<a href="<?php echo $trt_url; ?>" class="trt-cta-block__btn">Get Started &rarr;</a>
				<p class="trt-cta-block__price">From <strong>$567</strong>/mo &middot; Consultation included</p>
			</div>
		</div>

	</div><!-- .trt-article__body -->

	<!-- Testimonials -->
	<section class="trt-testimonials" aria-label="Patient testimonials">
		<div class="trt-testimonials__inner">
			<h2 class="trt-testimonials__heading">From Struggle to Success: Before &amp; After TRT</h2>
			<p class="trt-testimonials__sub">Real patients. Real results. Verified by our clinical team.</p>

			<div class="trt-testimonials__grid">
				<?php foreach ( $testimonials as $t ) : ?>
				<article class="trt-testimonial-card">
					<div class="trt-testimonial-card__photo">
						<img
							src="<?php echo esc_url( $img_base . $t['img'] ); ?>"
							alt="<?php echo esc_attr( $t['name'] ); ?> — before and after TRT"
							loading="lazy"
						/>
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

	<!-- Article Footer -->
	<footer class="trt-article-footer">
		<p class="trt-article-footer__disclaimer">
			<strong>Medical Disclaimer:</strong> The information provided in this article is for educational purposes only and does not constitute medical advice. Testosterone replacement therapy carries risks and is only appropriate for individuals with a clinically confirmed deficiency, as determined by a licensed physician. Always consult a qualified healthcare provider before starting any hormone therapy. Results vary by individual. Myogenix providers comply with all applicable state and federal regulations governing the prescription and dispensing of controlled substances.
		</p>
	</footer>

</div><!-- .trt-article -->

<!-- Sticky Bottom Bar -->
<div class="trt-sticky-bar" id="trt-sticky-bar" aria-label="TRT sticky CTA">
	<div class="trt-sticky-bar__inner">
		<div class="trt-sticky-bar__info">
			<div class="trt-sticky-bar__pricing">
				Testosterone Cypionate &mdash; <strong>$567/mo</strong>
				<span class="trt-sticky-bar__incl">(+ Doctor Consultation Included)</span>
			</div>
			<div class="trt-sticky-bar__avail">
				<span class="trt-sticky-bar__dot" aria-hidden="true"></span>
				Availability: In Stock
			</div>
		</div>
		<a href="<?php echo $trt_url; ?>" class="trt-sticky-bar__btn">Start Now</a>
	</div>
</div>

<?php get_footer(); ?>
