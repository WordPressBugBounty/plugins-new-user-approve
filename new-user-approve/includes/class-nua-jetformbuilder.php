<?php
/**
 * JetFormBuilder integration for New User Approve.
 *
 * @package New_User_Approve
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NUA_JetFormBuilder
 *
 * Handles JetFormBuilder invitation code field and validation.
 */
class NUA_JetFormBuilder {

	/**
	 * The single instance of this class.
	 *
	 * @var NUA_JetFormBuilder
	 */
	private static $instance;

	/**
	 * Returns the main instance.
	 *
	 * @return NUA_JetFormBuilder
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Register Shortcode for the field to be used in JFB.
		add_shortcode( 'nua_invitation_code', array( $this, 'render_invitation_code_field' ) );

		// Hook into JFB validation.
		add_action( 'jet-form-builder/form-handler/before-send', array( $this, 'validate_invitation_code' ) );
	}

	/**
	 * Render the invitation code field for JetFormBuilder.
	 *
	 * @return string
	 */
	public function render_invitation_code_field() {
		$options  = get_option( 'new_user_approve_options' );
		$required = ! empty( $options['nua_checkbox_textbox'] );

		ob_start();
		?>
		<div class="jet-form-builder-row nua-invitation-code-wrapper">
			<div class="jet-form-builder__field-wrap">
				<label class="jet-form-builder__label">
					<span
						class="jet-form-builder__label-text"><?php esc_html_e( 'Invitation Code', 'new-user-approve' ); ?></span>
					<?php if ( $required ) : ?>
						<span class="jet-form-builder__required-mark">*</span>
					<?php endif; ?>
				</label>
				<div class="jet-form-builder__field-container">
					<input type="text" name="nua_invitation_code" class="jet-form-builder__field text-field"
						value="<?php echo isset( $_POST['nua_invitation_code_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nua_invitation_code_nonce'] ) ), 'nua_invitation_code_action' ) && isset( $_POST['nua_invitation_code'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_POST['nua_invitation_code'] ) ) ) : ''; ?>">
				</div>
				<div class="jet-form-builder__field-description">
					<?php esc_html_e( 'Enter your invitation code.', 'new-user-approve' ); ?>
				</div>
				<?php wp_nonce_field( 'nua_invitation_code_action', 'nua_invitation_code_nonce' ); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Validate the invitation code on form submission.
	 *
	 * @param object $handler The JetFormBuilder form handler.
	 * @return void
	 *
	 * @throws \Jet_Form_Builder\Exceptions\Request_Exception If validation fails.
	 */
	public function validate_invitation_code( $handler ) {
		// Ensure this is a Register User form.
		$has_register_action = false;
		if ( method_exists( $handler->action_handler, 'get_all' ) ) {
			foreach ( $handler->action_handler->get_all() as $action ) {
				if ( $action->get_id() === 'register_user' ) {
					$has_register_action = true;
					break;
				}
			}
		}

		if ( ! $has_register_action ) {
			return;
		}

		// Get code from POST.
		if (
			! isset( $_POST['nua_invitation_code_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nua_invitation_code_nonce'] ) ), 'nua_invitation_code_action' )
		) {
			return;
		}
		$code     = isset( $_POST['nua_invitation_code'] ) ? sanitize_text_field( wp_unslash( $_POST['nua_invitation_code'] ) ) : '';
		$options  = get_option( 'new_user_approve_options' );
		$required = ! empty( $options['nua_checkbox_textbox'] );

		if ( empty( $code ) ) {
			if ( $required ) {

				throw new \Jet_Form_Builder\Exceptions\Request_Exception(
					'Please add an Invitation code.',
					array( 'nua_invitation_code' => 'Please add an Invitation code.' )
				);
			}
			return;
		}

		// Validate Code against Database.
		// This query matches logic in nua_add_user_to_invitation_code from invitation-code.php.
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
				// Double check code match for case and sanitization.
				$code_inv = get_post_meta( $post->ID, '_nua_code', true );
				if ( $code_inv === $code ) {
					$valid = true;
					break;
				}
			}
		}

		if ( ! $valid ) {
			throw new \Jet_Form_Builder\Exceptions\Request_Exception(
				'The Invitation code is invalid',
				array( 'nua_invitation_code' => 'The Invitation code is invalid' )
			);
		}
	}

	/**
	 * Initialize only if the feature is enabled.
	 *
	 * @return void
	 */
	public static function init() {
		$options = get_option( 'new_user_approve_options' );
		if ( isset( $options['nua_invitation_code'] ) ) {
			self::instance();
		}
	}
}

NUA_JetFormBuilder::init();
