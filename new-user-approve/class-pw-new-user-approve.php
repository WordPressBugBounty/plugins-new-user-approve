<?php
/**
 * New User Approve Plugin Main Class
 *
 * Contains the main functionality for the New User Approve plugin.
 *
 * @package NewUserApprove
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

if ( ! class_exists( 'PW_New_User_Approve' ) ) {
	#[AllowDynamicProperties]
	/**
	 * Main New User Approve Class
	 *
	 * Handles user approval functionality and plugin initialization.
	 *
	 * @since 1.0.0
	 */
	class PW_New_User_Approve {

		/**
		 * The only instance of PW_New_User_Approve.
		 *
		 * @var PW_New_User_Approve
		 */
		private static $instance;

		/**
		 * Email template tags instance.
		 *
		 * @var NUA_Email_Template_Tags
		 */
		public $email_tags;

		/**
		 * Returns the main instance.
		 *
		 * @return PW_New_User_Approve
		 */
		public static function instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new PW_New_User_Approve();
				self::$instance->includes();
				self::$instance->email_tags = new NUA_Email_Template_Tags();
			}

			return self::$instance;
		}

		/**
		 * Class constructor.
		 *
		 * Sets up hooks and actions for the plugin.
		 *
		 * @since 1.0.0
		 */
		private function __construct() {
			// Load up the localization file if we're using WordPress in a different language.
			// Just drop it in this plugin's "localization" folder and name it "new-user-approve-[value in wp-config].mo".
			// load_plugin_textdomain( 'new-user-approve', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );.
			register_activation_hook( __FILE__, array( $this, 'activation' ) );
			register_deactivation_hook( __FILE__, array( $this, 'deactivation' ) );
			add_action( 'admin_head', array( $this, 'change_background_color' ) );
			add_action( 'wp_loaded', array( $this, 'admin_loaded' ) );
			add_action( 'rightnow_end', array( $this, 'dashboard_stats' ) );
			add_action( 'user_register', array( $this, 'delete_new_user_approve_transient' ), 11 );
			add_action( 'new_user_approve_approve_user', array( $this, 'delete_new_user_approve_transient' ), 11 );
			add_action( 'new_user_approve_deny_user', array( $this, 'delete_new_user_approve_transient' ), 11 );
			add_action( 'deleted_user', array( $this, 'delete_new_user_approve_transient' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'nua_admin_scripts' ) );
			add_action( 'register_post', array( $this, 'create_new_user' ), 10, 3 );
			add_action( 'woocommerce_created_customer', array( $this, 'nua_welcome_email_woo_new_user' ) );
			add_action( 'lostpassword_post', array( $this, 'lost_password' ), 99, 2 );
			add_action( 'user_register', array( $this, 'add_user_status' ) );
			add_action( 'user_register', array( $this, 'request_admin_approval_email_2' ) );
			add_action( 'new_user_approve_approve_user', array( $this, 'approve_user' ) );
			add_action( 'new_user_approve_deny_user', array( $this, 'deny_user' ) );
			add_action( 'new_user_approve_deny_user', array( $this, 'update_deny_status' ) );
			add_action( 'admin_init', array( $this, 'nua_init_admin_functions' ) );
			add_action( 'wp_login', array( $this, 'login_user' ), 10, 2 );
			add_filter( 'wp_authenticate_user', array( $this, 'authenticate_user' ) );
			add_filter( 'registration_errors', array( $this, 'show_user_pending_message' ), 99 );
			add_filter( 'login_message', array( $this, 'welcome_user' ) );
			add_filter( 'new_user_approve_validate_status_update', array( $this, 'validate_status_update' ), 10, 3 );
			add_filter( 'shake_error_codes', array( $this, 'failure_shake' ) );
			add_filter( 'woocommerce_registration_auth_new_customer', array( $this, 'disable_woo_auto_login' ) );
			add_action( 'woocommerce_checkout_order_processed', array( $this, 'disable_woo_auto_login_on_checkout' ) );
			add_action( 'admin_menu', array( $this, 'admin_menu_link' ), 5 );
			add_action( 'plugins_loaded', array( $this, 'wpdocs_load_textdomain' ) );
			// Compatibility with membership pro plugin.
			add_filter( 'pmpro_setup_new_user', array( $this, 'allow_pmpro_setup_user' ), 20, 4 );
			add_action( 'pmpro_after_checkout', array( $this, 'logout_after_pmpro_registration' ), 20 );
			add_action( 'the_content', array( $this, 'nua_show_pending_user_message' ), 20 );
			add_filter( 'plugin_action_links_' . plugin_basename( NUA_FILE ), array( $this, 'plugin_action_links' ), 10, 4 );
			add_action( 'admin_init', array( $this, 'nua_synchronize_script_translations' ) );
		}

		/**
		 * Add plugin action links.
		 *
		 * @param array $actions Plugin action links.
		 * @return array Modified action links.
		 *
		 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
		 */
		public function plugin_action_links( $actions ) {
			// Black Friday Deal - Expires Dec 15th, 2025.
			if ( time() < strtotime( '2025-12-10 23:59:59' ) ) {
				$actions['black_friday'] = '<a href="https://newuserapprove.com/pricing/?utm_source=plugin&utm_medium=plugins_page_bf" target="_blank" style="color: green; font-weight: bold;">' . esc_html__( 'Black Friday Deals', 'new-user-approve' ) . '</a>';
			} else {
				$actions['black_friday'] = '<a href="https://newuserapprove.com/pricing/?utm_source=plugin&utm_medium=plugins_page" target="_blank" style="color: green; font-weight: bold;">' . esc_html__( 'Upgrade To Pro', 'new-user-approve' ) . '</a>';

			}

			ksort( $actions );
			return $actions;
		}

		/**
		 * Allow PMP Pro setup user.
		 *
		 * @param bool  $proceed   Whether to proceed (unused).
		 * @param int   $user_id   User ID (unused).
		 * @param array $user_data User data (unused).
		 * @param mixed $level     Membership level (unused).
		 * @return bool Always returns true.
		 */
		public function allow_pmpro_setup_user( $proceed, $user_id, $user_data, $level ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			return true;
		}

		/**
		 * Logout after PMP Pro registration.
		 *
		 * @param int $user_id User ID.
		 */
		public function logout_after_pmpro_registration( $user_id ) {
			if ( get_current_user_id() === (int) $user_id ) {
				wp_logout();
				wp_safe_redirect(
					add_query_arg( 'nua_status', 'pending', wp_get_referer() )
				);
				exit;
			}
		}

		/**
		 * Show pending user message on content.
		 *
		 * @param string $content The post content.
		 * @return string Modified content with pending message.
		 */
		public function nua_show_pending_user_message( $content ) {
			// This is a plugin-generated URL parameter (set via add_query_arg), not user-submitted form data.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['nua_status'] ) && 'pending' === sanitize_text_field( wp_unslash( $_GET['nua_status'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$message = '<div class="nua-pending-message" style="background:#00a6361f;padding:15px;border-left:4px solid #00a636;margin-bottom:20px;">' .
					__(
						'Thank you for registering. Your account is currently pending approval. Please wait for admin approval before attempting to log in.',
						'new-user-approve'
					) .
					'</div>';

				return $message . $content;
			}

			return $content;
		}

		/**
		 * Load plugin text domain for translations.
		 */
		public function wpdocs_load_textdomain() {
			load_plugin_textdomain( 'new-user-approve', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		}

		/**
		 * Initialize admin functions.
		 */
		public function nua_init_admin_functions() {
			$this->nua_register_caps();
		}

		/**
		 * Get plugin capabilities.
		 *
		 * @return array List of plugin capabilities.
		 */
		private function get_caps() {
			return array(
				'nua_main_menu',
				'nua_users_cap',
				'nua_view_invitation_tab',
				'nua_auto_approve_cap',
				'nua_integration_cap',
				'nua_settings_cap',
				'nua_mobile_app_cap',
			);
		}

		/**
		 * Register plugin capabilities.
		 */
		public function nua_register_caps() {
			// List of all plugin-specific capabilities.
			$role = get_role( 'administrator' );
			if ( $role ) {
				foreach ( $this->get_caps() as $cap ) {
					if ( ! $role->has_cap( $cap ) ) {
						$role->add_cap( $cap );
					}
				}
			}
		}

		/**
		 * Add admin menu link.
		 */
		public function admin_menu_link() {
			$hook = add_menu_page(
				__( 'New User Approve', 'new-user-approve' ),
				__( 'New User Approve', 'new-user-approve' ),
				'nua_main_menu', // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Custom capability registered via nua_register_caps().
				'new-user-approve-admin',
				array( $this, 'empty_admin_page' ),
				'dashicons-nua-main'
			);

			add_action(
				'admin_head-' . $hook,
				array(
					$this,
					'admin_custom_icon_style',
				)
			);
		}

		/**
		 * Empty admin page placeholder.
		 */
		public function empty_admin_page() {
			echo '<div class="wrap"></div>';
		}

		/**
		 * Add custom admin icon styles.
		 */
		public function admin_custom_icon_style() {
			echo '<style>
				.dashicons-nua-main:before {
					content: "\e900";
					font-family: "dashicons";
				}
			</style>';
		}

		/**
		 * Get plugin URL.
		 *
		 * @return string Plugin directory URL.
		 */
		public function get_plugin_url() {
			return plugin_dir_url( __FILE__ );
		}

		/**
		 * Get plugin directory path.
		 *
		 * @return string Plugin directory path.
		 */
		public function get_plugin_dir() {
			return plugin_dir_path( __FILE__ );
		}

		/**
		 * Include required files
		 *
		 * @access private
		 * @since 1.4
		 * @since 2.1 `required` zapier.php
		 * @return void
		 */
		private function includes() {
			require_once $this->get_plugin_dir() . 'includes/class-nua-email-template-tags.php';
			require_once $this->get_plugin_dir() . 'includes/messages.php';
			require_once $this->get_plugin_dir() . 'includes/class-nua-invitation-code.php';
			require_once $this->get_plugin_dir() . 'includes/zapier/class-zapier-init.php';
			require_once $this->get_plugin_dir() . 'includes/memberpress.php';
			require_once $this->get_plugin_dir() . 'includes/ultimate-member.php';
			// check if jetformbuilder is active before including the integration file.
			if ( file_exists( $this->get_plugin_dir() . '/includes/class-nua-jetformbuilder.php' ) ) {
				require_once $this->get_plugin_dir() . '/includes/class-nua-jetformbuilder.php';
			}
			// check if gravity forms is active before including the integration file.
			if ( file_exists( $this->get_plugin_dir() . 'premium-files/includes/class-nua-gravityforms.php' ) ) {
				require_once $this->get_plugin_dir() . 'premium-files/includes/class-nua-gravityforms.php';
			}
		}

		/**
		 * Require a minimum version of WordPress on activation
		 *
		 * @uses register_activation_hook
		 */
		public function activation() {
			global $wp_version;
			$min_wp_version = '3.5.1';
			$exit_msg       = sprintf(
				// translators: %s is for WordPress version.
				__(
					'New User Approve requires WordPress %s or newer.',
					'new-user-approve'
				),
				$min_wp_version
			);
			if ( version_compare( $wp_version, $min_wp_version, '<' ) ) {
				exit( esc_html( $exit_msg ) );
			}
			// since the right version of WordPress is being used, run a hook.
			do_action( 'new_user_approve_activate' );
		}

		/**
		 * Deactivation hook for the plugin.
		 *
		 * @uses register_deactivation_hook
		 */
		public function deactivation() {
			do_action( 'new_user_approve_deactivate' );
		}

		/**
		 * Changes the background color of the WordPress admin area.
		 */
		public function change_background_color() {
			?>
			<style>
				#wpwrap {
					background-color: #F4F4F4;
				}
			</style>

			<?php
		}

		/**
		 * Makes it possible to disable the user admin integration. Must happen after
		 * WordPress is loaded.
		 *
		 * @uses wp_loaded
		 */
		public function admin_loaded() {
			$user_admin_integration = apply_filters( 'new_user_approve_user_admin_integration', true );
			if ( $user_admin_integration ) {
				require_once __DIR__ . '/includes/class-pw-new-user-approve-user-list.php';
				require_once __DIR__ . '/includes/end-points/class-users-api.php';
				require_once __DIR__ . '/includes/end-points/class-invitation-code-api.php';
				require_once __DIR__ . '/includes/end-points/class-nua-settings-api.php';
				require_once __DIR__ . '/includes/end-points/class-nuaf-mobile-api.php';
				require_once __DIR__ . '/includes/help.php';
			}
			$legacy_panel = apply_filters( 'new_user_approve_user_admin_legacy', true );
			if ( $legacy_panel ) {
				require_once __DIR__ . '/includes/class-pw-new-user-approve-admin-approve.php';
			}
		}

		/**
		 * Get the status of a user.
		 *
		 * @param int $user_id The ID of the user.
		 * @return string the status of the user
		 */
		public function get_user_status( $user_id ) {
			$user_status = get_user_meta( $user_id, 'pw_user_status', true );
			if ( empty( $user_status ) ) {
				$user_status = 'approved';
			}
			return $user_status;
		}

		/**
		 * Update the status of a user. The new status must be either 'approve' or 'deny'.
		 *
		 * @param int    $user   The user ID or user object.
		 * @param string $status The new status for the user.
		 *
		 * @return boolean
		 */
		public function update_user_status( $user, $status ) {
			$user_id = absint( $user );
			if ( ! $user_id ) {
				return false;
			}
			if ( ! in_array( $status, array( 'approve', 'deny' ), true ) ) {
				return false;
			}
			$do_update = apply_filters( 'new_user_approve_validate_status_update', true, $user_id, $status );
			if ( ! $do_update ) {
				return false;
			}
			// where it all happens.
			do_action( 'new_user_approve_' . $status . '_user', $user_id );
			do_action( 'new_user_approve_user_status_update', $user_id, $status );
			// update user count.
			$user_statuses = $this->fetch_user_statuses();
			update_option( 'nua_users_count', $user_statuses );

			return true;
		}

		/**
		 * Get the valid statuses. Anything outside of the returned array is an invalid status.
		 *
		 * @return array
		 */
		public function get_valid_statuses() {
			return array( 'pending', 'approved', 'denied' );
		}

		/**
		 * Only validate the update if the status has been updated to prevent unnecessary update
		 * and especially emails.
		 *
		 * @param bool   $do_update Whether to proceed with the update.
		 * @param int    $user_id   The ID of the user.
		 * @param string $status    Either 'approve' or 'deny'.
		 */
		public function validate_status_update( $do_update, $user_id, $status ) {
			$current_status = pw_new_user_approve()->get_user_status( $user_id );

			if ( 'approve' === $status ) {
				$new_status = 'approved';
			} else {
				$new_status = 'denied';
			}

			if ( $new_status === $current_status ) {
				$do_update = false;
			}
			return $do_update;
		}

		/**
		 * The default message that is shown to a user depending on their status
		 * when trying to sign in.
		 *
		 * @param string $status The user status.
		 * @return string
		 */
		public function default_authentication_message( $status ) {
			$message = '';

			if ( 'pending' === $status ) {
				$message = __( '<strong>ERROR</strong>: Your account is still pending approval.', 'new-user-approve' );
				$message = apply_filters( 'new_user_approve_pending_error', $message );
			} elseif ( 'denied' === $status ) {
				$message = __( '<strong>ERROR</strong>: Your account has been denied access to this site.', 'new-user-approve' );
				$message = apply_filters( 'new_user_approve_denied_error', $message );
			}

			$message = apply_filters( 'new_user_approve_default_authentication_message', $message, $status );
			return $message;
		}

		/**
		 * Determine if the user is good to sign in based on their status.
		 *
		 * @uses wp_authenticate_user
		 * @param array $userdata The user data array.
		 */
		public function authenticate_user( $userdata ) {
			$status = $this->get_user_status( $userdata->ID );
			if ( empty( $status ) ) {
				// the user does not have a status so let's assume the user is good to go.
				return $userdata;
			}
			$message = false;
			switch ( $status ) {
				case 'pending':
					$pending_message = $this->default_authentication_message( 'pending' );
					$message         = new WP_Error( 'pending_approval', $pending_message );
					break;
				case 'denied':
					$denied_message = $this->default_authentication_message( 'denied' );
					$message        = new WP_Error( 'denied_access', $denied_message );
					break;
				case 'approved':
					$message = $userdata;
					break;
			}
			return $message;
		}
		/**
		 * Updated function for user count fix.
		 *
		 * @param bool $count Whether to count or return users.
		 * @return array
		 */
		public function fetch_user_statuses( $count = true ) {
			global $wpdb;

			$statuses = array();

			foreach ( $this->get_valid_statuses() as $status ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Count query on usermeta join; no WP API equivalent for this cross-table count.
				$total = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->users} u
                        LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
                        WHERE um.meta_key = %s AND um.meta_value = %s",
						'pw_user_status',
						$status
					)
				);

				if ( true === $count ) {
					$statuses[ $status ] = $total;
				} else {
					// If count is false, return the list of users.
					// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Required for WP_User_Query filtering by user status meta; no alternative API exists.
					$user_query = new WP_User_Query(
						array(
							'meta_key'   => 'pw_user_status',
							'meta_value' => $status,
						)
					);
					// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value

					$statuses[ $status ] = $user_query->get_results();
				}
			}
			return $statuses;
		}
		/**
		 * Get a list of statuses with a count of users using WPQuery not transient
		 *
		 * @param bool   $count  Whether to count or return users.
		 * @param string $status The status to filter by.
		 */
		public function get_users_by_status( $count = true, $status = '' ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- 'paged' is a pagination URL parameter, not user-submitted form data.
			$paged = isset( $_REQUEST['paged'] ) && ! empty( $_REQUEST['paged'] ) ? absint( $_REQUEST['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			$statuses = array();
			foreach ( $this->get_valid_statuses() as $status ) {
				// Query the users table.
				if ( 'approved' !== $status ) {
					// Query the users table.
					// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Required for WP_User_Query filtering by user status meta; no alternative API exists.
					$query = array(
						'meta_key'    => 'pw_user_status',
						'meta_value'  => $status,
						'count_total' => true,
						'number'      => 15,
						'paged'       => $paged,
					);
					// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				} else {
					// get all approved users and any user without a status.
					// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for WP_User_Query filtering by user status meta; no alternative API exists.
					$query = array(
						'meta_query'  => array(
							'relation' => 'OR',
							array(
								'key'     => 'pw_user_status',
								'value'   => 'approved',
								'compare' => '=',
							),
							array(
								'key'     => 'pw_user_status',
								'value'   => '',
								'compare' => 'NOT EXISTS',
							),
						),
						'count_total' => true,
						'number'      => 15,
						'paged'       => $paged,
					);
					// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				}

				$wp_user_search = new WP_User_Query( $query );

				if ( true === $count ) {
					$statuses[ $status ] = $wp_user_search->get_total();
				} else {
					$statuses[ $status ] = $wp_user_search->get_results();
				}
			}
			return $statuses;
		}

		/**
		 * Get a list of statuses with a count of users with that status
		 */
		public function get_count_of_user_statuses() {
			$user_statuses = get_option( 'new_user_approve_user_statuses_count', array() );
			if ( empty( $user_statuses ) ) {
				$user_statuses = $this->fetch_user_statuses();
				update_option( 'new_user_approve_user_statuses_count', $user_statuses );
			}

			return $user_statuses;
		}

		/**
		 * Get user statuses.
		 *
		 * @param string $status The status.
		 * @return array
		 */
		public function get_user_statuses( $status ) {
			$nua_users_transient = apply_filters( 'nua_users_transient', true );
			$user_statuses       = array();

			if ( ! $nua_users_transient ) {
				$user_statuses = $this->get_users_by_status( false, $status );
			} else {
				$user_statuses = get_transient( 'new_user_approve_user_statuses' );
				if ( false === $user_statuses ) {
					$user_statuses = $this->fetch_user_statuses( false );
					set_transient( 'new_user_approve_user_statuses', $user_statuses );
				}
			}

			foreach ( $this->get_valid_statuses() as $status ) {
				$user_statuses[ $status ] = apply_filters( 'new_user_approve_user_status', $user_statuses[ $status ], $status );
			}
			return $user_statuses;
		}

		/**
		 * Delete the transient storing all of the user statuses.
		 *
		 * @uses user_register
		 * @uses deleted_user
		 * @uses new_user_approve_approve_user
		 * @uses new_user_approve_deny_user
		 */
		public function delete_new_user_approve_transient() {
			delete_transient( 'new_user_approve_user_statuses' );
		}

		/**
		 * Display the stats on the WP dashboard. Will show 1 line with a count
		 * of users and their status.
		 *
		 * @uses rightnow_end
		 */

		/**
		 * Add scripts and styles for New User Approve admin pages
		 *
		 * @since 2.0 `nua-admin` enqueued for zapier
		 */
		public function nua_admin_scripts() {
			$mobile_app_auth_token = wp_generate_password( 20, false );
			update_option( 'nua_app_auth_token', $mobile_app_auth_token );

			$pages = array(
				'new-user-approve-auto-approve',
				'nua-invitation-code',
				'new-user-approve',
				'new-user-approve-admin',
			);

			wp_enqueue_style( 'nua-fonts', plugins_url( '/assets/css/nua-fonts.css', __FILE__ ), array(), NUA_VERSION );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- $_GET['page'] is a WordPress admin page slug, not form data.
			if ( isset( $_GET['page'] ) && in_array( $_GET['page'], $pages, true ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				wp_enqueue_script( 'jquery' );
				wp_enqueue_editor();
				wp_enqueue_media();
				wp_enqueue_script( 'new-user-approve-buildjs', plugins_url( '/build/index.js', __FILE__ ), array( 'wp-blocks', 'wp-block-library', 'wp-element', 'wp-i18n', 'wp-components', 'wp-editor', 'wp-data' ), NUA_VERSION, true );
				wp_enqueue_style( 'new-user-approve-buildcss', plugins_url( '/build/style-index.css', __FILE__ ), array(), NUA_VERSION );
				wp_enqueue_style( 'nua-admin-style', plugins_url( '/assets/css/nua-admin-style.css', __FILE__ ), array( 'nua-fonts' ), NUA_VERSION );
				wp_localize_script(
					'new-user-approve-buildjs',
					'siteDetail',
					array(
						'siteUrl'        => get_site_url(),
						'app_auth_token' => $mobile_app_auth_token,
					)
				);
				wp_set_script_translations( 'new-user-approve-buildjs', 'new-user-approve', plugin_dir_path( __FILE__ ) . 'languages' );

				wp_localize_script(
					'new-user-approve-buildjs',
					'NUARestAPI',
					array(
						'permalink_delimeter'     => str_contains( get_rest_url(), 'wp-json/' ) ? '?' : '&',
						'request_to'              => get_rest_url( null, 'nua-request/v1' ),
						'save_invitation_codes'   => get_rest_url( null, 'nua-request/v1/save-invitation-codes' ),
						'get_invitation_code'     => get_rest_url( null, 'nua-request/v1/get-invitation-settings' ),
						'get_nua_invite_codes'    => get_rest_url( null, 'nua-request/v1/get-nua-codes' ),
						'get_remaining_uses'      => get_rest_url( null, 'nua-request/v1/get-remaining-uses' ),
						'get_total_uses'          => get_rest_url( null, 'nua-request/v1/get-total-uses' ),
						'get_expiry'              => get_rest_url( null, 'nua-request/v1/get-expiry' ),
						'get_status'              => get_rest_url( null, 'nua-request/v1/get-status' ),
						'get_invited_users'       => get_rest_url( null, 'nua-request/v1/get-invited-users' ),
						'update_invitation_code'  => get_rest_url( null, 'nua-request/v1/update-invitation-code' ),
						'status_update_invCode'   => get_rest_url( null, 'nua-request/v1/status-update-invCode' ),
						'delete_invCode'          => get_rest_url( null, 'nua-request/v1/delete-invCode' ),
						'general_settings'        => get_rest_url( null, 'nua-request/v1/general-settings' ),
						'help_settings'           => get_rest_url( null, 'nua-request/v1/help-settings' ),
						'recent_user'             => get_rest_url( null, 'nua-request/v1/recent-users' ),
						'update_users'            => get_rest_url( null, 'nua-request/v1/update-user' ),
						'get_all_users'           => get_rest_url( null, 'nua-request/v1/get-all-users' ),
						'get_approved_users'      => get_rest_url( null, 'nua-request/v1/get-approved-users' ),
						'get_pending_users'       => get_rest_url( null, 'nua-request/v1/get-pending-users' ),
						'get_denied_users'        => get_rest_url( null, 'nua-request/v1/get-denied-users' ),
						'get_approved_user_roles' => get_rest_url( null, '/nua-request/v1/get-approved-user-roles' ),
						'get_user_roles'          => get_rest_url( null, 'nua-request/v1/get-user-roles' ),
						'update_user_role'        => get_rest_url( null, 'nua-request/v1/update-user-role' ),
						'get_activity_log'        => get_rest_url( null, 'nua-request/v1/get-activity-log' ),
						'get_api_key'             => get_rest_url( null, 'nua-request/v1/get-api-key' ),
						'update_api_key'          => get_rest_url( null, 'nua-request/v1/update-api-key' ),
						'all_statuses_users'      => get_rest_url( null, 'nua-request/v1/get-all-statuses-users' ),
					)
				);

				$current_user = wp_get_current_user();
				wp_localize_script(
					'new-user-approve-buildjs',
					'nuaAdmin',
					array(
						'info'                    => $current_user->user_nicename,
						// phpcs:disable WordPress.WP.Capabilities.Unknown -- Custom capabilities registered via nua_register_caps().
						'nua_view_invitation_tab' => current_user_can( 'nua_view_invitation_tab' ),
						'nua_auto_approve_cap'    => current_user_can( 'nua_auto_approve_cap' ),
						'nua_integration_cap'     => current_user_can( 'nua_integration_cap' ),
						'nua_settings_cap'        => current_user_can( 'nua_settings_cap' ),
						'nua_users_cap'           => current_user_can( 'nua_users_cap' ),
						'nua_mobile_app_cap'      => current_user_can( 'nua_mobile_app_cap' ),
						// phpcs:enable WordPress.WP.Capabilities.Unknown
					)
				);
			}
		}

		/**
		 * Display dashboard statistics for user statuses.
		 */
		public function dashboard_stats() {
			$user_status = $this->get_count_of_user_statuses();
			?>
			<div>
				<p>
					<span style="font-weight:bold;">
						<a
							href="<?php echo wp_kses_post( apply_filters( 'new_user_approve_dashboard_link', 'users.php' ) ); ?>"><?php esc_html_e( 'Users', 'new-user-approve' ); ?></a>
					</span>:
					<?php
					foreach ( $user_status as $status => $count ) {
						echo esc_html( ucwords( $status ) ) . '(' . esc_attr( $count ) . ')&nbsp;&nbsp;&nbsp;';
					}
					?>
				</p>
			</div>
			<?php
		}

		/**
		 * Send email to admin requesting approval.
		 *
		 * @param string $user_login The username.
		 * @param string $user_email The email address of the user.
		 */
		public function admin_approval_email( $user_login, $user_email ) {
			add_filter(
				'woocommerce_email_recipient_customer_new_account',
				function ( $recipient, $order ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
					return ''; // Return an empty string to prevent the email from being sent.
				},
				10,
				2
			);
			$default_admin_url = admin_url(
				'admin.php?page=new-user-approve-admin#/action=users/tab=pending-users'
			);
			$admin_url         = apply_filters(
				'new_user_approve_admin_link',
				$default_admin_url
			);
			/* send email to admin for approval */
			$message = apply_filters(
				'new_user_approve_request_approval_message_default',
				nua_default_notification_message()
			);
			$message = nua_do_email_tags(
				$message,
				array(
					'context'    => 'request_admin_approval_email',
					'user_login' => $user_login,
					'user_email' => $user_email,
					'admin_url'  => $admin_url,
				)
			);
			$message = apply_filters(
				'new_user_approve_request_approval_message',
				$message,
				$user_login,
				$user_email
			);
			if ( get_option( 'nua_notification_email_message_as_html' ) === '1' ) {
				add_filter( 'wp_mail_content_type', array( $this, 'welcome_html_content_type' ) );
				$message .= "\n\n<br><br><button style='padding: 10px 20px; background-color: green; color: white; border: none; margin-right: 10px;'>Approve</button><button style='padding: 10px 20px; background-color: red; color: white; border: none;'>Deny</button><br><br>\n\n";
			}
			$subject = sprintf(
				// translators: [%s] is for blogname in admin approve email subject.
				__( '[%s] User Approval', 'new-user-approve' ),
				wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES )
			);
			$subject = apply_filters(
				'new_user_approve_request_approval_subject',
				$subject
			);
			$to      = apply_filters(
				'new_user_approve_email_admins',
				array(
					get_option( 'admin_email' ),
				)
			);
			$to      = array_unique( $to );
			// send the mail.
			wp_mail( $to, $subject, $message, $this->email_message_headers() );
			if ( get_option( 'nua_notification_email_message_as_html' ) === '1' ) {
				remove_filter( 'wp_mail_content_type', array( $this, 'welcome_html_content_type' ) );
			}
		}

		/**
		 * Send an email to the admin to request approval. If there are already errors,
		 * just go back and let core do it's thing.
		 *
		 * @uses register_post
		 * @param string $user_login The user login.
		 * @param string $user_email The user email.
		 * @param object $errors     The errors object.
		 */
		public function request_admin_approval_email(
			$user_login,
			$user_email,
			$errors
		) {
			if ( $errors->get_error_code() ) {
				return;
			}
			$user = get_user_by( 'email', $user_email );
			if ( $user ) {
				$status = $this->get_user_status( $user->ID );
				if ( 'pending' === $status ) {
					$this->admin_approval_email( $user_login, $user_email );
				}
			}
		}

		/**
		 * Send an email to the admin to request approval.
		 *
		 * @uses user_register
		 * @param int $user_id The user ID.
		 */
		public function request_admin_approval_email_2( $user_id ) {
			$user       = new WP_User( $user_id );
			$user_login = stripslashes( $user->data->user_login );
			$user_email = stripslashes( $user->data->user_email );
			$status     = $this->get_user_status( $user_id );
			if ( 'pending' === $status ) {
				$this->admin_approval_email( $user_login, $user_email );
			}
		}

		/**
		 * Create a new user after the registration has been validated. Normally,
		 * when a user registers, an email is sent to the user containing their
		 * username and password. The email does not get sent to the user until
		 * the user is approved when using the default behavior of this plugin.
		 *
		 * @uses register_post
		 * @param string $user_login The user login.
		 * @param string $user_email The user email.
		 * @param object $errors     The errors object.
		 */
		public function create_new_user( $user_login, $user_email, $errors ) {
			// WP-Foro fires register_post for validation only and creates the user itself afterwards.
			// Skipping NUA's wp_create_user() here prevents duplicate user creation which would
			// cause WP-Foro's wp_create_user() to fail and return a white screen.
			// NUA's user_register hook will still run when WP-Foro creates the user.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST['wpfreg'] ) ) {
				return;
			}

			if ( $errors->get_error_code() ) {
				return;
			}
			// create the user.
			$user_pass = wp_generate_password( 12, false );
			$user_pass = apply_filters( 'nua_pass_create_new_user', $user_pass );
			$user_id   = wp_create_user( $user_login, $user_pass, $user_email );

			if ( ! $user_id ) {
				// translators: %s is for admin email.
				$errors->add( 'registerfail', sprintf( __( '<strong>ERROR</strong>: Couldn&#8217;t register you... please contact the <a href="mailto:%s">webmaster</a> !' ), get_option( 'admin_email' ) ) );
			} else {
				// User Registeration welcome email.
				$disable = apply_filters( 'nua_disable_welcome_email', false, $user_id );
				if ( false === $disable ) {
					$message = nua_default_registeration_welcome_email();

					$message = apply_filters( 'new_user_approve_welcome_user_message', $message, $user_email );
					// translators: %s is for blogname of wp  welcome email subject.
					$subject = sprintf( __( 'Your registration is pending for approval - [%s]', 'new-user-approve' ), get_option( 'blogname' ) );
					$subject = apply_filters( 'new_user_approve_welcome_user_subject', $subject );

					wp_mail( $user_email, $subject, $message, $this->email_message_headers() );
				}
			}
		}

		/**
		 * Send welcome email for WooCommerce new user.
		 *
		 * @param int $customer_id The customer ID.
		 */
		public function nua_welcome_email_woo_new_user( $customer_id ) {
			$customer = new WC_Customer( $customer_id );

			$user_email = $customer->get_email();

			$message = nua_default_registeration_welcome_email();

			$message = apply_filters( 'new_user_approve_welcome_user_message', $message, $user_email );
			// translators: %s is for blogname of woo welcome email subject.
			$subject = sprintf( __( 'Your registration is pending for approval - [%s]', 'new-user-approve' ), get_option( 'blogname' ) );
			$subject = apply_filters( 'new_user_approve_welcome_user_subject', $subject );
			// disable new account email.
			add_filter( 'woocommerce_email_enabled_customer_new_account', '__return_false' );
			$disable_welcome_email = apply_filters( 'disable_welcome_email_woo_new_user', array( $this, false ) );
			if ( true === $disable_welcome_email ) {
				return;
			}

			wp_mail( $user_email, $subject, $message, $this->email_message_headers() );
		}

		/**
		 * Determine whether a password needs to be reset.
		 *
		 * Password should only be reset for users that:
		 * * have never logged in
		 * * are just approved for the first time
		 *
		 * @param int $user_id The user ID.
		 * @return boolean
		 */
		public function do_password_reset( $user_id ) {
			// Default behavior is to reset password.
			$do_password_reset = true;
			// Get the current user status. By default each user is given a pending
			// status when the user is created (with this plugin activated). If the
			// user was created while this plugin was not active, the user will not
			// have a status set.
			$user_status = get_user_meta( $user_id, 'pw_user_status', true );
			// if no status is set, don't reset password.
			if ( empty( $user_status ) ) {
				$do_password_reset = false;
			}
			// if user has signed in, don't reset password.
			$user_has_signed_in = get_user_meta( $user_id, 'pw_new_user_approve_has_signed_in', true );
			if ( $user_has_signed_in ) {
				$do_password_reset = false;
			}
			// for backward compatability.
			$bypass_password_reset = apply_filters( 'new_user_approve_bypass_password_reset', ! $do_password_reset );
			return apply_filters( 'new_user_approve_do_password_reset', ! $bypass_password_reset );
		}

		/**
		 * Admin approval of user
		 *
		 * @uses new_user_approve_approve_user
		 * @param int $user_id The user ID.
		 */
		public function approve_user( $user_id ) {
			$user = new WP_User( $user_id );
			wp_cache_delete( $user->ID, 'users' );
			wp_cache_delete( $user->data->user_login, 'userlogins' );
			// send email to user telling of approval.
			$user_login = stripslashes( $user->data->user_login );
			$user_email = stripslashes( $user->data->user_email );
			// format the message.
			$message = nua_default_approve_user_message();
			$message = nua_do_email_tags(
				$message,
				array(
					'context'    => 'approve_user',
					'user'       => $user,
					'user_login' => $user_login,
					'user_email' => $user_email,
				)
			);
			$message = apply_filters( 'new_user_approve_approve_user_message', $message, $user );
			// translators: %s is for blogname of approved email subject.
			$subject = sprintf( __( '[%s] Registration Approved', 'new-user-approve' ), get_option( 'blogname' ) );
			$subject = apply_filters( 'new_user_approve_approve_user_subject', $subject );
			// send the mail.
			wp_mail( $user_email, $subject, $message, $this->email_message_headers() );
			// to update statuses count.
			$this->update_users_statuses_count( 'approved', $user_id );

			// change usermeta tag in database to approved.
			update_user_meta( $user->ID, 'pw_user_status', 'approved' );
			// update the status time.
			update_user_meta( $user->ID, 'pw_user_status_time', gmdate( 'Y-m-d h:i:s' ) );
			do_action( 'new_user_approve_user_approved', $user );
		}

		/**
		 * Send email to notify user of denial.
		 *
		 * @uses new_user_approve_deny_user
		 * @param int $user_id The user ID.
		 */
		public function deny_user( $user_id ) {
			$user = new WP_User( $user_id );

			$disable = apply_filters( 'new_user_approve_denied_email', false, $user_id );
			if ( false === $disable ) {
				$user_id = $user->ID;

				$sessions = WP_Session_Tokens::get_instance( $user_id );
				$sessions->destroy_all();

				// send email to user telling of denial.
				$user_email = stripslashes( $user->data->user_email );
				// format the message.
				$message = nua_default_deny_user_message();
				$message = nua_do_email_tags(
					$message,
					array(
						'context' => 'deny_user',
					)
				);
				$message = apply_filters( 'new_user_approve_deny_user_message', $message, $user );
				// translators: %s is for blogname of denied email subject.
				$subject = sprintf( __( '[%s] Registration Denied', 'new-user-approve' ), get_option( 'blogname' ) );
				$subject = apply_filters( 'new_user_approve_deny_user_subject', $subject );
				// send the mail.
				wp_mail( $user_email, $subject, $message, $this->email_message_headers() );
			}
		}

		/**
		 * Update user status when denying user.
		 *
		 * @uses new_user_approve_deny_user
		 * @param int $user_id The user ID.
		 */
		public function update_deny_status( $user_id ) {
			$user = new WP_User( $user_id );
			// to update statuses count.
			$this->update_users_statuses_count( 'denied', $user_id );
			// change usermeta tag in database to denied.
			update_user_meta( $user->ID, 'pw_user_status', 'denied' );
			update_user_meta( $user->ID, 'pw_user_status_time', gmdate( 'Y-m-d h:i:s' ) );

			do_action( 'new_user_approve_user_denied', $user );
		}

		/**
		 * Get email message headers.
		 *
		 * @return array
		 */
		public function email_message_headers() {
			$admin_email = get_option( 'admin_email' );
			if ( isset( $_SERVER['SERVER_NAME'] ) && empty( $admin_email ) ) {
				$admin_email = 'support@' . sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) );
			}
			$from_name = get_option( 'blogname' );
			$headers   = array( "From: \"{$from_name}\" <{$admin_email}>\n" );
			$headers   = apply_filters( 'new_user_approve_email_header', $headers );
			return $headers;
		}

		/**
		 * Display a message to the user after they have registered
		 *
		 * @uses registration_errors
		 * @param WP_Error $errors The errors object.
		 */
		public function show_user_pending_message( $errors ) {
			$nonce = '';
			if ( wp_verify_nonce( $nonce ) ) {
				return;
			}

			// For WP-Foro LearnPress
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST['learn-press-checkout-nonce'] ) || isset( $_POST['reg_email'] ) ) {
				return $errors;
			}
			// For WP-Foro
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST['wpfreg'] ) ) {
				return $errors;
			}

			$disable_redirect = apply_filters( 'nua_disable_redirect_to_field', false );
			if ( ! empty( $_POST['redirect_to'] ) && false === $disable_redirect ) {
				// if a redirect_to is set, honor it.
				wp_safe_redirect( wp_unslash( $_POST['redirect_to'] ) );
				exit();
			}

			// if there is an error already, let it do it's thing.
			if ( ! empty( $errors ) && is_wp_error( $errors ) && $errors->get_error_code() ) {
				return $errors;
			}
			$message = nua_default_registration_complete_message();
			$message = nua_do_email_tags(
				$message,
				array(
					'context' => 'pending_message',
				)
			);
			$message = apply_filters( 'new_user_approve_pending_message', $message );
			$errors->add( 'registration_required', $message, 'message' );
			$success_message = __( 'Registration successful.', 'new-user-approve' );
			$success_message = apply_filters( 'new_user_approve_registration_message', $success_message );

			if ( function_exists( 'login_header' ) ) {
				login_header( __( 'Pending Approval', 'new-user-approve' ), '<p class="message register">' . $success_message . '</p>', $errors );
			}
			if ( function_exists( 'login_footer' ) ) {
				login_footer();
			}

			do_action( 'new_user_approve_after_registration', $errors, $success_message );

			// an exit is necessary here so the normal process for user registration doesn't happen.
			exit();
		}

		/**
		 * Only give a user their password if they have been approved
		 *
		 * @uses lostpassword_post
		 * @param WP_Error $errors    The errors object.
		 * @param WP_User  $user_data The user data.
		 */
		public function lost_password( $errors, $user_data ) {
			$user_login = $user_data->user_login;
			if ( empty( $user_login ) ) {
				return;
			}

			$is_email = strpos( sanitize_text_field( wp_unslash( $user_login ) ), '@' );

			if ( false === $is_email ) {
				$username  = sanitize_user( wp_unslash( $user_login ) );
				$user_data = get_user_by( 'login', trim( $username ) );
			} else {
				$email     = is_email( wp_unslash( $user_login ) );
				$user_data = get_user_by( 'email', $email );
			}

			if ( isset( $user_data ) && is_object( $user_data ) && $user_data->pw_user_status && 'approved' !== $user_data->pw_user_status ) {
				$errors->add( 'unapproved_user', __( '<strong>ERROR</strong>: User has not been approved.', 'new-user-approve' ) );
			}
			return $errors;
		}

		/**
		 * Add message to login page saying registration is required.
		 *
		 * @uses login_message
		 * @param string $message The current message.
		 * @return string
		 */
		public function welcome_user( $message ) {
			if ( ! isset( $_GET['action'] ) ) {
				$welcome = nua_default_welcome_message();
				$welcome = nua_do_email_tags(
					$welcome,
					array(
						'context' => 'welcome_message',
					)
				);
				$welcome = apply_filters( 'new_user_approve_welcome_message', $welcome );
				if ( ! empty( $welcome ) ) {
					$message .= '<p class="message register">' . $welcome . '</p>';
				}
			}

			$nonce = '';
			if ( wp_verify_nonce( $nonce ) ) {
				return;
			}
			if ( isset( $_GET['action'] ) && 'register' === $_GET['action'] && ! $_POST ) {
				$instructions = nua_default_registration_message();
				$instructions = nua_do_email_tags(
					$instructions,
					array(
						'context' => 'registration_message',
					)
				);
				$instructions = apply_filters( 'new_user_approve_register_instructions', $instructions );
				if ( ! empty( $instructions ) ) {
					$message .= '<p class="message register">' . $instructions . '</p>';
				}
			}

			return $message;
		}

		/**
		 * Give the user a status
		 *
		 * @uses user_register
		 * @param int $user_id The user ID.
		 */
		public function add_user_status( $user_id ) {
			$status = 'pending';
			// This check needs to happen when a user is created in the admin.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_REQUEST['action'] ) && 'createuser' === $_REQUEST['action'] ) {
				$status = 'approved';
			}
			$status = apply_filters( 'new_user_approve_default_status', $status, $user_id );
			// update user count.
			$this->update_users_statuses_count( $status, $user_id );
			update_user_meta( $user_id, 'pw_user_status', $status );
			update_user_meta( $user_id, 'pw_user_status_time', gmdate( 'Y-m-d h:i:s' ) );

			// Sends push notification to NUA Mobile Application.
			do_action( 'nua_app_push_notif', $user_id );
		}

		/**
		 * Add error codes to shake the login form on failure
		 *
		 * @uses shake_error_codes
		 * @param array $error_codes The error codes.
		 * @return array
		 */
		public function failure_shake( $error_codes ) {
			$error_codes[] = 'pending_approval';
			$error_codes[] = 'denied_access';
			return $error_codes;
		}

		/**
		 * After a user successfully logs in, record in user meta. This will only be recorded
		 * one time. The password will not be reset after a successful login.
		 *
		 * @uses wp_login
		 * @param string  $user_login The user login.
		 * @param WP_User $user        The user object.
		 */
		public function login_user( $user_login, $user = null ) {
			if ( null !== $user && is_object( $user ) ) {
				if ( ! get_user_meta( $user->ID, 'pw_new_user_approve_has_signed_in', true ) ) {
					add_user_meta( $user->ID, 'pw_new_user_approve_has_signed_in', time() );
				}
			}
		}

		/**
		 * Disable auto login for WooCommerce
		 *
		 * @param mixed $new_customer The new customer data.
		 * @return boolean
		 */
		public function disable_woo_auto_login( $new_customer ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			return false;
		}

		/**
		 * Disable auto login on WooCommerce checkout
		 */
		public function disable_woo_auto_login_on_checkout() {
			// destroying session when pending user trying to checkout.
			$boolean = false;
			$boolean = apply_filters( 'new_user_approve_woo_checkout_process_logout', $boolean );
			if ( $boolean ) {
				if ( is_user_logged_in() ) {
					$user_id     = get_current_user_id();
					$user_status = get_user_meta( $user_id, 'pw_user_status', true );
					if ( 'denied' === $user_status || 'pending' === $user_status ) {
						wp_destroy_current_session();
						wp_clear_auth_cookie();
						wp_set_current_user( 0 );
					}
				}
			}
		}

		/**
		 * Update users statuses count
		 *
		 * @param string $new_status The new status.
		 * @param int    $user_id    The user ID.
		 */
		public function update_users_statuses_count( $new_status, $user_id ) {
			$old_status = get_user_meta( $user_id, 'pw_user_status', true );

			if ( $old_status === $new_status ) {
				return;
			}

			$user_statuses = get_option( 'new_user_approve_user_statuses_count', array() );

			if ( empty( $user_statuses ) ) {
				$user_statuses = $this->fetch_user_statuses();
			}

			foreach ( $this->get_valid_statuses() as $status ) {
				if ( isset( $user_statuses[ $status ] ) && $old_status === $status ) {
					$count                    = $user_statuses[ $status ];
					$user_statuses[ $status ] = $count - 1;
				} elseif (
					isset( $user_statuses[ $status ] ) &&
					$new_status === $status
				) {
					$count                    = $user_statuses[ $status ];
					$user_statuses[ $status ] = $count + 1;
				}
			}
			update_option( 'new_user_approve_user_statuses_count', $user_statuses );
		}

		/**
		 * Translation function for strings.
		 */
		public function nua_synchronize_script_translations() {
			if ( ! is_admin() ) {
				return;
			}

			global $wp_filesystem;
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			WP_Filesystem();

			$languages_dir = $this->get_plugin_dir() . 'languages/';
			if ( ! is_dir( $languages_dir ) ) {
				return;
			}

			$files = glob( $languages_dir . 'new-user-approve-*-*.json' );
			if ( ! $files ) {
				return;
			}

			$handle  = 'new-user-approve-buildjs';
			$domain  = 'new-user-approve';
			$locales = array();

			foreach ( $files as $file ) {
				$filename = basename( $file );
				if ( preg_match( '/^' . preg_quote( $domain, '/' ) . '-(.+)-([a-f0-9]{32})\.json$/', $filename, $matches ) ) {
					$locale               = $matches[1];
					$locales[ $locale ][] = $file;
				}
			}

			foreach ( $locales as $locale => $source_files ) {
				$target_filename = "{$domain}-{$locale}-{$handle}.json";
				$target_file     = $languages_dir . $target_filename;

				$merged_data = array(
					'translation-revision-date' => '',
					'generator'                 => 'New User Approve Translation Sync',
					'domain'                    => $domain,
					'locale_data'               => array(
						$domain => array(
							'' => array(
								'domain'       => '',
								'lang'         => $locale,
								'plural-forms' => 'nplurals=2; plural=n > 1;',
							),
						),
					),
				);

				$needs_update = false;
				$latest_mtime = 0;

				foreach ( $source_files as $source_file ) {
					$content = json_decode( $wp_filesystem->get_contents( $source_file ), true );
					if ( $content && isset( $content['locale_data'][ $domain ] ) ) {
						// Merge strings.
						foreach ( $content['locale_data'][ $domain ] as $msgid => $translation ) {
							if ( '' === $msgid ) {

								if ( isset( $translation['plural-forms'] ) ) {
									$merged_data['locale_data'][ $domain ]['']['plural-forms'] = $translation['plural-forms'];
								}
								continue;
							}
							$merged_data['locale_data'][ $domain ][ $msgid ] = $translation;
						}

						if ( isset( $content['translation-revision-date'] ) && $content['translation-revision-date'] > $merged_data['translation-revision-date'] ) {
							$merged_data['translation-revision-date'] = $content['translation-revision-date'];
						}
					}

					if ( filemtime( $source_file ) > $latest_mtime ) {
						$latest_mtime = filemtime( $source_file );
					}
				}

				if ( ! file_exists( $target_file ) || $latest_mtime > filemtime( $target_file ) ) {
					$wp_filesystem->put_contents( $target_file, wp_json_encode( $merged_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
				}
			}
		}
	}
}
