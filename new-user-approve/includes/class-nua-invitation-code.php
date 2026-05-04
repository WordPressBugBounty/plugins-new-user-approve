<?php
/**  Copyright 2013
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @package New_User_Approve
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}
if ( ! class_exists( 'NUA_Invitation_Code' ) ) {
	/**
	 * Handles invitation code functionality for user registration.
	 */
	class NUA_Invitation_Code {

		/**
		 * Singleton instance.
		 *
		 * @var NUA_Invitation_Code
		 */
		private static $instance;

		/**
		 * Post type for invitation codes.
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
		 * Meta key for code status.
		 *
		 * @var string
		 */
		public $status_key = '_nua_code_status';

		/**
		 * Meta key for the invitation code value.
		 *
		 * @var string
		 */
		public $code_key = '_nua_code';

		/**
		 * Meta key for total code usage count.
		 *
		 * @var string
		 */
		public $total_code_key = '_total_nua_code';

		/**
		 * Meta key for registered users list.
		 *
		 * @var string
		 */
		public $registered_users = '_registered_users';

		/**
		 * Option group name.
		 *
		 * @var string
		 */
		private $option_group = 'nua_options_group';

		/**
		 * Option key for stored settings.
		 *
		 * @var string
		 */
		public $option_key = 'new_user_approve_options';
		/**
		 * Returns the main instance.
		 *
		 * @return NUA_Invitation_Code
		 */
		public static function instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new NUA_Invitation_Code();
			}
			return self::$instance;
		}

		/**
		 * Constructor. Registers hooks for invitation code functionality.
		 */
		private function __construct() {

			// Filter.

			add_filter(
				'nua_disable_welcome_email',
				array( $this, 'nua_disable_welcome_email_callback' ),
				10,
				2
			);

			$options = get_option( 'new_user_approve_options' );
			if (
				isset( $options['nua_free_invitation'] ) &&
				'enable' === $options['nua_free_invitation']
			) {
				add_action(
					'register_form',
					array(
						$this,
						'nua_invitation_code_field',
					)
				);
				add_filter(
					'register_post',
					array( $this, 'nua_invitation_status_code_field_validation' ),
					6,
					3
				);
				add_filter(
					'woocommerce_register_post',
					array( $this, 'nua_woocommerce_invitation_code_validation' ),
					10,
					3
				);

				add_filter(
					'new_user_approve_default_status',
					array( $this, 'nua_invitation_status_code' ),
					10,
					2
				);
				add_action(
					'woocommerce_register_form',
					array(
						$this,
						'nua_invitation_code_field',
					)
				);
				// compatibility with Ultimate Member plugin.
				add_action(
					'um_after_form_fields',
					array( $this, 'um_nua_invitation_code_field' ),
					10,
					1
				);
				add_action(
					'um_submit_form_errors_hook__registration',
					array( $this, 'um_invite_code_check' ),
					20,
					1
				);
				add_action(
					'um_submit_form_errors_hook__profile',
					array( $this, 'um_invite_code_check' ),
					20,
					1
				);
				add_action(
					'um_submit_form_errors_hook_login',
					array( $this, 'um_invite_code_check' ),
					20,
					1
				);
				add_action(
					'nua_invited_user',
					array( $this, 'message_above_regform' ),
					10,
					1
				);
				// compatibility with UsersWP plugin.
				add_action(
					'uwp_template_fields',
					array( $this, 'uwp_nua_invitation_code_field' ),
					10,
					1
				);
				add_filter(
					'uwp_validate_fields_before',
					array( $this, 'uwp_invite_code_check' ),
					10,
					3
				);
				// compatibility with LearnPress checkout registration.
				add_filter( 'registration_errors', array( $this, 'learnpress_invitation_code_check' ), 6, 3 );
				add_action( 'nua_invited_user', array( $this, 'learnpress_auto_approve_message' ), 10, 1 );
				add_action( 'learn_press_checkout_register_form', array( $this, 'nua_invitation_code_field' ) );
				add_action( 'learn_press_register_form', array( $this, 'nua_invitation_code_field' ) );

			}
		}

		/**
		 * Validate invitation code during LearnPress checkout/register.
		 *
		 * @param WP_Error $errors     The registration errors object.
		 * @param string   $user_login The user login (unused).
		 * @param string   $user_email The user email (unused).
		 * @return WP_Error
		 */
		public function learnpress_invitation_code_check( $errors, $user_login, $user_email ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			// Only run for LearnPress checkout/register forms.
			if ( ! isset( $_POST['learn-press-checkout-nonce'] ) && ! isset( $_POST['reg_email'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				return $errors;
			}

			$options = get_option( 'new_user_approve_options' );

			// Skip nonce for reuse (verified below).
			if ( isset( $_POST['nua_invitation_code_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nua_invitation_code_nonce'] ) ), 'nua_invitation_code_action' ) ) {
				if ( isset( $_POST['nua_invitation_code'] ) && ! empty( $_POST['nua_invitation_code'] ) ) {
					$args  = array(
						'numberposts' => -1,
						'post_type'   => $this->code_post_type,
						'post_status' => 'publish',
						'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							'relation' => 'AND',
							array(
								array(
									'key'     => $this->code_key,
									'value'   => sanitize_text_field( wp_unslash( $_POST['nua_invitation_code'] ) ),
									'compare' => '=',
								),
								array(
									'key'     => $this->usage_limit_key,
									'value'   => '1',
									'compare' => '>=',
								),
								array(
									'key'     => $this->expiry_date_key,
									'value'   => time(),
									'compare' => '>=',
								),
								array(
									'key'     => $this->status_key,
									'value'   => 'Active',
									'compare' => '=',
								),
							),
						),
					);
					$posts = get_posts( $args );
					$flag  = true;
					foreach ( $posts as $post_inv ) {
						$code_inv = get_post_meta( $post_inv->ID, $this->code_key, true );
						if ( sanitize_text_field( wp_unslash( $_POST['nua_invitation_code'] ) ) === $code_inv ) {
							$flag = false;
							global $inv_file_lock;
							$inv_file_lock = $this->invite_code_hold( $post_inv->ID );
							if ( false === $inv_file_lock ) {
								$err = new WP_Error();
								$err->add( 'invcode_error', '<strong>Notice</strong>: Server is busy, please try again!' );
								return $err;
							}
							// Code is valid — return original $errors (null) so LearnPress proceeds.
							return $errors;
						}
					}
					if ( $flag ) {
						$err = new WP_Error();
						$err->add( 'invcode_error', '<strong>ERROR</strong>: The Invitation code is invalid' );
						return $err;
					}
				} elseif ( ! empty( $options['nua_checkbox_textbox'] ) ) {
					$err = new WP_Error();
					$err->add( 'invcode_error', '<strong>ERROR</strong>: Please add an Invitation code.' );
					return $err;
				}
			} elseif ( ! empty( $options['nua_checkbox_textbox'] ) && empty( $_POST['nua_invitation_code'] ) ) {
				$err = new WP_Error();
				$err->add( 'invcode_error', '<strong>ERROR</strong>: Please add an Invitation code.' );
				return $err;
			}

			// No errors — return original $errors (null) so LearnPress does not throw.
			return $errors;
		}

		/**
		 * Display auto-approve message after LearnPress registration.
		 *
		 * @param int $user_id The user ID (unused).
		 * @return void
		 */
		public function learnpress_auto_approve_message( $user_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			// Only act during a LearnPress registration.
			if ( ! isset( $_POST['learn-press-checkout-nonce'] ) && ! isset( $_POST['reg_email'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				return;
			}
			if ( ! function_exists( 'learn_press_set_message' ) ) {
				return;
			}
			$message = nua_auto_approve_message();
			learn_press_set_message(
				array(
					'status'  => 'success',
					'content' => wp_kses_post( $message ),
				)
			);
		}

		/**
		 * Output the invitation code input field on Ultimate Member registration forms.
		 *
		 * @param array $args The UM form arguments.
		 * @return void
		 */
		public function um_nua_invitation_code_field( $args ) {
			// Only render on registration forms, not profile or login forms.
			if ( empty( $args['mode'] ) || 'register' !== $args['mode'] ) {
				return;
			}

			$flag = apply_filters( 'um_nua_hide_invitation_code_field', false, $args );

			if ( $flag ) {
				return;
			}

			$form_id = $args['form_id'];
			$options = get_option( 'new_user_approve_options' );
			?>
			<div class="um-field-label"><label> <?php esc_html_e( 'Invitation Code', 'new-user-approve' ); ?></label>
			  <div class="um-clear"></div>
			</div>
			<div id="um_field_<?php echo esc_attr( $form_id ); ?>_nua_invitation_code"
				class="um-field um-field-text  um-field-nua_invitation_code um-field-text um-field-type_text"
				data-key="nua_invitation_code">
				<div class="um-field-area">
					<input autocomplete="off" class="um-form-field" type="text" name="nua_invitation_code"
						id="nua_invitation_code-<?php echo esc_attr( $form_id ); ?>"
						value="<?php echo esc_attr( isset( $_POST[ 'nua_invitation_code-' . esc_attr( $form_id ) ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'nua_invitation_code-' . esc_attr( $form_id ) ] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing ?>"> 
					<?php wp_nonce_field( 'nua_invitation_code_action', 'nua_invitation_code_nonce' ); ?>
				</div>
				<?php if ( isset( UM()->form()->errors['nua_invitation_code'] ) ) : ?>
					<div class="um-field-error" id="um-error-for-nua_invitation_code-<?php echo esc_attr( $form_id ); ?>">
						<span class="um-field-arrow"><i class="um-faicon-caret-up"></i></span>
						<?php echo esc_html( UM()->form()->errors['nua_invitation_code'] ); ?>
					</div>
				<?php endif; ?>
			</div>
			<?php
		}

		/**
		 * Validate invitation code on Ultimate Member registration forms.
		 *
		 * @param array $submitted_data The submitted form data.
		 * @return void
		 */
		public function um_invite_code_check( $submitted_data ) {
			$options = get_option( 'new_user_approve_options' );

			if ( isset( $submitted_data['nua_invitation_code_nonce'] ) && wp_verify_nonce( $submitted_data['nua_invitation_code_nonce'], 'nua_invitation_code_action' ) ) {
				if ( isset( $submitted_data['nua_invitation_code'] ) && ! empty( $submitted_data['nua_invitation_code'] ) ) {
					$args = array(
						'numberposts' => -1,
						'post_type'   => $this->code_post_type,
						'post_status' => 'publish',
						'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							'relation' => 'AND',
							array(
								array(
									'key'     => $this->code_key,
									'value'   => sanitize_text_field(
										$submitted_data['nua_invitation_code']
									),
									'compare' => '=',
								),
								array(
									'key'     => $this->usage_limit_key,
									'value'   => '1',
									'compare' => '>=',
								),
								array(
									'key'     => $this->expiry_date_key,
									'value'   => time(),
									'compare' => '>=',
								),
								array(
									'key'     => $this->status_key,
									'value'   => 'Active',
									'compare' => '=',
								),
							),
						),
					);

					$posts = get_posts( $args );
					$flag  = true;

					foreach ( $posts as $post_inv ) {
						$code_inv = get_post_meta( $post_inv->ID, $this->code_key, true );

						if ( sanitize_text_field( $submitted_data['nua_invitation_code'] ) === $code_inv ) {
							$flag = false;
							global $inv_file_lock;

							$inv_file_lock = $this->invite_code_hold( $post_inv->ID );
							if ( false === $inv_file_lock ) {
								UM()->form()->add_error( 'nua_invitation_code', 'Server is busy, please try again!' );
							}
						}
					}

					if ( $flag ) {
						UM()->form()->add_error( 'nua_invitation_code', 'The Invitation code is invalid' );
					}

					if ( isset( $submitted_data['nua_invitation_code'] ) && isset( $options['nua_registration_deadline'] ) && ! isset( $options['nua_auto_approve_deadline'] ) ) {
						UM()->form()->add_error( 'nua_invitation_code', 'Cannot use Code because deadline exceeded.' );
					}
				} elseif ( ! isset( $submitted_data['nua_invitation_code'] ) || ( isset( $submitted_data['nua_invitation_code'] ) && empty( $submitted_data['nua_invitation_code'] ) && ! empty( $options['nua_checkbox_textbox'] ) ) ) {
					UM()->form()->add_error( 'nua_invitation_code', 'Please add an Invitation code.' );
				}
			} elseif ( ! isset( $submitted_data['nua_invitation_code'] ) || ( isset( $submitted_data['nua_invitation_code'] ) && empty( $submitted_data['nua_invitation_code'] ) && ! empty( $options['nua_checkbox_textbox'] ) ) ) {
				UM()->form()->add_error( 'nua_invitation_code', 'Something went wrong.' );
			}
		}

		/**
		 * Output the invitation code input field on UsersWP registration forms.
		 *
		 * @param string $form_type The form type.
		 * @return void
		 */
		public function uwp_nua_invitation_code_field( $form_type ) {
			if ( 'register' !== $form_type ) {
				return;
			}
			$options  = get_option( 'new_user_approve_options' );
			$required = false;
			if ( ! empty( $options['nua_checkbox_textbox'] ) ) {
				$required = true;
			}
			?>

			<p class="nua_inv_field form-group">
				<?php if ( true === $required ) : ?>
					<!-- snfr -->
					<label for="invitation_code">
					<?php
					esc_html_e(
						'Invitation Code',
						'new-user-approve'
					);
					?>
					&nbsp;
						<span id="nua-required" aria-hidden="true" style="color:#a00">*</span>
						<span class="screen-reader-text">Required</span>
					</label>
				<?php else : ?>
					<!-- snfr -->
					<label> <?php esc_html_e( 'Invitation Code', 'new-user-approve' ); ?></label>
				<?php endif; ?>
				<input type="text" class="nua_invitation_code form-control" name="nua_invitation_code" />
				<?php
				wp_nonce_field(
					'nua_invitation_code_action',
					'nua_invitation_code_nonce'
				);
				?>
			</p>
			<?php
		}

		/**
		 * Validate invitation code on UsersWP registration forms.
		 *
		 * @param WP_Error $errors The errors object.
		 * @param array    $data   The submitted form data.
		 * @param string   $type   The form type.
		 * @return WP_Error
		 */
		public function uwp_invite_code_check( $errors, $data, $type ) {
			if ( 'register' !== $type ) {
				return $errors;
			}

			$options = get_option( 'new_user_approve_options' );

			// Use POST for nonce verification.
			if (
				isset( $_POST['nua_invitation_code_nonce'] ) &&
				wp_verify_nonce(
					sanitize_text_field( wp_unslash( $_POST['nua_invitation_code_nonce'] ) ),
					'nua_invitation_code_action'
				)
			) {
				if (
					isset( $data['nua_invitation_code'] ) &&
					! empty( $data['nua_invitation_code'] )
				) {
					$args = array(
						'numberposts' => -1,
						'post_type'   => $this->code_post_type,
						'post_status' => 'publish',
						'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							'relation' => 'AND',
							array(
								array(
									'key'     => $this->code_key,
									'value'   => sanitize_text_field(
										$data['nua_invitation_code']
									),
									'compare' => '=',
								),
								array(
									'key'     => $this->usage_limit_key,
									'value'   => '1',
									'compare' => '>=',
								),
								array(
									'key'     => $this->expiry_date_key,
									'value'   => time(),
									'compare' => '>=',
								),
								array(
									'key'     => $this->status_key,
									'value'   => 'Active',
									'compare' => '=',
								),
							),
						),
					);

					$posts = get_posts( $args );
					$flag  = true;

					foreach ( $posts as $post_inv ) {
						$code_inv = get_post_meta(
							$post_inv->ID,
							$this->code_key,
							true
						);
						if (
							sanitize_text_field( $data['nua_invitation_code'] ) ===
							$code_inv
						) {
							$flag = false;
							global $inv_file_lock;
							$inv_file_lock = $this->invite_code_hold(
								$post_inv->ID
							);
							if ( false === $inv_file_lock ) {
								$errors->add(
									'invcode_error',
									'<strong>Notice</strong>: Server is busy, please try again!'
								);
								return $errors;
							}
							return $errors;
						}
					}

					if ( $flag ) {
						$errors->add(
							'invcode_error',
							'<strong>ERROR</strong>: The Invitation code is invalid'
						);
						return $errors;
					}

					if (
						isset( $data['nua_invitation_code'] ) &&
						isset( $options['nua_registration_deadline'] ) &&
						! isset( $options['nua_auto_approve_deadline'] )
					) {
						$errors->add(
							'invcode_error',
							'<strong>Error</strong>: Cannot use Code because deadline exceeded.'
						);
					}
				} elseif (
					! isset( $data['nua_invitation_code'] ) ||
					( isset( $data['nua_invitation_code'] ) &&
						empty( $data['nua_invitation_code'] ) &&
						! empty( $options['nua_checkbox_textbox'] ) )
				) {
					$errors->add(
						'invcode_error',
						'<strong>ERROR</strong>: Please add an Invitation code.'
					);
				}
			} elseif (
				! isset( $data['nua_invitation_code'] ) ||
				( isset( $data['nua_invitation_code'] ) &&
					empty( $data['nua_invitation_code'] ) &&
					! empty( $options['nua_checkbox_textbox'] ) )
			) {
				$errors->add(
					'invcode_nonce_error',
					'<strong>ERROR</strong>: Something went wrong.'
				);
			}

			return $errors;
		}

		/**
		 * Disable the welcome email for auto-approved users.
		 *
		 * @param bool $disabled Whether the email is disabled.
		 * @param int  $user_id  The user ID.
		 * @return bool
		 */
		public function nua_disable_welcome_email_callback( $disabled, $user_id ) {
			$status = get_user_meta( $user_id, 'pw_user_status', true );
			if ( 'approved' === $status ) {
				$disabled = true;
			}
			return $disabled;
		}



		/**
		 * Check if an invitation code already exists.
		 *
		 * @since 2.5.2
		 * @param string $code The invitation code to check.
		 * @return bool
		 */
		public function invitation_code_already_exists( $code ) {
			$posts_with_meta = get_posts(
				array(
					'posts_per_page' => 1, // We only want to check if any exists, so don't need to get all of them.
					'meta_key'       => $this->code_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => $code, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'post_type'      => $this->code_post_type,
				)
			);

			if ( count( $posts_with_meta ) ) {
				return true;
			}
			return false;
		}
		/**
		 * Check if an invitation code is within its usage limit.
		 *
		 * @since 2.5.2
		 * @param string $code The invitation code to check.
		 * @return bool
		 */
		public function invitation_code_limit_check( $code ) {
			$is_inv_code_limit = array(
				'numberposts' => 1,
				'post_type'   => $this->code_post_type,
				// We are checking two things: code and its limit, so we are using meta query.
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						array(
							'key'     => $this->code_key,
							'value'   => $code,
							'compare' => '=',
						),
						array(
							'key'     => $this->usage_limit_key,
							'value'   => '1',
							'compare' => '>=',
						),
						array(
							'relation' => 'OR',
							array(
								'key'     => $this->status_key,
								'value'   => 'Active',
								'compare' => '=',
							),
							array(
								'key'     => $this->status_key,
								'value'   => 'Expired',
								'compare' => '=',
							),
						),
					),
				),
			);

			$is_inv_code_limit = get_posts( $is_inv_code_limit );
			if ( count( $is_inv_code_limit ) ) {
				return true;
			} else {
				return false;
			}
		}
		/**
		 * Check if an invitation code has not yet expired.
		 *
		 * @since 2.5.2
		 * @param string $code The invitation code to check.
		 * @return bool
		 */
		public function invitation_code_expiry_check( $code ) {
			$is_inv_code_expired = array(
				'numberposts' => 1,
				'post_type'   => $this->code_post_type,
				// We are checking two things: code and its expiry, so we are using meta query.
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						array(
							'key'     => $this->code_key,
							'value'   => $code,
							'compare' => '=',
						),
						array(
							'key'     => $this->expiry_date_key,
							'value'   => time(),
							'compare' => '>=',
						),
						array(
							'relation' => 'OR',
							array(
								'key'     => $this->status_key,
								'value'   => 'Active',
								'compare' => '=',
							),
							array(
								'key'     => $this->status_key,
								'value'   => 'Expired',
								'compare' => '=',
							),
						),
					),
				),
			);

			$is_inv_code_expired = get_posts( $is_inv_code_expired );

			if ( count( $is_inv_code_expired ) ) {
				return false;
			} else {
				return true;
			}
		}

		/**
		 * Get all currently available (active, non-expired) invitation codes.
		 *
		 * @return WP_Post[]
		 */
		public function get_available_invitation_codes() {
			$args = array(
				'numberposts' => -1,
				'post_type'   => $this->code_post_type,
				'post_status' => 'publish',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						array(
							'key'     => $this->usage_limit_key,
							'value'   => '1',
							'compare' => '>=',
						),

						array(
							'key'     => $this->expiry_date_key,
							'value'   => time(),
							'compare' => '>=',
						),
						array(
							'key'     => $this->status_key,
							'value'   => 'Active',
							'compare' => '=',
						),
					),
				),
			);

			$codes = get_posts( $args );

			return $codes;
		}

		/**
		 * Output the invitation code input field on the registration form.
		 *
		 * @return void
		 */
		public function nua_invitation_code_field() {
			$required = ' *';
			if ( true === apply_filters( 'nua_invitation_code_optional', true ) ) {
				$required = ' (optional)';
			}
			?>

			<p>
				<label> 
				<?php
				esc_html_e(
					'Invitation Code',
					'new-user-approve'
				);
				?>
				<span><?php echo esc_attr( $required ); ?></span></label>
				<?php wp_nonce_field( 'nua_invitation_code_action', 'nua_invitation_code_nonce' ); ?>
				<input type="text" class="nua_invitation_code" name="nua_invitation_code" />
			</p>
			<?php
		}

		/**
		 * Display admin notice when an invitation code already exists.
		 *
		 * @since 2.5.2
		 */
		public function inv_code_alreay_exists_notification() {
			$class   = 'notice notice-error';
			$message = esc_html__(
				'Invitation Code Already Exists.',
				'new-user-approve'
			);
			printf(
				'<div class="%1$s"><p>%2$s</p></div>',
				esc_attr( $class ),
				esc_html( $message )
			);
			delete_transient( 'inv_code_exists' ); // No need to keep this tip after displaying notification.
		}

		/**
		 * Acquire a file lock for an invitation code to prevent race conditions.
		 *
		 * @param int $inv_id The invitation code post ID.
		 * @return resource|false
		 */
		public function invite_code_hold( $inv_id ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			$inv_file = fopen( $this->invite_code_lock_file( $inv_id ), 'w+' );

			if ( ! flock( $inv_file, LOCK_EX | LOCK_NB ) ) {
				return false;
			}

			ftruncate( $inv_file, 0 );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
			fwrite( $inv_file, microtime( true ) );
			return $inv_file;
		}

		/**
		 * Release an invitation code file lock.
		 *
		 * @param resource|false $inv_file The file lock resource.
		 * @param int            $inv_id   The invitation code post ID.
		 * @return bool
		 */
		public function invite_code_release( $inv_file, $inv_id ) {
			if ( is_resource( $inv_file ) ) {
				fflush( $inv_file );
				flock( $inv_file, LOCK_UN );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				fclose( $inv_file );
				wp_delete_file( $this->invite_code_lock_file( $inv_id ) );

				return true;
			}

			return false;
		}

		/**
		 * Return the path to the lock file for an invitation code.
		 *
		 * @param int $inv_id The invitation code post ID.
		 * @return string
		 */
		public function invite_code_lock_file( $inv_id ) {
			return apply_filters(
				'invite_code_lock_file',
				get_temp_dir() . '/invite-code' . $inv_id . '.lock',
				$inv_id
			);
		}

		/**
		 * Validate invitation code on the default WP registration form.
		 *
		 * @param string   $user_login The user login.
		 * @param string   $user_email The user email.
		 * @param WP_Error $errors     The registration errors object.
		 * @return WP_Error
		 */
		public function nua_invitation_status_code_field_validation(
			$user_login,
			$user_email,
			$errors
		) {
			$options       = get_option( 'new_user_approve_options' );
			$code_optional = apply_filters(
				'nua_invitation_code_optional',
				true
			);
			$nonce         = isset( $_POST['nua_invitation_code_nonce_field'] )
				? sanitize_text_field(
					wp_unslash( $_POST['nua_invitation_code_nonce_field'] )
				)
				: '';
			if ( ! wp_verify_nonce( $nonce, 'nua-invitation-code-nonce' ) ) {
				$nonce = '';
			}

			if (
				isset( $_POST['nua_invitation_code'] ) &&
				! empty( $_POST['nua_invitation_code'] )
			) {
				// Display the error on registration form when invitation code has expired or limit exceeded.
				$code              = sanitize_text_field(
					wp_unslash( $_POST['nua_invitation_code'] )
				);
				$is_inv_code_exist = $this->invitation_code_already_exists(
					$code
				);
				$is_inv_code_limit = $this->invitation_code_limit_check( $code );
				$is_inv_expired    = $this->invitation_code_expiry_check( $code );
				if ( true === $is_inv_code_exist && true === $is_inv_expired ) {
					$error_message = apply_filters(
						'nua_invitation_code_err',
						__(
							'<strong>ERROR</strong>: Invitation code has been expired',
							'new-user-approve'
						),
						'',
						$errors
					);
					$errors->add( 'invcode_error', $error_message );
					return $errors;
				} elseif (
					true === $is_inv_code_exist &&
					false === $is_inv_code_limit
				) {
					$error_message = apply_filters(
						'nua_invitation_code_err',
						__(
							'<strong>ERROR</strong>: Invitation code limit_exceeded',
							'new-user-approve'
						),
						'',
						$errors
					);
					$errors->add( 'invcode_error', $error_message );
					return $errors;
				}

				$args     = array(
					'numberposts' => -1,
					'post_type'   => $this->code_post_type,
					'post_status' => 'publish',
					'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						'relation' => 'AND',
						array(
							array(
								'key'     => $this->code_key,
								'value'   => sanitize_text_field(
									wp_unslash( $_POST['nua_invitation_code'] )
								),
								'compare' => '=',
							),
							array(
								'key'     => $this->usage_limit_key,
								'value'   => '1',
								'compare' => '>=',
							),
							array(
								'key'     => $this->expiry_date_key,
								'value'   => time(),
								'compare' => '>=',
							),
							array(
								'key'     => $this->status_key,
								'value'   => 'Active',
								'compare' => '=',
							),
						),
					),
				);
				$posts    = get_posts( $args );
				$code_inv = '';
				foreach ( $posts as $post_inv ) {
					$code_inv = get_post_meta(
						$post_inv->ID,
						$this->code_key,
						true
					);

					if ( sanitize_text_field( wp_unslash( $_POST['nua_invitation_code'] ) ) === $code_inv ) {
						global $inv_file_lock;
						$inv_file_lock = $this->invite_code_hold( $post_inv->ID );
						if ( false === $inv_file_lock ) {
							$errors->add(
								'invcode_error',
								'<strong>Notice</strong>: Server is busy, please try again!'
							);
							return $errors;
						}

						return $errors;
					}
				}

				$error_message = apply_filters(
					'nua_invitation_code_err',
					__(
						'<strong>ERROR</strong>: The Invitation code is invalid',
						'new-user-approve'
					),
					$code_inv,
					$errors
				);
				$errors->add( 'invcode_error', $error_message );
			} elseif (
				! isset( $_POST['nua_invitation_code'] ) ||
				( isset( $_POST['nua_invitation_code'] ) &&
					empty( $_POST['nua_invitation_code'] ) &&
					! empty( get_option( 'nua_free_invitation' ) ) &&
					true !== $code_optional )
			) {
				$error_message = apply_filters(
					'nua_invitation_code_err',
					__(
						'<strong>ERROR</strong>: Please add an Invitation code.',
						'new-user-approve'
					),
					'',
					$errors
				);
				$errors->add( 'invcode_error', $error_message );
			}
			return $errors;
		}

		/**
		 * Validate invitation code on WooCommerce registration form.
		 *
		 * @param string   $username         The username.
		 * @param string   $email            The email address.
		 * @param WP_Error $validation_errors The validation errors object.
		 * @return WP_Error
		 */
		public function nua_woocommerce_invitation_code_validation(
			$username,
			$email,
			$validation_errors
		) {
			$code_optional = apply_filters(
				'nua_invitation_code_optional',
				true
			);

			$nonce = isset( $_POST['nua_invitation_code_nonce_field'] )
				? sanitize_text_field(
					wp_unslash( $_POST['nua_invitation_code_nonce_field'] )
				)
				: '';
			if ( ! wp_verify_nonce( $nonce, 'nua-invitation-code-nonce' ) ) {
				$nonce = '';
			}

			if ( isset( $_POST['nua_invitation_code'] ) ) {
				$code = sanitize_text_field(
					wp_unslash( $_POST['nua_invitation_code'] )
				);

				if ( empty( $code ) ) {
					// Don't run validation if field is empty and it's optional.
					if (
						! empty( get_option( 'nua_free_invitation' ) ) &&
						true !== $code_optional
					) {
						$error_message = apply_filters(
							'nua_invitation_code_err',
							__(
								'<strong>ERROR</strong>: Please add an Invitation code.',
								'new-user-approve'
							),
							'',
							$validation_errors
						);
						$validation_errors->add(
							'invcode_error',
							$error_message
						);
					}
					return $validation_errors;
				}

				$is_inv_code_exist = $this->invitation_code_already_exists(
					$code
				);
				$is_inv_code_limit = $this->invitation_code_limit_check( $code );
				$is_inv_expired    = $this->invitation_code_expiry_check( $code );
				if ( true === $is_inv_code_exist && true === $is_inv_expired ) {
					$error_message = apply_filters(
						'nua_invitation_code_err',
						__(
							'<strong>ERROR</strong>: Invitation code has been expired',
							'new-user-approve'
						),
						'',
						$validation_errors
					);
					$validation_errors->add( 'invcode_error', $error_message );
					return $validation_errors;
				} elseif (
					true === $is_inv_code_exist &&
					false === $is_inv_code_limit
				) {
					$error_message = apply_filters(
						'nua_invitation_code_err',
						__(
							'<strong>ERROR</strong>: Invitation code limit_exceeded',
							'new-user-approve'
						),
						'',
						$validation_errors
					);
					$validation_errors->add( 'invcode_error', $error_message );
					return $validation_errors;
				}

				$args     = array(
					'numberposts' => -1,
					'post_type'   => $this->code_post_type,
					'post_status' => 'publish',
					'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						'relation' => 'AND',
						array(
							array(
								'key'     => $this->code_key,
								'value'   => sanitize_text_field(
									wp_unslash( $_POST['nua_invitation_code'] )
								),
								'compare' => '=',
							),
							array(
								'key'     => $this->usage_limit_key,
								'value'   => '1',
								'compare' => '>=',
							),
							array(
								'key'     => $this->expiry_date_key,
								'value'   => time(),
								'compare' => '>=',
							),
							array(
								'key'     => $this->status_key,
								'value'   => 'Active',
								'compare' => '=',
							),
						),
					),
				);
				$posts    = get_posts( $args );
				$code_inv = '';
				foreach ( $posts as $post_inv ) {
					$code_inv = get_post_meta(
						$post_inv->ID,
						$this->code_key,
						true
					);

					if ( sanitize_text_field( wp_unslash( $_POST['nua_invitation_code'] ) ) === $code_inv ) {
						global $inv_file_lock;
						$inv_file_lock = $this->invite_code_hold( $post_inv->ID );
						if ( false === $inv_file_lock ) {
							$validation_errors->add(
								'invcode_error',
								'<strong>Notice</strong>: Server is busy, please try again!'
							);
							return $validation_errors;
						}

						return $validation_errors;
					}
				}

				$error_message = apply_filters(
					'nua_invitation_code_err',
					__(
						'<strong>ERROR</strong>: The Invitation code is invalid',
						'new-user-approve'
					),
					$code_inv,
					$validation_errors
				);
				$validation_errors->add( 'invcode_error', $error_message );
			}

			return $validation_errors;
		}

		/**
		 * Update invitation code status after user registration.
		 *
		 * @param string $status  The current user status.
		 * @param int    $user_id The newly registered user ID.
		 * @return string
		 */
		public function nua_invitation_status_code( $status, $user_id ) {
			$nonce = isset( $_POST['nua_invitation_code_nonce_field'] )
				? sanitize_text_field(
					wp_unslash( $_POST['nua_invitation_code_nonce_field'] )
				)
				: '';
			if ( ! wp_verify_nonce( $nonce, 'nua-invitation-code-nonce' ) ) {
				$nonce = '';
			}

			if (
				isset( $_POST['nua_invitation_code'] ) &&
				! empty( $_POST['nua_invitation_code'] )
			) {
				$args = array(
					'numberposts' => -1,
					'post_type'   => $this->code_post_type,
					'post_status' => 'publish',
					'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						'relation' => 'AND',
						array(
							array(
								'key'     => $this->code_key,
								'value'   => sanitize_text_field(
									wp_unslash( $_POST['nua_invitation_code'] )
								),
								'compare' => '=',
							),
							array(
								'key'     => $this->usage_limit_key,
								'value'   => '1',
								'compare' => '>=',
							),
							array(
								'key'     => $this->expiry_date_key,
								'value'   => time(),
								'compare' => '>=',
							),
							array(
								'key'     => $this->status_key,
								'value'   => 'Active',
								'compare' => '=',
							),
						),
					),
				);

				$posts = get_posts( $args );

				foreach ( $posts as $post_inv ) {
					$code_inv = get_post_meta(
						$post_inv->ID,
						$this->code_key,
						true
					);

					if (
						sanitize_text_field(
							wp_unslash( $_POST['nua_invitation_code'] )
						) === $code_inv
					) {
						$register_user = get_post_meta(
							$post_inv->ID,
							$this->registered_users,
							true
						);

						if ( empty( $register_user ) ) {
							update_post_meta(
								$post_inv->ID,
								$this->registered_users,
								array( $user_id )
							);
						} else {
							$register_user[] = $user_id;
							update_post_meta(
								$post_inv->ID,
								$this->registered_users,
								$register_user
							);
						}
						$current_useage = get_post_meta(
							$post_inv->ID,
							$this->usage_limit_key,
							true
						);
						--$current_useage;
						update_post_meta(
							$post_inv->ID,
							$this->usage_limit_key,
							$current_useage
						);
						// Release lock.
						global $inv_file_lock;
						$this->invite_code_release(
							$inv_file_lock,
							$post_inv->ID
						);

						if ( 0 === $current_useage ) {
							update_post_meta(
								$post_inv->ID,
								$this->status_key,
								'Expired'
							);
						}
						$status = 'approved';
						pw_new_user_approve()->approve_user( $user_id );
						do_action( 'nua_invited_user', $user_id, $code_inv );
						return $status;
					}
				}
			}
			return $status;
		}

		/**
		 * Add pending message filter for auto-approved invited users.
		 *
		 * @param int $user_id The user ID (unused).
		 * @return void
		 */
		public function message_above_regform( $user_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			add_filter(
				'new_user_approve_pending_message',
				array( $this, 'msg_on_auto_approve_invitation_callback' ),
				10,
				1
			);
		}


		/**
		 * Return the auto-approve message for invited users.
		 *
		 * @param string $message The original message.
		 * @return string
		 */
		public function msg_on_auto_approve_invitation_callback( $message ) {
			$message = nua_auto_approve_message();
			$message = nua_do_email_tags(
				$message,
				array(
					'context' => 'approved_message',
				)
			);
			return $message;
		}
	} // End Class
}
// phpcs:ignore
function nua_invitation_code() {
	return NUA_Invitation_Code::instance();
}

nua_invitation_code();
