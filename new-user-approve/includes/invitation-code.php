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
 */


class NUA_Invitation_Code {

	private static $instance;

	private $screen_name = 'nua-invitation-code';
	public $code_post_type = 'invitation_code';
	public $usage_limit_key = '_nua_usage_limit';
	public $expiry_date_key = '_nua_code_expiry';
	public $status_key = '_nua_code_status';
	public $code_key = '_nua_code';
	public $total_code_key = '_total_nua_code';
	public $registered_users = '_registered_users';
	private $option_group = 'nua_options_group';
	public $option_key = 'nua_options';
	/**
	 * Returns the main instance.
	 *
	 * @return NUA_Invitation_Code
	 */
	public static function instance() {
		if (!isset(self::$instance)) {
			self::$instance = new NUA_Invitation_Code();
		}
		return self::$instance;
	}

	private function __construct() {
		//Action
		add_action('admin_menu', array( $this, 'admin_menu_link' ), 30);
		add_action('admin_init', array( $this, 'nua_deactivate_code' ));
		add_action('init', array( $this, 'nua_invitation' ));
		add_action('add_meta_boxes_' . $this->code_post_type, array( $this, 'add_invitation_meta' ));
		add_action('save_post_' . $this->code_post_type, array( $this, 'save_nua_invitation' ));
		if (get_transient('inv_code_exists')==true) {
			
			add_action('admin_notices', array( $this, 'inv_code_alreay_exists_notification' ), 10);
		}

		//Filter
		add_filter('manage_' . $this->code_post_type . '_posts_columns', array( $this, 'invitation_code_columns' ));
		add_action('manage_' . $this->code_post_type . '_posts_custom_column', array( $this, 'invitation_code_columns_content' ), 10, 2);
		add_filter('post_row_actions', array( $this, 'remove_row_actions_from_table' ), 10, 2);
		add_action('admin_head', array( $this, 'invitation_code_edit_page_css' ));
		add_filter( 'nua_disable_welcome_email', array( $this, 'nua_disable_welcome_email_callback' ), 10, 2);

		$options = get_option('nua_free_invitation');
		if ($options == 'enable') {
			add_action('register_form', array( $this, 'nua_invitation_code_field' ));
			add_filter('register_post', array( $this, 'nua_invitation_status_code_field_validation' ), 6, 3);
			add_filter('new_user_approve_default_status', array( $this, 'nua_invitation_status_code' ), 10, 2);
			add_action('woocommerce_register_form', array( $this, 'nua_invitation_code_field' ));
			add_action('um_after_form_fields', array( $this, 'nua_invitation_code_field' ), 10, 2);
			add_action('nua_invited_user', array( $this, 'message_above_regform' ), 10, 1);

		}
	}
	public function nua_disable_welcome_email_callback( $disabled, $user_id ) { 
		$status=get_user_meta( $user_id, 'pw_user_status', true );
		if ('approved'==$status) {
			$disabled=true;
		}
		return $disabled;
	}
	
	public function nua_deactivate_code() {

		if (isset($_GET['post_type']) && $_GET['post_type'] == $this->code_post_type && is_admin()) {
			if (isset($_GET['post_id']) && check_admin_referer('nua_deactivate-' . absint($_GET['post_id']), 'nonce')) {
				update_post_meta(absint($_GET['post_id']), $this->status_key, 'InActive');
			}
		}
	}

	public function invitation_code_edit_page_css() {
		if (isset($_GET['post_type']) && $_GET['post_type'] == $this->code_post_type) {
			?>
			<style>
				.widefat td,
				.widefat th {
					height: 36px;
				}
			</style>
		<?php

		}
	}

	public function remove_row_actions_from_table( $actions, $post ) {
		if ($post->post_type == $this->code_post_type) {

			return array();
		}
		return $actions;
	}

	/**
	 * Add the link to the admin menu for the settings page
	 */
	public function admin_menu_link() {
		// don't show the link in the admin menu if the parent plugin is inactive
		if (!function_exists('pw_new_user_approve')) {
			return;
		}

		$hook = add_submenu_page('new-user-approve-admin', __('Invitation Code', 'new-user-approve'), __('Invitation Code', 'new-user-approve'), 'manage_options', $this->screen_name, array( $this, 'invitation_code_settings' ), 1);

		add_submenu_page('new-user-approve-admin', __('All Codes', 'new-user-approve'), __('All Codes', 'new-user-approve'), 'manage_options', 'edit.php?post_type=' . $this->code_post_type);

		add_action('admin_print_scripts-' . $hook, array( $this, 'admin_scripts' ));
	}

	public function admin_scripts() {
		wp_enqueue_script('nua-options');
	}


	/**
	 * Post Registration
	 */
	public function nua_invitation() {

		$labels = array(
			'name'                  => __('Invitation Code', 'new-user-approve'),
			'singular_name'         => __('Invitation Code', 'new-user-approve'),
			'menu_name'             => __('All Codes', 'new-user-approve'),
			'name_admin_bar'        => __('All Codes', 'new-user-approve'),
			'add_new'               => __('Add New', 'new-user-approve'),
			'add_new_item'          => __('Add New Invitation Code', 'new-user-approve'),
			'new_item'              => __('New Invitation Code', 'new-user-approve'),
			'edit_item'             => __('Edit Invitation Code', 'new-user-approve'),
			'view_item'             => __('View Invitation Code', 'new-user-approve'),
			'all_items'             => __('All Invitation Code', 'new-user-approve'),
		);

		$args = array(
			'labels' => $labels,
			'description' => false,
			'public' => false,
			'show_ui' => true,
			'show_in_menu' => false,
			'query_var' => false,
			'rewrite' => false,
			'capability_type' => 'page',
			'has_archive' => false,
			'hierarchical' => false,
			'menu_position' => null,
			'menu_icon' => '',
			'supports' => array( 'title' )
		);

		register_post_type($this->code_post_type, $args);
	}

	/**
	 * Output the settings
	 */
	public function invitation_code_settings() {
		$action = ( isset($_GET['action']) ) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'add-codes';
		
		

		?>
		<div class="wrap">
			<div id="icon-options-general" class="icon32"><br /></div>
			<h2 class="nua-settings-heading"><?php esc_html_e('Invitation Code Settings', 'new-user-approve'); ?></h2>

			<h2 class="nav-tab-wrapper">

				<a href="<?php echo esc_url(add_query_arg(array( 'page' => $this->screen_name ), admin_url('admin.php'))); ?>" class="nav-tab
									<?php
									if ('add-codes' == $action) {
					echo ' nav-tab-active';}
									?>
				"><?php esc_html_e('Add Codes', 'new-user-approve'); ?> </a>

				<a href="
				<?php
				echo esc_url(add_query_arg(array(
					'page' => $this->screen_name,
					'action' => 'Settings'
				), admin_url('admin.php')));
				?>
							" class="nav-tab 
							<?php
							if ('Settings' == $action) {
														echo ' nav-tab-active';}
							?>
"><?php esc_html_e('Settings', 'new-user-approve'); ?></a>
				<a href="
				<?php
				echo esc_url(add_query_arg(array(
					'page' => $this->screen_name,
					'action' => 'import-codes'
				), admin_url('admin.php')));
				?>
							" class="nav-tab
							<?php
							if ('import-codes' == $action) {
														echo ' nav-tab-active';}
							?>
"><?php esc_html_e('Import Codes', 'new-user-approve'); ?> <span class="invite-code-btn-label">Pro</span> </a>
				<a href="
				<?php
				echo esc_url(add_query_arg(array(
					'page' => $this->screen_name,
					'action' => 'email'
				), admin_url('admin.php')));
				?>
							" class="nav-tab
							<?php
							if ('email' == $action) {
														echo ' nav-tab-active';}
							?>
"><?php esc_html_e('Email', 'new-user-approve'); ?> <span class="invite-code-btn-label">Pro</span> </a>

			</h2>
			<?php
			$tab = ( isset($_GET['tab']) ) ? sanitize_text_field(wp_unslash($_GET['tab'])) : '';
			
			$tab = ( empty($tab) && !isset($_GET['action']) ) ? 'manual' : $tab;
			?>
			<?php if (!isset($_GET['action'])) : ?>
				<h2 class="nav-subtab-wrapper">

					<a href="
					<?php
					echo esc_url(add_query_arg(array(
						'page' => $this->screen_name,
						'tab' => 'manual'
					), admin_url('admin.php')));
					?>
								" class="nav-tab
								<?php
								if ('manual' == $tab) {
																echo ' nav-tab-active';}
								?>
"><?php esc_html_e('Manual Generate', 'new-user-approve'); ?></a>
					<a href="
					<?php
					echo esc_url(add_query_arg(array(
						'page' => $this->screen_name,
						'tab' => 'auto'
					), admin_url('admin.php')));
					?>
								" class="nav-tab
								<?php
								if ('auto' == $tab) {
																echo ' nav-tab-active';}
								?>
"><?php esc_html_e('Auto Generate', 'new-user-approve'); ?> <span class="invite-code-btn-label">Pro</span> </a>


				</h2>
			<?php endif; ?>
		<?php



		$this->get_the_required_tab($action, $tab);
	}

	public function get_the_required_tab( $action, $tab ) {

		if ('add-codes' == $action) {

			if ('manual' == $tab) {
				$this->manual_add_codes();
			} else {
				// 'auto' == $tab 
				$this->auto_add_codes();
			}
		} else if ('import-codes' == $action) {
			$this->import_codes();
		} else if ('email' == $action) {
			$this->email();
		} else if ('Settings' == $action) {
			$this->option_invitation_code();
		}
	}

	public function manual_add_codes() {
		$count = 0;
		$code_already_exists =array();
		if (isset($_POST['nua_manual_add'])) {
			if (!empty($_POST['nua-manual-add-nonce-field'])) {
$nonce = sanitize_text_field(wp_unslash($_POST['nua-manual-add-nonce-field']));}
			if (!wp_verify_nonce($nonce, 'nua-manual-add-nonce')) {
return;
			}
			
			$limit = empty( $_POST['nua_manual_add']['usage_limit'] ) ? 1 : absint($_POST['nua_manual_add']['usage_limit']);
			$expiry = !empty( $_POST['nua_manual_add']['expiry_date']) ? sanitize_text_field( wp_unslash($_POST['nua_manual_add']['expiry_date']) ):'';
			$Status = 'Active';
			//$dateTime = new DateTime(str_replace('/','-',$expiry)); 
			//$expiry_timestamp = $dateTime->format('U'); 
			$expiry_timestamp = strtotime("$expiry 23:59:59");

			$code = !empty($_POST['nua_manual_add']['codes']) ? sanitize_textarea_field(wp_unslash($_POST['nua_manual_add']['codes'])) :'' ;
			$code = explode("\n", $code);

			foreach ($code as $in_code) {
				if (empty(trim($in_code))) {

					continue;
				}

				if ($this->invitation_code_already_exists($in_code)) {
					$code_already_exists[] = $in_code;
					continue;
				}

				$my_post = array(
					'post_title'    => sanitize_text_field($in_code),
					'post_status'   => 'publish',
					'post_type'     => $this->code_post_type,

				);

				$post_code = wp_insert_post($my_post);
				if (!empty($post_code)) {
					update_post_meta($post_code, $this->code_key, sanitize_text_field($in_code));
					update_post_meta($post_code, $this->usage_limit_key, $limit);
					update_post_meta($post_code, $this->total_code_key, $limit);
					update_post_meta($post_code, $this->expiry_date_key, $expiry_timestamp);
					update_post_meta($post_code, $this->status_key, $Status);

					$count++;
				}
			}
			if (!empty($count)) {

				$inv_code_success_msg = ( $count > 1 ? 'Codes Have Been Added Successfully' : 'Code Has Been Added Successfully' );
				?>
				<p class="nua-success  nua-message" id="successMessage" > 
				<?php
				echo esc_html($inv_code_success_msg, 'new-user-approve');
				?>
				</p> 
				<?php

				if (!empty($code_already_exists)) {
					
					$inv_code_exist_msg =  count($code_already_exists) > 1 ? 'Codes Already Exist' : 'Code Already Exists';
					?>
					<p class="nua-already-exists nua-error nua-message" id="errorMessage" > 
					<?php
					echo esc_html(sprintf('%s ' . $inv_code_exist_msg, implode(', ', $code_already_exists)), 'new-user-approve');
					?>
					</p> 
					<?php
				}
			} else if (empty($count) && !empty($code_already_exists) ) {
				
					$inv_code_exist_msg = count($code_already_exists) > 1 ? 'Codes Already Exist.' : 'Code Already Exists.';
				?>
					<p class="nua-already-exists nua-error nua-message" id="errorMessage" > 
					<?php
					echo esc_html(sprintf('%s ' . $inv_code_exist_msg, implode( ', ', $code_already_exists)), 'new-user-approve');
					?>
					</p> 
					<?php
		
			} else {

				?>
				<p class="nua-fail nua-error nua-message" id="failMessage" > 
				<?php
				echo esc_html(sprintf('Code Not Added.', 'new-user-approve'));
				?>
				</p> 
				<?php
			}
			
		}


		?>
			<form method="post" action=''>
				<?php $nonce = wp_create_nonce('nua-manual-add-nonce'); ?>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th><?php esc_html_e('Add Codes', 'new-user-approve'); ?></th>
							<td>
								<div style="max-width: 600px;">
									<textarea id="nua_manual_add_add_codes" name="nua_manual_add[codes]" required class="nua-textarea"></textarea>
								</div>
								<p class="description"><?php esc_html_e('Enter one code per line.', 'new-user-approve'); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e('Usage Limit', 'new-user-approve'); ?></th>
							<td>
								<input id="nua_manual_add_usage_limit" name="nua_manual_add[usage_limit]" placeholder="1" size="40" type="text" class="nua-text-field">
								<input type = "hidden"  name="nua-manual-add-nonce-field" value = "<?php echo esc_attr($nonce); ?>">

							</td>
						</tr>
						<tr>
							<th><?php esc_html_e('Expiry Date', 'new-user-approve'); ?></th>
							<td>
								<input id="nua_manual_add_expiry_date" name="nua_manual_add[expiry_date]" size="40" type="date" class="nua-text-field">

							</td>
						</tr>
						<tr>
							<th colspan="2">
								<p class="submit nua-submit"><input type="submit" name="nua_manual_add[submit]" id="submit" class="button button-primary" value="Save Changes"></p>

							</th>
						</tr>
					</tbody>
			</form>
			</table>
		<?php
	}

	public function auto_add_codes() {
		?>
		<h2>Get pro version to avail these feature<br></h2>
		<h3><a href='https://newuserapprove.com/pricing/?utm_source=wordpress&utm_medium=plugin#lifetime-plan' target = _blank>Click here to get the Pro Version</a></h3>
		<?php
	}
	public function import_codes() {
		?>
		<h2>Get pro version to avail these feature<br></h2>
		<h3><a href='https://newuserapprove.com/pricing/?utm_source=wordpress&utm_medium=plugin#lifetime-plan' target = _blank>Click here to get the Pro Version</a></h3>
		<?php
	}
	public function email() {
		?>
		<h2>Get pro version to avail these feature<br></h2>
		<h3><a href='https://newuserapprove.com/pricing/?utm_source=wordpress&utm_medium=plugin#lifetime-plan' target = _blank>Click here to get the Pro Version</a></h3>
		<?php
	}

	/**
	 *
	 * @since 2.5.2
	 */
	public function invitation_code_already_exists( $code ) {
		
		$posts_with_meta = get_posts( array(
			'posts_per_page' => 1, // we only want to check if any exists, so don't need to get all of them
			'meta_key' => $this->code_key,
			'meta_value' => $code,
			'post_type' => $this->code_post_type,
		) );
		
		if ( count( $posts_with_meta ) ) {
			return true;
		}
		return false;
	}
	/**
	 *
	 * @since 2.5.2
	 */
	public function invitation_code_limit_check( $code ) {

		$is_inv_code_limit =array(
			'numberposts'            => 1,
			'post_type'              => $this->code_post_type,
			'meta_query' =>          // we are checking two things code and its limit , so we are using meta query 
			array( 
				'relation' => 'AND',
				array(
					array(
						'key'       =>  $this->code_key,
						'value'     => $code,
						'compare'   => '=',
					),
					array(
						'key'       =>  $this->usage_limit_key,
						'value'     => '1',
						'compare'   => '>=',
					),
					array(
						'relation' => 'OR',
						array(
							'key'       =>  $this->status_key,
							'value'     =>  'Active',
							'compare'   => '=',
						),
						array(
							'key'       =>  $this->status_key,
							'value'     =>  'Expired',
							'compare'   => '=',
						),
					),
				),
			)
		);

		$is_inv_code_limit = get_posts($is_inv_code_limit);
		if ( count( $is_inv_code_limit ) ) {
			return true;
		} else {
			return false;
		}
	}
	/**
	 *
	 * @since 2.5.2
	 */
	public function invitation_code_expiry_check( $code ) {

		$is_inv_code_expired  = array(
			'numberposts'            => 1,
			'post_type'              => $this->code_post_type,
			'meta_query' =>         // we are checking two things code and its expiry , so we are using meta query 
			array( 
				'relation' => 'AND',
				array(
					array(
						'key'       =>  $this->code_key,
						'value'     => $code,
						'compare'   => '=',
					),
					array(
						'key'       =>  $this->expiry_date_key,
						'value'     =>  time(),
						'compare'   => '>=',
					),
					array(
						'relation' => 'OR',
						array(
							'key'       =>  $this->status_key,
							'value'     =>  'Active',
							'compare'   => '=',
						),
						array(
							'key'       =>  $this->status_key,
							'value'     =>  'Expired',
							'compare'   => '=',
						),
					),
				
				

				)   
			)
		);

		$is_inv_code_expired = get_posts($is_inv_code_expired);
		
		if ( count( $is_inv_code_expired ) ) {
			return false;
		} else {
			return true;
		}
	}

	public function get_available_invitation_codes() {

		$args = array(
			'numberposts'           => -1,
			'post_type'              => $this->code_post_type,
			'post_status'            => 'publish',
			'meta_query' =>
			array(
				'relation' => 'AND',
				array(
					array(
						'key'       =>  $this->usage_limit_key,
						'value'     => '1',
						'compare'   => '>=',
					),

					array(
						'key'       =>  $this->expiry_date_key,
						'value'     =>  time(),
						'compare'   => '>=',
					),
					array(
						'key'       =>  $this->status_key,
						'value'     =>  'Active',
						'compare'   => '=',
					),

				),

			),

		);

		$codes = get_posts($args);

		return $codes;
	}

	/**
	 * Output the Meta Value
	 */
	public function add_invitation_meta() {
		add_meta_box('nua_invitation', __('Invitation code for new user', 'new-user-approve'), array( $this, 'funct_nua_invitation' ), $this->code_post_type);
	}
	public function funct_nua_invitation() {

		$code             = get_post_meta(get_the_ID(), $this->code_key, true);
		$useage           = get_post_meta(get_the_ID(), $this->usage_limit_key, true);
		$total_code_key   = get_post_meta(get_the_ID(), $this->total_code_key, true);
		$exp              = get_post_meta(get_the_ID(), $this->expiry_date_key, true);
		$Status           = get_post_meta(get_the_ID(), $this->status_key, true);
		$convert_date     = gmdate('Y-m-d', absint($exp));
		$registered_user  = get_post_meta(get_the_ID(), $this->registered_users, true);

		?>
			<form method="post" action=''>
				<?php $nonce = wp_create_nonce('user-reg-by-invite-code-nonce'); ?>
				<table class="form-table" role="presentation">

					<tbody>
						<tr>

							<th scope="row"><?php esc_html_e('Invitation Code', 'new-user-approve'); ?></th>
							<td><input type="text" name="codes" required value="<?php echo esc_attr($code); ?>" class="nua_codetxt" /><br>
								 <input type="hidden" name="user_reg_by_invite_code_nonce_field" value ="<?php echo esc_attr($nonce); ?>"> </input>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e('Uses left', 'new-user-approve'); ?></th>
							<td><input type="text" name="usage_limit" required value="<?php echo esc_attr( $useage) ; ?>" class="nua_codetxt" /><br>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e('Usage Limit', 'new-user-approve'); ?></th>
							<td><input type="text" name="total_code" required value="<?php echo esc_attr( $total_code_key ); ?>" class="nua_codetxt" /><br>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e('Date', 'new-user-approve'); ?></th>
							<td><input type="date" name="expiry_date" required value="<?php echo esc_attr( $convert_date ); ?>" class="nua_codetxt" /><br>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e('Status', 'new-user-approve'); ?></th>
							<td>
								<select name="code_status" required class="nua_codetxt">
									<option value='Active' <?php echo esc_attr ('Active' == $Status ? 'selected' : ''); ?>>Active</option>
									<option value='InActive' <?php echo esc_attr('InActive' == $Status ? 'selected' : ''); ?>> InActive</option>
									<option value='Expire' <?php echo esc_attr('Expire' == $Status ? 'selected' : ''); ?>> Expire</option>
								</select>
							</td>
						</tr>
						<tr>
							<td colspan="2">
								<table>
									<p><?php esc_html_e('Users that have registered by using this invitation code', 'new-user-approve'); ?></p>
									<thead>
										<tr>
											<th>USER-ID</th>
											<th>USER-EMAIL</th>
											<th>USER-LINK</th>
										</tr>
									</thead>
									<tbody>

										<?php
										if (!empty($registered_user)) {

											foreach ($registered_user as $userid) {
												?>
													<tr><td> <?php echo esc_html($userid); ?></td>
												<?php

												$the_user = get_user_by('id', $userid);

												if (!empty($the_user)) {
													$link = get_edit_user_link($userid);
													?>
														<td><?php echo esc_html($the_user->user_email); ?></td>
														<td><a href='<?php echo esc_url($link); ?>'><?php echo esc_html($the_user->user_email); ?></a></td>
													<?php
												} else {
													?>
														<td><?php esc_html_e('User Not Found', 'new-user-approve'); ?></td>
													<?php
												}
												?>
												</tr> 
												<?php
											}
										} else {
											?>
												<tr colspan="3"><td><?php esc_html_e('No User Found', 'new-user-approve'); ?></td></tr>
											<?php
										}
										?>
									</tbody>
								</table>
							</td>
						</tr>
					</tbody>
				</table>
			</form>
		<?php
	}

	public function save_nua_invitation() {
		
		$post_status = isset($_POST['original_publish']) ? sanitize_text_field($_POST['original_publish']) :'';
		$code = isset($_POST['codes']) ? sanitize_text_field(wp_unslash($_POST['codes'])):'';
		// to stop the post from adding the duplicate invitation code on publish post status
		if ($this->invitation_code_already_exists($code) && ( 'publish' == $post_status || 'Publish' == $post_status )) {
			wp_delete_post(get_the_ID()); // There is no need of parent post wherease the sub post (invitation code) has not created
			set_transient('inv_code_exists', 1 * HOUR_IN_SECONDS);
			wp_safe_redirect(admin_url('/post-new.php?post_type=invitation_code'));
			exit; // neccessary to exit
		}
		if (isset($_POST['post_type']) && $this->code_post_type == sanitize_text_field( wp_unslash( $_POST['post_type'] ) )  ) {
			$nonce = isset($_POST['user_reg_by_invite_code_nonce_field']) ? sanitize_text_field(wp_unslash($_POST['user_reg_by_invite_code_nonce_field'])):'';
			if (!wp_verify_nonce($nonce, 'user-reg-by-invite-code-nonce') ) {
return;}
			$code = isset($_POST['codes']) ? sanitize_text_field(wp_unslash($_POST['codes'])):'';
			$usage = isset($_POST['usage_limit']) ? absint($_POST['usage_limit']):'';
			$use_limit = isset($_POST['total_code']) ? absint( $_POST['total_code']):'';
			$expiry = isset($_POST['expiry_date'])  ? sanitize_text_field( wp_unslash( $_POST['expiry_date'] ) ):'';
			$Status = isset($_POST['code_status']) ?  sanitize_text_field( wp_unslash( $_POST['code_status'] ) ):'';
			//$dateTime = new DateTime(str_replace('/','-',$Expiry)); 
			//$expiry_timestamp = $dateTime->format('U'); 
			$expiry_timestamp = strtotime("$expiry 23:59:59");

			update_post_meta(get_the_ID(), $this->code_key, $code);
			update_post_meta(get_the_ID(), $this->usage_limit_key, $usage);
			update_post_meta(get_the_ID(), $this->total_code_key, $use_limit);
			update_post_meta(get_the_ID(), $this->expiry_date_key, $expiry_timestamp);
			update_post_meta(get_the_ID(), $this->status_key, $Status);
		}
	}

	public  function nua_invitation_code_field() {
		$required = ' *';
		if (true === apply_filters('nua_invitation_code_optional', true)) {
			$required = ' (optional)';
		}
		?>
		<?php $nonce = wp_create_nonce('nua-invitation-code-nonce'); ?>

			<p>	
				<label> <?php esc_html_e('Invitation Code', 'new-user-approve'); ?><span>
					<?php // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText ?>
					<?php esc_attr_e($required, 'new-user-approve'); ?>
				</span></label>
				<input type="hidden"  name="nua_invitation_code_nonce_field" value = <?php echo esc_attr($nonce); ?>/>
				<input type="text" class="nua_invitation_code" name="nua_invitation_code" />
			</p>
			<?php
	}

	/**
	 *
	 * @since 2.5.2
	 */
	public function inv_code_alreay_exists_notification() {

		$class = 'notice notice-error';
		$message = esc_html__( 'Invitation Code Already Exists.', 'new-user-approve' );
		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
		delete_transient('inv_code_exists');// No need to keep this tip after displaying notification
	}

	public function invite_code_hold( $inv_id ) {
		$inv_file = fopen( $this->invite_code_lock_file( $inv_id ), 'w+' );
		
		if ( ! flock( $inv_file, LOCK_EX | LOCK_NB ) ) {
			return false;
		}
	
		ftruncate( $inv_file, 0 );
		fwrite( $inv_file, microtime( true ) );
		return $inv_file;
	}
	
	public function invite_code_release( $inv_file, $inv_id ) {
		if ( is_resource( $inv_file ) ) {
			fflush( $inv_file );
			flock( $inv_file, LOCK_UN );
			fclose( $inv_file );
			unlink( $this->invite_code_lock_file( $inv_id ) );
	
			return true;
		}
	
		return false;
	}
	
	public function invite_code_lock_file( $inv_id ) {
		return apply_filters( 'invite_code_lock_file', get_temp_dir() . '/invite-code' . $inv_id . '.lock', $inv_id );
	}

	public function nua_invitation_status_code_field_validation( $user_login, $user_email, $errors ) { 
		$options = get_option('new_user_approve_options' ); 
		$code_optional = apply_filters('nua_invitation_code_optional', true);
		$nonce = sanitize_text_field($_POST['nua_invitation_code_nonce_field']);
		if ( isset($_POST['nua_invitation_code']) && !empty($_POST['nua_invitation_code']) && wp_verify_nonce($nonce, 'nua-invitation-code-nonce')) {

			// display the Error on registration form when invitation code has expired or limit exceeded
			$code = sanitize_text_field(wp_unslash($_POST['nua_invitation_code']));
			$is_inv_code_exist = $this->invitation_code_already_exists($code);
			$is_inv_code_limit = $this->invitation_code_limit_check($code);
			$is_inv_expired    = $this->invitation_code_expiry_check($code);
			if ( ( true == $is_inv_code_exist )  && ( true == $is_inv_expired ) ) {

				$error_message = apply_filters('nua_invitation_code_err', __('<strong>ERROR</strong>: Invitation code has been expired', 'new-user-approve'), '' , $errors);
				$errors->add( 'invcode_error', $error_message);
				return $errors;
			} else if ( ( true == $is_inv_code_exist ) && ( false == $is_inv_code_limit ) ) {
				$error_message = apply_filters('nua_invitation_code_err', __('<strong>ERROR</strong>: Invitation code limit_exceeded', 'new-user-approve'), '' , $errors);
				$errors->add( 'invcode_error', $error_message);
				return $errors;
			}

			$args = array(
				'numberposts'           => -1,
				'post_type'              => $this->code_post_type,
				'post_status'            => 'publish',
				'meta_query' => 
				array( 
					'relation' => 'AND',
					array(
						array(
							'key'       =>  $this->code_key,
							'value'     => sanitize_text_field(wp_unslash($_POST['nua_invitation_code'])),
							'compare'   => '=',
						),
						array(
							'key'       =>  $this->usage_limit_key,
							'value'     => '1',
							'compare'   => '>=',
						),
						array(
							'key'       =>  $this->expiry_date_key,
							'value'     =>  time(),
							'compare'   => '>=',
						),
						array(
							'key'       =>  $this->status_key,
							'value'     =>  'Active',
							'compare'   => '=',
						),
					),
	
				),
							
			);
			$posts = get_posts( $args );
			$code_inv = '';
			foreach ($posts as $post_inv) { 
	
				$code_inv =  get_post_meta($post_inv->ID , $this->code_key, true );
	
				if ($_POST['nua_invitation_code'] == $code_inv) {
					global $inv_file_lock;
					$inv_file_lock = $this->invite_code_hold( $post_inv->ID);
					if ( $inv_file_lock === false ) {
						$errors->add( 'invcode_error', '<strong>Notice</strong>: Server is busy, please try again!' );
						return $errors;
					}
						
					return $errors;
						
				}
			}
				
			$error_message = apply_filters('nua_invitation_code_err', __('<strong>ERROR</strong>: The Invitation code is invalid', 'new-user-approve'), $code_inv , $errors);
			$errors->add( 'invcode_error', $error_message);
		} else if ( ( !isset($_POST['nua_invitation_code']) ) || ( isset($_POST['nua_invitation_code']) && empty($_POST['nua_invitation_code']) && !empty(get_option('nua_free_invitation'))  && true !== $code_optional )  ) {
			$error_message = apply_filters('nua_invitation_code_err', __('<strong>ERROR</strong>: Please add an Invitation code.', 'new-user-approve'), '' , $errors);
			$errors->add( 'invcode_error', $error_message );
		}
		return $errors;
	}





	public  function nua_invitation_status_code( $status, $user_id ) {
		$nonce = isset($_POST['nua_invitation_code_nonce_field']) ? sanitize_text_field(wp_unslash($_POST['nua_invitation_code_nonce_field'])):'';
		if (!wp_verify_nonce($nonce, 'nua-invitation-code-nonce') ) {
$nonce='';}
		
		if (isset($_POST['nua_invitation_code']) && !empty($_POST['nua_invitation_code'])) {
			$args = array(
				'numberposts'           => -1,
				'post_type'              => $this->code_post_type,
				'post_status'            => 'publish',
				'meta_query' =>
				array(
					'relation' => 'AND',
					array(
						array(
							'key'       =>  $this->code_key,
							'value'     => sanitize_text_field(wp_unslash($_POST['nua_invitation_code'])),
							'compare'   => '=',
						),
						array(
							'key'       =>  $this->usage_limit_key,
							'value'     => '1',
							'compare'   => '>=',
						),
						array(
							'key'       =>  $this->expiry_date_key,
							'value'     =>  time(),
							'compare'   => '>=',
						),
						array(
							'key'       =>  $this->status_key,
							'value'     =>  'Active',
							'compare'   => '=',
						),
					),

				),

			);

			$posts = get_posts($args);
				
			foreach ($posts as $post_inv) {
					
				$code_inv =  get_post_meta($post_inv->ID, $this->code_key, true);

				if (sanitize_text_field(wp_unslash($_POST['nua_invitation_code'])) == $code_inv) {
					$register_user =  get_post_meta($post_inv->ID, $this->registered_users, true);

					if (empty($register_user)) {
						update_post_meta($post_inv->ID, $this->registered_users, array( $user_id ));
					} else {
						//$unserilize_array = unserialize($register_user);
						$register_user[] = $user_id;
						update_post_meta($post_inv->ID, $this->registered_users, $register_user);
					}
					$current_useage =  get_post_meta($post_inv->ID, $this->usage_limit_key, true);
					--$current_useage;
					update_post_meta($post_inv->ID, $this->usage_limit_key, $current_useage);
					// Release lock
					global $inv_file_lock;
					$this->invite_code_release( $inv_file_lock, $post_inv->ID );
						
					if ($current_useage == 0) {
						update_post_meta($post_inv->ID, $this->status_key, 'Expired');
					}
					$status = 'approved';
					pw_new_user_approve()->approve_user( $user_id );
					do_action('nua_invited_user', $user_id, $code_inv);
					return $status;
				}
			}
		}
		return $status;
	}


		
	public function message_above_regform( $user_id ) {
		add_filter( 'new_user_approve_pending_message', array( $this, 'msg_on_auto_approve_invitation_callback' ), 10, 1 );
	}
		
	public function msg_on_auto_approve_invitation_callback( $message ) { 
			// $opt=pw_new_user_approve_options()->option_key();
			// $id = 'nua_registration_auto_approve_complete_message';
			//require_once ( plugin_dir_path(__FILE__).'/includes/messages.php');
			$message = nua_auto_approve_message();
			$message = nua_do_email_tags( $message, array(
				'context' => 'approved_message',
			) );
			// $message=pw_new_user_approve_options()->auto_approve_registration_complete_message($message);
			return $message;
	}



	public function invitation_code_columns( $columns ) {
		unset($columns['date']);
		unset($columns['title']);
		$columns['inv_code'] = __('Invitation Code', 'new-user-approve');
		$columns['usage'] = __('Uses Remaining', 'new-user-approve');
		$columns['expiry'] = __('Expiry', 'new-user-approve');
		$columns['status'] = __('Status', 'new-user-approve');
		$columns['actions'] = __('Actions', 'new-user-approve');

		return $columns;
	}

	public function invitation_code_columns_content( $column, $post_id ) {

		switch ($column) {

			case 'usage':
				echo esc_attr ( get_post_meta($post_id, $this->usage_limit_key, true) . '/' . get_post_meta($post_id, $this->total_code_key, true));
				break;

			case 'expiry':
				$exp_date = get_post_meta($post_id, $this->expiry_date_key, true);
				if (!empty($exp_date)) {
					echo esc_attr(gmdate('Y-m-d', $exp_date));
				}
				break;
			case 'status':
				echo esc_attr( get_post_meta($post_id, $this->status_key, true));
				break;
			case 'inv_code':
				echo esc_attr( get_post_meta($post_id, $this->code_key, true));
				break;
			case 'actions':
				if ('trash' != get_post_status($post_id)) {
					$deactivate_link = admin_url('edit.php?post_type=' . $this->code_post_type);
					$deactivate_link .= '&nua_action=deactivate&post_id=' . $post_id . '&nonce=' . wp_create_nonce('nua_deactivate-' . $post_id);
					?>
						<a href="<?php echo esc_url( $deactivate_link); ?>"><?php esc_html_e('Deactivate', 'new-user-approve'); ?></a> | <a href="<?php echo esc_url( get_edit_post_link($post_id) ); ?>"><?php esc_html_e('Edit', 'new-user-approve'); ?></a> | <a href="<?php echo esc_url( get_delete_post_link($post_id)); ?>"><?php esc_html_e('Delete', 'new-user-approve'); ?></a>
	<?php
				}

				break;
		}
	}
	public function option_invitation_code() {   
		if (isset($_POST['nua-free-invite-nonce-field']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nua-free-invite-nonce-field'])), 'nua-free-invite-nonce') ) {
			if (isset($_POST['nua_free_invitation']) && sanitize_text_field(wp_unslash( $_POST['nua_free_invitation'])) == 'enable') {
				update_option('nua_free_invitation', sanitize_text_field(wp_unslash($_POST['nua_free_invitation'])));
			} else if (isset($_POST['nua_inv_code_submit'])) {
				update_option('nua_free_invitation', '');
			}
		}
			
		$options = get_option('nua_free_invitation');
		$invitation_code_invite = ( $options == 'enable' ) ? $options : false;
		$nonce = wp_create_nonce('nua-free-invite-nonce');
		?>
			<form method="post" action="">
				 
			<input type ="hidden" name="nua-free-invite-nonce-field" value ="<?php echo esc_attr($nonce); ?>" />
			<div class = "nua-switch-text-checkbox">   <h4 class="nua-switch-text"> Enable/Disable: </h4>  <label class="nua_switch" for="nua_free_invitation" > <input id="nua_free_invitation" name="nua_free_invitation" type="checkbox" value="enable" <?php checked($invitation_code_invite, 'enable'); ?> />  <span class="nua_slider round"></span></label> </div>
			<p class="description"><?php esc_html_e('Invitation Code for user to register', 'new-user-approve'); ?></p>
				<tr>
					<th colspan="2">
						<p class="submit nua-submit"><input type="submit" name="nua_inv_code_submit" id="submit" class="button button-primary" value="Save Changes"></p>
					</th>
				</tr>
			</form>

			<?php
	}
} // End Class

function nua_invitation_code() {


	return NUA_Invitation_Code::instance();
}

	nua_invitation_code();
