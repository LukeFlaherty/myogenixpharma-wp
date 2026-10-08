<?php
/** Product-specific research cards; kept separate from customer review data. */
defined( 'ABSPATH' ) || exit;
$evidence_product = $args['product'] ?? null;
if ( ! $evidence_product instanceof WC_Product ) {
	return;
}
$evidence_catalog = require get_stylesheet_directory() . '/inc/product-evidence-data.php';
$evidence = $evidence_catalog[ $evidence_product->get_slug() ] ?? null;
if ( ! $evidence ) {
	return;
}
?>
<section class="myo-evidence" aria-labelledby="myo-evidence-title">
	<div class="grunge-container">
		<div class="myo-evidence__header">
			<p class="grunge-kicker">Published research · <?php echo esc_html( $evidence['name'] ); ?></p>
			<h2 id="myo-evidence-title">What the <span class="grunge-text-red">research shows</span></h2>
			<p><?php echo esc_html( $evidence['context'] ); ?></p>
		</div>
		<div class="myo-evidence__grid">
			<?php foreach ( $evidence['cards'] as $card ) : ?>
			<article class="myo-evidence__card">
				<span class="myo-evidence__tag"><?php echo esc_html( $card['tag'] ); ?></span>
				<h3><?php echo esc_html( $card['title'] ); ?></h3>
				<p><?php echo esc_html( $card['text'] ); ?></p>
				<a href="<?php echo esc_url( $card['url'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( 'Read source: ' . $card['source'] . ' (opens in a new tab)' ); ?>"><?php echo esc_html( $card['source'] ); ?> <span aria-hidden="true">&#8599;</span></a>
			</article>
			<?php endforeach; ?>
		</div>
		<p class="myo-evidence__context"><?php echo esc_html( $evidence['note'] ); ?><?php if ( ! empty( $evidence['label_url'] ) ) : ?> <a href="<?php echo esc_url( $evidence['label_url'] ); ?>" target="_blank" rel="noopener noreferrer">Prescribing information &#8599;</a><?php endif; ?></p>
		<p class="myo-evidence__footnote">Published findings, not customer testimonials. Individual outcomes vary; a provider determines whether treatment is appropriate.</p>
	</div>
</section>
