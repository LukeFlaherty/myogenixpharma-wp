<?php
/**
 * Site-wide grunge redesign footer.
 */
defined( 'ABSPATH' ) || exit;

$_grunge_asset = function( $path ) {
	$base  = get_stylesheet_directory_uri() . '/assets/images/grunge-redesign/';
	$parts = explode( '/', $path );
	return esc_url( $base . implode( '/', array_map( 'rawurlencode', $parts ) ) );
};

$_footer_year = (int) date( 'Y' );
$_footer_groups = [
	'Programs' => [
		[ 'Weight Management', home_url( '/weight-management/' ) ],
		[ 'Peptides', home_url( '/wellness/' ) ],
		[ 'Sexual Health', home_url( '/sexual-health/' ) ],
		[ 'Mens Health', home_url( '/mens-health/' ) ],
	],
	'Company' => [
		[ 'How it works', home_url( '/#how-it-works' ) ],
		[ 'FAQ', home_url( '/#faq' ) ],
		[ 'Affiliate Program', home_url( '/affiliate-registration/' ) ],
		[ 'Concierge', home_url( '/reach-a-concierge/' ) ],
	],
	'Legal' => [
		[ 'Privacy Policy', home_url( '/privacy-policy/' ) ],
		[ 'Terms of Service', home_url( '/terms-of-service/' ) ],
		[ 'Contact', home_url( '/reach-a-concierge/' ) ],
	],
];
?>
<footer class="home-footer grunge-footer" role="contentinfo">
	<div class="grunge-footer__texture" style="background-image:url('<?php echo $_grunge_asset( 'grunge black section bg blank.png' ); ?>')" aria-hidden="true"></div>
	<div class="grunge-footer__shade" aria-hidden="true"></div>

	<div class="home-footer__inner grunge-footer__inner">
		<div class="home-footer__grid grunge-footer__grid">
			<div class="home-footer__brand grunge-footer__brand">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?> home">
					<img src="<?php echo $_grunge_asset( 'red and white logo.svg' ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" class="home-footer__logo grunge-footer__logo" width="180" height="54">
				</a>
				<p class="home-footer__tagline grunge-footer__tagline">Concierge telehealth care, guided by humans.</p>
			</div>

			<?php foreach ( $_footer_groups as $_heading => $_links ) : ?>
			<div class="grunge-footer__col">
				<p class="home-footer__col-heading grunge-footer__col-heading"><?php echo esc_html( $_heading ); ?></p>
				<ul class="home-footer__links grunge-footer__links">
					<?php foreach ( $_links as $_link ) : ?>
					<li><a href="<?php echo esc_url( $_link[1] ); ?>"><?php echo esc_html( $_link[0] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php endforeach; ?>
		</div>

		<div class="home-footer__bottom grunge-footer__bottom">
			<p class="home-footer__copy grunge-footer__copy">© <?php echo $_footer_year; ?> MyoGenix Pharma. For informational purposes only. Not medical advice.</p>
			<p class="home-footer__disclaimer grunge-footer__disclaimer">Compounded medications are not FDA-approved.</p>
		</div>
	</div>
</footer>
