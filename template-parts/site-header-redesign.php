<?php
/**
 * Redesign navbar — used ONLY by page-home-redesign.php (the hidden staging
 * page for the new home page). Deliberately NOT shared with header.php or
 * front-page.php, and uses its own "rdx-nav" class namespace + its own
 * stylesheet/script (home-redesign.css / home-redesign.js) so nothing here
 * can affect the rest of the live site while the redesign is in progress.
 *
 * "Treatments," "Our Approach," "About Us," and the primary CTA have
 * no dedicated pages yet — they placeholder-link to the current page until
 * those are built later in the page-by-page redesign.
 */
defined( 'ABSPATH' ) || exit;

global $wp;
$_rdx_logo_url        = get_stylesheet_directory_uri() . '/assets/images/nav/myogenix-logo.svg';
$_rdx_logo_alt        = get_bloginfo( 'name' );
$_rdx_account_url     = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'dashboard' ) : wp_login_url();
$_rdx_cart_url        = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
$_rdx_cart_count      = ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0;
$_rdx_placeholder_url = home_url( isset( $wp->request ) ? $wp->request : '' );
$_rdx_ask_url         = home_url( '/contact/' );

// Inline arrow icon, using the approved button-arrow-white-v2 path directly
// (the original arrow-white.svg export was a Figma "recolor" raster+mask with
// an opaque background rect that rendered as a solid square — this is the
// clean vector approved in the hero asset sheet).
$_rdx_arrow_svg = '<svg class="rdx-nav__cta-arrow" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" aria-hidden="true"><path fill="currentColor" d="M36.8 12.7 56.1 32 36.8 51.3l-6.4-6.4 8.4-8.4H7.9v-9h30.9l-8.4-8.4 6.4-6.4Z"/></svg>';

$_rdx_links = [
	[ 'label' => 'How It Works',  'url' => $_rdx_placeholder_url ],
	[ 'label' => 'Treatments',    'url' => $_rdx_placeholder_url ],
	[ 'label' => 'Our Approach',  'url' => $_rdx_placeholder_url ],
	[ 'label' => 'FAQ',           'url' => $_rdx_placeholder_url ],
	[ 'label' => 'About Us',      'url' => $_rdx_placeholder_url ],
];
?>
<header class="rdx-nav" role="banner">
	<div class="rdx-nav__inner">

		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="rdx-nav__logo-link" aria-label="<?php echo esc_attr( $_rdx_logo_alt ); ?> home">
			<img src="<?php echo esc_url( $_rdx_logo_url ); ?>" alt="<?php echo esc_attr( $_rdx_logo_alt ); ?>" class="rdx-nav__logo">
		</a>

		<nav class="rdx-nav__links" aria-label="Primary">
			<ul class="rdx-nav__menu-list">
				<?php foreach ( $_rdx_links as $_link ) : ?>
				<li><a href="<?php echo esc_url( $_link['url'] ); ?>"><?php echo esc_html( strtoupper( $_link['label'] ) ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>

		<div class="rdx-nav__actions">
			<a href="<?php echo esc_url( $_rdx_placeholder_url ); ?>" class="rdx-nav__cta rdx-nav__cta--primary">
				Learn More
				<?php echo $_rdx_arrow_svg; ?>
			</a>
			<a href="<?php echo esc_url( $_rdx_ask_url ); ?>" class="rdx-nav__cta rdx-nav__cta--secondary">Ask a Question</a>

			<div class="rdx-nav__icons">
				<a href="<?php echo esc_url( $_rdx_cart_url ); ?>" class="rdx-nav__icon-link rdx-nav__cart-link" aria-label="Cart<?php echo $_rdx_cart_count ? ' (' . $_rdx_cart_count . ' items)' : ''; ?>">
					<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true" width="22" height="22">
						<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
					</svg>
					<span class="rdx-nav__cart-count<?php echo $_rdx_cart_count > 0 ? '' : ' rdx-nav__cart-count--zero'; ?>" aria-hidden="true"><?php echo (int) $_rdx_cart_count; ?></span>
				</a>
				<a href="<?php echo esc_url( $_rdx_account_url ); ?>" class="rdx-nav__icon-link rdx-nav__profile-link" aria-label="My account">
					<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true" width="22" height="22">
						<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
					</svg>
				</a>
			</div>

			<button class="rdx-nav__hamburger" aria-label="Open menu" aria-expanded="false" aria-controls="rdx-mobile-menu">
				<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true" width="26" height="26">
					<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
				</svg>
			</button>
		</div>

	</div>
</header>

<div class="rdx-nav__overlay" id="rdx-nav-overlay" hidden></div>
<div id="rdx-mobile-menu" class="rdx-nav__drawer" role="dialog" aria-modal="true" aria-label="Menu" hidden>
	<div class="rdx-nav__drawer-head">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="rdx-nav__logo-link">
			<img src="<?php echo esc_url( $_rdx_logo_url ); ?>" alt="<?php echo esc_attr( $_rdx_logo_alt ); ?>" class="rdx-nav__logo">
		</a>
		<button class="rdx-nav__drawer-close" aria-label="Close menu">
			<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true" width="24" height="24">
				<path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
			</svg>
		</button>
	</div>
	<ul class="rdx-nav__drawer-list">
		<?php foreach ( $_rdx_links as $_link ) : ?>
		<li><a href="<?php echo esc_url( $_link['url'] ); ?>"><?php echo esc_html( $_link['label'] ); ?></a></li>
		<?php endforeach; ?>
	</ul>
	<div class="rdx-nav__drawer-actions">
		<a href="<?php echo esc_url( $_rdx_placeholder_url ); ?>" class="rdx-nav__cta rdx-nav__cta--primary">
			Learn More
			<?php echo $_rdx_arrow_svg; ?>
		</a>
		<a href="<?php echo esc_url( $_rdx_ask_url ); ?>" class="rdx-nav__cta rdx-nav__cta--secondary">Ask a Question</a>
	</div>
	<div class="rdx-nav__drawer-icons">
		<a href="<?php echo esc_url( $_rdx_cart_url ); ?>" class="rdx-nav__icon-link rdx-nav__cart-link" aria-label="Cart<?php echo $_rdx_cart_count ? ' (' . $_rdx_cart_count . ' items)' : ''; ?>">
			<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true" width="22" height="22">
				<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
			</svg>
			<span>Cart<?php echo $_rdx_cart_count ? ' (' . (int) $_rdx_cart_count . ')' : ''; ?></span>
		</a>
		<a href="<?php echo esc_url( $_rdx_account_url ); ?>" class="rdx-nav__icon-link rdx-nav__profile-link" aria-label="My account">
			<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true" width="22" height="22">
				<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
			</svg>
			<span>My Account</span>
		</a>
	</div>
</div>
