<?php
/** Patient-completed quarterly TRT intake through Prescribery's questionnaire API. */
defined( 'ABSPATH' ) || exit;

function myogenix_trt_automated_intake_enabled( $sub ) {
	return $sub instanceof WC_Subscription && ( MYOGENIX_TRT_AUTOMATED_INTAKE_LIVE || myogenix_trt_is_qa( $sub ) );
}

function myogenix_trt_questionnaire_token( $order ) {
	$created = $order instanceof WC_Order && $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0;
	return wp_hash( 'trt_renewal_questionnaire|' . absint( $order ? $order->get_id() : 0 ) . '|' . $created );
}

function myogenix_trt_questionnaire_url( $order ) {
	if ( ! myogenix_trt_is_renewal_order( $order ) ) { return ''; }
	return myogenix_trt_consent_url( array( 'trt_step' => 'intake', 'order_id' => $order->get_id(), 'token' => myogenix_trt_questionnaire_token( $order ) ) );
}

function myogenix_trt_validate_questionnaire_request( array $params ) {
	$id = is_scalar( $params['order_id'] ?? null ) ? absint( $params['order_id'] ) : 0;
	$token = is_scalar( $params['token'] ?? null ) ? (string) $params['token'] : '';
	$order = wc_get_order( $id );
	if ( ! myogenix_trt_is_renewal_order( $order ) || ! $token || ! hash_equals( myogenix_trt_questionnaire_token( $order ), $token ) ) {
		return new WP_Error( 'invalid', 'This intake link is invalid.', array( 'status' => 403 ) );
	}
	$sub = wcs_get_subscription( $order->get_meta( '_trt_subscription_id' ) );
	if ( ! $sub || ! myogenix_trt_automated_intake_enabled( $sub ) || (int) $sub->get_meta( '_trt_pending_renewal_order' ) !== $order->get_id() || 'continue' !== $sub->get_meta( '_trt_consent_resolved_action' ) ) {
		return new WP_Error( 'unavailable', 'Online intake is not available for this renewal. Your care team will help with the next step.', array( 'status' => 403 ) );
	}
	if ( $order->get_transaction_id() || $order->has_status( array( 'cancelled', 'refunded', 'trash' ) ) ) {
		return new WP_Error( 'resolved', 'This renewal is no longer awaiting intake.', array( 'status' => 409 ) );
	}
	if ( 'submitted' === $order->get_meta( '_trt_questionnaire_state' ) ) {
		return array( $sub, $order, true );
	}
	return array( $sub, $order, false );
}

function myogenix_trt_questionnaire_request( $method, $path, $body = null ) {
	$settings = myogenix_trt_lab_api_settings();
	$token = myogenix_trt_lab_api_token();
	if ( is_wp_error( $token ) ) { return $token; }
	if ( empty( $settings['api_base_url'] ) ) { return new WP_Error( 'questionnaire_config', 'The intake connection is not configured.' ); }
	$args = array(
		'method' => $method,
		'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json', 'Content-Type' => 'application/json' ),
		'timeout' => 25,
	);
	if ( null !== $body ) { $args['body'] = wp_json_encode( $body ); }
	$response = wp_remote_request( rtrim( $settings['api_base_url'], '/' ) . $path, $args );
	if ( is_wp_error( $response ) ) { return new WP_Error( 'questionnaire_uncertain', 'We could not confirm the intake request. Your care team will check it.' ); }
	return array( 'code' => wp_remote_retrieve_response_code( $response ), 'body' => json_decode( wp_remote_retrieve_body( $response ), true ) );
}

function myogenix_trt_questionnaire_schema() {
	$response = myogenix_trt_questionnaire_request( 'GET', '/questionnaires/' . MYOGENIX_TRT_REFILL_TEMPLATE_ID . '?service_ids=' . MYOGENIX_TRT_REFILL_SERVICE_ID );
	if ( is_wp_error( $response ) ) { return $response; }
	$questions = $response['body']['data'] ?? null;
	if ( 200 !== $response['code'] || ! is_array( $questions ) || ! $questions || count( $questions ) > 50 ) {
		return new WP_Error( 'questionnaire_config', 'The quarterly intake is temporarily unavailable.' );
	}
	$seen = array();
	foreach ( $questions as $question ) {
		$id = is_scalar( $question['entry_id'] ?? null ) ? trim( (string) $question['entry_id'] ) : '';
		$type = (string) ( $question['type'] ?? '' );
		if ( ! $id || isset( $seen[ $id ] ) || ! in_array( $type, array( 'radio-group', 'checkbox-group', 'text' ), true ) || (int) ( $question['template_id'] ?? 0 ) !== MYOGENIX_TRT_REFILL_TEMPLATE_ID ) {
			return new WP_Error( 'questionnaire_config', 'The quarterly intake needs review before it can be displayed.' );
		}
		$seen[ $id ] = true;
	}
	return $questions;
}

function myogenix_trt_questionnaire_answers( array $submitted, array $schema ) {
	$answers = array();
	foreach ( $schema as $question ) {
		$id = (string) $question['entry_id'];
		$type = (string) $question['type'];
		$required = ! empty( $question['is_mandatory'] );
		$raw = $submitted[ $id ] ?? ( 'checkbox-group' === $type ? array() : '' );
		if ( ( 'checkbox-group' === $type && ! is_array( $raw ) ) || ( 'checkbox-group' !== $type && ! is_scalar( $raw ) ) ) {
			return new WP_Error( 'questionnaire_invalid', 'One of the intake answers has an invalid format.', array( 'status' => 422 ) );
		}
		if ( is_array( $raw ) && ( count( $raw ) > 50 || array_filter( $raw, function ( $value ) { return ! is_scalar( $value ); } ) ) ) {
			return new WP_Error( 'questionnaire_invalid', 'One of the intake answers has an invalid format.', array( 'status' => 422 ) );
		}
		$values = is_array( $raw ) ? array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $raw ), 'strlen' ) ) ) : array( sanitize_textarea_field( (string) $raw ) );
		$values = array_values( array_filter( $values, 'strlen' ) );
		if ( $required && ! $values ) { return new WP_Error( 'questionnaire_required', 'Please answer every required question.', array( 'status' => 422 ) ); }
		if ( ! $values ) { continue; }
		if ( 'text' === $type ) {
			if ( strlen( $values[0] ) > 2000 ) { return new WP_Error( 'questionnaire_invalid', 'Please keep written answers under 2,000 characters.', array( 'status' => 422 ) ); }
			$answers[] = array( 'entry_id' => $id, 'answer' => $values[0] );
			continue;
		}
		$allowed = array_map( 'strval', is_array( $question['value'] ?? null ) ? $question['value'] : array() );
		if ( ! $allowed || array_diff( $values, $allowed ) || ( 'radio-group' === $type && 1 !== count( $values ) ) ) {
			return new WP_Error( 'questionnaire_invalid', 'One of the selected intake answers is invalid. Please review the form.', array( 'status' => 422 ) );
		}
		$answer = array( 'entry_id' => $id, 'answer' => implode( ', ', $values ) );
		if ( 'checkbox-group' === $type ) { $answer['multiple_answers'] = array_map( function ( $value ) { return array( 'answer' => $value ); }, $values ); }
		$answers[] = $answer;
	}
	return $answers;
}

function myogenix_trt_record_automated_intake( $order, $map_id ) {
	$work = $order->get_meta( '_wave_trt_workflow' );
	$work = is_array( $work ) ? $work : array();
	$now = time();
	$note = 'Prescribery questionnaire ' . MYOGENIX_TRT_REFILL_TEMPLATE_ID . ' submitted for service ' . MYOGENIX_TRT_REFILL_SERVICE_ID . '; mapping ' . absint( $map_id ) . '.';
	$work['milestones']['intake'] = array( 'at' => $now, 'by' => 'Prescribery API', 'note' => $note );
	$work['status'] = 'provider';
	$work['due'] = wp_date( 'Y-m-d', $now + DAY_IN_SECONDS );
	$work['next'] = 'Quarterly intake was submitted through Prescribery. Check lab completion, provider review, payment outcome, and pharmacy handoff.';
	$work['revision'] = (int) ( $work['revision'] ?? 0 ) + 1;
	$work['updated'] = $now;
	$work['history'][] = array( 'at' => $now, 'by' => 'Prescribery API', 'label' => 'Patient submitted quarterly intake', 'note' => $note );
	$work['history'] = array_slice( $work['history'], -50 );
	$order->update_meta_data( '_wave_trt_workflow', $work );
	$order->update_meta_data( '_trt_questionnaire_state', 'submitted' );
	$order->update_meta_data( '_trt_questionnaire_map_id', absint( $map_id ) );
	$order->update_meta_data( '_trt_questionnaire_submitted_at', $now );
	$order->save();
	$order->add_order_note( 'Patient submitted the Prescribery quarterly intake. Clinical answers remain in Prescribery; no answers are stored in WooCommerce.' );
	do_action( 'wave_trt_milestone_updated', $order, 'intake', 'verify' );
}

function myogenix_trt_submit_questionnaire( array $params ) {
	$id = is_scalar( $params['order_id'] ?? null ) ? absint( $params['order_id'] ) : 0;
	$order = wc_get_order( $id );
	$sub_id = $order ? absint( $order->get_meta( '_trt_subscription_id' ) ) : 0;
	if ( ! $sub_id || ! myogenix_trt_lock( $sub_id ) ) { return new WP_Error( 'busy', 'Your intake is being processed. Please wait a moment.', array( 'status' => 409 ) ); }
	try {
		$validated = myogenix_trt_validate_questionnaire_request( $params );
		if ( is_wp_error( $validated ) ) { return $validated; }
		list( $sub, $order, $submitted ) = $validated;
		if ( $submitted ) { return array( 'order' => $order, 'already_submitted' => true ); }
		$state = (string) $order->get_meta( '_trt_questionnaire_state' );
		if ( in_array( $state, array( 'submitting', 'uncertain' ), true ) ) { return new WP_Error( 'questionnaire_uncertain', 'Your care team is checking whether this intake was received. Please do not submit it again.', array( 'status' => 409 ) ); }
		$schema = myogenix_trt_questionnaire_schema();
		if ( is_wp_error( $schema ) ) { return $schema; }
		$answers = myogenix_trt_questionnaire_answers( is_array( $params['answers'] ?? null ) ? $params['answers'] : array(), $schema );
		if ( is_wp_error( $answers ) ) { return $answers; }
		$order->update_meta_data( '_trt_questionnaire_state', 'submitting' );
		$order->save();
		$response = myogenix_trt_questionnaire_request( 'POST', '/questionnaires/answers', array(
			'template_id' => MYOGENIX_TRT_REFILL_TEMPLATE_ID,
			'patient_id' => myogenix_trt_get_prescribery_patient_id( $sub ),
			'answers' => $answers,
			'service_ids' => (string) MYOGENIX_TRT_REFILL_SERVICE_ID,
		) );
		if ( is_wp_error( $response ) ) {
			$order->update_meta_data( '_trt_questionnaire_state', 'uncertain' );
			$order->save();
			return $response;
		}
		$map_id = absint( $response['body']['data']['ques_map_id'] ?? 0 );
		$order->update_meta_data( '_trt_questionnaire_http_code', (int) $response['code'] );
		if ( ! in_array( $response['code'], array( 200, 201 ), true ) || ! $map_id ) {
			$order->update_meta_data( '_trt_questionnaire_state', in_array( $response['code'], array( 400, 401, 403, 422 ), true ) ? 'rejected' : 'uncertain' );
			$order->save();
			return new WP_Error( 'questionnaire_' . ( 'rejected' === $order->get_meta( '_trt_questionnaire_state' ) ? 'rejected' : 'uncertain' ), 'We could not confirm your intake. Your care team will help with the next step.', array( 'status' => 503 ) );
		}
		myogenix_trt_record_automated_intake( $order, $map_id );
		return array( 'order' => $order, 'already_submitted' => false );
	} finally {
		myogenix_trt_unlock( $sub_id );
	}
}

function myogenix_trt_render_questionnaire_form( $order, $error = null ) {
	$schema = myogenix_trt_questionnaire_schema();
	if ( is_wp_error( $schema ) ) {
		return '<p>We’ve prepared your renewal and requested your labs.</p><div class="steps"><strong>Your care team will help complete your quarterly intake.</strong><p>No renewal payment has been taken.</p></div>';
	}
	$body = '<p>Complete this secure medical check-in for your provider. Your answers go directly to Prescribery and are not stored in WooCommerce.</p><p><strong>No renewal payment is taken when you submit this form.</strong></p>';
	if ( is_wp_error( $error ) ) { $body .= '<p class="form-error" role="alert">' . esc_html( $error->get_error_message() ) . '</p>'; }
	$body .= '<form class="trt-intake-form" method="post" action="' . esc_url( myogenix_trt_consent_url( array() ) ) . '"><input type="hidden" name="trt_step" value="intake"><input type="hidden" name="order_id" value="' . esc_attr( $order->get_id() ) . '"><input type="hidden" name="token" value="' . esc_attr( myogenix_trt_questionnaire_token( $order ) ) . '">';
	foreach ( $schema as $index => $question ) {
		$id = (string) $question['entry_id'];
		$type = (string) $question['type'];
		$required = ! empty( $question['is_mandatory'] );
		$label = trim( (string) ( $question['notes'] ?? '' ) );
		$body .= '<fieldset><legend><span>' . esc_html( $index + 1 ) . '.</span> ' . esc_html( $label ) . ( $required ? ' <strong aria-label="required">*</strong>' : '' ) . '</legend>';
		if ( ! empty( $question['description'] ) ) { $body .= '<p class="field-help">' . esc_html( $question['description'] ) . '</p>'; }
		if ( 'text' === $type ) {
			$body .= '<textarea name="answers[' . esc_attr( $id ) . ']" maxlength="2000" rows="4"' . ( $required ? ' required' : '' ) . '></textarea>';
		} else {
			$options = is_array( $question['options'] ?? null ) ? $question['options'] : array();
			$values = is_array( $question['value'] ?? null ) ? $question['value'] : array();
			foreach ( $values as $option_index => $value ) {
				$input_id = 'trt-' . $index . '-' . $option_index;
				$name = 'answers[' . $id . ']' . ( 'checkbox-group' === $type ? '[]' : '' );
				$body .= '<label class="choice" for="' . esc_attr( $input_id ) . '"><input id="' . esc_attr( $input_id ) . '" type="' . ( 'checkbox-group' === $type ? 'checkbox' : 'radio' ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . ( $required && 'radio-group' === $type ? ' required' : '' ) . '><span>' . esc_html( $options[ $option_index ] ?? $value ) . '</span></label>';
			}
		}
		$body .= '</fieldset>';
	}
	$body .= '<button type="submit">Submit medical check-in</button></form><p class="muted">Renewal order #' . absint( $order->get_id() ) . '</p>';
	return $body;
}
