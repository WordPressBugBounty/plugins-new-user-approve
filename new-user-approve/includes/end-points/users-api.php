<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Users_API {

	public static $instance;


	public static function instance() {
		if (!isset ( self::$instance ) ) {
			self::$instance = new Users_API();
		}
		return self::$instance;
	}

	
	public function __construct() {

		add_action('rest_api_init', array( $this, 'register_user_routes' ) );
		// add_action('admin_init', array($this, 'get_activity_log'));
	}

	public function register_user_routes() {

		register_rest_route( 'nua-request', '/v1/recent-users', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'recent_users' ),
			'permission_callback' => array( $this, 'nua_users_api_permission_callback' )
		) );

		register_rest_route( 'nua-request', '/v1/update-user', array(
			'methods'  => 'POST',
			'callback' => array( $this, 'update_user' ),
			'permission_callback' => array( $this, 'nua_users_api_permission_callback' )
		) );

		register_rest_route( 'nua-request', '/v1/get-all-users', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_all_users' ),
			'permission_callback' => array( $this, 'nua_users_api_permission_callback' )
		) );

		register_rest_route( 'nua-request', '/v1/get-approved-users', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_approved_users' ),
			'permission_callback' => array( $this, 'nua_users_api_permission_callback' )
		) );

		register_rest_route( 'nua-request', '/v1/get-pending-users', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_pending_users' ),
			'permission_callback' => array( $this, 'nua_users_api_permission_callback' )
		) );

		register_rest_route( 'nua-request', '/v1/get-denied-users', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_denied_users' ),
			'permission_callback' => array( $this, 'nua_users_api_permission_callback' )
		) );

		register_rest_route( 'nua-request', '/v1/get-approved-user-roles', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_approved_user_roles' ),
			'permission_callback' => array( $this, 'nua_users_api_permission_callback' )
		) );

		register_rest_route( 'nua-request', '/v1/get-user-roles', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_user_roles' ),
			'permission_callback' => array( $this, 'nua_users_api_permission_callback' )
		) );

		register_rest_route( 'nua-request', '/v1/get-activity-log', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_activity_log' ),
			'permission_callback' => array( $this, 'nua_users_api_permission_callback' )
		) );

		register_rest_route( 'nua-request', '/v1/update-user-role', array(
			'methods'  => 'POST',
			'callback' => array( $this, 'update_user_role' ),
			'permission_callback' => array( $this, 'nua_users_api_permission_callback' )
		) );
		
		register_rest_route( 'nua-request', '/v1/get-api-key', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_api_key' ),
			'permission_callback' => array( $this, 'nua_users_api_permission_callback' )
		) );

		register_rest_route( 'nua-request', '/v1/update-api-key', array(
			'methods'  => 'POST',
			'callback' => array( $this, 'update_api_key' ),
			'permission_callback' => array( $this, 'nua_users_api_permission_callback' )
		) );

		register_rest_route( 'nua-request', '/v1/get-all-statuses-users', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_all_statuses_users' ),
			'permission_callback' => array( $this, 'nua_users_api_permission_callback' )
		) );
	}


	public function recent_users( $request ) {
		
		$nonce = $request->get_header('X-WP-Nonce');
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'rest_forbidden', __('Invalid nonce.', 'new-user-approve'), array( 'status' => 403 ) );
		}

		$filter_by = isset($_GET['filter_by']) && !empty($_GET['filter_by']) ? sanitize_text_field($_GET['filter_by']) :'30 days ago';

		$number_limit = apply_filters('recent_users_limit', 5);
		$new_results = $this->nua_users_filter($filter_by, $number_limit);
		if ( !empty( $new_results ) ) {

			$users = array();
			foreach ( $new_results as  $user) {
				
				$user_status = pw_new_user_approve()->get_user_status( $user->ID );
				$user_status = array( 'nua_status' => $user_status );
				 // Convert `user_registered` to local timezone
				$user_registered = get_date_from_gmt( $user->user_registered, 'Y-m-d H:i:s' );
				$usersData   = (object) array_merge( (array) $user->data, array( 'user_registered' => $user_registered ), (array) $user_status  );
				$users []  = $usersData;
			}
			return new WP_REST_Response( $users, 200 );
		}
		return array( 'data' => 'empty' );
	}
	// updating user manually.
	public function update_user( $request ) {

		// Nonce Verification
		$nonce = $request->get_header('X-WP-Nonce');
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'rest_forbidden', __('Invalid nonce.', 'new-user-approve'), array( 'status' => 403 ) );
		}

		if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
			$postData = file_get_contents('php://input');
			$data = json_decode($postData, true);
			
			if ( $data ) {
				// request handling function
				if ( ( !isset($data['userID']) || empty($data['userID']) ) || ( !isset($data['status_label']) || empty($data['status_label']) ) ) {
					return new \WP_Error( 400, __( 'Incomplete Request', 'new-user-approve' ), 'Incomplete Request' );
				}

				$statuses = array(
					'approve' => 'approved',
					'deny' => 'denied'
				);

				$user_id =  absint($data['userID']);
				$label   =  sanitize_text_field($data['status_label']) ;
				$user_status = $statuses[ $label ] ;
			   
				if ( $user_status == 'approved' ) {
					pw_new_user_approve()->approve_user( $user_id );
				}
				
				if ( $user_status == 'denied' ) {

					//change the status
					pw_new_user_approve()->update_deny_status( $user_id ); 

					// send the denied email
					pw_new_user_approve()->deny_user( $user_id );
				}
				// do_action('updated_recent_requested_user', $user_id, $user_status);

				return new WP_REST_Response( $data , 200 );
			} else {
				$response =new \WP_Error( 400, __( 'Request has been Failed', 'new-user-approve' ), 'Request has been Failed' );
				return $response;
			}
		}
	}
	public function get_all_users( $request ) {
	
		// Nonce Verification
		$nonce = $request->get_header('X-WP-Nonce');
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'rest_forbidden', __('Invalid nonce.', 'new-user-approve'), array( 'status' => 403 ) );
		}

		$page = $request->get_param('page') ? intval($request->get_param('page')) : 1;
		$limit = $request->get_param('limit') ? intval($request->get_param('limit')) : 10;
		$offset = ( $page - 1 ) * $limit;
		$search = $request->get_param( 'search' ) ? sanitize_text_field( $request->get_param( 'search' ) ) : '';
		
		$args = array(

			'meta_query' => array(
				array(
					'key' => 'pw_user_status',
					'value' => '',
					'compare' => '!='
				)
			),
		
			'orderby' => 'user_registered', // Order by registration date
			'order'   => 'DESC',            // Get the most recent record
			'number'  => $limit, 
			'offset' => $offset,            
			'search'  => '*' . $search . '*',
			'search_columns' => array( 'user_login', 'user_nicename', 'user_email' ),



		);
		$query = apply_filters('get_all_users_query', $args);

		$results = new WP_User_Query( $query );
		$new_results =  $results->get_results();
		
		$total_users = $results->get_total();
		$users = array();
		$all_users = array();
		foreach ( $new_results as  $user) {

			$user_status = pw_new_user_approve()->get_user_status( $user->ID );
			$user_status = array( 'nua_status' => $user_status );
			 // Convert `user_registered` to local timezone
			$user_registered = get_date_from_gmt( $user->user_registered, 'Y-m-d H:i:s' );
			$usersData = (object) array(
			'ID' => $user->ID,
			'user_nicename' => $user->user_nicename,
			'user_email' => $user->user_email,
			'display_name' => $user->display_name,
			'user_registered' => $user_registered,
			'nua_status' => $user_status['nua_status'],
		);
			$users[]    = $usersData;
		}

		$all_users['users'] = $users;
		$all_users['totals']  = $total_users;
		return $all_users;
	}
	public function get_approved_users( $request ) {

		// Nonce Verification
		$nonce = $request->get_header('X-WP-Nonce');
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'rest_forbidden', __('Invalid nonce.', 'new-user-approve'), array( 'status' => 403 ) );
		}

		$page = $request->get_param('page') ? intval($request->get_param('page')) : 1;
		$limit = $request->get_param('limit') ? intval($request->get_param('limit')) : 5;
		$offset = ( $page - 1 ) * $limit;
		$search = $request->get_param( 'search' ) ? sanitize_text_field( $request->get_param( 'search' ) ) : '';

		$args = array(
			'meta_query' => array(
				array(
					'key' => 'pw_user_status',
					'value' => 'approved',
					'compare' => '='
				)
			),
			'orderby' => 'user_registered', // Order by registration date
			'order'   => 'DESC',  
			'number' => $limit,
			'offset' => $offset,
			'search' => '*' . $search . '*',
			'search_columns' => array( 'user_login', 'user_nicename', 'user_email' ),
		);
		$query = apply_filters('get_approved_users_query', $args);

		$results = new WP_User_Query( $query );
		$new_results =  $results->get_results();
		$total_users = $results->get_total();
		$users = array();
		$all_users = array();
		foreach ( $new_results as  $user) {
			
			$user_status = pw_new_user_approve()->get_user_status( $user->ID );
			$user_status = array( 'nua_status' => $user_status );
			$user_registered = get_date_from_gmt( $user->user_registered, 'Y-m-d H:i:s' );
			$usersData = (object) array(
			'ID' => $user->ID,
			'user_nicename' => $user->user_nicename,
			'user_email' => $user->user_email,
			'display_name' => $user->display_name,
			'user_registered' => $user_registered,
			'nua_status' => $user_status['nua_status'],
		);
			$users[]     = $usersData;
		}

		$all_users['users'] = $users;
		$all_users['totals']  = $total_users;
		return $all_users;
	}


	public function get_pending_users( $request ) {

		// Nonce Verification
		$nonce = $request->get_header('X-WP-Nonce');
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'rest_forbidden', __('Invalid nonce.', 'new-user-approve'), array( 'status' => 403 ) );
		}

		$page = $request->get_param('page') ? intval($request->get_param('page')) : 1;
		$limit = $request->get_param('limit') ? intval($request->get_param('limit')) : 5;
		$offset = ( $page - 1 ) * $limit;
		$search = $request->get_param( 'search' ) ? sanitize_text_field( $request->get_param( 'search' ) ) : '';

		$args = array(
			'meta_query' => array(
				array(
					'key' => 'pw_user_status',
					'value' => 'pending',
					'compare' => '='
				)
			),
			'orderby' => 'user_registered', // Order by registration date
			'order'   => 'DESC',  
			'number' => $limit,
			'offset' => $offset,
			'search' => '*' . $search . '*',
			'search_columns' => array( 'user_login', 'user_nicename', 'user_email' ),
		);
		$query = apply_filters('get_pending_users_query', $args);

		$results = new WP_User_Query( $query );
		$new_results =  $results->get_results();
		$total_users = $results->get_total();
		$users = array();
		$all_users = array();
		foreach ( $new_results as  $user) {
			
			$user_status = pw_new_user_approve()->get_user_status( $user->ID );
			$user_status = array( 'nua_status' => $user_status );
			$user_registered = get_date_from_gmt( $user->user_registered, 'Y-m-d H:i:s' );
			
			$usersData = (object) array(
			'ID' => $user->ID,
			'user_nicename' => $user->user_nicename,
			'user_email' => $user->user_email,
			'display_name' => $user->display_name,
			'user_registered' => $user_registered,
			'nua_status' => $user_status['nua_status'],
		);
			$users[]     = $usersData;
		}

		$all_users['users'] = $users;
		$all_users['totals']  = $total_users;
		return $all_users;
	}


	public function get_denied_users( $request ) {

		// Nonce Verification
		$nonce = $request->get_header('X-WP-Nonce');
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'rest_forbidden', __('Invalid nonce.', 'new-user-approve'), array( 'status' => 403 ) );
		}

		$page = $request->get_param('page') ? intval($request->get_param('page')) : 1;
		$limit = $request->get_param('limit') ? intval($request->get_param('limit')) : 5;
		$offset = ( $page - 1 ) * $limit;
		$search = $request->get_param( 'search' ) ? sanitize_text_field( $request->get_param( 'search' ) ) : '';

		$args = array(
			'meta_query' => array(
				array(
					'key' => 'pw_user_status',
					'value' => 'denied',
					'compare' => '='
				)
			),
			'orderby' => 'user_registered', // Order by registration date
			'order'   => 'DESC',  
			'number' => $limit,
			'offset' => $offset,
			'search' => '*' . $search . '*',
			'search_columns' => array( 'user_login', 'user_nicename', 'user_email' ),
		);
		$query = apply_filters('get_denied_users_query', $args);

		$results = new WP_User_Query( $query );
		$new_results =  $results->get_results();
		$total_users = $results->get_total(); 
		$users = array();
		$all_users = array();
		foreach ( $new_results as  $user) {
			
			$user_status = pw_new_user_approve()->get_user_status( $user->ID );
			$user_status = array( 'nua_status' => $user_status );
			$user_registered = get_date_from_gmt( $user->user_registered, 'Y-m-d H:i:s' );
			$usersData = (object) array(
			'ID' => $user->ID,
			'user_nicename' => $user->user_nicename,
			'user_email' => $user->user_email,
			'display_name' => $user->display_name,
			'user_registered' => $user_registered,
			'nua_status' => $user_status['nua_status'],
		);
			$users[]     = $usersData;
		}

		$all_users['users'] = $users;
		$all_users['totals']  = $total_users;
		return $all_users;
	}


	public function get_approved_user_roles( $request ) {

		// Nonce Verification
		$nonce = $request->get_header('X-WP-Nonce');
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'rest_forbidden', __('Invalid nonce.', 'new-user-approve'), array( 'status' => 403 ) );
		}

		$args = array(
			'meta_query' => array(
				array(
					'key' => 'pw_user_status',
					'value' => 'approved',
					'compare' => '='
				)
			),
			'orderby' => 'date', 
			'order' => 'DESC',
			'number' => 5,
		);
		$query = apply_filters('get_approved_user_roles_query', $args);

		$results = new WP_User_Query( $query );
		$new_results =  $results->get_results();
		$users = array();
		foreach ( $new_results as  $user) {
			
			$user_current_role = isset( $user->roles[0] ) && !empty($user->roles[0]) ? sanitize_text_field($user->roles[0]): '';
			$user_current_role = apply_filters('new_user_approve_user_roles', $user_current_role, $user->roles); 

			$user_requseted_role = get_user_meta( $user->ID, 'nua_request_new_role', true );
			$user_roles = array(
				'user_current_role' => $user_current_role,
				'user_requested_role' => $user_requseted_role
			);

			$usersData   = (object) array_merge( (array) $user->data, (array) $user_roles  );
			$users []    = $usersData;
		}

		return $users;
	}


	public function get_user_roles( $request ) {

		// Nonce Verification
		$nonce = $request->get_header('X-WP-Nonce');
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'rest_forbidden', __('Invalid nonce.', 'new-user-approve'), array( 'status' => 403 ) );
		}

		global $wp_roles;

		if ( !isset( $wp_roles ) ) { 
			$all_roles = new WP_Roles();
		}

		$all_roles = $wp_roles->get_names();

		return apply_filters( 'user_roles_edit', $all_roles );
	}


	public function get_activity_log( $request ) {
		
		// Nonce Verification
		$nonce = $request->get_header('X-WP-Nonce');
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'rest_forbidden', __('Invalid nonce.', 'new-user-approve'), array( 'status' => 403 ) );
		}

		$user_query = new WP_User_Query(array(
			'meta_key'     => 'pw_user_status_time',
			'orderby'      => 'meta_value',
			'order'        => 'DESC',
			'number'       => 3,
		));
		$new_results =  $user_query->get_results();
		$activity_log = array();
		foreach ( $new_results as $user) {
			if ( isset( $user->ID ) ) {

				$status = get_user_meta( $user->ID, 'pw_user_status' , true);
				$time = get_user_meta( $user->ID, 'pw_user_status_time', true);
				$activity_log[][ $status ] = array(
					'ID'           => $user->ID,
					'display_name' => $user->display_name,
					'status_time'  => $this->timeAgo($time)
				);
			}
		}

		return array(
			'status' => 'success',
			'data' => $activity_log
		);
	}

	public function update_user_role( $request ) {

		// Nonce Verification
		$nonce = $request->get_header('X-WP-Nonce');
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'rest_forbidden', __('Invalid nonce.', 'new-user-approve'), array( 'status' => 403 ) );
		}
		
		if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
			$params = $request->get_json_params();
			$user_id = isset( $params['user_id'] ) ? intval( $params['user_id'] ) : 0;
			$new_role = isset( $params['new_role'] ) ? sanitize_text_field( $params['new_role'] ) : '';
			if ($user_id && $new_role) {
				$user = get_user_by('id', $user_id);
			   
				if ($user) {

					$user->set_role( $new_role );
					return new WP_REST_Response(array(
						'status' => 'success',
						'message' => 'User role updated successfully.',
					), 200);
				} else {
					return new WP_REST_Response(array(
						'status' => 'error',
						'message' => 'User not found.',
					), 404);
				}
			} else {
				return new WP_REST_Response(array(
					'status' => 'error',
					'message' => 'Invalid user ID or role.',
				), 400);
			}
				

		}

		return new \WP_Error( 400, __( 'Incomplete Request', 'new-user-approve' ), 'Incomplete Request' );
	}
	

	public function get_api_key( $request ) {

		// Nonce Verification
		$nonce = $request->get_header('X-WP-Nonce');
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'rest_forbidden', __('Invalid nonce.', 'new-user-approve'), array( 'status' => 403 ) );
		}

		$api_key = get_option( 'nua_api_key' );
		return array( 'api_key' => $api_key );
	}

	public function update_api_key( $request ) {

		// Nonce Verification
		$nonce = $request->get_header('X-WP-Nonce');
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'rest_forbidden', __('Invalid nonce.', 'new-user-approve'), array( 'status' => 403 ) );
		}

		$api_key = get_option( 'nua_api_key');
		$params = $request->get_json_params();
		$api_key = isset($params['api_key']) ? sanitize_text_field($params['api_key']) : '';
		update_option('nua_api_key', $api_key);

		return new WP_REST_Response(array(
			'status' => 'success',
			'message' => 'Zapier API has been updated successfully.',
		), 200);
	}

	public function get_all_statuses_users( $request ) {

		// Nonce Verification
		$nonce = $request->get_header('X-WP-Nonce');
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'rest_forbidden', __('Invalid nonce.', 'new-user-approve'), array( 'status' => 403 ) );
		}

		$filter_by = $request->get_param( 'filter_by' );
		$filter_by = !empty($filter_by) ? $filter_by : '30 days ago';
		$number_limit = apply_filters('analytics_users_limit', -1);
		$results = $this->nua_users_filter($filter_by, $number_limit);
		$pending = 0;
		$approved = 0;
		$denied  = 0;
		if ( !empty( $results ) ) {
			foreach ( $results as  $user) {
				$user_status = pw_new_user_approve()->get_user_status( $user->ID );
				switch ($user_status) {

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
			$total = absint($pending) + absint($approved) + absint($denied);
			$users = array(
				'total' => $total,
				'pending' => $pending,
				'approved' => $approved,
				'denied' => $denied
			);
			return new WP_REST_Response( $users, 200 );
		} else {
			return array(
				'total' => 0,
				'pending' => 0,
				'approved' => 0,
				'denied' => 0
			);
		}
	}

	public function nua_users_filter( $filter_by = '', $number_limit = '' ) {

		$date_query = array();
		switch ($filter_by) {
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

		
		$args = array(
			'meta_query' => array(
				array(
					'key'     => 'pw_user_status',
					'value'   => '',
					'compare' => '!='
				),
			),
			'date_query' => $date_query,
			'number'     => $number_limit,
			'orderby'    => 'user_registered',
			'order'      => 'DESC',
		);
		$results = new WP_User_Query( $args );

		if ( !empty( $results->get_results() ) ) {

			return $results->get_results();
		
		} else {
			return array();
		}
	}

	public function timeAgo( $datetime ) {
		$now = new DateTime();
		$ago = new DateTime($datetime);
		$diff = $now->diff($ago);
	
		// Compute weeks separately
		$weeks = floor($diff->d / 7);
		$diff->d -= $weeks * 7;
	
		$string = array(
			'y' => 'year',
			'm' => 'month',
			'w' => 'week',   // Handle weeks manually
			'd' => 'day',
			'h' => 'hour',
			'i' => 'minute',
			's' => 'second',
		);
	
		$result = array();
	
		foreach ($string as $k => $v) {
			if ($k === 'w' && $weeks) {
				$result[ $k ] = $weeks . ' ' . $v . ( $weeks > 1 ? 's' : '' );
			} elseif ($k !== 'w' && $diff->$k) {
				$result[ $k ] = $diff->$k . ' ' . $v . ( $diff->$k > 1 ? 's' : '' );
			}
		}
	
		$result = array_slice($result, 0, 1);
		return $result ? implode(', ', $result) . ' ago' : 'just now';
	}

	// users api permission callback
	public function nua_users_api_permission_callback( $request ) {

		$nonce = $request->get_header('X-WP-Nonce');

		$current_user = wp_get_current_user();
		$cap = apply_filters('new_user_approve_min_users_api_cap', 'edit_users');
		
		if (!is_user_logged_in()) {
			return new WP_Error('rest_forbidden', __('Non-logged-in users do not have permission to access this endpoint.', 'new-user-approve'), array( 'status' => 403 ));
		}

		if ( !in_array('administrator', $current_user->roles) && !current_user_can($cap)) {
			return new WP_Error('rest_forbidden', __('You do not have permission to access this endpoint.', 'new-user-approve'), array( 'status' => 403 ));
		}

		$permission = apply_filters('users_api_permission', true, $request);
		return $permission;
	}
}
// phpcs:ignore
function users_api() {
	return Users_API::instance();
}

users_api();
