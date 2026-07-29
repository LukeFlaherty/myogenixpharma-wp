<?php
/**
 * Site-wide navbar partial — included by header.php (all pages) and front-page.php.
 * Computes its own variables so it can be dropped in anywhere.
 *
 * Redesign (2026-07): dark navbar replacing the old white one. "Treatments," "Our
 * Approach," "About Us," and "Start Your Evaluation" have no dedicated pages yet —
 * they placeholder-link to the current page until those are built later in the
 * page-by-page redesign. Update $_nav_links / $_nav_cta_evaluation_url then.
 */
defined( 'ABSPATH' ) || exit;

global $wp;
$_nav_logo_url        = get_stylesheet_directory_uri() . '/assets/images/nav/myogenix-logo.svg';
$_nav_logo_alt        = get_bloginfo( 'name' );
$_nav_account_url     = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'dashboard' ) : wp_login_url();
$_nav_cart_url        = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
$_nav_cart_count      = ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0;
$_nav_placeholder_url = home_url( isset( $wp->request ) ? $wp->request : '' );
$_nav_ask_url         = home_url( '/contact/' );

$_nav_links = [
	[ 'label' => 'How It Works',  'url' => $_nav_placeholder_url ],
	[ 'label' => 'Treatments',    'url' => $_nav_placeholder_url ],
	[ 'label' => 'Our Approach',  'url' => $_nav_placeholder_url ],
	[ 'label' => 'FAQ',           'url' => $_nav_placeholder_url ],
	[ 'label' => 'About Us',      'url' => $_nav_placeholder_url ],
];
?>
<header class="home-nav" role="banner">
	<div class="home-nav__inner">

		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="home-nav__logo-link" aria-label="<?php echo esc_attr( $_nav_logo_alt ); ?> home">
			<img src="<?php echo esc_url( $_nav_logo_url ); ?>" alt="<?php echo esc_attr( $_nav_logo_alt ); ?>" class="home-nav__logo">
		</a>

		<nav class="home-nav__links" aria-label="Primary">
			<ul class="home-nav__menu-list">
				<?php foreach ( $_nav_links as $_link ) : ?>
				<li><a href="<?php echo esc_url( $_link['url'] ); ?>"><?php echo esc_html( strtoupper( $_link['label'] ) ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>

		<div class="home-nav__actions">
			<a href="<?php echo esc_url( $_nav_placeholder_url ); ?>" class="home-nav__cta home-nav__cta--primary">
				Start Your Evaluation
				<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/nav/arrow-white.svg' ); ?>" alt="" class="home-nav__cta-arrow" aria-hidden="true">
			</a>
			<a href="<?php echo esc_url( $_nav_ask_url ); ?>" class="home-nav__cta home-nav__cta--secondary">Ask a Question</a>

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
			</div>

			<button class="home-nav__hamburger" aria-label="Open menu" aria-expanded="false" aria-controls="home-mobile-menu">
				<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true" width="26" height="26">
					<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
				</svg>
			</button>
		</div>

	</div>
</header>

<div class="home-nav__overlay" id="home-nav-overlay" hidden></div>
<div id="home-mobile-menu" class="home-nav__drawer" role="dialog" aria-modal="true" aria-label="Menu" hidden>
	<div class="home-nav__drawer-head">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="home-nav__logo-link">
			<img src="<?php echo esc_url( $_nav_logo_url ); ?>" alt="<?php echo esc_attr( $_nav_logo_alt ); ?>" class="home-nav__logo">
		</a>
		<button class="home-nav__drawer-close" aria-label="Close menu">
			<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true" width="24" height="24">
				<path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
			</svg>
		</button>
	</div>
	<ul class="home-nav__drawer-list">
		<?php foreach ( $_nav_links as $_link ) : ?>
		<li><a href="<?php echo esc_url( $_link['url'] ); ?>"><?php echo esc_html( $_link['label'] ); ?></a></li>
		<?php endforeach; ?>
	</ul>
	<div class="home-nav__drawer-actions">
		<a href="<?php echo esc_url( $_nav_placeholder_url ); ?>" class="home-nav__cta home-nav__cta--primary">
			Start Your Evaluation
			<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/nav/arrow-white.svg' ); ?>" alt="" class="home-nav__cta-arrow" aria-hidden="true">
		</a>
		<a href="<?php echo esc_url( $_nav_ask_url ); ?>" class="home-nav__cta home-nav__cta--secondary">Ask a Question</a>
	</div>
	<div class="home-nav__drawer-icons">
		<a href="<?php echo esc_url( $_nav_cart_url ); ?>" class="home-nav__icon-link home-nav__cart-link" aria-label="Cart<?php echo $_nav_cart_count ? ' (' . $_nav_cart_count . ' items)' : ''; ?>">
			<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true" width="22" height="22">
				<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
			</svg>
			<span>Cart<?php echo $_nav_cart_count ? ' (' . (int) $_nav_cart_count . ')' : ''; ?></span>
		</a>
		<a href="<?php echo esc_url( $_nav_account_url ); ?>" class="home-nav__icon-link home-nav__profile-link" aria-label="My account">
			<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true" width="22" height="22">
				<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
			</svg>
			<span>My Account</span>
		</a>
	</div>
</div>
