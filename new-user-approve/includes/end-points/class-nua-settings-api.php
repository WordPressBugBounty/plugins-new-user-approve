<?php
/**
 * Settings API endpoint for the New User Approve plugin.
 *
 * @package New_User_Approve
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Handles REST API endpoints for plugin settings.
 */
class Nua_Settings_API {

	/**
	 * Singleton instance.
	 *
	 * @var Nua_Settings_API
	 */
	public static $instance;

	/**
	 * Option group name.
	 *
	 * @var string
	 */
	private $option_group = 'new_user_approve_options_group';

	/**
	 * Option page slug.
	 *
	 * @var string
	 */
	private $option_page = 'new_user_approve';

	/**
	 * Admin screen name.
	 *
	 * @var string
	 */
	private $screen_name = 'new-user-approve-admin';

	/**
	 * Option key for stored settings.
	 *
	 * @var string
	 */
	public $option_key = 'new_user_approve_options';

	/**
	 * Get or create the singleton instance.
	 *
	 * @return Nua_Settings_API
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new Nua_Settings_API();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Registers REST API hooks.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_user_routes' ) );
	}

	/**
	 * Register REST API routes for settings.
	 *
	 * @return void
	 */
	public function register_user_routes() {
		register_rest_route(
			'nua-request',
			'/v1/general-settings',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'general_settings' ),
				'permission_callback' => array(
					$this,
					'nua_settings_api_permission_callback',
				),
				'args'                => array(
					'method' => array(
						'required'          => true,
						'validate_callback' => function ( $param, $request, $key ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
							// Validate filter_by parameter.
							return is_string( $param ) && ! empty( $param );
						},
					),
				),
			)
		);

		// Help settings.
		register_rest_route(
			'nua-request',
			'/v1/help-settings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'help_settings' ),
				'permission_callback' => array(
					$this,
					'nua_settings_api_permission_callback',
				),
			)
		);
	}

	/**
	 * Handle general settings GET and UPDATE via REST API.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return array|WP_Error
	 */
	public function general_settings( $request ) {
		// Nonce verification.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid nonce.', 'new-user-approve' ),
				array( 'status' => 403 )
			);
		}
		$method = $request->get_param( 'method' );
		if ( 'get' === $method ) {
			$general_settings = $this->get_general_settings();

			return array(
				'status' => 'success',
				'data'   => $general_settings,
			);
		}

		if ( 'update' === $method ) {
			$general_settings = $request->get_json_params();

			$sanitized_settings = $this->sanitize( $general_settings );

			update_option( $this->option_key, $sanitized_settings );
			return array(
				'status' => 'success',
				'method' => $sanitized_settings,
			);
		}
	}

	/**
	 * Get general settings.
	 *
	 * @return array
	 */
	public function get_general_settings() {
		$invitation_code = $this->option_invitation_code();
		return array(
			'nua_free_invitation' =>
				'enable' === $invitation_code ? 'enable' : false,
		);
	}

	/**
	 * Get help/diagnostics settings via REST API.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return array
	 */
	public function help_settings( $request ) {
		// Nonce verification.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid nonce.', 'new-user-approve' ),
				array( 'status' => 403 )
			);
		}

		$diagnostics_options = nua_opt_diagnostics();
		return array(
			'status' => 'success',
			'data'   => $diagnostics_options,
		);
	}

	/**
	 * Callback of invitation code API for validation and permission.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return bool|WP_Error
	 */
	public function nua_settings_api_permission_callback( $request ) {
		$current_user = wp_get_current_user();
		$cap          = apply_filters( 'new_user_approve_settings_api_cap', 'edit_users' );

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

		$permission = apply_filters( 'settings_api_permission', true, $request );
		return $permission;
	}

	/**
	 * Get the option key value.
	 *
	 * @return mixed
	 */
	public function option_key() {
		$options = get_option( $this->option_key );
		return $options;
	}

	/**
	 * Sanitize settings input.
	 *
	 * @param array $input The input data to sanitize.
	 * @return array
	 */
	public function sanitize( $input ) {
		$current = get_option( $this->option_key, array() );

		if (
			isset( $input['nua_settings_tab'] ) &&
			'general' === $input['nua_settings_tab']
		) {
			if (
				isset( $input['nua_free_invitation'] ) &&
				in_array(
					(string) $input['nua_free_invitation'],
					array( 'enable', '1' ),
					true
				)
			) {
				$current['nua_free_invitation'] = 'enable';

				if ( get_option( 'nua_free_invitation' ) !== false ) {
					delete_option( 'nua_free_invitation' );
				}
			} else {
				unset( $current['nua_free_invitation'] );
			}
		}

		$current = apply_filters( 'nua_input_sanitize_hook', $current, $input );
		return $current;
	}
	/**
	 * Get the invitation code option, migrating from legacy if needed.
	 *
	 * @return string|false
	 */
	public function option_invitation_code() {
		$options = get_option( $this->option_key );

		// If setting already exists in the new version, just return it.
		if ( isset( $options['nua_free_invitation'] ) ) {
			return $options['nua_free_invitation'];
		}
		// Check for the legacy option.
		$legacy_option = get_option( 'nua_free_invitation' );

		// If legacy option is enabled, migrate it to new version.
		if ( 'enable' === $legacy_option ) {
			if ( ! is_array( $options ) ) {
				$options = array();
			}

			$options['nua_free_invitation'] = 'enable';
			// Save to new DB structure.
			update_option( $this->option_key, $options );
			// Delete the old option to clean up.
			delete_option( 'nua_free_invitation' );
			return 'enable';
		}

		return false;
	}
}
// phpcs:ignore
function nua_settings_API() {
	return Nua_Settings_API::instance();
}

nua_settings_API();
