<?php
/**
 * Zapier integration for New User Approve plugin.
 *
 * @package New_User_Approve
 */

namespace NewUserApproveZapier;

if ( ! defined( 'NUA_ZAPIER_DB_VERSION' ) ) {
	define( 'NUA_ZAPIER_DB_VERSION', '1.0' );
}
if ( ! defined( 'NUA_ZAPIER_OPTION_STATUS' ) ) {
	define( 'NUA_ZAPIER_OPTION_STATUS', true );
}

/**
 * Main initialization class for Zapier integration.
 */
class Zapier_Init {

	/**
	 * Singleton instance.
	 *
	 * @var Zapier_Init
	 */
	private static $instance;

	/**
	 * Get singleton instance.
	 *
	 * @version 1.0
	 * @since 2.1
	 * @return Init
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
		$this->require_files();
		register_activation_hook( NUA_FILE, 'create_nua_zapier_table' );
	}

	/**
	 * Require necessary files.
	 *
	 * @version 1.0
	 * @since 2.1
	 */
	public function require_files() {
		require_once plugin_dir_path( __FILE__ ) .
			'/includes/nua-zapier-functions.php';
		require_once plugin_dir_path( __FILE__ ) . '/includes/class-restroutes.php';
		require_once plugin_dir_path( __FILE__ ) . '/includes/class-user.php';
	}
}

\NewUserApproveZapier\Zapier_Init::get_instance();
