<?php
/**
 * Users API Endpoint
 *
 * Registers and handles REST API routes for user management.
 *
 * @package NewUserApprove
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Users API Class
 *
 * Handles REST API routes for user approval management.
 *
 * @since 2.0.0
 */
class Users_API {

	/**
	 * The only instance of Users_API.
	 *
	 * @var Users_API
	 */
	public static $instance;

	/**
	 * Returns the main instance.
	 *
	 * @return Users_API
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new Users_API();
		}
		return self::$instance;
	}

	/**
	 * Class constructor.
	 *
	 * Registers REST API hooks.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_user_routes' ) );
	}

	/**
	 * Register REST API routes for user management.
	 */
	public function register_user_routes() {
		register_rest_route(
			'nua-request',
			'/v1/recent-users',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'recent_users' ),
				'permission_callback' => array(
					$this,
					'nua_users_api_permission_callback',
				),
			)
		);

		register_rest_route(
			'nua-request',
			'/v1/update-user',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_user' ),
				'permission_callback' => array(
					$this,
					'nua_users_api_permission_callback',
				),
			)
		);

		register_rest_route(
			'nua-request',
			'/v1/get-all-users',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_all_users' ),
				'permission_callback' => array(
					$this,
					'nua_users_api_permission_callback',
				),
			)
		);

		register_rest_route(
			'nua-request',
			'/v1/get-approved-users',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_approved_users' ),
				'permission_callback' => array(
					$this,
					'nua_users_api_permission_callback',
				),
			)
		);

		register_rest_route(
			'nua-request',
			'/v1/get-pending-users',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_pending_users' ),
				'permission_callback' => array(
					$this,
					'nua_users_api_permission_callback',
				),
			)
		);

		register_rest_route(
			'nua-request',
			'/v1/get-denied-users',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_denied_users' ),
				'permission_callback' => array(
					$this,
					'nua_users_api_permission_callback',
				),
			)
		);

		register_rest_route(
			'nua-request',
			'/v1/get-approved-user-roles',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_approved_user_roles' ),
				'permission_callback' => array(
					$this,
					'nua_users_api_permission_callback',
				),
			)
		);

		register_rest_route(
			'nua-request',
			'/v1/get-user-roles',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_user_roles' ),
				'permission_callback' => array(
					$this,
					'nua_users_api_permission_callback',
				),
			)
		);

		register_rest_route(
			'nua-request',
			'/v1/get-activity-log',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_activity_log' ),
				'permission_callback' => array(
					$this,
					'nua_users_api_permission_callback',
				),
			)
		);

		register_rest_route(
			'nua-request',
			'/v1/update-user-role',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_user_role' ),
				'permission_callback' => array(
					$this,
					'nua_users_api_permission_callback',
				),
			)
		);

		register_rest_route(
			'nua-request',
			'/v1/get-api-key',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_api_key' ),
				'permission_callback' => array(
					$this,
					'nua_users_api_permission_callback',
				),
			)
		);

		register_rest_route(
			'nua-request',
			'/v1/update-api-key',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_api_key' ),
				'permission_callback' => array(
					$this,
					'nua_users_api_permission_callback',
				),
			)
		);

		register_rest_route(
			'nua-request',
			'/v1/get-all-statuses-users',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_all_statuses_users' ),
				'permission_callback' => array(
					$this,
					'nua_users_api_permission_callback',
				),
			)
		);
	}

	/**
	 * Get recent registered users via REST API.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return array
	 */
	public function recent_users( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid nonce.', 'new-user-approve' ),
				array( 'status' => 403 )
			);
		}

		$param_filter_by = $request->get_param( 'filter_by' );
		$filter_by       = sanitize_text_field(
			$param_filter_by ? $param_filter_by : '30 days ago'
		);
		$limit           = (int) apply_filters( 'recent_users_limit', 5 );
		$new_results     = $this->nua_users_filter( $filter_by, $limit );

		$users        = array();
		$default_cols = array(
			'user_login',
			'user_email',
			'user_registered',
			'nua_status',
			'actions',
		);

		foreach ( $new_results as $user ) {
			$data    = array(
				'ID'              => $user->ID,
				'user_login'      => $user->user_login,
				'user_email'      => $user->user_email,
				'user_registered' => get_date_from_gmt(
					$user->user_registered,
					'Y-m-d H:i:s'
				),
				'nua_status'      => pw_new_user_approve()->get_user_status(
					$user->ID
				),
			);
			$users[] = (object) apply_filters(
				'nua_recent_user_data',
				$data,
				$user
			);
		}

		$extra_cols = ! empty( $users[0] ) ? array_keys( (array) $users[0] ) : array();
		$extra_cols = array_filter(
			$extra_cols,
			function ( $col ) {
				return 'ID' !== $col;
			}
		);

		$columns = apply_filters(
			'nua_user_columns',
			array_merge( $default_cols, $extra_cols )
		);

		return array(
			'users'         => $users,
			'totals'        => count( $users ),
			'columns_order' => array_values( array_unique( $columns ) ),
		);
	}

	/**
	 * Update user status manually via REST API.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_user( $request ) {
		// Nonce verification.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid nonce.', 'new-user-approve' ),
				array( 'status' => 403 )
			);
		}

		if (
			isset( $_SERVER['REQUEST_METHOD'] ) &&
			'POST' === $_SERVER['REQUEST_METHOD']
		) {
			$post_data = file_get_contents( 'php://input' );
			$data      = json_decode( $post_data, true );

			if ( $data ) {
				// Handle Bulk Users.
				$user_ids = array();

				if (
					isset( $data['userIDs'] ) &&
					is_array( $data['userIDs'] ) &&
					! empty( $data['userIDs'] )
				) {
					$user_ids = array_map( 'absint', $data['userIDs'] );
				} elseif ( isset( $data['userID'] ) && ! empty( $data['userID'] ) ) {
					$user_ids[] = absint( $data['userID'] ); // fallback for single user.
				} else {
					return new \WP_Error(
						400,
						__( 'Incomplete Request', 'new-user-approve' ),
						'Incomplete Request'
					);
				}

				if (
					! isset( $data['status_label'] ) ||
					empty( $data['status_label'] )
				) {
					return new \WP_Error(
						400,
						__( 'Incomplete Request', 'new-user-approve' ),
						'Incomplete Request'
					);
				}

				$statuses = array(
					'approve' => 'approved',
					'deny'    => 'denied',
				);

				$label       = sanitize_text_field( $data['status_label'] );
				$user_status = $statuses[ $label ];

				foreach ( $user_ids as $user_id ) {
					if ( 'approved' === $user_status ) {
						pw_new_user_approve()->approve_user( $user_id );
					} elseif ( 'denied' === $user_status ) {
						pw_new_user_approve()->update_deny_status( $user_id );
						pw_new_user_approve()->deny_user( $user_id );
					}
				}

				return new WP_REST_Response( array( 'message' => 'Success' ), 200 );
			} else {
				return new \WP_Error(
					400,
					__( 'Request has been Failed', 'new-user-approve' ),
					'Request has been Failed'
				);
			}
		}
	}

	/**
	 * Get all users via REST API.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return array
	 */
	public function get_all_users( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid nonce.', 'new-user-approve' ),
				array( 'status' => 403 )
			);
		}

		$page_param   = $request->get_param( 'page' );
		$page         = $page_param ? (int) $page_param : 1;
		$limit_param  = $request->get_param( 'limit' );
		$limit        = $limit_param ? (int) $limit_param : 10;
		$offset       = ( $page - 1 ) * $limit;
		$search_param = $request->get_param( 'search' );
		$search       = $search_param ? sanitize_text_field( $search_param ) : '';

		$args = array(
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => 'pw_user_status',
					'value'   => '',
					'compare' => '!=',
				),
			),
			'orderby'        => 'user_registered',
			'order'          => 'DESC',
			'number'         => $limit,
			'offset'         => $offset,
			'search'         => '*' . $search . '*',
			'search_columns' => array( 'user_login', 'user_nicename', 'user_email' ),
		);

		$results      = new WP_User_Query(
			apply_filters( 'get_all_users_query', $args )
		);
		$users        = array();
		$default_cols = array(
			'user_login',
			'user_email',
			'user_registered',
			'nua_status',
			'actions',
		);

		foreach ( $results->get_results() as $user ) {
			$data    = array(
				'ID'              => $user->ID,
				'user_login'      => $user->user_login,
				'user_email'      => $user->user_email,
				'user_registered' => get_date_from_gmt(
					$user->user_registered,
					'Y-m-d H:i:s'
				),
				'nua_status'      => pw_new_user_approve()->get_user_status(
					$user->ID
				),
			);
			$users[] = (object) apply_filters( 'nua_user_data', $data, $user );
		}

		$extra_cols = ! empty( $users[0] ) ? array_keys( (array) $users[0] ) : array();
		$extra_cols = array_filter(
			$extra_cols,
			function ( $col ) {
				return 'ID' !== $col;
			}
		);

		$columns = apply_filters(
			'nua_user_columns',
			array_merge( $default_cols, $extra_cols )
		);

		return array(
			'users'         => $users,
			'totals'        => $results->get_total(),
			'columns_order' => array_values( array_unique( $columns ) ),
		);
	}

	/**
	 * Get approved users via REST API.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return array
	 */
	public function get_approved_users( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid nonce.', 'new-user-approve' ),
				array( 'status' => 403 )
			);
		}

		$page_param   = $request->get_param( 'page' );
		$page         = $page_param ? (int) $page_param : 1;
		$limit_param  = $request->get_param( 'limit' );
		$limit        = $limit_param ? (int) $limit_param : 5;
		$offset       = ( $page - 1 ) * $limit;
		$search_param = $request->get_param( 'search' );
		$search       = $search_param ? sanitize_text_field( $search_param ) : '';

		$args = array(
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => 'pw_user_status',
					'value' => 'approved',
				),
			),
			'orderby'        => 'user_registered',
			'order'          => 'DESC',
			'number'         => $limit,
			'offset'         => $offset,
			'search'         => '*' . $search . '*',
			'search_columns' => array( 'user_login', 'user_nicename', 'user_email' ),
		);

		$results      = new WP_User_Query(
			apply_filters( 'get_approved_users_query', $args )
		);
		$users        = array();
		$default_cols = array(
			'user_login',
			'user_email',
			'user_registered',
			'nua_status',
			'actions',
		);

		foreach ( $results->get_results() as $user ) {
			$data    = array(
				'ID'              => $user->ID,
				'user_login'      => $user->user_login,
				'user_email'      => $user->user_email,
				'user_registered' => get_date_from_gmt(
					$user->user_registered,
					'Y-m-d H:i:s'
				),
				'nua_status'      => pw_new_user_approve()->get_user_status(
					$user->ID
				),
			);
			$users[] = (object) apply_filters( 'nua_user_data', $data, $user );
		}

		$extra_cols = ! empty( $users[0] ) ? array_keys( (array) $users[0] ) : array();
		$extra_cols = array_filter(
			$extra_cols,
			function ( $col ) {
				return 'ID' !== $col;
			}
		);

		$columns = apply_filters(
			'nua_user_columns',
			array_merge( $default_cols, $extra_cols )
		);

		return array(
			'users'         => $users,
			'totals'        => $results->get_total(),
			'columns_order' => array_values( array_unique( $columns ) ),
		);
	}

	/**
	 * Get pending users via REST API.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return array
	 */
	public function get_pending_users( $request ) {
		// Nonce verification.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid nonce.', 'new-user-approve' ),
				array( 'status' => 403 )
			);
		}

		$page   = $request->get_param( 'page' )
			? intval( $request->get_param( 'page' ) )
			: 1;
		$limit  = $request->get_param( 'limit' )
			? intval( $request->get_param( 'limit' ) )
			: 5;
		$offset = ( $page - 1 ) * $limit;
		$search = $request->get_param( 'search' )
			? sanitize_text_field( $request->get_param( 'search' ) )
			: '';

		$args = array(
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => 'pw_user_status',
					'value'   => 'pending',
					'compare' => '=',
				),
			),
			'orderby'        => 'user_registered',
			'order'          => 'DESC',
			'number'         => $limit,
			'offset'         => $offset,
			'search'         => '*' . $search . '*',
			'search_columns' => array( 'user_login', 'user_nicename', 'user_email' ),
		);

		$query       = apply_filters( 'get_pending_users_query', $args );
		$results     = new WP_User_Query( $query );
		$new_results = $results->get_results();
		$total_users = $results->get_total();

		$users = array();
		foreach ( $new_results as $user ) {
			$status          = pw_new_user_approve()->get_user_status( $user->ID );
			$user_registered = get_date_from_gmt(
				$user->user_registered,
				'Y-m-d H:i:s'
			);

			$data = array(
				'ID'              => $user->ID,
				'user_login'      => $user->user_login,
				'user_email'      => $user->user_email,
				'user_registered' => $user_registered,
				'nua_status'      => $status,
			);

			$users[] = (object) apply_filters( 'nua_user_data', $data, $user );
		}

		$default_cols = array(
			'user_login',
			'user_email',
			'user_registered',
			'nua_status',
			'actions',
		);
		$extra_cols   = ! empty( $users[0] ) ? array_keys( (array) $users[0] ) : array();
		$extra_cols   = array_filter(
			$extra_cols,
			function ( $col ) {
				return 'ID' !== $col;
			}
		);

		$columns = array_merge( $default_cols, $extra_cols );
		$columns = apply_filters( 'nua_user_columns', $columns );
		$columns = array_values( array_unique( $columns ) );

		return array(
			'users'         => $users,
			'totals'        => $total_users,
			'columns_order' => $columns,
		);
	}

	/**
	 * Get denied users via REST API.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return array
	 */
	public function get_denied_users( $request ) {
		// Nonce verification.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid nonce.', 'new-user-approve' ),
				array( 'status' => 403 )
			);
		}

		$page   = $request->get_param( 'page' )
			? intval( $request->get_param( 'page' ) )
			: 1;
		$limit  = $request->get_param( 'limit' )
			? intval( $request->get_param( 'limit' ) )
			: 5;
		$offset = ( $page - 1 ) * $limit;
		$search = $request->get_param( 'search' )
			? sanitize_text_field( $request->get_param( 'search' ) )
			: '';

		$args = array(
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => 'pw_user_status',
					'value'   => 'denied',
					'compare' => '=',
				),
			),
			'orderby'        => 'user_registered',
			'order'          => 'DESC',
			'number'         => $limit,
			'offset'         => $offset,
			'search'         => '*' . $search . '*',
			'search_columns' => array( 'user_login', 'user_nicename', 'user_email' ),
		);

		$query       = apply_filters( 'get_denied_users_query', $args );
		$results     = new WP_User_Query( $query );
		$new_results = $results->get_results();
		$total_users = $results->get_total();

		$users = array();
		foreach ( $new_results as $user ) {
			$status          = pw_new_user_approve()->get_user_status( $user->ID );
			$user_registered = get_date_from_gmt(
				$user->user_registered,
				'Y-m-d H:i:s'
			);

			$data = array(
				'ID'              => $user->ID,
				'user_login'      => $user->user_login,
				'user_email'      => $user->user_email,
				'user_registered' => $user_registered,
				'nua_status'      => $status,
			);

			$users[] = (object) apply_filters( 'nua_user_data', $data, $user );
		}

		$default_cols = array(
			'user_login',
			'user_email',
			'user_registered',
			'nua_status',
			'actions',
		);
		$extra_cols   = ! empty( $users[0] ) ? array_keys( (array) $users[0] ) : array();
		$extra_cols   = array_filter(
			$extra_cols,
			function ( $col ) {
				return 'ID' !== $col;
			}
		);

		$columns = array_merge( $default_cols, $extra_cols );
		$columns = apply_filters( 'nua_user_columns', $columns );
		$columns = array_values( array_unique( $columns ) );

		return array(
			'users'         => $users,
			'totals'        => $total_users,
			'columns_order' => $columns,
		);
	}

	/**
	 * Get approved users with their current and requested roles via REST API.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return array
	 */
	public function get_approved_user_roles( $request ) {
		// Nonce verification.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid nonce.', 'new-user-approve' ),
				array( 'status' => 403 )
			);
		}

		$args  = array(
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => 'pw_user_status',
					'value'   => 'approved',
					'compare' => '=',
				),
			),
			'orderby'    => 'date',
			'order'      => 'DESC',
			'number'     => 5,
		);
		$query = apply_filters( 'get_approved_user_roles_query', $args );

		$results     = new WP_User_Query( $query );
		$new_results = $results->get_results();
		$users       = array();
		foreach ( $new_results as $user ) {
			$user_current_role =
				isset( $user->roles[0] ) && ! empty( $user->roles[0] )
					? sanitize_text_field( $user->roles[0] )
					: '';
			$user_current_role = apply_filters(
				'new_user_approve_user_roles',
				$user_current_role,
				$user->roles
			);

			$user_requseted_role = get_user_meta(
				$user->ID,
				'nua_request_new_role',
				true
			);
			$user_roles          = array(
				'user_current_role'   => $user_current_role,
				'user_requested_role' => $user_requseted_role,
			);

			$users_data = (object) array_merge(
				(array) $user->data,
				(array) $user_roles
			);
			$users[]    = $users_data;
		}

		return $users;
	}

	/**
	 * Get all available user roles via REST API.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return array
	 */
	public function get_user_roles( $request ) {
		// Nonce verification.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid nonce.', 'new-user-approve' ),
				array( 'status' => 403 )
			);
		}

		global $wp_roles;

		if ( ! isset( $wp_roles ) ) {
			$all_roles = new WP_Roles();
		}

		$all_roles = $wp_roles->get_names();

		return apply_filters( 'user_roles_edit', $all_roles );
	}

	/**
	 * Get recent user activity log via REST API.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return array
	 */
	public function get_activity_log( $request ) {
		// Nonce verification.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid nonce.', 'new-user-approve' ),
				array( 'status' => 403 )
			);
		}

		$user_query   = new WP_User_Query(
			array(
				'meta_key' => 'pw_user_status_time', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required for ordering by status time; no alternative API exists.
				'orderby'  => 'meta_value',
				'order'    => 'DESC',
				'number'   => 3,
			)
		);
		$new_results  = $user_query->get_results();
		$activity_log = array();
		foreach ( $new_results as $user ) {
			if ( isset( $user->ID ) ) {
				$status                    = get_user_meta( $user->ID, 'pw_user_status', true );
				$time                      = get_user_meta( $user->ID, 'pw_user_status_time', true );
				$activity_log[][ $status ] = array(
					'ID'           => $user->ID,
					'display_name' => $user->display_name,
					'status_time'  => $this->time_ago( $time ),
				);
			}
		}

		return array(
			'status' => 'success',
			'data'   => $activity_log,
		);
	}

	/**
	 * Update a user's role via REST API.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_user_role( $request ) {
		// Nonce verification.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid nonce.', 'new-user-approve' ),
				array( 'status' => 403 )
			);
		}

		if (
			isset( $_SERVER['REQUEST_METHOD'] ) &&
			'POST' === $_SERVER['REQUEST_METHOD']
		) {
			$params   = $request->get_json_params();
			$user_id  = isset( $params['user_id'] )
				? intval( $params['user_id'] )
				: 0;
			$new_role = isset( $params['new_role'] )
				? sanitize_text_field( $params['new_role'] )
				: '';
			if ( $user_id && $new_role ) {
				$user = get_user_by( 'id', $user_id );

				if ( $user ) {
					$user->set_role( $new_role );
					return new WP_REST_Response(
						array(
							'status'  => 'success',
							'message' => 'User role updated successfully.',
						),
						200
					);
				} else {
					return new WP_REST_Response(
						array(
							'status'  => 'error',
							'message' => 'User not found.',
						),
						404
					);
				}
			} else {
				return new WP_REST_Response(
					array(
						'status'  => 'error',
						'message' => 'Invalid user ID or role.',
					),
					400
				);
			}
		}

		return new \WP_Error(
			400,
			__( 'Incomplete Request', 'new-user-approve' ),
			'Incomplete Request'
		);
	}

	/**
	 * Get the Zapier API key via REST API.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return array
	 */
	public function get_api_key( $request ) {
		// Nonce verification.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid nonce.', 'new-user-approve' ),
				array( 'status' => 403 )
			);
		}

		$api_key = get_option( 'nua_api_key' );
		return array( 'api_key' => $api_key );
	}

	/**
	 * Update the Zapier API key via REST API.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response
	 */
	public function update_api_key( $request ) {
		// Nonce verification.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid nonce.', 'new-user-approve' ),
				array( 'status' => 403 )
			);
		}

		$api_key = get_option( 'nua_api_key' );
		$params  = $request->get_json_params();
		$api_key = isset( $params['api_key'] )
			? sanitize_text_field( $params['api_key'] )
			: '';
		update_option( 'nua_api_key', $api_key );

		return new WP_REST_Response(
			array(
				'status'  => 'success',
				'message' => 'Zapier API has been updated successfully.',
			),
			200
		);
	}

	/**
	 * Get user counts for all statuses within a date range via REST API.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response|array
	 */
	public function get_all_statuses_users( $request ) {
		// Nonce verification.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid nonce.', 'new-user-approve' ),
				array( 'status' => 403 )
			);
		}

		$filter_by    = $request->get_param( 'filter_by' );
		$filter_by    = ! empty( $filter_by ) ? $filter_by : '30 days ago';
		$number_limit = apply_filters( 'analytics_users_limit', -1 );
		$results      = $this->nua_users_filter( $filter_by, $number_limit );
		$pending      = 0;
		$approved     = 0;
		$denied       = 0;
		if ( ! empty( $results ) ) {
			foreach ( $results as $user ) {
				$user_status = pw_new_user_approve()->get_user_status(
					$user->ID
				);
				switch ( $user_status ) {
					case 'pending':
						++$pending;
						break;
					case 'approved':
						++$approved;
						break;
					case 'denied':
						++$denied;
						break;
					default:
						break;
				}
			}
			$total = absint( $pending ) + absint( $approved ) + absint( $denied );
			$users = array(
				'total'    => $total,
				'pending'  => $pending,
				'approved' => $approved,
				'denied'   => $denied,
			);
			return new WP_REST_Response( $users, 200 );
		} else {
			return array(
				'total'    => 0,
				'pending'  => 0,
				'approved' => 0,
				'denied'   => 0,
			);
		}
	}

	/**
	 * Filter users by registration date range.
	 *
	 * @param string $filter_by    Date range filter string.
	 * @param int    $number_limit Maximum number of users to return.
	 * @return array
	 */
	public function nua_users_filter( $filter_by = '', $number_limit = '' ) {
		$date_query = array();
		switch ( $filter_by ) {
			case 'today':
				$date_query[] = array(
					'after'     => 'today',
					'inclusive' => true,
					'column'    => 'user_registered',
				);
				break;

			case 'yesterday':
				$date_query[] = array(
					'after'     => 'yesterday',
					'before'    => 'today',
					'inclusive' => true,
					'column'    => 'user_registered',
				);
				break;

			case '1 week ago':
				$date_query[] = array(
					'after'     => 'last Sunday',
					'inclusive' => true,
					'column'    => 'user_registered',
				);
				break;

			case '30 days ago':
				$date_query[] = array(
					'after'     => '30 days ago',
					'inclusive' => true,
					'column'    => 'user_registered',
				);
				break;

			default:
				return null;
		}

		$args    = array(
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => 'pw_user_status',
					'value'   => '',
					'compare' => '!=',
				),
			),
			'date_query' => $date_query,
			'number'     => $number_limit,
			'orderby'    => 'user_registered',
			'order'      => 'DESC',
		);
		$results = new WP_User_Query( $args );

		if ( ! empty( $results->get_results() ) ) {
			return $results->get_results();
		} else {
			return array();
		}
	}

	/**
	 * Returns a human-readable "time ago" string for a given datetime.
	 *
	 * @param string $datetime A datetime string.
	 * @return string
	 */
	public function time_ago( $datetime ) {
		$now  = new DateTime();
		$ago  = new DateTime( $datetime );
		$diff = $now->diff( $ago );

		// Compute weeks separately.
		$weeks    = floor( $diff->d / 7 );
		$diff->d -= $weeks * 7;

		if ( $diff->y > 0 ) {
			return sprintf(
				// translators: %d is the number of years.
				_n(
					'%d year ago',
					'%d years ago',
					$diff->y,
					'new-user-approve'
				),
				$diff->y
			);
		}
		if ( $diff->m > 0 ) {
			return sprintf(
				// translators: %d is the number of months.
				_n(
					'%d month ago',
					'%d months ago',
					$diff->m,
					'new-user-approve'
				),
				$diff->m
			);
		}
		if ( $weeks > 0 ) {
			return sprintf(
				// translators: %d is the number of weeks.
				_n(
					'%d week ago',
					'%d weeks ago',
					$weeks,
					'new-user-approve'
				),
				$weeks
			);
		}
		if ( $diff->d > 0 ) {
			return sprintf(
				// translators: %d is the number of days.
				_n(
					'%d day ago',
					'%d days ago',
					$diff->d,
					'new-user-approve'
				),
				$diff->d
			);
		}
		if ( $diff->h > 0 ) {
			return sprintf(
				// translators: %d is the number of hours.
				_n(
					'%d hour ago',
					'%d hours ago',
					$diff->h,
					'new-user-approve'
				),
				$diff->h
			);
		}
		if ( $diff->i > 0 ) {
			return sprintf(
				// translators: %d is the number of minutes.
				_n(
					'%d minute ago',
					'%d minutes ago',
					$diff->i,
					'new-user-approve'
				),
				$diff->i
			);
		}
		if ( $diff->s > 0 ) {
			return sprintf(
				// translators: %d is the number of seconds.
				_n(
					'%d second ago',
					'%d seconds ago',
					$diff->s,
					'new-user-approve'
				),
				$diff->s
			);
		}

		return __( 'just now', 'new-user-approve' );
	}

	/**
	 * Permission callback for users API endpoints.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return bool|WP_Error
	 */
	public function nua_users_api_permission_callback( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		$current_user = wp_get_current_user();
		$cap          = apply_filters(
			'new_user_approve_min_users_api_cap',
			'edit_users'
		);

		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_forbidden',
				__(
					'Non-logged-in users do not have permission to access this endpoint.',
					'new-user-approve'
				),
				array( 'status' => 403 )
			);
		}

		if (
			! in_array( 'administrator', $current_user->roles, true ) &&
			! current_user_can( $cap )
		) {
			return new WP_Error(
				'rest_forbidden',
				__(
					'You do not have permission to access this endpoint.',
					'new-user-approve'
				),
				array( 'status' => 403 )
			);
		}

		$permission = apply_filters( 'users_api_permission', true, $request );
		return $permission;
	}
}
// phpcs:ignore
function users_api() {
	return Users_API::instance();
}

users_api();
