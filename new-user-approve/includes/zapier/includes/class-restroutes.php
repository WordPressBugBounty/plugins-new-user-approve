<?php
/**
 * REST API routes for Zapier integration.
 *
 * @package New_User_Approve
 */

namespace Premium_NewUserApproveZapier;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

use WP_REST_Server;
if ( ! class_exists( 'RestRoutes' ) ) {
	/**
	 * REST API routes for Zapier integration.
	 */
	class RestRoutes {

		/**
		 * Singleton instance.
		 *
		 * @var RestRoutes
		 */
		private static $instance;

		/**
		 * Get singleton instance.
		 *
		 * @version 1.0
		 * @since 2.1
		 * @return RestRoutes
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
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		}

		/**
		 * Register REST API routes.
		 *
		 * @version 1.0
		 * @since 2.1
		 */
		public function register_routes() {
			register_rest_route(
				'nua-zapier',
				'/v1/auth',
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'authenticate' ),
					'permission_callback' => '__return_true',
				)
			);

			register_rest_route(
				'nua-zapier',
				'/v1/user-approved',
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'user_approved' ),
					'permission_callback' => '__return_true',
				)
			);

			register_rest_route(
				'nua-zapier',
				'/v1/user-denied',
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'user_denied' ),
					'permission_callback' => '__return_true',
				)
			);

			register_rest_route(
				'nua-zapier',
				'/v1/user-invcode',
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'user_invcode' ),
					'permission_callback' => '__return_true',
				)
			);

			register_rest_route(
				'nua-zapier',
				'/v1/user-pending',
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'user_pending' ),
					'permission_callback' => '__return_true',
				)
			);

			register_rest_route(
				'nua-zapier',
				'/v1/user-whitelisted',
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'user_whitelisted' ),
					'permission_callback' => '__return_true',
				)
			);

			register_rest_route(
				'nua-zapier',
				'/v1/user-approved-via-role',
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'user_approved_via_role' ),
					'permission_callback' => '__return_true',
				)
			);
		}

		/**
		 * Get API key.
		 *
		 * @version 1.0
		 * @since 2.1
		 * @return string
		 */
		public static function api_key() {
			return get_option( 'nua_api_key' );
		}

		/**
		 * Authenticate API request.
		 *
		 * @param WP_REST_Request $request The request object.
		 * @version 1.0
		 * @since 2.1
		 * @return WP_REST_Response|WP_Error
		 */
		public function authenticate( $request ) {
			$api_key = $request->get_param( 'api_key' );

			if ( null === $api_key ) {
				return new \WP_Error(
					400,
					__( 'Required Parameter Missing', 'new-user-approve' ),
					'api_key required'
				);
			}

			if ( $api_key === $this->api_key() ) {
				return new \WP_REST_Response( true, 200 );
			}

			// else invalid key.
			return new \WP_Error(
				400,
				__( 'Invalid API Key', 'new-user-approve' ),
				'invalid api_key'
			);
		}

		/**
		 * Get whitelisted users data.
		 *
		 * @param WP_REST_Request $request The request object.
		 * @return array|WP_Error
		 */
		public function user_whitelisted( $request ) {
			$api_key = $request->get_param( 'api_key' );

			if ( null === $api_key ) {
				return new \WP_Error(
					400,
					__( 'Required Parameter Missing', 'new-user-approve' ),
					'api_key required'
				);
			}

			if ( $api_key !== $this->api_key() ) {
				return new \WP_Error(
					401,
					__( 'Invalid API Key', 'new-user-approve' ),
					'invalid api_key'
				);
			}

			return $this->user_data( 'nua_user_whitelisted' );
		}


		/**
		 * Get pending users data.
		 *
		 * @param WP_REST_Request $request The request object.
		 * @return array|WP_Error
		 */
		public function user_pending( $request ) {
			$api_key = $request->get_param( 'api_key' );

			if ( null === $api_key ) {
				return new \WP_Error(
					400,
					__( 'Required Parameter Missing', 'new-user-approve' ),
					'api_key required'
				);
			}

			if ( $api_key !== $this->api_key() ) {
				return new \WP_Error(
					401,
					__( 'Invalid API Key', 'new-user-approve' ),
					'invalid api_key'
				);
			}

			return $this->user_data( 'nua_user_pending' );
		}


		/**
		 * Get invitation code users data.
		 *
		 * @param WP_REST_Request $request The request object.
		 * @return array|WP_Error
		 */
		public function user_invcode( $request ) {
			$api_key = $request->get_param( 'api_key' );

			if ( null === $api_key ) {
				return new \WP_Error(
					400,
					__( 'Required Parameter Missing', 'new-user-approve' ),
					'api_key required'
				);
			}

			if ( $api_key !== $this->api_key() ) {
				return new \WP_Error(
					401,
					__( 'Invalid API Key', 'new-user-approve' ),
					'invalid api_key'
				);
			}

			return $this->user_data( 'nua_user_invcode' );
		}

		/**
		 * Get approved users data.
		 *
		 * @param WP_REST_Request $request The request object.
		 * @return array|WP_Error
		 */
		public function user_approved( $request ) {
			$api_key = $request->get_param( 'api_key' );

			if ( null === $api_key ) {
				return new \WP_Error(
					400,
					__( 'Required Parameter Missing', 'new-user-approve' ),
					'api_key required'
				);
			}

			if ( $api_key !== $this->api_key() ) {
				return new \WP_Error(
					401,
					__( 'Invalid API Key', 'new-user-approve' ),
					'invalid api_key'
				);
			}

			return $this->user_data( 'nua_user_approved' );
		}


		/**
		 * Get role-approved users data.
		 *
		 * @param WP_REST_Request $request The request object.
		 * @return array|WP_Error
		 */
		public function user_approved_via_role( $request ) {
			$api_key = $request->get_param( 'api_key' );

			if ( null === $api_key ) {
				return new \WP_Error(
					400,
					__( 'Required Parameter Missing', 'new-user-approve' ),
					'api_key required'
				);
			}

			if ( $api_key !== $this->api_key() ) {
				return new \WP_Error(
					401,
					__( 'Invalid API Key', 'new-user-approve' ),
					'invalid api_key'
				);
			}

			return $this->user_data( 'nua_user_approved_via_role' );
		}


		/**
		 * Get denied users data.
		 *
		 * @param WP_REST_Request $request The request object.
		 * @return array|WP_Error
		 */
		public function user_denied( $request ) {
			$api_key = $request->get_param( 'api_key' );

			if ( null === $api_key ) {
				return new \WP_Error(
					400,
					__( 'Required Parameter Missing', 'new-user-approve' ),
					'api_key required'
				);
			}

			if ( $api_key !== $this->api_key() ) {
				return new \WP_Error(
					401,
					__( 'Invalid API Key', 'new-user-approve' ),
					'invalid api_key'
				);
			}

			return $this->user_data( 'nua_user_denied' );
		}


		/**
		 * Get user data by option name.
		 *
		 * @param string $option_name The option name.
		 * @return array|null
		 */
		public function user_data( $option_name ) {
			// data migrating, to make compatible with previous NUA version.
			if ( ! get_option( 'nua_zapier_option_status' ) ) {
				\Premium_NewUserApproveZapier\User::get_instance()->nua_zap_compatible_legacy_options();
				update_option(
					'nua_zapier_option_status',
					NUA_ZAPIER_OPTION_STATUS
				);
			}

			$user_data = premium_get_users_by_nua_zap( $option_name );

			if ( $user_data ) {
				$data = array();

				$time_key = 'nua_user_pending';

				if ( 'nua_user_approved' === $option_name ) {
					$time_key = 'approval_time';
				} elseif ( 'nua_user_denied' === $option_name ) {
					$time_key = 'denial_time';
				} elseif ( 'nua_user_invcode' === $option_name ) {
					$time_key = 'invitation_code';
				} elseif ( 'nua_user_whitelisted' === $option_name ) {
					$time_key = 'whitelisted_domain';
				} elseif ( 'nua_user_approved_via_role' === $option_name ) {
					$time_key = 'user_role';
				}

				foreach ( $user_data as $key => $value ) {
					$user_id = $value['user_id'];

					$user     = get_userdata( $user_id );
					$time_val = gmdate( DATE_ISO8601, $value['time'] );

					if ( 'nua_user_invcode' === $option_name ) {
						$inv_code_id = get_user_meta(
							$user->ID,
							'nua_invcode_used',
							true
						);
						$time_val    = get_the_title( $inv_code_id );
					} elseif ( 'nua_user_whitelisted' === $option_name ) {
						$time_val = get_user_meta(
							$user->ID,
							'nua_wl_domain_used',
							true
						);
					} elseif ( 'nua_user_approved_via_role' === $option_name ) {
						$time_val = get_user_meta(
							$user->ID,
							'nua_user_role_based_approved',
							true
						);
					}

					$data[] = array(
						'id'              => $value['id'],
						'user_login'      => $user->user_login,
						'user_nicename'   => $user->user_nicename,
						'user_email'      => $user->user_email,
						'user_registered' => gmdate(
							DATE_ISO8601,
							strtotime( $user->user_registered )
						),
						$time_key         => $time_val,
					);
					$data   = apply_filters(
						'nua_zapier_data_fields',
						$data,
						$user
					);
				}

				return apply_filters( "{$option_name}_zapier", $data );
			}
		}
		/**
		 * Check API permission.
		 *
		 * @param WP_REST_Request $request The request object.
		 * @return bool
		 */
		public function nua_zapier_permission_callback( $request ) {
			$api_key = $request->get_param( 'api_key' );

			if ( null === $api_key || $api_key !== $this->api_key() ) {
				return false;
			}

			return apply_filters( 'zapier_api_permission', true, $request );
		}
	}
}

\Premium_NewUserApproveZapier\RestRoutes::get_instance();
