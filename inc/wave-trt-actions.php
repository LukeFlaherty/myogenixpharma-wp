<?php
/** Staff workflow only; never writes provider approval, payment or subscription status. */
defined( 'ABSPATH' ) || exit;

function wave_trt_workflow_states() {
	return array( 'open' => 'Needs follow-up', 'patient' => 'Waiting on patient', 'labs' => 'Waiting on labs', 'provider' => 'Waiting on provider', 'pharmacy' => 'Waiting on pharmacy', 'resolved' => 'Follow-up closed' );
}
function wave_trt_milestones() {
	return array( 'intake' => 'Intake completed', 'labs' => 'Lab completion verified', 'pharmacy' => 'Pharmacy handoff verified', 'shipped' => 'Shipment verified', 'delivery' => 'Delivery verified' );
}
function wave_trt_work( $order ) {
	$value = $order->get_meta( '_wave_trt_workflow' );
	return is_array( $value ) ? $value : array();
}
function wave_trt_action_input( $key ) {
	return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
}
function wave_trt_action_fail( $message, $code = 400 ) {
	wp_die( esc_html( $message ), 'TRT follow-up', array( 'response' => $code, 'back_link' => true ) );
}
/** Validate user-authored workflow values independently of WordPress persistence. */
function wave_trt_validate_update( $operation, $status, $due, $next, $note, $milestone ) {
	if ( ! in_array( $operation, array( 'assign', 'unassign', 'save', 'note', 'verify', 'clear_milestone', 'resolve', 'reopen' ), true ) ) { return 'Unknown action.'; }
	if ( 'save' === $operation ) {
		if ( ! isset( wave_trt_workflow_states()[ $status ] ) || 'resolved' === $status ) { return 'Choose an open follow-up status.'; }
		if ( '' === trim( $next ) ) { return 'Enter the next action before saving.'; }
		if ( $due ) {
			$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $due );
			if ( ! $date || $date->format( 'Y-m-d' ) !== $due ) { return 'Enter a valid follow-up date.'; }
		}
	}
	if ( in_array( $operation, array( 'note', 'verify', 'clear_milestone', 'resolve' ), true ) && '' === trim( $note ) ) { return 'Add a note explaining the contact, evidence, or resolution.'; }
	if ( in_array( $operation, array( 'verify', 'clear_milestone' ), true ) && ! isset( wave_trt_milestones()[ $milestone ] ) ) { return 'Select the milestone you verified.'; }
	if ( strlen( $note ) > 2000 || strlen( $next ) > 300 ) { return 'Keep notes under 2,000 characters and next actions under 300 characters.'; }
	return '';
}
add_action( 'admin_post_wave_trt_action', 'wave_trt_handle_action' );
function wave_trt_handle_action() {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! current_user_can( 'manage_woocommerce' ) ) { wave_trt_action_fail( 'You do not have permission to make this update.', 403 ); }
	$id = absint( wave_trt_action_input( 'record_id' ) );
	check_admin_referer( 'wave_trt_action_' . $id );
	$operation = sanitize_key( wave_trt_action_input( 'operation' ) );
	$status = sanitize_key( wave_trt_action_input( 'workflow_status' ) );
	$due = sanitize_text_field( wave_trt_action_input( 'due' ) );
	$next = sanitize_text_field( wave_trt_action_input( 'next' ) );
	$note = sanitize_textarea_field( wave_trt_action_input( 'note' ) );
	$milestone = sanitize_key( wave_trt_action_input( 'milestone' ) );
	$error = wave_trt_validate_update( $operation, $status, $due, $next, $note, $milestone );
	if ( $error ) { wave_trt_action_fail( $error ); }
	global $wpdb;
	$lock = 'wave_trt_action_' . $id;
	if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) { wave_trt_action_fail( 'Another update is saving. Refresh this dashboard and try again.', 409 ); }
	try {
		$order = wc_get_order( $id );
		if ( ! $order || ! in_array( $order->get_type(), array( 'shop_order', 'shop_subscription' ), true ) || ! wave_trt_contains_product( $order ) || wave_trt_test_record( $order ) || $order->has_status( array( 'trash', 'auto-draft', 'checkout-draft' ) ) ) { throw new RuntimeException( 'This TRT record is not available for updates.' ); }
		$work = wave_trt_work( $order );
		if ( (string) ( $work['revision'] ?? 0 ) !== wave_trt_action_input( 'revision' ) ) { throw new RuntimeException( 'This record changed since you opened it. Refresh the dashboard before saving.' ); }
		$user = wp_get_current_user();
		$label = '';
		switch ( $operation ) {
			case 'assign': $work['owner'] = $user->ID; $label = 'Assigned follow-up to self'; break;
			case 'unassign': $work['owner'] = 0; $label = 'Cleared follow-up assignment'; break;
			case 'save': $work['status'] = $status; $work['due'] = $due; $work['next'] = $next; $label = 'Updated follow-up: ' . wave_trt_workflow_states()[ $status ] . '; due ' . ( $due ?: 'not set' ) . '; next: ' . $next; break;
			case 'note': $label = 'Logged contact / internal note'; break;
			case 'verify':
				if ( 'shop_order' !== $order->get_type() ) { throw new RuntimeException( 'Record journey evidence on a treatment order, not a subscription.' ); }
				$work['milestones'][ $milestone ] = array( 'at' => time(), 'by' => $user->display_name, 'note' => $note );
				$label = 'Staff verified: ' . wave_trt_milestones()[ $milestone ]; break;
			case 'clear_milestone': unset( $work['milestones'][ $milestone ] ); $label = 'Removed staff verification: ' . wave_trt_milestones()[ $milestone ]; break;
			case 'resolve': $work['status'] = 'resolved'; $label = 'Closed staff follow-up (source alerts retained)'; break;
			case 'reopen': $work['status'] = 'open'; $label = 'Reopened staff follow-up'; break;
		}
		$work['revision'] = (int) ( $work['revision'] ?? 0 ) + 1;
		$work['updated'] = time();
		$work['history'][] = array( 'at' => time(), 'by' => $user->display_name, 'label' => $label, 'note' => $note );
		$work['history'] = array_slice( $work['history'], -50 );
		$order->update_meta_data( '_wave_trt_workflow', $work );
		$order->save_meta_data();
		$order->add_order_note( 'Wave Consulting — ' . $user->display_name . ': ' . $label . ( $note ? "\n" . $note : '' ), false, true );
	} catch ( Throwable $e ) {
		$error = $e instanceof RuntimeException ? $e->getMessage() : 'The update could not be completed. Refresh the dashboard and check the record before retrying.';
	} finally {
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
	}
	if ( $error ) { wave_trt_action_fail( $error, 409 ); }
	wp_safe_redirect( admin_url( 'admin.php?page=wave-trt&wave_saved=1' ) . '#wave-record-' . $id );
	exit;
}

function wave_trt_render_actions( $row ) {
	$order = $row['record']; $id = $order->get_id(); $work = wave_trt_work( $order );
	$owner = ! empty( $work['owner'] ) ? get_userdata( $work['owner'] ) : null;
	$states = wave_trt_workflow_states();
	?>
	<div class="wave-action-bar">
		<a class="button" href="<?php echo esc_url( $order->get_edit_order_url() ); ?>"><?php echo $row['order'] ? 'Open order / refund' : 'Manage subscription'; ?></a>
		<?php foreach ( $row['subscriptions'] as $sub ) : ?><a class="button" href="<?php echo esc_url( $sub->get_edit_order_url() ); ?>">Manage subscription #<?php echo esc_html( $sub->get_id() ); ?></a><?php endforeach; ?>
		<span><strong><?php echo esc_html( $states[ $work['status'] ?? 'open' ] ?? 'Needs follow-up' ); ?></strong> · <?php echo esc_html( $owner ? $owner->display_name : 'Unassigned' ); ?><?php if ( ! empty( $work['due'] ) ) : ?> · Follow up <?php echo esc_html( $work['due'] ); ?><?php endif; ?></span>
	</div>
	<?php if ( ! empty( $work['next'] ) ) : ?><p class="wave-staff-next"><strong>Team next step:</strong> <?php echo esc_html( $work['next'] ); ?></p><?php endif; ?>
	<details class="wave-action-panel"><summary>Take action / record an update</summary>
		<form class="wave-action-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wave_trt_action"><input type="hidden" name="record_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="revision" value="<?php echo esc_attr( $work['revision'] ?? 0 ); ?>">
			<?php wp_nonce_field( 'wave_trt_action_' . $id ); ?>
			<p>Updates apply to <?php echo $row['order'] ? 'order' : 'subscription'; ?> #<?php echo esc_html( $id ); ?>. Notes are private to staff. Record operational details and the source of verification; keep clinical results in the provider system.</p>
			<div class="wave-action-buttons"><button class="button" name="operation" value="assign">Assign to me</button><button class="button" name="operation" value="unassign">Clear assignment</button></div>
			<div class="wave-form-grid"><label>Follow-up status<select name="workflow_status"><?php foreach ( $states as $key => $text ) { if ( 'resolved' === $key ) { continue; } ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $work['status'] ?? 'open', $key ); ?>><?php echo esc_html( $text ); ?></option><?php } ?></select></label><label>Follow-up date<input type="date" name="due" value="<?php echo esc_attr( $work['due'] ?? '' ); ?>"></label></div>
			<label>Next action<input name="next" maxlength="300" value="<?php echo esc_attr( $work['next'] ?? '' ); ?>" placeholder="e.g. Ask pharmacy for tracking confirmation"></label>
			<button class="button button-primary" name="operation" value="save">Save follow-up</button>
			<label>Contact note, verification source, or resolution<textarea name="note" maxlength="2000" rows="3" placeholder="What was checked, with whom, and the outcome"></textarea></label>
			<div class="wave-action-buttons"><button class="button" name="operation" value="note">Log contact / add note</button><?php if ( 'resolved' === ( $work['status'] ?? '' ) ) : ?><button class="button" name="operation" value="reopen">Reopen follow-up</button><?php else : ?><button class="button" name="operation" value="resolve">Close follow-up</button><?php endif; ?></div>
			<?php if ( $row['order'] ) : ?><div class="wave-form-grid"><label>Verified milestone<select name="milestone"><option value="">Choose a verified milestone</option><?php foreach ( wave_trt_milestones() as $key => $text ) : ?><option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $text ); ?></option><?php endforeach; ?></select></label><div class="wave-action-buttons"><button class="button" name="operation" value="verify">Record verified milestone</button><button class="button" name="operation" value="clear_milestone">Remove staff verification</button></div></div><?php endif; ?>
			<p class="wave-muted">Closing a follow-up does not clear source alerts. These actions do not send messages, order labs, approve treatment, or change billing.</p>
		</form>
		<?php if ( ! empty( $work['history'] ) ) : ?><div class="wave-action-history"><h3>Staff action history</h3><?php foreach ( array_reverse( $work['history'] ) as $event ) : ?><p><strong><?php echo esc_html( $event['label'] ); ?></strong><br><small><?php echo esc_html( $event['by'] . ' · ' . wp_date( 'M j, Y g:i a', $event['at'] ) ); ?></small><?php if ( $event['note'] ) : ?><br><?php echo nl2br( esc_html( $event['note'] ) ); ?><?php endif; ?></p><?php endforeach; ?></div><?php endif; ?>
	</details>
	<?php
}
