<?php
/**
 * Invitation Code REST API endpoints.
 *
 * @package New_User_Approve
 */

/**
 * Class Invitation_Code_API
 *
 * Handles REST API routes for invitation codes.
 */
class Invitation_Code_API {

	/**
	 * The single instance of this class.
	 *
	 * @var Invitation_Code_API
	 */
	public static $instance;

	/**
	 * Screen name.
	 *
	 * @var string
	 */
	private $screen_name = 'nua-invitation-code';

	/**
	 * Custom post type name.
	 *
	 * @var string
	 */
	public $code_post_type = 'invitation_code';

	/**
	 * Meta key for usage limit.
	 *
	 * @var string
	 */
	public $usage_limit_key = '_nua_usage_limit';

	/**
	 * Meta key for expiry date.
	 *
	 * @var string
	 */
	public $expiry_date_key = '_nua_code_expiry';

	/**
	 * Meta key for status.
	 *
	 * @var string
	 */
	public $status_key = '_nua_code_status';

	/**
	 * Meta key for code.
	 *
	 * @var string
	 */
	public $code_key = '_nua_code';

	/**
	 * Meta key for total code.
	 *
	 * @var string
	 */
	public $total_code_key = '_total_nua_code';

	/**
	 * Meta key for registered users.
	 *
	 * @var string
	 */
	public $registered_users = '_registered_users';

	/**
	 * Returns the main instance.
	 *
	 * @return Invitation_Code_API
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new Invitation_Code_API();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_user_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_user_routes() {
		register_rest_route(
			'nua-request',
			'/v1/save-invitation-codes',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_invitation_codes' ),
				'permission_callback' => array(
					$this,
					'nua_invitation_api_permission_callback',
				),
			)
		);

		register_rest_route(
			'nua-request',
			'/v1/get-invitation-settings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_invitation_settings' ),
				'permission_callback' => array(
					$this,
					'nua_invitation_api_permission_callback',
				),
			)
		);

		register_rest_route(
			'nua-request',
			'/v1/update-invitation-settings',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_invitation_settings' ),
				'permission_callback' => array(
					$this,
					'nua_invitation_api_permission_callback',
				),
			)
		);

		register_rest_route(
			'nua-request',
			'/v1/get-nua-codes',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_nua_invite_codes' ),
				'permission_callback' => array(
					$this,
					'nua_invitation_api_permission_callback',
				),
			)
		);

		register_rest_route(
			'nua-request',
			'/v1/get-remaining-uses',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_remaining_uses' ),
				'permission_callback' => array(
					$this,
					'nua_invitation_api_permission_callback',
				),
			)
		);

		register_rest_route(
			'nua-request',
			'/v1/get-total-uses',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_total_uses' ),
				'permission_callback' => array(
					$this,
					'nua_invitation_api_permission_callback',
				),
			)
		);

		register_rest_route(
			'nua-request',
			'/v1/get-expiry',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_expiry' ),
				'permission_callback' => array(
					$this,
					'nua_invitation_api_permission_callback',
				),
			)
		);

		register_rest_route(
			'nua-request',
			'/v1/get-status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array(
					$this,
					'nua_invitation_api_permission_callback',
				),
			)
		);

		register_rest_route(
			'nua-request',
			'/v1/get-invited-users',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_invited_users' ),
				'permission_callback' => array(
					$this,
					'nua_invitation_api_permission_callback',
				),
			)
		);

		register_rest_route(
			'nua-request',
			'/v1/update-invitation-code',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_invitation_code' ),
				'permission_callback' => array(
					$this,
					'nua_invitation_api_permission_callback',
				),
			)
		);

		register_rest_route(
			'nua-request',
			'/v1/delete-invCode',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'delete_inv_code' ),
				'permission_callback' => array(
					$this,
					'nua_invitation_api_permission_callback',
				),
			)
		);
	}

	/**
	 * Save invitation codes.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_invitation_codes( $request ) {
		// Nonce verification.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid nonce.', 'new-user-approve' ),
				array( 'status' => 403 )
			);
		}
		$params = $request->get_json_params();

		$codes  =
			isset( $request['codes'] ) && ! empty( $request['codes'] )
				? explode( "\n", $request['codes'] )
				: '';
		$status = isset( $params['code_status'] )
			? sanitize_text_field( $params['code_status'] )
			: 'Active';

		if ( empty( $codes ) ) {
			return new WP_REST_Response(
				array(
					'status'  => 'error',
					'message' => 'Code is empty.',
				),
				200
			);
		}
		$uses_left   = intval( $params['usageLeft'] ?? 0 );
		$usage_limit = intval( $params['usage_limit'] ?? 0 );
		$expiry      = sanitize_text_field( $request['expiry_date'] );

		// Date time formatting.
		$formated_date    = str_replace( '/', '-', $expiry );
		$expiry_timestamp = strtotime( "$formated_date 23:59:59" );

		$count               = 0;
		$code_already_exists = array();
		foreach ( $codes as $in_code ) {
			if ( empty( trim( $in_code ) ) ) {
				continue;
			}

			if (
				NUA_Invitation_Code()->invitation_code_already_exists( $in_code )
			) {
				$code_already_exists[] = $in_code;
				continue;
			}
			$my_post = array(
				'post_title'  => sanitize_text_field( $in_code ),
				'post_status' => 'publish',
				'post_type'   => $this->code_post_type,
			);

			$post_code = wp_insert_post( $my_post );
			if ( ! empty( $post_code ) ) {
				$added_post_ids[] = $post_code;
				do_action( 'nua_code_update_post', $post_code );
				update_post_meta(
					$post_code,
					$this->code_key,
					sanitize_text_field( $in_code )
				);
				update_post_meta(
					$post_code,
					$this->usage_limit_key,
					$usage_limit
				);
				update_post_meta( $post_code, $this->total_code_key, $uses_left );
				update_post_meta(
					$post_code,
					$this->expiry_date_key,
					$expiry_timestamp
				);
				update_post_meta( $post_code, $this->status_key, $status );

				++$count;
			}
		}

		if ( ! empty( $count ) ) {
			$inv_code_success_msg     =
				$count > 1
					? 'Codes Have Been Added Successfully'
					: 'Code Has Been Added Successfully';
			$exists_code_notification = 0;

			if ( ! empty( $code_already_exists ) ) {
				$inv_code_exist_msg =
					count( $code_already_exists ) > 1
						? 'Codes Already Exist'
						: 'Code Already Exists';
				// translators: %s is the invitation code exists message.
				$exists_code_notification = sprintf(
					'%s ' . $inv_code_exist_msg,
					implode( ', ', $code_already_exists )
				);
			}
			$added_codes = array_filter(
				array_map( 'trim', $codes ),
				function (
					$code
				) use ( $code_already_exists ) {
					return ! in_array( $code, $code_already_exists, true );
				}
			);

			return new WP_REST_Response(
				array(
					'status'      => 'success',
					'code_error'  => $exists_code_notification,
					'codes'       => $added_codes,
					'usage_limit' => $usage_limit,
					'usageLeft'   => $uses_left,
					'expiry_date' => $expiry,
					'code_status' => $status,
					'code_id'     => $added_post_ids,
					'message'     => sprintf(
						// translators: %s is the invitation code success message.
						__( '%s .', 'new-user-approve' ),
						$inv_code_success_msg
					),
				),
				200
			);
		} elseif ( empty( $count ) && ! empty( $code_already_exists ) ) {
			$inv_code_exist_msg =
				count( $code_already_exists ) > 1
					? 'Codes Already Exist.'
					: 'Code Already Exists.';

			return new WP_REST_Response(
				array(
					'status'  => 'error',
					'message' => sprintf(
						// translators: %s is the invitation code exists message.
						__( '%s .', 'new-user-approve' ),
						$inv_code_exist_msg
					),
				),
				404
			);
		} else {
			return new WP_REST_Response(
				array(
					'status'  => 'error',
					'message' => sprintf(
						// translators: %u is the number of invitation codes not added.
						__( '%u Invitation Code Not Added.', 'new-user-approve' ),
						$count
					),
				),
				404
			);
		}
	}

	/**
	 * Get invitation code settings.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return array|WP_Error
	 */
	public function get_invitation_settings( $request ) {
		// Nonce verification.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid nonce.', 'new-user-approve' ),
				array( 'status' => 403 )
			);
		}

		$invitation_code_toggle = get_option( 'nua_free_invitation' );
		$settings               = array( 'invite_code_toggle' => $invitation_code_toggle );
		return array( 'nua_invitation_code_setting' => $settings );
	}

	/**
	 * Get all pages.
	 *
	 * @return array
	 */
	public function get_all_pages() {
		$pages     = array();
		$all_pages = get_pages();
		foreach ( $all_pages as $page ) {
			$pages[ $page->post_name ] = array(
				'page_id'    => $page->ID,
				'page_title' => $page->post_title,
			);
		}
		return $pages;
	}

	/**
	 * Get all invite codes.
	 *
	 * @return array
	 */
	public function get_all_invite_codes() {
		$codes     = array();
		$all_codes = nua_invitation_code()->get_available_invitation_codes();
		if ( empty( $all_codes ) ) {
			return array();
		}

		foreach ( $all_codes as $code ) {
			$invite_code           = get_post_meta( $code->ID, $this->code_key, true );
			$codes[ $invite_code ] = array(
				'code_id'         => $code->ID,
				'invitation_code' => $invite_code,
			);
		}

		return $codes;
	}

	/**
	 * Get paginated NUA invite codes.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response
	 */
	public function get_nua_invite_codes( $request ) {
		$codes = array();

		$page   = $request->get_param( 'page' )
			? intval( $request->get_param( 'page' ) )
			: 1;
		$limit  = $request->get_param( 'limit' )
			? intval( $request->get_param( 'limit' ) )
			: 10;
		$offset = ( $page - 1 ) * $limit;
		$search = $request->get_param( 'search' )
			? sanitize_text_field( $request->get_param( 'search' ) )
			: '';

		$args = array(
			'post_type'      => 'invitation_code',
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'post_status'    => 'publish',
			's'              => $search, // WordPress handles search using 's' param.
		);

		$query     = new WP_Query( $args );
		$all_codes = $query->get_posts();

		$total_found = $query->found_posts; // Total for pagination.

		foreach ( $all_codes as $post ) {
			$invite_code    = get_post_meta( $post->ID, $this->code_key, true );
			$uses_left      = get_post_meta( $post->ID, $this->usage_limit_key, true );
			$uses_remaining = get_post_meta(
				$post->ID,
				$this->total_code_key,
				true
			);

			if ( ! empty( $invite_code ) ) {
				$codes[] = array(
					'code_id'         => $post->ID,
					'invitation_code' => $invite_code,
					'uses_left'       => $uses_left,
					'usage_limit'     => $uses_remaining,
				);
			}
		}

		return rest_ensure_response(
			array(
				'codes' => $codes,
				'total' => $total_found,
			)
		);
	}

	/**
	 * Get remaining uses for all invitation codes.
	 *
	 * @return array
	 */
	public function get_remaining_uses() {
		$uses = array();
		$args = array(
			'post_type'      => 'invitation_code',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		);

		$all_uses = get_posts( $args );

		if ( empty( $all_uses ) ) {
			return array();
		}

		foreach ( $all_uses as $post ) {
			$uses_left      = get_post_meta( $post->ID, $this->usage_limit_key, true );
			$uses_remaining = get_post_meta(
				$post->ID,
				$this->total_code_key,
				true
			);

			if ( ! empty( $uses_left ) ) {
				$uses[] = array(
					'code_id'     => $post->ID,
					'uses_left'   => $uses_left,
					'usage_limit' => $uses_remaining,
				);
			}
		}

		return $uses;
	}

	/**
	 * Get total uses for all invitation codes.
	 *
	 * @return array
	 */
	public function get_total_uses() {
		$total = array();
		$args  = array(
			'post_type'      => 'invitation_code',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		);

		$total_uses = get_posts( $args );

		if ( empty( $total_uses ) ) {
			return array();
		}

		foreach ( $total_uses as $post ) {
			$total_remaining = get_post_meta(
				$post->ID,
				$this->total_code_key,
				true
			);

			if ( ! empty( $total_remaining ) ) {
				$total[] = array(
					'code_id'         => $post->ID,
					'total_remaining' => $total_remaining,
				);
			}
		}

		return $total;
	}

	/**
	 * Get status for all invitation codes.
	 *
	 * @return array
	 */
	public function get_status() {
		$status_info = array();
		$args        = array(
			'post_type'      => 'invitation_code',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		);
		$get_posts   = get_posts( $args );

		if ( empty( $get_posts ) ) {
			return array();
		}

		foreach ( $get_posts as $post ) {
			$code_status = get_post_meta( $post->ID, $this->status_key, true );

			if ( ! empty( $code_status ) ) {
				$status_info[] = array(
					'code_id'     => $post->ID,
					'code_status' => $code_status,
				);
			}
		}
		return $status_info;
	}

	/**
	 * Get expiry information for all invitation codes.
	 *
	 * @return array
	 */
	public function get_expiry() {
		$expiry_info = array();
		$args        = array(
			'post_type'      => 'invitation_code',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		);

		$get_posts = get_posts( $args );

		if ( empty( $get_posts ) ) {
			return array();
		}

		foreach ( $get_posts as $post ) {
			$expiry_date = get_post_meta(
				$post->ID,
				$this->expiry_date_key,
				true
			);
			$code_status = get_post_meta( $post->ID, $this->status_key, true );
			$timezone    = wp_timezone();
			$date_time   = false;

			if ( is_numeric( $expiry_date ) ) {
				// If expiry_date is a Unix timestamp.
				$date_time = new DateTime( "@$expiry_date" );
				$date_time->setTimezone( $timezone );
			} else {
				// If expiry_date is a string, try to parse it.
				$date_time = DateTime::createFromFormat(
					'Y-m-d',
					$expiry_date,
					$timezone
				);
			}

			if ( $date_time ) {
				// Format as Y-m-d.
				$expiry_date_formatted = $date_time
					->setTime( 23, 59, 59 )
					->format( 'Y-m-d' );
				$expiry_info[]         = array(
					'code_id'     => $post->ID,
					'expiry_data' => $expiry_date_formatted,
					'code_status' => $code_status,
				);
			}
		}

		return $expiry_info;
	}

	/**
	 * Get all invited users for each invitation code.
	 *
	 * @return array
	 */
	public function get_invited_users() {
		$user_data = array();
		$args      = array(
			'post_type'      => 'invitation_code',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		);

		$all_posts = get_posts( $args );

		if ( empty( $all_posts ) ) {
			return array();
		}

		foreach ( $all_posts as $post ) {
			$registered_user = get_post_meta(
				$post->ID,
				$this->registered_users,
				true
			);

			if ( ! empty( $registered_user ) ) {
				foreach ( $registered_user as $userid ) {
					$the_user = get_user_by( 'id', $userid );
					if ( ! empty( $the_user ) ) {
						$user_data[] = array(
							'code_id'    => $post->ID,
							'user_id'    => $userid,
							'user_link'  => get_edit_user_link( $userid ),
							'user_email' => $the_user->user_email,
							'user_name'  => $the_user->user_login,
							'empty_user' => '',
						);
					} else {
						$user_data[] = array(
							'user_id'    => $userid,
							'user_link'  => '',
							'user_email' => '',
							'user_name'  => '',
							'empty_user' => esc_html__(
								'User Not Found',
								'new-user-approve'
							),
						);
					}
				}
			}
		}
		return $user_data;
	}

	/**
	 * Delete invitation codes.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_inv_code( $request ) {
		// Nonce verification.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid nonce.', 'new-user-approve' ),
				array( 'status' => 403 )
			);
		}

		$params   = $request->get_json_params();
		$code_ids = array_map( 'intval', (array) $params['code_ids'] );

		if ( empty( $code_ids ) ) {
			return new WP_Error(
				'no_ids_provided',
				__( 'No code IDs provided.', 'new-user-approve' ),
				array( 'status' => 400 )
			);
		}

		$deleted_count = 0;

		foreach ( $code_ids as $code_id ) {
			if ( get_post_type( $code_id ) === $this->code_post_type ) {
				$deleted = wp_delete_post( $code_id, true );
				if ( $deleted ) {
					++$deleted_count;
				}
			}
		}

		if ( $deleted_count > 0 ) {
			return new WP_REST_Response(
				array(
					'status'  => 'Success',
					'message' => sprintf(
						// translators: %d is the number of deleted invitation codes.
						__(
							'%d invitation code(s) deleted successfully.',
							'new-user-approve'
						),
						$deleted_count
					),
				),
				200
			);
		}

		return new WP_Error(
			'delete_failed',
			__( 'Failed to delete the invitation code(s).', 'new-user-approve' ),
			array( 'status' => 500 )
		);
	}

	/**
	 * Update an invitation code.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_invitation_code( $request ) {
		// Nonce verification.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid nonce.', 'new-user-approve' ),
				array( 'status' => 403 )
			);
		}

		$params           = $request->get_json_params();
		$code_id          = sanitize_text_field( $params['codeId'] ?? '' );
		$code             = sanitize_text_field( $params['editCode'] ?? '' );
		$uses_left        = intval( $params['usesLeft'] ?? 0 );
		$usage_limit      = intval( $params['usageLimit'] ?? 0 );
		$expiry_date      = sanitize_text_field( $params['expiryDate'] ?? '' );
		$status           = sanitize_text_field( $params['status'] ?? '' );
		$formated_date    = str_replace( '/', '-', $expiry_date );
		$timestamp        = strtotime( $formated_date );
		$expiry_formatted = wp_date( 'Y-m-d', $timestamp );

		// Validate required fields.
		if ( empty( $code ) || $usage_limit < 1 || $uses_left < 1 ) {
			return new WP_REST_Response(
				array(
					'status'  => 'error',
					'message' => __(
						'Please fill in all required fields with valid values.',
						'new-user-approve'
					),
				),
				422
			);
		}

		// Check if code already exists.
		$args = array(
			'post_type'      => $this->code_post_type,
			'posts_per_page' => 1,
			'post_status'    => 'publish',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => $this->code_key,
					'value'   => $code,
					'compare' => '=',
				),
			),
			'post__not_in'   => array( intval( $code_id ) ),
		);

		$existing_code = get_posts( $args );

		if ( ! empty( $existing_code ) ) {
			return new WP_REST_Response(
				array(
					'status'  => 'error',
					'message' => __(
						'This invitation code already exists.',
						'new-user-approve'
					),
				),
				409
			);
		}

		// Update post meta.
		update_post_meta( $code_id, $this->code_key, $code );
		update_post_meta( $code_id, $this->usage_limit_key, $uses_left );
		update_post_meta( $code_id, $this->total_code_key, $usage_limit );
		update_post_meta( $code_id, $this->expiry_date_key, $expiry_formatted );
		update_post_meta( $code_id, $this->status_key, $status );

		return new WP_REST_Response(
			array(
				'status'  => 'success',
				'message' => __(
					'Invitation code updated successfully.',
					'new-user-approve'
				),
			),
			200
		);
	}

	/**
	 * Permission callback for the invitation code API.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return bool|WP_Error
	 */
	public function nua_invitation_api_permission_callback( $request ) {
		$current_user = wp_get_current_user();
		$cap          = apply_filters(
			'new_user_approve_invitation_api_cap',
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

		$permission = apply_filters(
			'invitation_api_permission',
			true,
			$request
		);
		return $permission;
	}
}

// phpcs:ignore
function invitation_code_API() {
	return Invitation_Code_API::instance();
}

invitation_code_API();
