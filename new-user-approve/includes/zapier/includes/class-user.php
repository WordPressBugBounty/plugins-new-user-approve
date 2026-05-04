<?php
/**
 * User handling for Zapier integration.
 *
 * @package New_User_Approve
 */

namespace Premium_NewUserApproveZapier;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Handles user status updates for Zapier integration.
 */
class User {

	/**
	 * Singleton instance.
	 *
	 * @var User
	 */
	private static $instance;

	/**
	 * Get singleton instance.
	 *
	 * @version 1.0
	 * @since 2.1
	 * @return User
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @version 1.0
	 * @since 2.1
	 */
	public function __construct() {
		add_action( 'new_user_approve_user_approved', array( $this, 'user_approved' ) );
		add_action( 'new_user_approve_user_denied', array( $this, 'user_denied' ) );
		add_filter(
			'new_user_approve_default_status',
			array( $this, 'user_pending' ),
			999,
			2
		);
		add_action(
			'nua_invited_user',
			array( $this, 'user_auto_approved_via_inv_code' ),
			15,
			2
		);
		add_action(
			'nua_whitelisted_users',
			array( $this, 'user_auto_approved_via_whitelist' ),
			15,
			3
		);
		add_action(
			'role_base_approval_completed',
			array( $this, 'user_auto_approved_via_user_role' ),
			15,
			4
		);
	}

	// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	/**
	 * Handle user auto-approved via user role.
	 *
	 * @param int   $user_id                The user ID.
	 * @param array $user_roles             The user roles.
	 * @param array $auto_approve_role_list Auto approve role list (unused).
	 * @param mixed $status                 Status (unused).
	 * @since 2.6
	 */
	public function user_auto_approved_via_user_role(
		$user_id,
		$user_roles,
		$auto_approve_role_list,
		$status
	) {
		// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		update_user_meta( $user_id, 'nua_user_role_based_approved', $user_roles );
		$this->update_user( 'nua_user_approved_via_role', $user_id );
	}

	/**
	 * Handle user auto-approved via whitelist.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $email   The user email.
	 * @param string $domain  The domain used.
	 * @version 1.0
	 * @since 2.5
	 */
	public function user_auto_approved_via_whitelist( $user_id, $email, $domain ) {
		update_user_meta( $user_id, 'nua_wl_domain_used', $domain );
		$this->update_user( 'nua_user_whitelisted', $user_id );
	}

	/**
	 * Handle user auto-approved via invitation code.
	 *
	 * @param int    $user_id  The user ID.
	 * @param string $code_inv The invitation code used.
	 * @version 1.0
	 * @since 2.5
	 */
	public function user_auto_approved_via_inv_code( $user_id, $code_inv ) {
		update_user_meta( $user_id, 'nua_invcode_used', $code_inv );
		$this->update_user( 'nua_user_invcode', $user_id );
	}

	/**
	 * Handle user pending status.
	 *
	 * @param string $status  The user status.
	 * @param int    $user_id The user ID.
	 * @version 1.0
	 * @since 2.5
	 * @return string
	 */
	public function user_pending( $status, $user_id ) {
		if ( 'pending' === $status ) {
			$this->update_user( 'nua_user_pending', $user_id );
		}
		return $status;
	}

	/**
	 * Handle user approved status.
	 *
	 * @param WP_User $user The user object.
	 * @version 1.0
	 * @since 2.1
	 */
	public function user_approved( $user ) {
		if (
			! empty( get_user_meta( $user->ID, 'nua_invcode_used', true ) ) ||
			! empty( get_user_meta( $user->ID, 'nua_wl_domain_used', true ) )
		) {
			// user is auto approved through invitation code or whitelist.
			return;
		}
		$this->update_user( 'nua_user_approved', $user->ID );
	}

	/**
	 * Handle user denied status.
	 *
	 * @param WP_User $user The user object.
	 * @version 1.0
	 * @since 2.1
	 */
	public function user_denied( $user ) {
		$this->update_user( 'nua_user_denied', $user->ID );
	}

	/**
	 * Update user status in Zapier table.
	 *
	 * @param string $option_name The option name.
	 * @param int    $user_id     The user ID.
	 */
	public function update_user( $option_name, $user_id ) {
		// inserting the data into nua-zapier table.
		nua_zapier_insert_log( $option_name, $user_id );
	}
	/**
	 * Making compatibility with NUA previous version
	 * Inserting the nua-zapier options users data to nua-zapier table
	 */
	public function nua_zap_compatible_legacy_options() {
		global $wpdb;
		$table_name       = $wpdb->prefix . 'nua_zapier';
		$options_statuses = array(
			'nua_user_pending',
			'nua_user_approved',
			'nua_user_denied',
			'nua_user_invcode',
		);
		foreach ( $options_statuses as $status ) {
			$user_data = get_option( $status );
			$option    = array( 'users_status' => $status );
			if ( ! empty( $user_data ) ) {
				foreach ( $user_data as $key => $value ) {
					$time = $value['time'];
					unset( $value['time'] );
					unset( $value['id'] );
					$created_time = array( 'created_time' => $time );
					$value        = array_merge( $value, $created_time );
					$value        = array_merge( $value, $option );
					$wpdb->insert( $table_name, $value ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				}
			}
		}
	}
}

\Premium_NewUserApproveZapier\User::get_instance();
