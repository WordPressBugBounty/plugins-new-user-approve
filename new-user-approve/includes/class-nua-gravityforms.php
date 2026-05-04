<?php
/**
 * Gravity Forms Compatibility for New User Approve.
 *
 * @package NewUserApprove
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed
// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

// Hook the registration to gform_loaded.
add_action( 'gform_loaded', 'nua_register_gf_invitation_field_type' );

/**
 * Function to define and register the custom field.
 */
function nua_register_gf_invitation_field_type() {
	if ( ! class_exists( 'GF_Field' ) ) {
		return;
	}

	if ( ! class_exists( 'NUA_GRAVITYFORMS' ) ) {
		/**
		 * Custom Gravity Forms field for NUA Invitation Code.
		 */
		class NUA_GRAVITYFORMS extends GF_Field {

			/**
			 * Field type identifier.
			 *
			 * @var string
			 */
			public $type = 'nua_invitation_code';

			/**
			 * Get the field title for the form editor.
			 *
			 * @return string
			 */
			public function get_form_editor_field_title() {
				return esc_html__( 'Invitation Code', 'new-user-approve' );
			}

			/**
			 * Get the button configuration for the form editor.
			 *
			 * @return array
			 */
			public function get_form_editor_button() {
				return array(
					'group' => 'standard_fields',
					'text'  => $this->get_form_editor_field_title(),
				);
			}

			/**
			 * Get the field settings for the form editor.
			 *
			 * @return array
			 */
			public function get_form_editor_field_settings() {
				return array(
					'label_setting',
					'description_setting',
					'rules_setting',
					'placeholder_setting',
					'css_class_setting',
				);
			}

			/**
			 * Get the field input markup.
			 *
			 * @param array      $form  The current form.
			 * @param string     $value The field value.
			 * @param array|null $entry The current entry.
			 *
			 * @return string
			 */
			public function get_field_input( $form, $value = '', $entry = null ) {
				$is_form_editor = $this->is_form_editor();

				// Check if NUA Invitation Code is enabled in settings.
				$options    = get_option( 'new_user_approve_options' );
				$is_enabled = isset( $options['nua_invitation_code'] );

				// If disabled and NOT in editor, hide completely.
				if ( ! $is_enabled && ! $is_form_editor ) {
					return '';
				}

				if ( $is_form_editor ) {
					$msg = $is_enabled ? '' : ' <span style="color:red; font-size:10px;">(Disabled in NUA Settings)</span>';
					return '<div class="ginput_container ginput_container_text"><input type="text" disabled="disabled" class="large" value="' . esc_attr( $value ) . '" />' . $msg . '</div>';
				}

				$id          = (int) $this->id;
				$placeholder = $this->placeholder;
				$size        = $this->size;
				$class       = $this->type . ' ' . $size;

				return sprintf( "<div class='ginput_container ginput_container_text'><input name='input_%d' id='input_%d_%d' type='text' value='%s' class='%s' placeholder='%s' /></div>", $id, $form['id'], $id, esc_attr( $value ), esc_attr( $class ), esc_attr( $placeholder ) );
			}

			/**
			 * Get the field content, hiding if disabled on frontend.
			 *
			 * @param string $value                The field value.
			 * @param bool   $force_frontend_label  Whether to force frontend label.
			 * @param array  $form                  The current form.
			 *
			 * @return string
			 */
			public function get_field_content( $value, $force_frontend_label, $form ) {
				$options = get_option( 'new_user_approve_options' );
				if ( ! isset( $options['nua_invitation_code'] ) && ! $this->is_form_editor() ) {
					return '';
				}
				return parent::get_field_content( $value, $force_frontend_label, $form );
			}
		}

		GF_Fields::register( new NUA_GRAVITYFORMS() );
	}

	NUA_GravityForms_Validator::instance();
}

/**
 * Controller class for Handling Validation and NUA integration.
 */
class NUA_GravityForms_Validator {

	/**
	 * Singleton instance.
	 *
	 * @var NUA_GravityForms_Validator|null
	 */
	private static $instance;

	/**
	 * Get the singleton instance.
	 *
	 * @return NUA_GravityForms_Validator
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Registers hooks for invitation code handling.
	 */
	private function __construct() {
		add_filter( 'gform_validation', array( $this, 'validate_invitation_code' ) );

		// Hook after user is registered by GF User Registration add-on.
		add_action( 'gform_user_registered', array( $this, 'process_invitation_code_on_registration' ), 10, 4 );

		// Hook to set auto-approve status BEFORE admin email is sent.
		add_filter( 'new_user_approve_default_status', array( $this, 'check_invitation_code_before_email' ), 10, 2 );

		// Block admin approval email for auto-approved users with valid invitation codes.
		add_filter( 'nua_block_admin_approval_email', array( $this, 'block_admin_email_for_invited_users' ), 10, 3 );
	}


	/**
	 * Handle post-registration tasks for Gravity Forms submissions.
	 *
	 * @param int   $user_id   The registered user ID.
	 * @param array $feed      The GF User Registration feed.
	 * @param array $entry     The form entry.
	 * @param array $user_data The user data.
	 */
	public function process_invitation_code_on_registration( $user_id, $feed, $entry, $user_data ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			return;
		}

		$status = get_user_meta( $user_id, 'pw_user_status', true );
		$form   = GFAPI::get_form( $entry['form_id'] );
		$code   = '';

		// Find the invitation code field.
		foreach ( $form['fields'] as $field ) {
			if ( 'nua_invitation_code' === $field->type ) {
				$code = rgar( $entry, (string) $field->id );
				break;
			}
		}

		// Handle approved users (with valid invitation code).
		if ( 'approved' === $status && ! empty( $code ) ) {
			// Find the invitation code post.
			$posts = get_posts(
				array(
					'post_type'      => 'invitation_code',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'     => '_nua_code',
							'value'   => $code,
							'compare' => '=',
						),
					),
				)
			);

			if ( ! empty( $posts ) ) {
				$inv_id = $posts[0]->ID;

				// Update Usage Count.
				$current_usage = (int) get_post_meta( $inv_id, '_nua_usage_limit', true );
				--$current_usage;
				update_post_meta( $inv_id, '_nua_usage_limit', $current_usage );

				if ( $current_usage <= 0 ) {
					update_post_meta( $inv_id, '_nua_code_status', 'Expired' );
				}

				// Add User to Registered List.
				$registered_users = get_post_meta( $inv_id, '_registered_users', true );
				if ( ! is_array( $registered_users ) ) {
					$registered_users = array();
				}
				$registered_users[] = $user_id;
				update_post_meta( $inv_id, '_registered_users', $registered_users );

				// Trigger NUA approval action for integrations.
				do_action( 'new_user_approve_approve_user', $user_id );
			}
		}

		// Handle pending users (without invitation code or invalid code).
		// Send welcome email to pending users.
		if ( 'pending' === $status ) {
			$this->send_pending_user_welcome_email( $user_id, $user->user_login, $user->user_email );
		}
	}

	/**
	 * Send welcome email to pending users after Gravity Form registration.
	 *
	 * @param int    $user_id    The user ID.
	 * @param string $user_login The user login name.
	 * @param string $user_email The user email address.
	 */
	private function send_pending_user_welcome_email( $user_id, $user_login, $user_email ) {
		$options = get_option( 'new_user_approve_options' );

		// Check if welcome email is enabled.
		if ( ! isset( $options['nua_user_approve_welcome_email'] ) || 1 !== (int) $options['nua_user_approve_welcome_email'] ) {
			return;
		}

		// Allow other plugins to disable this email.
		$disable = apply_filters( 'nua_disable_welcome_email', false, $user_id );
		if ( true === $disable ) {
			return;
		}

		$subject = isset( $options['nua_user_approve_welcome_email_subject'] ) ? $options['nua_user_approve_welcome_email_subject'] : __( 'Welcome', 'new-user-approve' );
		$message = isset( $options['nua_user_approve_welcome_message'] ) ? $options['nua_user_approve_welcome_message'] : '';

		if ( empty( $message ) ) {
			return;
		}

		// Process email tags.
		$message = nua_do_email_tags( $message, array( 'context' => 'create_new_user' ) );
		$message = apply_filters( 'nua_user_welcome_message', $message, $user_login, $user_email, null );

		// Set HTML content type if needed.
		add_filter(
			'wp_mail_content_type',
			function () {
				return 'text/html';
			}
		);

		// Get email headers.
		$admin_email     = get_option( 'admin_email' );
		$from_name       = get_option( 'blogname' );
		$get_admin_email = ( isset( $options['nua_admin_email_sender'] ) && ! empty( $options['nua_admin_email_sender'] ) ) ? $options['nua_admin_email_sender'] : $admin_email;
		$headers         = array( "From: \"{$from_name}\" <{$get_admin_email}>\n" );
		$headers         = apply_filters( 'new_user_approve_email_header', $headers );

		// Send the email.
		wp_mail( $user_email, $subject, $message, $headers );

		// Remove HTML content type filter.
		remove_all_filters( 'wp_mail_content_type' );
	}

	/**
	 * Validate the invitation code field in a Gravity Form.
	 *
	 * @param array $validation_result The validation result array from Gravity Forms.
	 *
	 * @return array Modified validation result.
	 */
	public function validate_invitation_code( $validation_result ) {
		$options = get_option( 'new_user_approve_options' );
		// Do not validate if the feature is disabled in NUA settings.
		if ( ! isset( $options['nua_invitation_code'] ) ) {
			return $validation_result;
		}

		$form            = $validation_result['form'];
		$nua_field_found = false;
		$code            = '';
		$target_field    = null;

		foreach ( $form['fields'] as &$field ) {
			if ( 'nua_invitation_code' === $field->type ) {
				$nua_field_found = true;
				$target_field    = &$field;
				$code            = rgpost( 'input_' . $field->id );
				break;
			}
		}

		if ( ! $nua_field_found ) {
			return $validation_result;
		}

		$code = sanitize_text_field( $code );

		if ( ! empty( $code ) ) {
			$_POST['nua_invitation_code'] = $code; // phpcs:ignore WordPress.Security.NonceVerification.Missing

			if ( ! isset( $_POST['nua_invitation_code_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$_POST['nua_invitation_code_nonce'] = wp_create_nonce( 'nua_invitation_code_action' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
		}

		$required      = ! empty( $options['nua_checkbox_textbox'] );
		$error_message = '';

		if ( empty( $code ) ) {
			if ( $required ) {
				$error_message = __( 'Please add an Invitation code.', 'new-user-approve' );
			} else {
				return $validation_result;
			}
		} else {

			$args = array(
				'post_type'      => 'invitation_code',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'     => '_nua_code',
						'value'   => $code,
						'compare' => '=',
					),
					array(
						'key'     => '_nua_usage_limit',
						'value'   => '1',
						'compare' => '>=',
					),
					array(
						'key'     => '_nua_code_expiry',
						'value'   => time(),
						'compare' => '>=',
					),
					array(
						'key'     => '_nua_code_status',
						'value'   => 'Active',
						'compare' => '=',
					),
				),
			);

			$posts = get_posts( $args );

			$valid = false;
			if ( ! empty( $posts ) ) {
				foreach ( $posts as $post ) {
					$code_inv = get_post_meta( $post->ID, '_nua_code', true );
					if ( $code_inv === $code ) {
						$valid = true;
						break;
					}
				}
			}

			if ( ! $valid ) {
				$error_message = __( 'The Invitation code is invalid', 'new-user-approve' );
			} else {
				// Store valid code in transient for 60 seconds so other hooks can check it.
				set_transient( 'nua_gf_valid_inv_code_' . $code, true, 60 );
			}
		}

		if ( ! empty( $error_message ) ) {
			$validation_result['is_valid']    = false;
			$target_field->failed_validation  = true;
			$target_field->validation_message = $error_message;
		}

		$validation_result['form'] = $form;
		return $validation_result;
	}

	/**
	 * Check for valid invitation code BEFORE admin email is sent.
	 * This runs on new_user_approve_default_status filter.
	 *
	 * @param string $status  The default user status.
	 * @param int    $user_id The user ID.
	 *
	 * @return string The user status.
	 */
	public function check_invitation_code_before_email( $status, $user_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$options = get_option( 'new_user_approve_options' );
		// Do not check if the feature is disabled in NUA settings.
		if ( ! isset( $options['nua_invitation_code'] ) ) {
			return $status;
		}

		// Check ALL transients that start with our prefix to find a valid one.
		// This is more reliable than checking $_POST which may not be available.
		global $wpdb;
		$transient_prefix = '_transient_nua_gf_valid_inv_code_';
		$transients       = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s",
				$wpdb->esc_like( $transient_prefix ) . '%'
			)
		);

		if ( ! empty( $transients ) ) {
			// Found a valid invitation code transient - user should be approved.
			foreach ( $transients as $transient_name ) {
				$code = str_replace( $transient_prefix, '', $transient_name );
				// Delete this transient after use.
				delete_transient( 'nua_gf_valid_inv_code_' . $code );
				return 'approved';
			}
		}

		// Otherwise, return the default status (pending).
		return $status;
	}

	/**
	 * Block admin approval email for users who are already approved.
	 * This runs on nua_block_admin_approval_email filter.
	 *
	 * @param bool   $block      Whether to block the email.
	 * @param string $user_login The user login name.
	 * @param string $user_email The user email address.
	 *
	 * @return bool Whether to block the email.
	 */
	public function block_admin_email_for_invited_users( $block, $user_login, $user_email ) {
		// Get the user by email.
		$user = get_user_by( 'email', $user_email );

		// If user not found, try by login.
		if ( ! $user ) {
			$user = get_user_by( 'login', $user_login );
		}

		// If still no user found, don't block.
		if ( ! $user ) {
			return $block;
		}

		// Check the user's current status.
		$status = get_user_meta( $user->ID, 'pw_user_status', true );

		// If user is already approved, block the admin notification email.
		// Approved users shouldn't trigger pending user emails.
		if ( 'approved' === $status ) {
			return true;
		}

		// Otherwise, don't block (allow email for pending users).
		return $block;
	}
}
