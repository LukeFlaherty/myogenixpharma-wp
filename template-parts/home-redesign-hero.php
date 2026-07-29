<?php
/**
 * Hero section — used ONLY by page-home-redesign.php. Namespaced under
 * "rdx-hero" alongside the rdx-nav component; nothing here is shared with
 * the rest of the live site.
 *
 * "Start Evaluation" placeholder-links to the current page pending a real
 * evaluation flow, same as the navbar CTA — see site-header-redesign.php.
 */
defined( 'ABSPATH' ) || exit;

global $wp;
$_rdx_hero_img_base = get_stylesheet_directory_uri() . '/assets/images/hero/';
$_rdx_hero_cta_url  = home_url( isset( $wp->request ) ? $wp->request : '' );
$_rdx_hero_ask_url  = home_url( '/contact/' );
$_rdx_hero_arrow    = '<svg class="rdx-nav__cta-arrow" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" aria-hidden="true"><path fill="currentColor" d="M36.8 12.7 56.1 32 36.8 51.3l-6.4-6.4 8.4-8.4H7.9v-9h30.9l-8.4-8.4 6.4-6.4Z"/></svg>';
?>
<section class="rdx-hero" aria-label="Introduction">
	<div class="rdx-hero__bg" style="background-image:url('<?php echo esc_url( $_rdx_hero_img_base . 'hero-bg-top.jpg' ); ?>');"></div>
	<div class="rdx-hero__bg-fade" aria-hidden="true"></div>

	<div class="rdx-hero__inner">
		<div class="rdx-hero__copy">
			<p class="rdx-hero__eyebrow">25+ Years of Performance</p>

			<h1 class="rdx-hero__headline">
				<span class="screen-reader-text">Myogenix Pharma</span>
				<span class="rdx-hero__headline-visual" aria-hidden="true">
					<span class="rdx-hero__headline-line rdx-hero__headline-line--accent" data-text="MYOGENIX">MYOGENIX</span>
					<span class="rdx-hero__headline-line rdx-hero__headline-line--white" data-text="PHARMA">PHARMA</span>
				</span>
			</h1>

			<p class="rdx-hero__tagline">Concierge Telehealth for TRT</p>
			<p class="rdx-hero__sub">Performance care, guided by humans.</p>

			<div class="rdx-hero__ctas">
				<a href="<?php echo esc_url( $_rdx_hero_cta_url ); ?>" class="rdx-nav__cta rdx-nav__cta--primary rdx-hero__cta">
					Start Evaluation
					<?php echo $_rdx_hero_arrow; ?>
				</a>
				<a href="<?php echo esc_url( $_rdx_hero_ask_url ); ?>" class="rdx-nav__cta rdx-nav__cta--secondary rdx-hero__cta">Ask a Question</a>
			</div>
		</div>

		<div class="rdx-hero__media">
			<img src="<?php echo esc_url( $_rdx_hero_img_base . 'hero-foreground.jpg' ); ?>" alt="Myogenix Pharma care team reviewing a patient's treatment plan on a tablet, with the care-journey app and a testosterone vial" class="rdx-hero__foreground" width="840" height="700">
		</div>
	</div>

	<div class="rdx-hero__bleed" style="background-image:url('<?php echo esc_url( $_rdx_hero_img_base . 'hero-bg-bottom.jpg' ); ?>');" aria-hidden="true"></div>
</section>
