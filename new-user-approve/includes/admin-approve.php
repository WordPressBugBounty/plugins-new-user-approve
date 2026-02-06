<?php

/**
 * Class pw_new_user_approve_admin_approve
 * Admin must approve all new users
 */

// Exit if accessed directly
if (!defined("ABSPATH")) {
    exit();
}
if (!class_exists("PW_New_User_Approve_Admin_Approve")) {
    class PW_New_User_Approve_Admin_Approve
    {
        public $_admin_page = "new-user-approve-admin";
        

        /**
         * The only instance of pw_new_user_approve_admin_approve.
         *
         * @var PW_New_User_Approve_Admin_Approve
         */
        private static $instance;

        /**
         * Returns the main instance.
         *
         * @return PW_New_User_Approve_Admin_Approve
         */
        public static function instance()
        {
            if (!isset(self::$instance)) {
                self::$instance = new PW_New_User_Approve_Admin_Approve();
            }
            return self::$instance;
        }

        /**
         * @since 1.0
         * @since 2.1 `admin_post_nua-save-api-key` added for zapier
         */
        private function __construct()
        {
            // Actions
            add_action("admin_menu", [$this, "admin_menu_link"], 10);
            add_action(
                "admin_menu",
                [$this, "admin_menu_upgrade_link"],
                99999999999999
            );
            add_action("admin_menu", [$this, "admin_menu_settings_pro"], 31);
            add_action("admin_menu", [$this, "admin_menu_autoApprove_pro"], 31);
            // add_action('admin_menu', array( $this, 'admin_menu_account_pro' ), 999999999999);
            add_action("admin_init", [$this, "process_input"]);
            add_action("admin_notices", [$this, "admin_notice"]);
            add_action("admin_init", [$this, "notice_ignore"]);
            add_action("admin_footer", [$this, "highlight_nua_menu"]);
        }

        /**
         * Add the new menu item to the users portion of the admin menu
         *
         * @uses admin_menu
         */
        public function admin_menu_link()
        {
            $show_admin_page = apply_filters(
                "new_user_approve_show_admin_page",
                true
            );
            $cap_main = current_user_can("manage_options")
                ? "manage_options"
                : "nua_main_menu";
            if ($show_admin_page) {
                $hook = add_submenu_page(
                    "new-user-approve-admin",
                    __("New User Approve", "new-user-approve"),
                    __("Dashboard", "new-user-approve"),
                    $cap_main,
                    $this->_admin_page,
                    [$this, "approve_admin"],
                    1
                );
                $hook = add_submenu_page(
                    "new-user-approve-admin",
                    __("New User Approve", "new-user-approve"),
                    __("Users", "new-user-approve"),
                    "nua_users_cap",
                    "new-user-approve-admin#/action=users/tab=all-users",
                    [$this, "menu_options_page"],
                    2
                );
                $hook = add_submenu_page(
                    "new-user-approve-admin",
                    __("New User Approve", "new-user-approve"),
                    __("Invitation Code", "new-user-approve"),
                    "nua_view_invitation_tab",
                    "new-user-approve-admin#/action=inv-codes/tab=all-codes",
                    [$this, "menu_options_page"],
                    3
                );
                $hook = add_submenu_page(
                    "new-user-approve-admin",
                    __("New User Approve", "new-user-approve"),
                    __("Auto Approve", "new-user-approve"),
                    "nua_auto_approve_cap",
                    "new-user-approve-admin#/action=auto-approve/tab=whitelist",
                    [$this, "menu_options_page"],
                    5
                );

                $menu_text = sprintf( 
                    '%s<span class="dashicons dashicons-smartphone"></span><span class="menu-counter">%s</span>', 
                    __( "Mobile App", "new-user-approve" ), 
                    __( "New", "new-user-approve" ) 
                );

                $hook = add_submenu_page(
                    "new-user-approve-admin",
                    __("New User Approve", "new-user-approve"),
                    $menu_text,
                    "nua_mobile_app_cap",
                    "new-user-approve-admin#/action=mobile-app",
                    [$this, "menu_options_page"],
                    8
                );

                add_action("load-" . $hook, [$this, "admin_enqueue_scripts"]);
            }
        }

        public function admin_menu_upgrade_link()
        {
            $show_admin_page = apply_filters(
                "new_user_approve_show_admin_page",
                true
            );

            $_admin_upgrade_page = "https://newuserapprove.com/pricing/?utm_source=plugin&utm_medium=get_pro_menu";

            $now         = current_time('timestamp');
            $bf_deadline = strtotime('2025-12-10 23:59:59');

            $label_main = __("Upgrade To", "new-user-approve");
            $label_pro  = __("Pro", "new-user-approve");

            if ( $now < $bf_deadline ) {
                $label_main = __("Black Friday", "new-user-approve");
                $label_pro  = __("Deals", "new-user-approve");

                $_admin_upgrade_page = "https://newuserapprove.com/pricing/?utm_source=plugin&utm_medium=plugins_page_bf";
            }

            if ( $show_admin_page ) {
                add_submenu_page(
                    $this->_admin_page,
                    __("🎁 Get Pro Bundle", "new-user-approve"),
                    sprintf(
                       '<span style="color:#adff2f!important;">🎁 %1$s <b>%2$s</b></span>',
                        $label_main,
                        $label_pro
                    ),
                    "nua_main_menu",
                    $_admin_upgrade_page,
                    "",
                    7
                );
            }
        }

        public function admin_menu_autoApprove_pro()
        {
            $cap_main = current_user_can("manage_options")
                ? "manage_options"
                : "nua_main_menu";
            add_submenu_page(
                $this->_admin_page,
                __("Integration", "new-user-approve"),
                __("Integration", "new-user-approve"),
                "nua_integration_cap",
                "new-user-approve-admin#/action=integrations",
                [$this, "menu_options_page"],
                4
            );
        }

        public function admin_menu_settings_pro()
        {
            $cap_main = current_user_can("manage_options")
                ? "manage_options"
                : "nua_main_menu";
            add_submenu_page(
                $this->_admin_page,
                __("Settings", "new-user-approve"),
                __("Settings", "new-user-approve"),
                "nua_settings_cap",
                "new-user-approve-admin#/action=settings/tab=general",
                [$this, "menu_options_page"],
                6
            );
        }

        public function highlight_nua_menu()
        {
            global $current_screen;

            if (
                isset($current_screen->id) &&
                "toplevel_page_new-user-approve-admin" === $current_screen->id
            ) { ?>
			<script type="text/javascript">
				function updateMenuHighlight() {
					var hash = window.location.hash;
					var menuItems = jQuery('#adminmenu .toplevel_page_new-user-approve-admin ul.wp-submenu li');
					menuItems.removeClass('current');

					// Dashboard tab
					if (hash === '' || hash === '#' || hash === '#/') {
						// Match the link WITHOUT hash (dashboard)
						menuItems.find('a[href="admin.php?page=new-user-approve-admin"]').parent().addClass('current');
					} else {
						// Match other links with hash
						menuItems.find('a').each(function () {
							var href = jQuery(this).attr('href');
							if (href && href.endsWith(hash)) {
								jQuery(this).parent().addClass('current');
							}
						});
					}
				}

				jQuery(document).ready(function ($) {
					$(window).on('hashchange', updateMenuHighlight);
					updateMenuHighlight();

					$(document).on('click', '.nua_dash_parent_tablist button', function () {
						setTimeout(updateMenuHighlight, 50);
					});
				});

			</script>

				<?php }
        }

        /**
         * Create the view for the admin interface
         */
        public function approve_admin()
        {
            require_once pw_new_user_approve()->get_plugin_dir() .
                "/admin/templates/approve.php";
        }

        /**
         * Output the table that shows the registered users grouped by status
         *
         * @param string $status the filter to use for which the users will be queried. Possible values are pending, approved, or denied.
         */
        public function user_table($status)
        {
            global $current_user;

            $approve = "denied" == $status || "pending" == $status;
            $deny = "approved" == $status || "pending" == $status;

            $user_status = pw_new_user_approve()->get_user_statuses($status);
            $users = $user_status[$status];
            //filter user by search
            if (isset($_GET["nua_search_box"])) {
                $searchTerm = sanitize_text_field($_GET["nua_search_box"]);

                $filterFunction = function ($users) use ($searchTerm) {
                    $usernameMatches =
                        stripos($users->user_login, $searchTerm) !== false;
                    $emailMatches =
                        stripos($users->user_email, $searchTerm) !== false;
                    $firstNameMatches =
                        stripos($users->first_name, $searchTerm) !== false;
                    $lastNameMatches =
                        stripos($users->last_name, $searchTerm) !== false;
                    return $usernameMatches ||
                        $emailMatches ||
                        $firstNameMatches ||
                        $lastNameMatches;
                };
                $users = array_filter($users, $filterFunction);
            }

            //get user count for pagination
            $user_count = 1;
            $paged =
                isset($_REQUEST["paged"]) && !empty($_REQUEST["paged"])
                    ? absint($_REQUEST["paged"])
                    : 1;
            $total_pages = 0;
            $nua_users_transient = apply_filters("nua_users_transient", true);
            if (!$nua_users_transient) {
                $user_count = pw_new_user_approve()->_get_users_by_status(
                    true,
                    $status
                );
            } else {
                //for transient when all status user retrieve
                $user_count = count($users);
                $offset = ($paged - 1) * 15;
                $users = array_slice($users, $offset, 15);
                $total_pages = ceil($user_count / 15);
            }

            if (count($users) > 0) {
                if ("denied" == $status) { ?>
				<p class="status_heading"><?php esc_html_e(
        "Denied Users",
        "new-user-approve"
    ); ?></p>
				<?php } elseif ("approved" == $status) { ?>
				<p class="status_heading"><?php esc_html_e(
        "Approved Users",
        "new-user-approve"
    ); ?></p>
				<?php } elseif ("pending" == $status) { ?>
				<p class="status_heading"><?php esc_html_e(
        "Pending Users",
        "new-user-approve"
    ); ?></p>
				<?php } ?>
			<table class="widefat">
				<thead>
				<tr class="thead">
					<th><?php esc_html_e("Username", "new-user-approve"); ?></th>
					<th><?php esc_html_e("Name", "new-user-approve"); ?></th>
					<th><?php esc_html_e("E-mail", "new-user-approve"); ?></th>
					<?php if ("pending" == $status) { ?>
						<th colspan="2"><?php esc_html_e("Action", "new-user-approve"); ?></th>
					<?php } else { ?>
						<th><?php esc_html_e("Action", "new-user-approve"); ?></th>
					<?php } ?>
				</tr>
				</thead>
				<tbody class="nua-user-list">
				<?php
    // show each of the users
    $row = 1;
    foreach ($users as $user) {

        $class = $row % 2 ? "" : ' class="alternate"';
        $avatar = get_avatar($user->user_email, 32);

        if ($approve) {
            $approve_link =
                get_option("siteurl") .
                "/wp-admin/admin.php?page=" .
                $this->_admin_page .
                "&user=" .
                $user->ID .
                "&status=approve";
            if (isset($_REQUEST["tab"])) {
                $approve_link = add_query_arg(
                    [
                        "tab" => sanitize_text_field(
                            wp_unslash($_REQUEST["tab"])
                        ),
                    ],
                    $approve_link
                );
            }

            $approve_link = wp_nonce_url(
                $approve_link,
                "pw_new_user_approve_action_" . get_class($this)
            );
        }
        if ($deny) {
            $deny_link =
                get_option("siteurl") .
                "/wp-admin/admin.php?page=" .
                $this->_admin_page .
                "&user=" .
                $user->ID .
                "&status=deny";
            if (isset($_REQUEST["tab"])) {
                $deny_link = add_query_arg(
                    "tab",
                    sanitize_text_field(wp_unslash($_REQUEST["tab"])),
                    $deny_link
                );
            }

            $deny_link = wp_nonce_url(
                $deny_link,
                "pw_new_user_approve_action_" . get_class($this)
            );
        }

        if (current_user_can("edit_user", $user->ID)) {
            if ($current_user->ID == $user->ID) {
                $edit_link = "profile.php";
            } else {
                $SERVER_URI = get_admin_url();
                if (isset($_SERVER["REQUEST_URI"])) {
                    $SERVER_URI = sanitize_text_field(
                        wp_unslash($_SERVER["REQUEST_URI"])
                    );
                }
                $edit_link = add_query_arg(
                    "wp_http_referer",
                    urlencode(esc_url($SERVER_URI)),
                    "user-edit.php?user_id=$user->ID"
                );
            }
            $edit =
                $avatar == true
                    ? '<strong style="position: relative; top: -17px; left: 6px;"><a class="users_edit_links" href="' .
                        esc_url($edit_link) .
                        '">' .
                        esc_html($user->user_login) .
                        "</a></strong>"
                    : '<strong style="top: -17px; left: 6px;"><a href="' .
                        esc_url($edit_link) .
                        '">' .
                        esc_html($user->user_login) .
                        "</a></strong>";
        } else {
            $edit =
                $avatar == true
                    ? '<strong style="position: relative; top: -17px; left: 6px;">' .
                        esc_html($user->user_login) .
                        "</strong>"
                    : '<strong style="top: -17px; left: 6px;">' .
                        esc_html($user->user_login) .
                        "</strong>";
        }
        ?>
					<tr <?php echo esc_attr($class); ?>>
					<td><?php echo wp_kses_post($avatar . " " . $edit); ?></td>
					<td><?php echo esc_attr(get_user_meta($user->ID, "first_name", true)) .
         " " .
         esc_attr(get_user_meta($user->ID, "last_name", true)); ?></td>
					<td><a href="mailto:<?php esc_attr_e($user->user_email); ?>"
						   title="<?php esc_attr_e("email:", "new-user-approve"); ?> <?php esc_attr_e(
     $user->user_email
 ); ?>"><?php esc_html_e($user->user_email); ?></a>
					</td>

					<td class="actions-btn">
						<?php if ($approve && $user->ID != get_current_user_id()) { ?>
							<span><a class="button approve-btn" href= "<?php echo esc_url(
           $approve_link
       ); ?>" title="<?php esc_attr_e(
    "Approve",
    "new-user-approve"
); ?> <?php esc_attr_e($user->user_login); ?>"><?php esc_html_e(
    "Approve",
    "new-user-approve"
); ?></a> </span>
						<?php } ?>

						<?php if ($deny && $user->ID != get_current_user_id()) { ?>
							<span><a class="button deny-btn" href="<?php echo esc_url(
           $deny_link
       ); ?>" title="<?php esc_attr_e(
    "Deny",
    "new-user-approve"
); ?> <?php esc_attr_e($user->user_login); ?>"><?php echo esc_html(
    "Deny",
    "new-user-approve"
); ?></a></span>
						<?php } ?>

					</td>

					<?php if ($user->ID == get_current_user_id()): ?>
						<td>&nbsp;</td>
					<?php endif; ?>
					</tr>
					<?php $row++;
    }
    ?>
				</tbody>
				<tfoot>
				<tr class="tfoot">
					<th colspan="4" >
					<?php
     $pagination = paginate_links([
         "base" => add_query_arg("paged", "%#%"),
         "format" => "",
         "current" => $paged,
         "total" => $total_pages,
     ]);
     echo '<nav class="pagination">';
     echo wp_kses_post($pagination ?? "");
     echo "</nav>";
     ?>
					</th>
				</tr>
				</tfoot>
			</table>
			<?php
            } else {

                $status_i18n = $status;
                if ($status == "approved") {
                    $status_i18n = __("approved", "new-user-approve");
                } elseif ($status == "denied") {
                    $status_i18n = __("denied", "new-user-approve");
                } elseif ($status == "pending") {
                    $status_i18n = __("pending", "new-user-approve");
                }
                // translators: %s is for translated status of user
                echo "<p>" .
                    sprintf(
                        esc_html__(
                            "There is no user found in %s status tab.",
                            "new-user-approve"
                        ),
                        esc_attr($status_i18n)
                    ) .
                    "</p>";
                ?>
		<?php
            }
        }

        /**
         * Accept input from admin to modify a user
         *
         * @uses init
         */
        public function process_input()
        {
            if (
                isset($_GET["page"]) &&
                $_GET["page"] == $this->_admin_page &&
                isset($_GET["status"]) &&
                isset($_GET["user"])
            ) {
                $valid_request = check_admin_referer(
                    "pw_new_user_approve_action_" . get_class($this)
                );

                if ($valid_request) {
                    $status = sanitize_key($_GET["status"]);
                    $user_id = absint(sanitize_user(wp_unslash($_GET["user"])));

                    pw_new_user_approve()->update_user_status(
                        $user_id,
                        $status
                    );
                }
            }
        }

        /**
         * Display a notice on the legacy page that notifies the user of the new interface.
         *
         * @uses admin_notices
         */
        public function admin_notice()
        {
            $screen = get_current_screen();

            if ($screen->id == "users_page_new-user-approve-admin") {
                $user_id = get_current_user_id();

                // Check that the user hasn't already clicked to ignore the message
                if (
                    !get_user_meta(
                        $user_id,
                        "pw_new_user_approve_ignore_notice"
                    )
                ) { ?>
				<div class="updated"><p>
					<?php
                    // translators: %1$s is for user admin page url and %2$s for hide notice url
                    ?>
				<?php printf(
        wp_kses_post(
            __(
                'You can now update user status on the <a href="%1$s">users admin page</a>. | <a href="%2$s">Hide Notice</a>',
                "new-user-approve"
            ),
            admin_url("users.php"),
            esc_url(add_query_arg(["new-user-approve-ignore-notice" => 1]))
        )
    ); ?>
				 </p></div>
			<?php }
            }
        }

        /**
         * If user clicks to ignore the notice, add that to their user meta
         *
         * @uses admin_init
         */
        public function notice_ignore()
        {
            if (
                isset($_GET["new-user-approve-ignore-notice"]) &&
                "1" == $_GET["new-user-approve-ignore-notice"]
            ) {
                $user_id = get_current_user_id();
                add_user_meta(
                    $user_id,
                    "pw_new_user_approve_ignore_notice",
                    "1",
                    true
                );
            }
        }

        public function admin_enqueue_scripts()
        {
            wp_enqueue_script("post");
        }

    }
}
// phpcs:ignore
function pw_new_user_approve_admin_approve()
{
    return PW_New_User_Approve_Admin_Approve::instance();
}

pw_new_user_approve_admin_approve();
