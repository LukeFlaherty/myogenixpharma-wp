<?php
/**
 * Cart item data
 *
 * Overrides default to remove wpautop() so <br> line breaks in item data
 * values (e.g. Dose Schedule bullets) render correctly.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 2.4.0
 */

defined( 'ABSPATH' ) || exit;
?>
<dl class="variation">
	<?php foreach ( $item_data as $data ) : ?>
		<dt class="<?php echo sanitize_html_class( 'variation-' . $data['key'] ); ?>"><?php echo wp_kses_post( $data['key'] ); ?>:</dt>
		<dd class="<?php echo sanitize_html_class( 'variation-' . $data['key'] ); ?>"><?php echo wp_kses_post( $data['display'] ); ?></dd>
	<?php endforeach; ?>
</dl>
