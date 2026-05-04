<?php
/**
 * Zapier integration functions.
 *
 * @package New_User_Approve
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

if ( ! function_exists( 'create_nua_zapier_table' ) ) :
	/**
	 * Create the NUA Zapier database table.
	 */
	function create_nua_zapier_table() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;
		$nua_zapier_table = $wpdb->prefix . 'nua_zapier';

		$tbl_sql = "CREATE TABLE $nua_zapier_table (
            id            INT(11) NOT NULL AUTO_INCREMENT, 
            user_id       INT(11) NOT NULL, 
            created_time  BIGINT(20) DEFAULT NULL,
            users_status   VARCHAR(225) NOT NULL DEFAULT '',
            PRIMARY KEY   (id), 
            UNIQUE KEY id (id),
            INDEX 		  user_id (user_id),
            INDEX 		  created_time (created_time)
        ) ";

		if ( maybe_create_table( $nua_zapier_table, $tbl_sql ) ) {
			update_option( 'nua_zapier_db_version', NUA_ZAPIER_DB_VERSION );
		}
	}
endif;
// avoiding redeclaring error.
if ( ! function_exists( 'nua_zapier_insert_log' ) ) :
	/**
	 * Insert user log into Zapier table.
	 *
	 * @param string $option_name The option name.
	 * @param int    $user_id     The user ID.
	 * @return int|false
	 */
	function nua_zapier_insert_log( $option_name, $user_id ) {
		global $wpdb;
		$table_name    = $wpdb->prefix . 'nua_zapier';
		$nua_zapier_db = get_option( 'nua_zapier_db_version', false );
		if ( NUA_ZAPIER_DB_VERSION !== $nua_zapier_db ) {
			// creating the nua_zapier table if does not exist.
			create_nua_zapier_table();
		}

		$data = array(
			'user_id'      => $user_id,
			'users_status' => $option_name,
			'created_time' => time(),
		);

		premium_nua_delete_previous_same_id_user( $user_id );

		$wpdb->insert( $table_name, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		return $wpdb->insert_id;
	}
endif;
/**
 * Delete previous user entries while updating user status.
 *
 * @param int $user_id The user ID.
 */
function premium_nua_delete_previous_same_id_user( $user_id ) {
	global $wpdb;

	$table_name = $wpdb->prefix . 'nua_zapier';

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name cannot be prepared.
	$query = $wpdb->prepare(
		"SELECT user_id FROM {$table_name} WHERE user_id = %d",
		$user_id
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$result = $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

	if ( $result ) {
		$wpdb->delete( $table_name, array( 'user_id' => $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	return $result;
}

/**
 * Get users by NUA Zapier status.
 *
 * @param string $option_name The option name.
 * @return array
 */
function premium_get_users_by_nua_zap( $option_name ) {
	global $wpdb;

	$table_name = $wpdb->prefix . 'nua_zapier';

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name cannot be prepared.
	$query = $wpdb->prepare(
		"SELECT id, user_id, created_time AS time FROM {$table_name} WHERE users_status = %s ORDER BY created_time DESC",
		$option_name
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$results = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

	return apply_filters( 'nua_zapier_users', $results );
}
