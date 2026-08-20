<?php
/**
 * Product Reviews: custom post type + admin CRUD + front-end submission.
 *
 * Reviews are stored as the `myogenix_review` CPT. New submissions from the
 * public /reviews/ page are created with post_status = 'pending' so an admin
 * can moderate them in wp-admin before they appear on the front end.
 */
defined( 'ABSPATH' ) || exit;

// ─── CPT registration ─────────────────────────────────────────────────────

add_action( 'init', function() {
	register_post_type( 'myogenix_review', [
		'labels' => [
			'name'               => 'Product Reviews',
			'singular_name'      => 'Product Review',
			'menu_name'          => 'Reviews',
			'add_new'            => 'Add Review',
			'add_new_item'       => 'Add Product Review',
			'edit_item'          => 'Edit Product Review',
			'new_item'           => 'New Product Review',
			'view_item'          => 'View Product Review',
			'all_items'          => 'All Reviews',
			'search_items'       => 'Search Reviews',
			'not_found'          => 'No reviews found',
			'not_found_in_trash' => 'No reviews found in Trash',
		],
		'public'             => false,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_in_admin_bar'  => true,
		'menu_icon'          => 'dashicons-star-filled',
		'menu_position'      => 26,
		'supports'           => [ 'title' ],
		'capability_type'    => 'post',
		'map_meta_cap'       => true,
	] );
} );

// Default new reviews created via wp-admin to "pending" (Add New defaults to
// draft; nudge admins toward the moderation states actually used here is left
// to the normal Publish box — draft/pending/publish all work).

// ─── Admin list columns ────────────────────────────────────────────────────

add_filter( 'manage_myogenix_review_posts_columns', function( $columns ) {
	$new = [];
	foreach ( $columns as $key => $label ) {
		if ( 'title' === $key ) {
			$new[ $key ] = 'Reviewer';
			continue;
		}
		$new[ $key ] = $label;
	}
	$new['myo_review_rating']   = 'Rating';
	$new['myo_review_products'] = 'Products';
	$new['myo_review_email']    = 'Email';
	return $new;
} );

add_action( 'manage_myogenix_review_posts_custom_column', function( $column, $post_id ) {
	switch ( $column ) {
		case 'myo_review_rating':
			$ratings = get_post_meta( $post_id, '_myo_review_ratings', true );
			$ratings = is_array( $ratings ) ? $ratings : [];
			if ( empty( $ratings ) ) {
				echo '&mdash;';
				break;
			}
			$avg = array_sum( $ratings ) / count( $ratings );
			echo esc_html( number_format( $avg, 1 ) ) . ' / 5';
			break;

		case 'myo_review_products':
			$product_ids = get_post_meta( $post_id, '_myo_review_products', true );
			$product_ids = is_array( $product_ids ) ? $product_ids : [];
			if ( empty( $product_ids ) ) {
				echo '&mdash;';
				break;
			}
			$names = array_map( function( $pid ) {
				$title = get_the_title( $pid );
				return $title ?: '#' . $pid;
			}, $product_ids );
			echo esc_html( implode( ', ', $names ) );
			break;

		case 'myo_review_email':
			echo esc_html( get_post_meta( $post_id, '_myo_review_email', true ) );
			break;
	}
}, 10, 2 );

add_filter( 'manage_edit-myogenix_review_sortable_columns', function( $columns ) {
	$columns['myo_review_email'] = 'myo_review_email';
	return $columns;
} );

// ─── Admin meta box (full edit CRUD for a review's structured data) ───────

add_action( 'add_meta_boxes', function() {
	add_meta_box(
		'myo_review_details',
		'Review Details',
		'myogenix_render_review_meta_box',
		'myogenix_review',
		'normal',
		'high'
	);
} );

function myogenix_render_review_meta_box( $post ) {
	wp_nonce_field( 'myo_review_save', 'myo_review_nonce' );

	$email    = get_post_meta( $post->ID, '_myo_review_email', true );
	$message  = get_post_meta( $post->ID, '_myo_review_message', true );
	$ratings  = get_post_meta( $post->ID, '_myo_review_ratings', true );
	$ratings  = is_array( $ratings ) ? $ratings : [];
	$verified = (bool) get_post_meta( $post->ID, '_myo_review_verified', true );

	$products = [];
	if ( function_exists( 'wc_get_products' ) ) {
		$products = wc_get_products( [
			'status'  => 'publish',
			'limit'   => -1,
			'orderby' => 'title',
			'order'   => 'ASC',
			'return'  => 'objects',
		] );
	}
	?>
	<style>
		.myo-review-admin-field { margin-bottom: 16px; }
		.myo-review-admin-field label { display: block; font-weight: 600; margin-bottom: 4px; }
		.myo-review-admin-field input[type="text"],
		.myo-review-admin-field input[type="email"],
		.myo-review-admin-field textarea { width: 100%; max-width: 480px; }
		.myo-review-admin-products { border: 1px solid #dcdcde; padding: 10px 12px; max-width: 560px; }
		.myo-review-admin-product-row { display: flex; align-items: center; gap: 10px; padding: 6px 0; border-bottom: 1px solid #f0f0f1; }
		.myo-review-admin-product-row:last-child { border-bottom: none; }
		.myo-review-admin-product-row label.product-name { flex: 1; font-weight: 400; margin: 0; }
	</style>

	<div class="myo-review-admin-field">
		<label for="myo_review_email">Reviewer email</label>
		<input type="email" id="myo_review_email" name="myo_review_email" value="<?php echo esc_attr( $email ); ?>">
	</div>

	<div class="myo-review-admin-field">
		<label>
			<input type="checkbox" name="myo_review_verified" value="1" <?php checked( $verified ); ?>>
			Verified purchase
		</label>
	</div>

	<div class="myo-review-admin-field">
		<label>Products &amp; star ratings</label>
		<div class="myo-review-admin-products">
			<?php if ( empty( $products ) ) : ?>
				<p>No published products found.</p>
			<?php else : ?>
				<?php foreach ( $products as $product ) :
					$pid    = $product->get_id();
					$rating = isset( $ratings[ $pid ] ) ? (int) $ratings[ $pid ] : 0;
					?>
					<div class="myo-review-admin-product-row">
						<label class="product-name">
							<input type="checkbox" name="myo_review_product_included[]" value="<?php echo esc_attr( $pid ); ?>" <?php checked( $rating > 0 ); ?>>
							<?php echo esc_html( $product->get_name() ); ?>
						</label>
						<select name="myo_review_product_rating[<?php echo esc_attr( $pid ); ?>]">
							<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
								<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $rating, $i ); ?>><?php echo esc_html( $i ); ?> star<?php echo 1 === $i ? '' : 's'; ?></option>
							<?php endfor; ?>
						</select>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<p class="description">Check a product and pick its star rating to include it on this review.</p>
	</div>

	<div class="myo-review-admin-field">
		<label for="myo_review_message">Additional information</label>
		<textarea id="myo_review_message" name="myo_review_message" rows="4"><?php echo esc_textarea( $message ); ?></textarea>
	</div>
	<?php
}

add_action( 'save_post_myogenix_review', function( $post_id ) {
	if ( ! isset( $_POST['myo_review_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['myo_review_nonce'] ), 'myo_review_save' ) ) return;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;

	$email = isset( $_POST['myo_review_email'] ) ? sanitize_email( wp_unslash( $_POST['myo_review_email'] ) ) : '';
	update_post_meta( $post_id, '_myo_review_email', $email );

	update_post_meta( $post_id, '_myo_review_verified', ! empty( $_POST['myo_review_verified'] ) ? 1 : 0 );

	$message = isset( $_POST['myo_review_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['myo_review_message'] ) ) : '';
	update_post_meta( $post_id, '_myo_review_message', $message );

	$included = isset( $_POST['myo_review_product_included'] ) && is_array( $_POST['myo_review_product_included'] )
		? array_map( 'absint', wp_unslash( $_POST['myo_review_product_included'] ) )
		: [];
	$rating_input = isset( $_POST['myo_review_product_rating'] ) && is_array( $_POST['myo_review_product_rating'] )
		? wp_unslash( $_POST['myo_review_product_rating'] )
		: [];

	$ratings = [];
	foreach ( $included as $pid ) {
		$pid = absint( $pid );
		if ( ! $pid || 'product' !== get_post_type( $pid ) ) continue;
		$rating = isset( $rating_input[ $pid ] ) ? absint( $rating_input[ $pid ] ) : 0;
		if ( $rating < 1 || $rating > 5 ) continue;
		$ratings[ $pid ] = $rating;
	}

	update_post_meta( $post_id, '_myo_review_ratings', $ratings );
	update_post_meta( $post_id, '_myo_review_products', array_map( 'absint', array_keys( $ratings ) ) );
} );

// ─── Front-end submission (AJAX) ───────────────────────────────────────────

add_action( 'wp_ajax_myogenix_submit_review', 'myogenix_handle_review_submission' );
add_action( 'wp_ajax_nopriv_myogenix_submit_review', 'myogenix_handle_review_submission' );

function myogenix_handle_review_submission() {
	check_ajax_referer( 'myogenix_review_submit', 'nonce' );

	// Honeypot: real users never fill this hidden field.
	if ( ! empty( $_POST['myo_review_website'] ) ) {
		wp_send_json_success( [ 'message' => 'Thanks! Your review has been submitted and is pending approval.' ] );
	}

	$name  = isset( $_POST['reviewer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['reviewer_name'] ) ) : '';
	$email = isset( $_POST['reviewer_email'] ) ? sanitize_email( wp_unslash( $_POST['reviewer_email'] ) ) : '';
	$note  = isset( $_POST['additional_info'] ) ? sanitize_textarea_field( wp_unslash( $_POST['additional_info'] ) ) : '';

	if ( '' === $name || ! is_email( $email ) ) {
		wp_send_json_error( [ 'message' => 'Please provide your name and a valid email.' ] );
	}

	$raw_ratings = isset( $_POST['ratings'] ) && is_array( $_POST['ratings'] ) ? wp_unslash( $_POST['ratings'] ) : [];
	$ratings = [];
	foreach ( $raw_ratings as $product_id => $rating ) {
		$product_id = absint( $product_id );
		$rating     = absint( $rating );
		if ( ! $product_id || $rating < 1 || $rating > 5 ) continue;
		if ( 'product' !== get_post_type( $product_id ) ) continue;
		$ratings[ $product_id ] = $rating;
	}

	if ( empty( $ratings ) ) {
		wp_send_json_error( [ 'message' => 'Please select at least one product you purchased and give it a star rating.' ] );
	}

	$user_id = get_current_user_id();
	if ( $user_id ) {
		$user = get_userdata( $user_id );
		if ( $user && '' === $email ) {
			$email = $user->user_email;
		}
	}

	$post_id = wp_insert_post( [
		'post_type'   => 'myogenix_review',
		'post_title'  => $name . ' — ' . gmdate( 'Y-m-d H:i' ),
		'post_status' => 'pending',
		'post_content' => $note,
	], true );

	if ( is_wp_error( $post_id ) ) {
		wp_send_json_error( [ 'message' => 'Something went wrong submitting your review. Please try again.' ] );
	}

	update_post_meta( $post_id, '_myo_review_email', $email );
	update_post_meta( $post_id, '_myo_review_reviewer_name', $name );
	update_post_meta( $post_id, '_myo_review_message', $note );
	update_post_meta( $post_id, '_myo_review_ratings', $ratings );
	update_post_meta( $post_id, '_myo_review_products', array_map( 'absint', array_keys( $ratings ) ) );
	update_post_meta( $post_id, '_myo_review_user_id', $user_id );

	$verified = false;
	if ( function_exists( 'wc_customer_bought_product' ) ) {
		foreach ( array_keys( $ratings ) as $product_id ) {
			if ( wc_customer_bought_product( $email, $user_id, $product_id ) ) {
				$verified = true;
				break;
			}
		}
	}
	update_post_meta( $post_id, '_myo_review_verified', $verified ? 1 : 0 );

	wp_send_json_success( [ 'message' => 'Thanks! Your review has been submitted and is pending approval.' ] );
}

// ─── Helper: fetch published reviews for front-end display ────────────────

function myogenix_get_published_reviews( int $limit = -1 ): array {
	$posts = get_posts( [
		'post_type'      => 'myogenix_review',
		'post_status'    => 'publish',
		'numberposts'    => $limit,
		'orderby'        => 'date',
		'order'          => 'DESC',
	] );

	$reviews = [];
	foreach ( $posts as $post ) {
		$ratings = get_post_meta( $post->ID, '_myo_review_ratings', true );
		$ratings = is_array( $ratings ) ? $ratings : [];
		if ( empty( $ratings ) ) continue;

		$reviewer_name = get_post_meta( $post->ID, '_myo_review_reviewer_name', true );
		if ( '' === $reviewer_name ) {
			// Fallback for reviews created/edited directly in wp-admin.
			$reviewer_name = preg_replace( '/\s*—\s*\d{4}-\d{2}-\d{2}.*$/', '', $post->post_title );
		}

		$products = [];
		foreach ( $ratings as $product_id => $rating ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) continue;
			$products[] = [
				'id'     => $product_id,
				'name'   => $product->get_name(),
				'url'    => $product->get_permalink(),
				'rating' => (int) $rating,
			];
		}
		if ( empty( $products ) ) continue;

		$avg = array_sum( $ratings ) / count( $ratings );

		$reviews[] = [
			'id'       => $post->ID,
			'name'     => $reviewer_name ?: 'Verified Customer',
			'message'  => get_post_meta( $post->ID, '_myo_review_message', true ),
			'verified' => (bool) get_post_meta( $post->ID, '_myo_review_verified', true ),
			'date'     => $post->post_date,
			'avg'      => $avg,
			'products' => $products,
		];
	}
	return $reviews;
}
