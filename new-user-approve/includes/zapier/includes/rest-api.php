<?php

namespace NewUserApproveZapier;

use WP_REST_Server;

class RestRoutes {

	private static $_instance;

	/**
	 * @version 1.0
	 * @since   2.1
	 */
	public static function get_instance() {
		if (self::$_instance == null ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * @version 1.0
	 * @since   2.1
	 */
	public function __construct() {
		add_action('rest_api_init', array( $this, 'register_routes' ));
	}

	/**
	 * @version 1.0
	 * @since   2.1
	 */
	public function register_routes() {
		register_rest_route(
			'nua-zapier', '/v1/auth', array(
				'methods'  => WP_REST_Server::EDITABLE,
				'callback' => array( $this, 'authenticate' ),
				'permission_callback' => '__return_true'
			) 
		);

		register_rest_route(
			'nua-zapier', '/v1/user-approved', array(
				'methods'  => WP_REST_Server::EDITABLE,
				'callback' => array( $this, 'user_approved' ),
				'permission_callback' => '__return_true'
			) 
		);

		register_rest_route(
			'nua-zapier', '/v1/user-denied', array(
				'methods'  => WP_REST_Server::EDITABLE,
				'callback' => array( $this, 'user_denied' ),
				'permission_callback' => '__return_true'
			) 
		);
		
		register_rest_route(
			'nua-zapier', '/v1/user-pending', array(
				'methods'  => WP_REST_Server::EDITABLE,
				'callback' => array( $this, 'user_pending' ),
				'permission_callback' => '__return_true'
			) 
		);
	}

	/**
	 * @version 1.0
	 * @since   2.1
	 */
	public static function api_key() {
		return get_option('nua_api_key');
	}

	/**
	 * @version 1.0
	 * @since   2.1
	 */
	public function authenticate( $request ) {
		$api_key = $request->get_param('api_key');

		if ($api_key == $this->api_key() ) {
			return new \WP_REST_Response(true, 200);
		}

		if ($api_key == null ) {
			return new \WP_Error(400, __('Required Parameter Missing', 'new-user-approve'), 'api_key required');
		}

		if ($api_key != $this->api_key() ) {
			return new \WP_Error(400, __('Invalid API Key', 'new-user-approve'), 'invalid api_key');
		}
	}

	public function user_pending( $request ) {
		$api_key = $request->get_param('api_key');

		if ($api_key == null ) {
			return new \WP_Error(400, __('Required Parameter Missing', 'new-user-approve'), 'api_key required');
		}

		if ($api_key != $this->api_key() ) {
			return new \WP_Error(400, __('Invalid API Key', 'new-user-approve'), 'invalid api_key');
		}

		if ($api_key == $this->api_key() ) {
			return $this->user_data('nua_user_pending');
		}
	}

	public function user_approved( $request ) {
		$api_key = $request->get_param('api_key');

		if ($api_key == null ) {
			return new \WP_Error(400, __('Required Parameter Missing', 'new-user-approve'), 'api_key required');
		}

		if ($api_key != $this->api_key() ) {
			return new \WP_Error(400, __('Invalid API Key', 'new-user-approve'), 'invalid api_key');
		}

		if ($api_key == $this->api_key() ) {
			return $this->user_data('nua_user_approved');
		}
	}

	public function user_denied( $request ) {
		$api_key = $request->get_param('api_key');

		if ($api_key == null ) {
			return new \WP_Error(400, __('Required Parameter Missing', 'new-user-approve'), 'api_key required');
		}

		if ($api_key != $this->api_key() ) {
			return new \WP_Error(400, __('Invalid API Key', 'new-user-approve'), 'invalid api_key');
		}

		if ($api_key == $this->api_key() ) {
			return $this->user_data('nua_user_denied');
		}
	}

	public function user_data( $option_name ) {
		  // data migrating,  to make compatible with previous NUA version
		if (!get_option('nua_zapier_option_status') ) {
			\NewUserApproveZapier\User::get_instance()->nua_zap_compatible_legacy_options();
			update_option('nua_zapier_option_status', NUA_ZAPIER_OPTION_STATUS);
		}

		$user_data = get_users_by_nua_zap($option_name);

		if ($user_data ) {
			$data = array();

			$time_key = 'nua_user_pending';

			if ($option_name == 'nua_user_approved') {
				$time_key = 'approval_time';
			} else if ($option_name == 'nua_user_denied') {
				$time_key = 'denial_time';
			} 

			foreach ( $user_data as $key => $value ) {
				$user_id = $value['user_id'];

				$user = get_userdata($user_id);
				$time_val = gmdate(DATE_ISO8601, $value['created_time']);

				$data[] = array(
					'id'                =>  $value['id'],
					'user_login'        =>  $user->user_login,
					'user_nicename'     =>  $user->user_nicename,
					'user_email'        =>  $user->user_email,
					'user_registered'   =>  gmdate(DATE_ISO8601, strtotime($user->user_registered)),
					$time_key           =>  $time_val
				);
				$data=apply_filters('nua_zapier_data_fields', $data, $user);
			}

			return apply_filters("{$option_name}_zapier", $data);
		}
	}
}

\NewUserApproveZapier\RestRoutes::get_instance();
