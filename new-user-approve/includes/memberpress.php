<?php
/**
 * MemberPress integration for New User Approve.
 *
 * @package New_User_Approve
 */

/**
 * Check whether the NUA invitation code feature is enabled.
 *
 * @return bool
 */
function nuamp_is_invitation_code_enabled() {
	$options = get_option( 'new_user_approve_options' );
	return isset( $options['nua_free_invitation'] ) && 'enable' === $options['nua_free_invitation'];
}

/**
 * Render the NUA invitation code field on the MemberPress checkout form.
 *
 * @param int $product_id The MemberPress product ID.
 */
function nuamp_checkout_invitation_code_field( $product_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	if ( ! nuamp_is_invitation_code_enabled() ) {
		return;
	}

	$options  = get_option( 'new_user_approve_options' );
	$required = ! empty( $options['nua_checkbox_textbox'] );
	?>
	<div class="mepr-form-row mepr-nua-invitation-code-row">
		<label for="nua_invitation_code">
			<?php esc_html_e( 'Invitation Code', 'new-user-approve' ); ?>
			<?php if ( $required ) : ?>
				<span class="mepr-required" aria-hidden="true"> *</span>
				<span class="screen-reader-text"><?php esc_html_e( 'Required', 'new-user-approve' ); ?></span>
			<?php else : ?>
				<span class="mepr-optional"> (<?php esc_html_e( 'optional', 'new-user-approve' ); ?>)</span>
			<?php endif; ?>
		</label>
		<input type="text" name="nua_invitation_code" id="nua_invitation_code"
			class="mepr-form-input"
			value="<?php echo esc_attr( isset( $_POST['nua_invitation_code'] ) ? sanitize_text_field( wp_unslash( $_POST['nua_invitation_code'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing ?>"
			autocomplete="off" />
		<?php wp_nonce_field( 'nua_invitation_code_action', 'nua_invitation_code_nonce' ); ?>
	</div>
	<?php
}

add_action( 'mepr-checkout-before-submit', 'nuamp_checkout_invitation_code_field', 10, 1 );

/**
 * Validate the NUA invitation code on MemberPress signup.
 *
 * Hooked into mepr-validate-signup which passes an array of error strings and
 * expects the (possibly extended) array back.
 *
 * @param array $errors Existing validation errors.
 * @return array
 */
function nuamp_validate_invitation_code( $errors ) {
	if ( ! nuamp_is_invitation_code_enabled() ) {
		return $errors;
	}

	$options      = get_option( 'new_user_approve_options' );
	$required     = ! empty( $options['nua_checkbox_textbox'] );
	$code_entered = isset( $_POST['nua_invitation_code'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		? sanitize_text_field( wp_unslash( $_POST['nua_invitation_code'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		: '';

	// Nonce verification (optional field, used when present).
	$nonce_valid = isset( $_POST['nua_invitation_code_nonce'] ) &&
		wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['nua_invitation_code_nonce'] ) ),
			'nua_invitation_code_action'
		);

	if ( empty( $code_entered ) ) {
		if ( $required ) {
			$errors[] = __( 'Please add an Invitation code.', 'new-user-approve' );
		}
		return $errors;
	}

	if ( ! $nonce_valid ) {
		$errors[] = __( 'Security check failed. Please reload the page and try again.', 'new-user-approve' );
		return $errors;
	}

	$inv = nua_invitation_code();

	// Check existence, expiry and usage limit using NUA helpers.
	$exists = $inv->invitation_code_already_exists( $code_entered );

	if ( ! $exists ) {
		$errors[] = __( 'The Invitation code is invalid.', 'new-user-approve' );
		return $errors;
	}

	$expired       = $inv->invitation_code_expiry_check( $code_entered );
	$within_limit  = $inv->invitation_code_limit_check( $code_entered );

	if ( $expired ) {
		$errors[] = __( 'Invitation code has been expired.', 'new-user-approve' );
		return $errors;
	}

	if ( ! $within_limit ) {
		$errors[] = __( 'Invitation code limit exceeded.', 'new-user-approve' );
		return $errors;
	}

	// Acquire a file lock to prevent race conditions on heavily-used codes.
	$args = array(
		'numberposts' => -1,
		'post_type'   => $inv->code_post_type,
		'post_status' => 'publish',
		'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'relation' => 'AND',
			array(
				array(
					'key'     => $inv->code_key,
					'value'   => $code_entered,
					'compare' => '=',
				),
				array(
					'key'     => $inv->usage_limit_key,
					'value'   => '1',
					'compare' => '>=',
				),
				array(
					'key'     => $inv->expiry_date_key,
					'value'   => time(),
					'compare' => '>=',
				),
				array(
					'key'     => $inv->status_key,
					'value'   => 'Active',
					'compare' => '=',
				),
			),
		),
	);

	$posts = get_posts( $args );

	foreach ( $posts as $post_inv ) {
		$code_inv = get_post_meta( $post_inv->ID, $inv->code_key, true );
		if ( $code_entered === $code_inv ) {
			global $nuamp_inv_file_lock, $nuamp_inv_post_id;
			$nuamp_inv_post_id   = $post_inv->ID;
			$nuamp_inv_file_lock = $inv->invite_code_hold( $post_inv->ID );
			if ( false === $nuamp_inv_file_lock ) {
				$errors[] = __( 'Server is busy, please try again.', 'new-user-approve' );
			}
			return $errors;
		}
	}

	$errors[] = __( 'The Invitation code is invalid.', 'new-user-approve' );
	return $errors;
}

add_filter( 'mepr-validate-signup', 'nuamp_validate_invitation_code', 10, 1 );

/**
 * Consume the invitation code and auto-approve the user after a successful MemberPress signup.
 *
 * @param object $txn The MemberPress transaction object.
 */
function nuamp_process_invitation_code_on_signup( $txn ) {
	if ( ! nuamp_is_invitation_code_enabled() ) {
		return;
	}

	$code_entered = isset( $_POST['nua_invitation_code'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		? sanitize_text_field( wp_unslash( $_POST['nua_invitation_code'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		: '';

	if ( empty( $code_entered ) ) {
		return;
	}

	// Nonce was already verified in nuamp_validate_invitation_code(); trust it here.
	global $nuamp_inv_file_lock, $nuamp_inv_post_id;

	$inv     = nua_invitation_code();
	$user_id = $txn->user_id;

	// Fall back to a fresh post lookup if globals were not set (e.g. AJAX path).
	if ( empty( $nuamp_inv_post_id ) ) {
		$args = array(
			'numberposts' => 1,
			'post_type'   => $inv->code_post_type,
			'post_status' => 'publish',
			'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				array(
					array(
						'key'     => $inv->code_key,
						'value'   => $code_entered,
						'compare' => '=',
					),
					array(
						'key'     => $inv->usage_limit_key,
						'value'   => '1',
						'compare' => '>=',
					),
					array(
						'key'     => $inv->expiry_date_key,
						'value'   => time(),
						'compare' => '>=',
					),
					array(
						'key'     => $inv->status_key,
						'value'   => 'Active',
						'compare' => '=',
					),
				),
			),
		);
		$posts = get_posts( $args );
		foreach ( $posts as $post_inv ) {
			$code_inv = get_post_meta( $post_inv->ID, $inv->code_key, true );
			if ( $code_entered === $code_inv ) {
				$nuamp_inv_post_id = $post_inv->ID;
				break;
			}
		}
	}

	if ( empty( $nuamp_inv_post_id ) ) {
		return;
	}

	$inv_id = $nuamp_inv_post_id;

	// Record user against the invitation code.
	$registered_users = get_post_meta( $inv_id, $inv->registered_users, true );
	if ( empty( $registered_users ) ) {
		update_post_meta( $inv_id, $inv->registered_users, array( $user_id ) );
	} else {
		$registered_users[] = $user_id;
		update_post_meta( $inv_id, $inv->registered_users, $registered_users );
	}

	// Decrement usage limit.
	$current_usage = (int) get_post_meta( $inv_id, $inv->usage_limit_key, true );
	--$current_usage;
	update_post_meta( $inv_id, $inv->usage_limit_key, $current_usage );

	// Release the file lock.
	$inv->invite_code_release( $nuamp_inv_file_lock, $inv_id );

	// Expire the code if the limit has reached zero.
	if ( 0 === $current_usage ) {
		update_post_meta( $inv_id, $inv->status_key, 'Expired' );
	}

	// Auto-approve the user.
	pw_new_user_approve()->approve_user( $user_id );

	do_action( 'nua_invited_user', $user_id, $code_entered );
}

add_action( 'mepr-signup', 'nuamp_process_invitation_code_on_signup', 10, 1 );
