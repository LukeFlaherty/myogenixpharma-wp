<?php
/**
 * Searchable grunge medications directory.
 */
defined( 'ABSPATH' ) || exit;

$myo_asset = function( $path ) {
	return function_exists( 'myogenix_grunge_asset_url' ) ? myogenix_grunge_asset_url( $path ) : '';
};

add_filter( 'body_class', function( $classes ) {
	$classes[] = 'grunge-redesign-page';
	$classes[] = 'grunge-medications-page';
	return $classes;
} );

add_filter( 'pre_get_document_title', function() {
	return 'Medications - Myogenix Pharma';
} );

add_filter( 'rank_math/frontend/title', function() {
	return 'Medications - Myogenix Pharma';
} );

$unit_by_slug = [
	'compound-semaglutide' => '/mo',
	'compound-tirzepatide' => '/mo',
	'compound-retatrutide' => '/mo',
	'compound-oral-tadalafil' => '/tablet',
	'compound-sildenafil' => '/tablet',
	'bpc' => '/vial',
	'motsc' => '/vial',
	'epithalon' => '/vial',
	'compound-injectable-nad' => '/vial',
	'compound-injectable-sermorelin' => '/vial',
	'compound-injectable-glutathione' => '/vial',
	'tesamorelin-ipamorelin' => '/vial',
	'cjc1295-ipamorelin' => '/vial',
	'klow-stack-bpc157-10mg-ghk-cu-50mg-tb50010mg-kpv-10mg' => '/vial',
	'2606' => '/vial',
];

$line_by_slug = [
	'testosterone' => 'Men\'s health',
	'hcg' => 'Men\'s health',
	'compound-semaglutide' => 'Weight management',
	'compound-tirzepatide' => 'Weight management',
	'compound-retatrutide' => 'Weight management',
	'compound-oral-tadalafil' => 'Sexual health',
	'compound-sildenafil' => 'Sexual health',
];

$extra_search_by_slug = [
	'testosterone' => 'trt testosterone therapy hormone optimization mens health',
	'hcg' => 'mens health hormone testosterone fertility',
	'compound-semaglutide' => 'glp glp-1 weight loss appetite metabolic',
	'compound-tirzepatide' => 'glp glp-1 gip weight loss appetite metabolic',
	'compound-retatrutide' => 'glp glp-1 weight loss appetite metabolic',
	'compound-oral-tadalafil' => 'ed sexual health cialis tablet',
	'compound-sildenafil' => 'ed sexual health viagra tablet',
	'compound-injectable-nad' => 'nad cellular energy longevity',
];

$medications = [];
if ( function_exists( 'wc_get_products' ) ) {
	$wc_products = wc_get_products( [
		'status'  => 'publish',
		'limit'   => -1,
		'orderby' => 'title',
		'order'   => 'ASC',
		'return'  => 'objects',
	] );

	foreach ( $wc_products as $product ) {
		if ( ! $product || ! is_object( $product ) || ! method_exists( $product, 'get_slug' ) ) continue;
		$slug = $product->get_slug();
		$terms = wp_get_post_terms( $product->get_id(), 'product_cat', [ 'fields' => 'names' ] );
		$terms = is_wp_error( $terms ) ? [] : array_values( array_filter( $terms, fn( $term ) => 'Uncategorized' !== $term ) );
		$category_label = $line_by_slug[ $slug ] ?? ( $terms[0] ?? 'Medication' );
		$unit = $unit_by_slug[ $slug ] ?? '';
		$raw_price = function_exists( 'myogenix_get_lowest_product_price' )
			? myogenix_get_lowest_product_price( $product, $unit )
			: (float) $product->get_price();
		$decimals = '/tablet' === $unit ? 2 : 0;
		$price = null === $raw_price
			? 'Select for pricing'
			: 'Starting at $' . number_format( max( 0, (float) $raw_price ), $decimals ) . $unit;
		$image = function_exists( 'myogenix_grunge_bottle_url' ) ? myogenix_grunge_bottle_url( $product ) : '';
		if ( ! $image ) {
			$image = get_the_post_thumbnail_url( $product->get_id(), 'large' ) ?: get_the_post_thumbnail_url( $product->get_id(), 'full' );
		}
		if ( ! $image ) {
			$image = $myo_asset( 'pharma support staff tp bg.png' );
		}

		$search_parts = array_merge( [ $product->get_name(), $slug, $category_label, $extra_search_by_slug[ $slug ] ?? '' ], $terms );
		$medications[] = [
			'name' => $product->get_name(),
			'category' => $category_label,
			'price' => $price,
			'url' => $product->get_permalink(),
			'image' => $image,
			'search' => strtolower( implode( ' ', $search_parts ) ),
		];
	}
}

get_header();
?>

<main id="content" class="grunge-medications">
	<section class="grunge-category-hero grunge-medications__hero">
		<div class="grunge-category-hero__bg" style="background-image:url('<?php echo $myo_asset( 'hero bg.png' ); ?>')" aria-hidden="true"></div>
		<div class="grunge-category-hero__shade" aria-hidden="true"></div>
		<div class="grunge-category-hero__inner">
			<div class="grunge-category-hero__copy">
				<h1 class="grunge-category-hero__title">
					<span class="grunge-word grunge-word--white">Search</span>
					<span class="grunge-word grunge-word--red">Medications</span>
				</h1>
				<p class="grunge-category-hero__body">Search by medication, category, or treatment line, then select a product to review the details.</p>
			</div>
			<div class="grunge-category-hero__media">
				<img src="<?php echo $myo_asset( 'mgrx-hero-team.webp' ); ?>" alt="" width="620" height="520">
			</div>
		</div>
	</section>

	<section class="grunge-section grunge-medications__directory">
		<div class="grunge-section__texture" style="background-image:url('<?php echo $myo_asset( 'red-dots-grid-background.webp' ); ?>')" aria-hidden="true"></div>
		<div class="grunge-container">
			<div class="grunge-med-search">
				<label for="grunge-med-search-input">Search medications</label>
				<input id="grunge-med-search-input" type="search" placeholder="Search semaglutide, TRT, peptides..." autocomplete="off">
			</div>
			<div class="grunge-med-grid" id="grunge-med-grid">
				<?php foreach ( $medications as $medication ) : ?>
				<a class="grunge-med-card" href="<?php echo esc_url( $medication['url'] ); ?>" data-med-search="<?php echo esc_attr( $medication['search'] ); ?>">
					<div class="grunge-med-card__image">
						<img src="<?php echo esc_url( $medication['image'] ); ?>" alt="<?php echo esc_attr( $medication['name'] ); ?>" width="240" height="240" loading="lazy">
					</div>
					<div class="grunge-med-card__body">
						<p><?php echo esc_html( $medication['category'] ); ?></p>
						<h2><?php echo esc_html( $medication['name'] ); ?></h2>
						<strong><?php echo esc_html( $medication['price'] ); ?></strong>
						<span>Select Medication <?php echo myogenix_grunge_arrow_svg(); ?></span>
					</div>
				</a>
				<?php endforeach; ?>
			</div>
			<p class="grunge-med-empty" id="grunge-med-empty" hidden>No medications match your search.</p>
		</div>
	</section>
</main>

<script>
(function() {
	var input = document.getElementById('grunge-med-search-input');
	var cards = Array.prototype.slice.call(document.querySelectorAll('[data-med-search]'));
	var empty = document.getElementById('grunge-med-empty');
	if (!input || !cards.length) return;
	input.addEventListener('input', function() {
		var query = input.value.trim().toLowerCase();
		var visible = 0;
		cards.forEach(function(card) {
			var match = !query || card.getAttribute('data-med-search').indexOf(query) !== -1;
			card.hidden = !match;
			if (match) visible++;
		});
		if (empty) empty.hidden = visible !== 0;
	});
})();
</script>

<?php
get_footer();
