<?php
/**
 * Site-wide footer partial — included by footer.php (all pages) and front-page.php.
 * Computes its own variables so it can be dropped in anywhere.
 */
defined( 'ABSPATH' ) || exit;

$_footer_logo_url = wp_get_attachment_image_url( 16, 'full' ) ?: '';
$_footer_logo_alt = get_bloginfo( 'name' );
$_footer_year     = (int) date( 'Y' );
?>
<footer class="home-footer" role="contentinfo">
	<div class="home-footer__inner">
		<div class="home-footer__grid">

			<div class="home-footer__brand">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( $_footer_logo_alt ); ?> home">
					<?php if ( $_footer_logo_url ) : ?>
						<img src="<?php echo esc_url( $_footer_logo_url ); ?>" alt="<?php echo esc_attr( $_footer_logo_alt ); ?>" class="home-footer__logo" width="auto" height="32">
					<?php else : ?>
						<span style="font-size:16px;font-weight:800;color:#000;"><?php echo esc_html( $_footer_logo_alt ); ?></span>
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
					<li><a href="<?php echo esc_url( home_url( '/#how-it-works' ) ); ?>">How it works</a></li>
					<li><a href="<?php echo esc_url( home_url( '/#faq' ) ); ?>">FAQ</a></li>
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
			<p class="home-footer__copy">© <?php echo $_footer_year; ?> MyoGenix Pharma. For informational purposes only. Not medical advice.</p>
			<p class="home-footer__disclaimer">Compounded medications are not FDA-approved.</p>
		</div>
	</div>
</footer>
