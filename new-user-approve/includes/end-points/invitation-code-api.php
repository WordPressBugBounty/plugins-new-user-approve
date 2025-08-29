<?php


class Invitation_Code_API {


	public static $instance;

	private $screen_name = 'nua-invitation-code';
	public $code_post_type = 'invitation_code';
	public $usage_limit_key = '_nua_usage_limit';
	public $expiry_date_key = '_nua_code_expiry';
	public $status_key = '_nua_code_status';
	public $code_key = '_nua_code';
	public $total_code_key = '_total_nua_code';
	public $registered_users = '_registered_users';

	public static function instance() {
		if (!isset ( self::$instance ) ) {
			self::$instance = new Invitation_Code_API();
		}
		return self::$instance;
	}

	public function __construct() {

		add_action('rest_api_init', array( $this, 'register_user_routes' ) );
	}

	public function register_user_routes() {

		register_rest_route( 'nua-request', '/v1/save-invitation-codes', array(
			'methods'  => 'POST',
			'callback' => array( $this, 'save_invitation_codes' ),
			'permission_callback' => array( $this, 'nua_invitation_api_permission_callback' )
		) );

		register_rest_route( 'nua-request', '/v1/get-invitation-settings', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_invitation_settings' ),
			'permission_callback' => array( $this, 'nua_invitation_api_permission_callback' )
		) );
		
		register_rest_route( 'nua-request', '/v1/update-invitation-settings' , array(
			'methods'  => WP_REST_Server::EDITABLE,
			'callback' => array( $this, 'update_invitation_settings' ),
			'permission_callback' => array( $this, 'nua_invitation_api_permission_callback' )
		) );

		register_rest_route( 'nua-request', '/v1/get-nua-codes' , array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_nua_invite_codes' ),
			'permission_callback' => array( $this, 'nua_invitation_api_permission_callback' )
		) );

		register_rest_route( 'nua-request', '/v1/get-remaining-uses' , array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_remaining_uses' ),
			'permission_callback' => array( $this, 'nua_invitation_api_permission_callback' )
		) );

		register_rest_route( 'nua-request', '/v1/get-total-uses' , array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_total_uses' ),
			'permission_callback' => array( $this, 'nua_invitation_api_permission_callback' )
		) );

		register_rest_route( 'nua-request', '/v1/get-expiry' , array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_expiry' ),
			'permission_callback' => array( $this, 'nua_invitation_api_permission_callback' )
		) );

		register_rest_route( 'nua-request', '/v1/get-status' , array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_status' ),
			'permission_callback' => array( $this, 'nua_invitation_api_permission_callback' )
		) );

		register_rest_route( 'nua-request', '/v1/get-invited-users' , array(
			'methods'  => 'GET',
			'callback' => array( $this, 'get_invited_users' ),
			'permission_callback' => array( $this, 'nua_invitation_api_permission_callback' )
		) );

		register_rest_route( 'nua-request', '/v1/update-invitation-code', array(
			'methods'  => 'POST',
			'callback' => array( $this, 'update_invitation_code' ),
			'permission_callback' => array( $this, 'nua_invitation_api_permission_callback' )
		) );

		register_rest_route( 'nua-request', '/v1/delete-invCode', array(
			'methods'  => 'POST',
			'callback' => array( $this, 'delete_invCode' ),
			'permission_callback' => array( $this, 'nua_invitation_api_permission_callback' )
		) );
	}

	public function save_invitation_codes( $request ) {

		// Nonce verification
		$nonce = $request->get_header('X-WP-Nonce');
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'rest_forbidden', __('Invalid nonce.', 'new-user-approve'), array( 'status' => 403 ) );
		}
		$params = $request->get_json_params();
		
		$codes = isset( $request['codes'] ) && !empty( $request['codes'] ) ? explode("\n", $request['codes']) : '';
		$Status = isset($params['code_status']) ? sanitize_text_field($params['code_status']) : 'Active';
		
		if ( empty($codes) ) {
			return new WP_REST_Response(array(
				'status' => 'error',
				'message' => 'Code is empty.',
			), 200);
		}
			$usesLeft       = intval( $params['usageLeft'] ?? 0 );
			$usageLimit     = intval( $params['usage_limit'] ?? 0 );
			$expiry = sanitize_text_field( $request['expiry_date']);
			
			// date time formating
			$formated_date = str_replace('/', '-', $expiry);
			$expiry_timestamp = strtotime("$formated_date 23:59:59");
		
			$count = 0;
			$code_already_exists =array();
		foreach ($codes as $in_code) {

			if (empty(trim($in_code))) {
				continue;
			}

			if ( NUA_Invitation_Code()->invitation_code_already_exists( $in_code ) ) {
				$code_already_exists[] = $in_code;
				continue;
			}
			$my_post = array(
				'post_title'    =>sanitize_text_field(  $in_code ),
				'post_status'   => 'publish',
				'post_type'     => $this->code_post_type,
						
			);

			$post_code =wp_insert_post( $my_post );
			if (!empty($post_code)) {
				$added_post_ids[] = $post_code;
				do_action('nua_code_update_post', $post_code);
				update_post_meta($post_code, $this->code_key, sanitize_text_field(  $in_code ));
				update_post_meta( $post_code, $this->usage_limit_key, $usageLimit );
				update_post_meta( $post_code, $this->total_code_key, $usesLeft );
				update_post_meta($post_code, $this->expiry_date_key, $expiry_timestamp );
				update_post_meta($post_code, $this->status_key, $Status );  

			 $count++;

			}

		}

		if (!empty($count)) {
		
		  $inv_code_success_msg = ( $count > 1 ? 'Codes Have Been Added Successfully' : 'Code Has Been Added Successfully' );
		  $exists_code_notification = 0;
				
			if (!empty($code_already_exists)) {
					$inv_code_exist_msg =  count($code_already_exists) > 1 ? 'Codes Already Exist' : 'Code Already Exists';
					// translators: %s is for invitation code exists message
					$exists_code_notification = sprintf('%s ' . $inv_code_exist_msg, implode(', ', $code_already_exists));
			}
			$added_codes = array_filter(array_map('trim', $codes), function ( $code ) use ( $code_already_exists ) {
				return !in_array($code, $code_already_exists);
			});
			
				return new WP_REST_Response(array(
					'status' => 'success',
					'code_error' => $exists_code_notification,
					'codes' => $added_codes,
					'usage_limit'     => $usageLimit,
					'usageLeft'     => $usesLeft,
					'expiry_date' => $expiry,
					'code_status' => $Status,
					'code_id' =>$added_post_ids,
					// translators: %s is for invitation code success message
					'message' => sprintf(__('%s .', 'new-user-approve') , $inv_code_success_msg)
				), 200);
		} else if (empty($count) && !empty($code_already_exists) ) {
			$inv_code_exist_msg = count($code_already_exists) > 1 ? 'Codes Already Exist.' : 'Code Already Exists.';

			return new WP_REST_Response(array(
				'status' => 'error',
				// translators: %s is for invitation code exists message
				'message' => sprintf(__('%s .', 'new-user-approve') , $inv_code_exist_msg)
			), 404);
		} else {
			return new WP_REST_Response(array(
				'status' => 'error',
				// translators: %u is for invitation code not added message
				'message' => sprintf(__('%u Invitation Code Not Added.', 'new-user-approve'), $count)
			), 404);
		}
	}


	public function get_invitation_settings( $request ) {

		// Nonce verification
		$nonce = $request->get_header('X-WP-Nonce');
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'rest_forbidden', __('Invalid nonce.', 'new-user-approve'), array( 'status' => 403 ) );
		}

		$invitation_code_toggle = get_option('nua_free_invitation');
		$settings = array( 'invite_code_toggle' => $invitation_code_toggle );
		return array( 'nua_invitation_code_setting' => $settings );
	}

	// public function update_invitation_settings( $request ) {

	// 	// Nonce verification
	// 	$nonce = $request->get_header('X-WP-Nonce');
	// 	if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
	// 		return new WP_Error( 'rest_forbidden', __('Invalid nonce.', 'new-user-approve'), array( 'status' => 403 ) );
	// 	}

	// 	$params = $request->get_json_params();
	// 	$bool = isset($params['enable_invitation_code']) ? sanitize_text_field( $params['enable_invitation_code'] ) : false;
	// 	$bool = $bool == 1 || $bool == true ? 'enable' : '';
	// 	update_option('nua_free_invitation', $bool );
	// 	return array( 'settings' => $params );
	// }


	public function get_all_pages() {
		$pages = array();
		$all_pages = get_pages();
		foreach ($all_pages as $page) {
			$pages[ $page->post_name ] = array(
				'page_id' => $page->ID,
				'page_title' => $page->post_title
			);
		}
		return $pages;
	}

	public function get_all_invite_codes() {
		$codes = array();
		$all_codes = nua_invitation_code()->get_available_invitation_codes();
		if ( empty( $all_codes ) ) {
		   return array();
		}

		foreach ( $all_codes as $code ) {
		
			$invite_code = get_post_meta( $code->ID, $this->code_key, true );
			$codes[ $invite_code ] = array(
				'code_id' => $code->ID,
				'invitation_code' => $invite_code
			);

		}

		return $codes;
	}

	public function get_nua_invite_codes( $request ) {
		$codes = array();
	
		$page = $request->get_param('page') ? intval($request->get_param('page')) : 1;
		$limit = $request->get_param('limit') ? intval($request->get_param('limit')) : 10;
		$offset = ( $page - 1 ) * $limit;
		$search = $request->get_param( 'search' ) ? sanitize_text_field( $request->get_param( 'search' ) ) : '';
	
		$args = array(
			'post_type'      => 'invitation_code',
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'post_status'    => 'publish',
			's'              => $search, // WordPress handles search using 's' param
		);
	
		$query = new WP_Query($args);
		$all_codes = $query->get_posts();
	
		$total_found = $query->found_posts; // total for pagination
	
		foreach ($all_codes as $post) {
			$invite_code = get_post_meta($post->ID, $this->code_key, true);
			$uses_left = get_post_meta($post->ID, $this->usage_limit_key, true);
			$uses_remaining = get_post_meta($post->ID, $this->total_code_key, true);
	
			if (!empty($invite_code)) {
				$codes[] = array(
					'code_id'         => $post->ID,
					'invitation_code' => $invite_code,
					'uses_left'       => $uses_left,
					'usage_limit'     => $uses_remaining
				);
			}
		}
	
		return rest_ensure_response(array(
			'codes' => $codes,
			'total' => $total_found,
		));
	}

	public function get_remaining_uses() {
		$uses = array();
		$args = array(
			'post_type'      => 'invitation_code', // change this to your actual CPT if different
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		);

		$all_uses = get_posts($args);
		
		if (empty($all_uses)) {
			return array();
		}

		foreach ($all_uses as $post) {
			$uses_left = get_post_meta($post->ID, $this->usage_limit_key, true);
			$uses_remaining = get_post_meta($post->ID, $this->total_code_key, true);
			
			if (!empty($uses_left)) {
				$uses[] = array(
					'code_id'         => $post->ID,
					'uses_left' => $uses_left,
					'usage_limit' => $uses_remaining
				);
			}
		}
	
		return $uses;
	}

	public function get_total_uses() {
		$total = array();
		$args = array(
			'post_type'      => 'invitation_code', // change this to your actual CPT if different
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		);

		$total_uses = get_posts($args);
		
		if (empty($total_uses)) {
			return array();
		}

		foreach ($total_uses as $post) {
			$total_remaining = get_post_meta($post->ID, $this->total_code_key, true);
			
			if (!empty($total_remaining)) {
				$total[] = array(
					'code_id'         => $post->ID,
					'total_remaining' => $total_remaining
				);
			}
		}
	
		return $total;
	}

	public function get_status() {
		$status_info = array();
		$args = array(
			'post_type'      => 'invitation_code',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		);
		$get_posts = get_posts($args);
	
		if (empty($get_posts)) {
			return array();
		}

		foreach ($get_posts as $post) {
			$code_status = get_post_meta($post->ID, $this->status_key, true);

			if (!empty($code_status)) {
				$status_info[] = array(
					'code_id'      => $post->ID,
					'code_status' => $code_status
				);
			}
		}
		return $status_info;
	}

	public function get_expiry() {
		$expiry_info = array();
		$args = array(
			'post_type'      => 'invitation_code',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		);
	
		$get_posts = get_posts($args);
	
		if (empty($get_posts)) {
			return array();
		}
	
		foreach ($get_posts as $post) {
			$expiry_date = get_post_meta($post->ID, $this->expiry_date_key, true);
			$code_status = get_post_meta($post->ID, $this->status_key, true);
			$timezone = wp_timezone();
			$dateTime = false;
	
			if (is_numeric($expiry_date)) {
				// If expiry_date is a Unix timestamp
				$dateTime = new DateTime("@$expiry_date");
				$dateTime->setTimezone($timezone);
			} else {
				// If expiry_date is a string, try to parse it
				$dateTime = DateTime::createFromFormat('Y-m-d', $expiry_date, $timezone);
			}
	
			if ($dateTime) {
				// Format as Y-m-d
				$expiry_date_formatted = $dateTime->setTime(23, 59, 59)->format('Y-m-d');
				$expiry_info[] = array(
					'code_id'      => $post->ID,
					'expiry_data'  => $expiry_date_formatted,
					'code_status' => $code_status
				);
			}
		}   
	
		return $expiry_info;
	}

	public function get_invited_users() {
		$userData = [];
		$args = array(
			'post_type'      => 'invitation_code', // change this to your actual CPT if different
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		);

		$all_posts = get_posts($args);
		
		if (empty($all_posts)) {
			return [];
		}

		foreach ($all_posts as $post) {
			
			$registered_user  = get_post_meta($post->ID, $this->registered_users, true );

			if (!empty($registered_user)) {

				foreach ($registered_user as $userid) {
					$the_user = get_user_by( 'id', $userid); 
					if (!empty($the_user)) {
						$userData[] = [
							'code_id'    => $post->ID,
							'user_id'    => $userid,
							'user_link'  => get_edit_user_link($userid),
							'user_email' => $the_user->user_email,
							'user_name'  => $the_user->user_login,
							'empty_user' => '',
						];

				  } else {
					$userData[] = [
                        'user_id'    => $userid,
                        'user_link'  => '',
                        'user_email' => '',
                        'user_name'  => '',
                        'empty_user' => esc_html__('User Not Found', 'new-user-approve'),
                    ];
				  }
				}
			}
		}
		return $userData;
	}

	public function delete_invCode( $request ) {
		// Nonce verification
		$nonce = $request->get_header('X-WP-Nonce');
		if (!wp_verify_nonce($nonce, 'wp_rest')) {
			return new WP_Error('rest_forbidden', __('Invalid nonce.', 'new-user-approve'), array( 'status' => 403 ));
		}
		
		$params = $request->get_json_params();
		$code_id = $params['code_id'] ?? null;
		
		if (!$code_id || get_post_type($code_id) !== $this->code_post_type) {
			return new WP_Error('invalid_code', __('Invalid or missing invitation code.', 'new-user-approve'), array( 'status' => 400 ));
		}
	
		$deleted = wp_delete_post($code_id, true);
	
		if ($deleted) {
			return new WP_REST_Response(array(
				'status'  => 'Success',
				'message' => __('This invitation code has been deleted successfully.', 'new-user-approve'),
			), 200);
		}
	
		return new WP_Error('delete_failed', __('Failed to delete the invitation code.', 'new-user-approve'), array( 'status' => 500 ));
	}

	// Update invitation code
	public function update_invitation_code( $request ) {
		// Nonce verification
			$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Invalid nonce.', 'new-user-approve' ), array( 'status' => 403 ) );
		}

		$params         = $request->get_json_params();
		$codeId         = sanitize_text_field( $params['codeId'] ?? '' );
		$code           = sanitize_text_field( $params['editCode'] ?? '' );
		$usesLeft = intval($params['usesLeft'] ?? 0);
		$usageLimit = intval($params['usageLimit'] ?? 0);
		$expiryDate     = sanitize_text_field( $params['expiryDate'] ?? '' );
		$status         = sanitize_text_field( $params['status'] ?? '' );
		$formated_date  = str_replace( '/', '-', $expiryDate );
		$timestamp = strtotime($formated_date);
		$expiry_formatted = wp_date('Y-m-d', $timestamp);

		// var_dump('uses left' . $usesLeft);
		// var_dump('uses limit' . $usageLimit);
	

	// Validate required fields
		if ( empty( $code ) || $usageLimit < 1 || $usesLeft < 1 ) {
			return new WP_REST_Response( array(
				'status'  => 'error',
				'message' => __( 'Please fill in all required fields with valid values.', 'new-user-approve' ),
			), 422 );
		}


	//code already exisit
	$args = array(
		'post_type'      => $this->code_post_type,
		'posts_per_page' => 1,
		'post_status'    => 'publish',
		'meta_query'     => array(
			array(
				'key'     => $this->code_key,
				'value'   => $code,
				'compare' => '='
			)
		),
		'post__not_in' => array( intval( $codeId ) ) 
	);

	$existing_code = get_posts( $args );

		if ( ! empty( $existing_code ) ) {
			return new WP_REST_Response( array(
				'status'  => 'error',
				'message' => __( 'This invitation code already exists.', 'new-user-approve' ),
			), 409 );
		}

	// Update post meta
	update_post_meta( $codeId, $this->code_key, $code );
	update_post_meta( $codeId, $this->usage_limit_key, $usesLeft );
	update_post_meta( $codeId, $this->total_code_key, $usageLimit );
	update_post_meta( $codeId, $this->expiry_date_key, $expiry_formatted );
	update_post_meta( $codeId, $this->status_key, $status );

	return new WP_REST_Response( array(
		'status'  => 'success',
		'message' => __( 'Invitation code updated successfully.', 'new-user-approve' ),
	), 200 );
	}


	// Invitation Code API Permission Callback
	public function nua_invitation_api_permission_callback( $request ) {

		$current_user = wp_get_current_user();
		$cap = apply_filters('new_user_approve_invitation_api_cap', 'edit_users');
		
		if (!is_user_logged_in()) {
			return new WP_Error('rest_forbidden', __('Non-logged-in users do not have permission to access this endpoint.', 'new-user-approve'), array( 'status' => 403 ));
		}

		if ( !in_array('administrator', $current_user->roles) && !current_user_can($cap)) {
			return new WP_Error('rest_forbidden', __('You do not have permission to access this endpoint.', 'new-user-approve'), array( 'status' => 403 ));
		}

		$permission = apply_filters('invitation_api_permission', true, $request);
		return $permission;
	}
}

// phpcs:ignore
function invitation_code_API() {

	return Invitation_Code_API::instance();
}

invitation_code_API();
