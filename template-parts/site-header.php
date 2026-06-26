<?php
/**
 * Site-wide navbar partial — included by header.php (all pages) and front-page.php.
 * Computes its own variables so it can be dropped in anywhere.
 */
defined( 'ABSPATH' ) || exit;

$_nav_logo_url    = wp_get_attachment_image_url( 16, 'full' ) ?: '';
$_nav_logo_alt    = get_bloginfo( 'name' );
$_nav_account_url = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'dashboard' ) : wp_login_url();
$_nav_cart_url    = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
$_nav_cart_count  = ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0;
?>
<header class="home-nav" role="banner">
	<div class="home-nav__inner">

		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="home-nav__logo-link" aria-label="<?php echo esc_attr( $_nav_logo_alt ); ?> home">
			<?php if ( $_nav_logo_url ) : ?>
				<img src="<?php echo esc_url( $_nav_logo_url ); ?>" alt="<?php echo esc_attr( $_nav_logo_alt ); ?>" class="home-nav__logo" width="auto" height="40">
			<?php else : ?>
				<span style="font-size:18px;font-weight:800;color:#000;"><?php echo esc_html( $_nav_logo_alt ); ?></span>
			<?php endif; ?>
		</a>

		<?php
		wp_nav_menu( [
			'menu'            => 3,
			'container'       => 'nav',
			'container_class' => 'home-nav__links',
			'container_id'    => '',
			'menu_class'      => 'home-nav__menu-list',
			'fallback_cb'     => false,
		] );
		?>

		<div class="home-nav__icons">
			<a href="<?php echo esc_url( $_nav_cart_url ); ?>" class="home-nav__icon-link home-nav__cart-link" aria-label="Cart<?php echo $_nav_cart_count ? ' (' . $_nav_cart_count . ' items)' : ''; ?>">
				<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true" width="22" height="22">
					<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
				</svg>
				<span class="home-nav__cart-count<?php echo $_nav_cart_count > 0 ? '' : ' home-nav__cart-count--zero'; ?>" aria-hidden="true"><?php echo (int) $_nav_cart_count; ?></span>
			</a>
			<a href="<?php echo esc_url( $_nav_account_url ); ?>" class="home-nav__icon-link home-nav__profile-link" aria-label="My account">
				<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true" width="22" height="22">
					<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
				</svg>
			</a>
			<button class="home-nav__hamburger" aria-label="Open menu" aria-expanded="false" aria-controls="home-mobile-menu">
				<span></span><span></span><span></span>
			</button>
		</div>

	</div>

	<div id="home-mobile-menu" class="home-nav__mobile-menu">
		<?php
		wp_nav_menu( [
			'menu'        => 3,
			'container'   => false,
			'menu_class'  => 'home-nav__mobile-list',
			'fallback_cb' => false,
		] );
		?>
	</div>
</header>
