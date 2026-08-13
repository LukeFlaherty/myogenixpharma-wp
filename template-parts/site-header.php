<?php
/**
 * Site-wide grunge redesign navbar.
 */
defined( 'ABSPATH' ) || exit;

$_grunge_asset = function( $path ) {
	$base  = get_stylesheet_directory_uri() . '/assets/images/grunge-redesign/';
	$parts = explode( '/', $path );
	return esc_url( $base . implode( '/', array_map( 'rawurlencode', $parts ) ) );
};

$_nav_account_url = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'dashboard' ) : wp_login_url();
$_nav_cart_url    = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
$_nav_cart_count  = ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0;
$_nav_path        = isset( $_SERVER['REQUEST_URI'] ) ? strtok( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), '?' ) : '/';

$_nav_all_links = [
	[ 'label' => 'Weight Management', 'url' => home_url( '/weight-management/' ), 'match' => [ '/weight-management/', '/product-category/weight-loss/' ] ],
	[ 'label' => 'Mens Health',       'url' => home_url( '/mens-health/' ),       'match' => [ '/mens-health/', '/product-category/mens-health/' ] ],
	[ 'label' => 'Peptides',          'url' => home_url( '/wellness/' ),          'match' => [ '/wellness/', '/product-category/peptides-longevity/' ] ],
	[ 'label' => 'Sexual Health',     'url' => home_url( '/sexual-health/' ),     'match' => [ '/sexual-health/', '/product-category/sexual-health/' ] ],
];

$_nav_current_index = null;
foreach ( $_nav_all_links as $_idx => $_link ) {
	if ( in_array( $_nav_path, $_link['match'], true ) ) {
		$_nav_current_index = $_idx;
		break;
	}
}
$_nav_links = array_values( array_filter(
	$_nav_all_links,
	fn( $_link, $_idx ) => $_idx !== $_nav_current_index,
	ARRAY_FILTER_USE_BOTH
) );
$_nav_links = array_slice( $_nav_links, 0, 3 );

$_mobile_links = array_merge( $_nav_all_links, [
	[ 'label' => 'How it works', 'url' => home_url( '/#how-it-works' ), 'match' => [] ],
	[ 'label' => 'FAQ', 'url' => home_url( '/#faq' ), 'match' => [] ],
] );
?>
<header class="home-nav grunge-nav" role="banner">
	<div class="grunge-nav__texture" style="background-image:url('<?php echo $_grunge_asset( 'thin section bg.png' ); ?>')" aria-hidden="true"></div>
	<div class="grunge-nav__shade" aria-hidden="true"></div>

	<div class="home-nav__inner grunge-nav__inner">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="home-nav__logo-link grunge-nav__logo-link" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?> home">
			<img src="<?php echo $_grunge_asset( 'red and white logo.svg' ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" class="home-nav__logo grunge-nav__logo" width="170" height="50">
		</a>

		<nav class="home-nav__links grunge-nav__links" aria-label="Primary navigation">
			<ul class="home-nav__menu-list grunge-nav__menu-list">
				<?php foreach ( $_nav_links as $_link ) :
					$_active = in_array( $_nav_path, $_link['match'], true );
				?>
				<li class="<?php echo $_active ? 'current-menu-item' : ''; ?>">
					<a href="<?php echo esc_url( $_link['url'] ); ?>"><?php echo esc_html( $_link['label'] ); ?></a>
				</li>
				<?php endforeach; ?>
			</ul>
		</nav>

		<div class="home-nav__icons grunge-nav__actions">
			<a href="<?php echo esc_url( $_nav_cart_url ); ?>" class="home-nav__icon-link home-nav__cart-link grunge-nav__icon-link" aria-label="Cart<?php echo $_nav_cart_count ? ' (' . $_nav_cart_count . ' items)' : ''; ?>">
				<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true" width="22" height="22">
					<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
				</svg>
				<span class="home-nav__cart-count<?php echo $_nav_cart_count > 0 ? '' : ' home-nav__cart-count--zero'; ?>" aria-hidden="true"><?php echo (int) $_nav_cart_count; ?></span>
			</a>
			<a href="<?php echo esc_url( $_nav_account_url ); ?>" class="grunge-nav__signin">Sign in</a>
			<a href="<?php echo esc_url( home_url( '/weight-management/' ) ); ?>" class="grunge-nav__cta">Get started</a>
			<button class="home-nav__hamburger grunge-nav__hamburger" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="home-mobile-menu">
				<span></span><span></span><span></span>
			</button>
		</div>
	</div>

	<div id="home-mobile-menu" class="home-nav__mobile-menu grunge-nav__mobile-menu">
		<nav aria-label="Mobile navigation">
			<ul class="home-nav__mobile-list grunge-nav__mobile-list">
				<?php foreach ( $_mobile_links as $_link ) :
					$_active = ! empty( $_link['match'] ) && in_array( $_nav_path, $_link['match'], true );
				?>
				<li class="<?php echo $_active ? 'current-menu-item' : ''; ?>">
					<a href="<?php echo esc_url( $_link['url'] ); ?>"><?php echo esc_html( $_link['label'] ); ?></a>
				</li>
				<?php endforeach; ?>
				<li><a href="<?php echo esc_url( $_nav_account_url ); ?>">Sign in</a></li>
				<li><a href="<?php echo esc_url( home_url( '/weight-management/' ) ); ?>">Get started</a></li>
			</ul>
		</nav>
	</div>
</header>
