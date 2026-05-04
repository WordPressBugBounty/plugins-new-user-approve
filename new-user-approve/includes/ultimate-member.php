<?php
/**
 * Ultimate Member integration for New User Approve.
 *
 * @package New_User_Approve
 */

/**
 * Intercept UM registration at priority 99 (before um_check_user_status at 100).
 * If NUA marks the user pending, sync UM's account_status and redirect back to
 * the registration page without auto-logging the user in.
 */
function nua_um_prevent_pending_auto_login( $user_id, $args, $form_data ) {
	if ( ! function_exists( 'UM' ) ) {
		return;
	}

	if ( is_null( $form_data ) || is_admin() ) {
		return;
	}

	$nua_status = get_user_meta( $user_id, 'pw_user_status', true );
	if ( 'pending' !== $nua_status ) {
		return;
	}

	UM()->common()->users()->set_status( $user_id, 'awaiting_admin_review' );

	$url = UM()->permalinks()->get_current_url( true );
	$url = add_query_arg( 'nua_pending', '1', $url );

	wp_safe_redirect( $url );
	exit;
}
add_action( 'um_registration_complete', 'nua_um_prevent_pending_auto_login', 99, 3 );

/**
 * Inject a styled pending notice above the UM registration form when
 * ?nua_pending=1 is present in the URL.
 */
function nua_um_pending_notice() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_GET['nua_pending'] ) || '1' !== $_GET['nua_pending'] ) {
		return;
	}

	$msg = nua_default_registration_complete_message();
	$msg = nua_do_email_tags( $msg, array( 'context' => 'pending_message' ) );
	$msg = apply_filters( 'new_user_approve_pending_message', $msg );
	?>
	<style>
	.nua-pending-notice {
		background: #eaf4fb;
		border-left: 4px solid #2196f3;
		color: #1a3c50;
		padding: 14px 18px;
		border-radius: 4px;
		margin-bottom: 18px;
		font-size: 14px;
		line-height: 1.6;
	}
	</style>
	<script>
	document.addEventListener( 'DOMContentLoaded', function () {
		var form = document.querySelector( '.um-form[data-mode="register"]' );
		if ( ! form ) { return; }
		var notice = document.createElement( 'div' );
		notice.className = 'nua-pending-notice';
		notice.innerHTML = <?php echo wp_json_encode( $msg ); ?>;
		form.parentNode.insertBefore( notice, form );
	} );
	</script>
	<?php
}
add_action( 'wp_footer', 'nua_um_pending_notice' );

/**
 * When NUA approves a user, sync UM's account_status to 'approved'.
 */
function nua_um_sync_approved_status( $user ) {
	if ( ! function_exists( 'UM' ) ) {
		return;
	}

	$user_id = is_object( $user ) ? $user->ID : absint( $user );
	if ( ! $user_id ) {
		return;
	}

	UM()->common()->users()->set_status( $user_id, 'approved' );
}
add_action( 'new_user_approve_user_approved', 'nua_um_sync_approved_status', 10, 1 );

/**
 * When NUA denies a user, sync UM's account_status to 'rejected'.
 */
function nua_um_sync_denied_status( $user ) {
	if ( ! function_exists( 'UM' ) ) {
		return;
	}

	$user_id = is_object( $user ) ? $user->ID : absint( $user );
	if ( ! $user_id ) {
		return;
	}

	UM()->common()->users()->set_status( $user_id, 'rejected' );
}
add_action( 'new_user_approve_user_denied', 'nua_um_sync_denied_status', 10, 1 );
