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

if (!defined("ABSPATH")) {
    exit();
}
if (!class_exists("NUA_Invitation_Code")) {
    class NUA_Invitation_Code
    {
        private static $instance;
        public $code_post_type = "invitation_code";
        public $usage_limit_key = "_nua_usage_limit";
        public $expiry_date_key = "_nua_code_expiry";
        public $status_key = "_nua_code_status";
        public $code_key = "_nua_code";
        public $total_code_key = "_total_nua_code";
        public $registered_users = "_registered_users";
        private $option_group = "nua_options_group";
        public $option_key = "new_user_approve_options";
        /**
         * Returns the main instance.
         *
         * @return NUA_Invitation_Code
         */
        public static function instance()
        {
            if (!isset(self::$instance)) {
                self::$instance = new NUA_Invitation_Code();
            }
            return self::$instance;
        }

        private function __construct()
        {

            //Filter

            add_filter(
                "nua_disable_welcome_email",
                [$this, "nua_disable_welcome_email_callback"],
                10,
                2
            );

            $options = get_option("new_user_approve_options");
            if (
                isset($options["nua_free_invitation"]) &&
                $options["nua_free_invitation"] === "enable"
            ) {
                add_action("register_form", [
                    $this,
                    "nua_invitation_code_field",
                ]);
                add_filter(
                    "register_post",
                    [$this, "nua_invitation_status_code_field_validation"],
                    6,
                    3
                );
                add_filter(
                    "woocommerce_register_post",
                    [$this, "nua_woocommerce_invitation_code_validation"],
                    10,
                    3
                );

                add_filter(
                    "new_user_approve_default_status",
                    [$this, "nua_invitation_status_code"],
                    10,
                    2
                );
                add_action("woocommerce_register_form", [
                    $this,
                    "nua_invitation_code_field",
                ]);
                // compatibility with Ultimate Member plugin.
                add_action(
                    "um_after_form_fields",
                    [$this, "um_nua_invitation_code_field"],
                    10,
                    1
                );
                add_action(
                    "um_submit_form_errors_hook__registration",
                    [$this, "um_invite_code_check"],
                    20,
                    1
                );
                add_action(
                    "um_submit_form_errors_hook__profile",
                    [$this, "um_invite_code_check"],
                    20,
                    1
                );
                add_action(
                    "um_submit_form_errors_hook_login",
                    [$this, "um_invite_code_check"],
                    20,
                    1
                );
                add_action(
                    "nua_invited_user",
                    [$this, "message_above_regform"],
                    10,
                    1
                );
                // compatibility with UsersWP plugin.
                add_action(
                    "uwp_template_fields",
                    [$this, "uwp_nua_invitation_code_field"],
                    10,
                    1
                );
                add_filter(
                    "uwp_validate_fields_before",
                    [$this, "uwp_invite_code_check"],
                    10,
                    3
                );
            }

        }

        public function um_nua_invitation_code_field($args)
        {
            $flag = apply_filters("um_nua_hide_invitation_code_field", false, $args);

            if ($flag) {
                return;
            }

            $form_id = $args['form_id'];
            $options = get_option("new_user_approve_options");
            ?>

            <div id="um_field_<?php echo esc_attr($form_id); ?>_nua_invitation_code"
                class="um-field um-field-text  um-field-nua_invitation_code um-field-text um-field-type_text"
                data-key="nua_invitation_code">
                <div class="um-field-area">
                    <input autocomplete="off" class="um-form-field" type="text" name="nua_invitation_code"
                        id="nua_invitation_code-<?php echo esc_attr($form_id); ?>"
                        value="<?php echo esc_attr($_POST["nua_invitation_code-" . esc_attr($form_id)]); ?>">
                    <?php wp_nonce_field("nua_invitation_code_action", "nua_invitation_code_nonce"); ?>
                </div>
                <?php if (isset(UM()->form()->errors["nua_invitation_code"])): ?>
                    <div class="um-field-error" id="um-error-for-nua_invitation_code-<?php echo esc_attr($form_id); ?>">
                        <span class="um-field-arrow"><i class="um-faicon-caret-up"></i></span>
                        <?php echo esc_html(UM()->form()->errors["nua_invitation_code"]); ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        }

        public function um_invite_code_check($submitted_data)
        {
            $options = get_option("new_user_approve_options");

            if (isset($submitted_data["nua_invitation_code_nonce"]) && wp_verify_nonce($submitted_data["nua_invitation_code_nonce"], "nua_invitation_code_action")) {
                if (isset($submitted_data["nua_invitation_code"]) && !empty($submitted_data["nua_invitation_code"])) {
                    $args = [
                        "numberposts" => -1,
                        "post_type" => $this->code_post_type,
                        "post_status" => "publish",
                        "meta_query" => [
                            "relation" => "AND",
                            [
                                [
                                    "key" => $this->code_key,
                                    "value" => sanitize_text_field(
                                        $submitted_data["nua_invitation_code"]
                                    ),
                                    "compare" => "=",
                                ],
                                [
                                    "key" => $this->usage_limit_key,
                                    "value" => "1",
                                    "compare" => ">=",
                                ],
                                [
                                    "key" => $this->expiry_date_key,
                                    "value" => time(),
                                    "compare" => ">=",
                                ],
                                [
                                    "key" => $this->status_key,
                                    "value" => "Active",
                                    "compare" => "=",
                                ],
                            ],
                        ],
                    ];

                    $posts = get_posts($args);
                    $flag = true;

                    foreach ($posts as $post_inv) {
                        $code_inv = get_post_meta($post_inv->ID, $this->code_key, true);

                        if ($code_inv === sanitize_text_field($submitted_data["nua_invitation_code"])) {
                            $flag = false;
                            global $inv_file_lock;

                            $inv_file_lock = $this->invite_code_hold($post_inv->ID);
                            if ($inv_file_lock === false) {
                                UM()->form()->add_error('nua_invitation_code', "Server is busy, please try again!");
                            }
                        }
                    }

                    if ($flag) {
                        UM()->form()->add_error("nua_invitation_code", "The Invitation code is invalid");
                    }

                    if (isset($submitted_data["nua_invitation_code"]) && isset($options["nua_registration_deadline"]) && !isset($options["nua_auto_approve_deadline"])) {
                        UM()->form()->add_error("nua_invitation_code", "Cannot use Code because deadline exceeded.");
                    }
                } elseif (!isset($submitted_data["nua_invitation_code"]) || (isset($submitted_data["nua_invitation_code"]) && empty($submitted_data["nua_invitation_code"]) && !empty($options["nua_checkbox_textbox"]))) {
                    UM()->form()->add_error("nua_invitation_code", "Please add an Invitation code.");
                }
            } elseif (!isset($submitted_data["nua_invitation_code"]) || (isset($submitted_data["nua_invitation_code"]) && empty($submitted_data["nua_invitation_code"]) && !empty($options["nua_checkbox_textbox"]))) {
                UM()->form()->add_error("nua_invitation_code", "Something went wrong.");
            }
        }

        public function uwp_nua_invitation_code_field($form_type)
        {
            if ($form_type !== "register") {
                return;
            }
            $options = get_option("new_user_approve_options");
            $required = false;
            if (!empty($options["nua_checkbox_textbox"])) {
                $required = true;
            }
            ?>

            <p class="nua_inv_field form-group">
                <?php if ($required == true): ?>
                    <!-- snfr -->
                    <label for="invitation_code"><?php esc_html_e(
                        "Invitation Code",
                        "new-user-approve"
                    ); ?>&nbsp;
                        <span id="nua-required" aria-hidden="true" style="color:#a00">*</span>
                        <span class="screen-reader-text">Required</span>
                    </label>
                <?php else: ?>
                    <!-- snfr -->
                    <label> <?php esc_html_e("Invitation Code", "new-user-approve"); ?></label>
                <?php endif; ?>
                <input type="text" class="nua_invitation_code form-control" name="nua_invitation_code" />
                <?php wp_nonce_field(
                    "nua_invitation_code_action",
                    "nua_invitation_code_nonce"
                ); ?>
            </p>
            <?php
        }

        public function uwp_invite_code_check($errors, $data, $type)
        {
            if ($type !== "register") {
                return $errors;
            }

            $options = get_option("new_user_approve_options");

            // Use POST for nonce verification
            if (
                isset($_POST["nua_invitation_code_nonce"]) &&
                wp_verify_nonce(
                    $_POST["nua_invitation_code_nonce"],
                    "nua_invitation_code_action"
                )
            ) {
                if (
                    isset($data["nua_invitation_code"]) &&
                    !empty($data["nua_invitation_code"])
                ) {
                    $args = [
                        "numberposts" => -1,
                        "post_type" => $this->code_post_type,
                        "post_status" => "publish",
                        "meta_query" => [
                            "relation" => "AND",
                            [
                                [
                                    "key" => $this->code_key,
                                    "value" => sanitize_text_field(
                                        $data["nua_invitation_code"]
                                    ),
                                    "compare" => "=",
                                ],
                                [
                                    "key" => $this->usage_limit_key,
                                    "value" => "1",
                                    "compare" => ">=",
                                ],
                                [
                                    "key" => $this->expiry_date_key,
                                    "value" => time(),
                                    "compare" => ">=",
                                ],
                                [
                                    "key" => $this->status_key,
                                    "value" => "Active",
                                    "compare" => "=",
                                ],
                            ],
                        ],
                    ];

                    $posts = get_posts($args);
                    $flag = true;

                    foreach ($posts as $post_inv) {
                        $code_inv = get_post_meta(
                            $post_inv->ID,
                            $this->code_key,
                            true
                        );
                        if (
                            $code_inv ===
                            sanitize_text_field($data["nua_invitation_code"])
                        ) {
                            $flag = false;
                            global $inv_file_lock;
                            $inv_file_lock = $this->invite_code_hold(
                                $post_inv->ID
                            );
                            if ($inv_file_lock === false) {
                                $errors->add(
                                    "invcode_error",
                                    "<strong>Notice</strong>: Server is busy, please try again!"
                                );
                                return $errors;
                            }
                            return $errors;
                        }
                    }

                    if ($flag) {
                        $errors->add(
                            "invcode_error",
                            "<strong>ERROR</strong>: The Invitation code is invalid"
                        );
                        return $errors;
                    }

                    if (
                        isset($data["nua_invitation_code"]) &&
                        isset($options["nua_registration_deadline"]) &&
                        !isset($options["nua_auto_approve_deadline"])
                    ) {
                        $errors->add(
                            "invcode_error",
                            "<strong>Error</strong>: Cannot use Code because deadline exceeded."
                        );
                    }
                } elseif (
                    !isset($data["nua_invitation_code"]) ||
                    (isset($data["nua_invitation_code"]) &&
                        empty($data["nua_invitation_code"]) &&
                        !empty($options["nua_checkbox_textbox"]))
                ) {
                    $errors->add(
                        "invcode_error",
                        "<strong>ERROR</strong>: Please add an Invitation code."
                    );
                }
            } elseif (
                !isset($data["nua_invitation_code"]) ||
                (isset($data["nua_invitation_code"]) &&
                    empty($data["nua_invitation_code"]) &&
                    !empty($options["nua_checkbox_textbox"]))
            ) {
                $errors->add(
                    "invcode_nonce_error",
                    "<strong>ERROR</strong>: Something went wrong."
                );
            }

            return $errors;
        }

        public function nua_disable_welcome_email_callback($disabled, $user_id)
        {
            $status = get_user_meta($user_id, "pw_user_status", true);
            if ("approved" == $status) {
                $disabled = true;
            }
            return $disabled;
        }



        /**
         *
         * @since 2.5.2
         */
        public function invitation_code_already_exists($code)
        {
            $posts_with_meta = get_posts([
                "posts_per_page" => 1, // we only want to check if any exists, so don't need to get all of them
                "meta_key" => $this->code_key,
                "meta_value" => $code,
                "post_type" => $this->code_post_type,
            ]);

            if (count($posts_with_meta)) {
                return true;
            }
            return false;
        }
        /**
         *
         * @since 2.5.2
         */
        public function invitation_code_limit_check($code)
        {
            $is_inv_code_limit = [
                "numberposts" => 1,
                "post_type" => $this->code_post_type,
                // we are checking two things code and its limit , so we are using meta query
                "meta_query" => [
                    "relation" => "AND",
                    [
                        [
                            "key" => $this->code_key,
                            "value" => $code,
                            "compare" => "=",
                        ],
                        [
                            "key" => $this->usage_limit_key,
                            "value" => "1",
                            "compare" => ">=",
                        ],
                        [
                            "relation" => "OR",
                            [
                                "key" => $this->status_key,
                                "value" => "Active",
                                "compare" => "=",
                            ],
                            [
                                "key" => $this->status_key,
                                "value" => "Expired",
                                "compare" => "=",
                            ],
                        ],
                    ],
                ],
            ];

            $is_inv_code_limit = get_posts($is_inv_code_limit);
            if (count($is_inv_code_limit)) {
                return true;
            } else {
                return false;
            }
        }
        /**
         *
         * @since 2.5.2
         */
        public function invitation_code_expiry_check($code)
        {
            $is_inv_code_expired = [
                "numberposts" => 1,
                "post_type" => $this->code_post_type,
                // we are checking two things code and its expiry , so we are using meta query
                "meta_query" => [
                    "relation" => "AND",
                    [
                        [
                            "key" => $this->code_key,
                            "value" => $code,
                            "compare" => "=",
                        ],
                        [
                            "key" => $this->expiry_date_key,
                            "value" => time(),
                            "compare" => ">=",
                        ],
                        [
                            "relation" => "OR",
                            [
                                "key" => $this->status_key,
                                "value" => "Active",
                                "compare" => "=",
                            ],
                            [
                                "key" => $this->status_key,
                                "value" => "Expired",
                                "compare" => "=",
                            ],
                        ],
                    ],
                ],
            ];

            $is_inv_code_expired = get_posts($is_inv_code_expired);

            if (count($is_inv_code_expired)) {
                return false;
            } else {
                return true;
            }
        }

        public function get_available_invitation_codes()
        {
            $args = [
                "numberposts" => -1,
                "post_type" => $this->code_post_type,
                "post_status" => "publish",
                "meta_query" => [
                    "relation" => "AND",
                    [
                        [
                            "key" => $this->usage_limit_key,
                            "value" => "1",
                            "compare" => ">=",
                        ],

                        [
                            "key" => $this->expiry_date_key,
                            "value" => time(),
                            "compare" => ">=",
                        ],
                        [
                            "key" => $this->status_key,
                            "value" => "Active",
                            "compare" => "=",
                        ],
                    ],
                ],
            ];

            $codes = get_posts($args);

            return $codes;
        }

        public function nua_invitation_code_field()
        {
            $required = " *";
            if (true === apply_filters("nua_invitation_code_optional", true)) {
                $required = " (optional)";
            }
            ?>
            <?php $nonce = wp_create_nonce("nua-invitation-code-nonce"); ?>

            <p>
                <label> <?php esc_html_e(
                    "Invitation Code",
                    "new-user-approve"
                ); ?><span><?php esc_attr_e($required); ?></span></label>
                <input type="hidden" name="nua_invitation_code_nonce_field" value=<?php esc_attr_e(
                    $nonce
                ); ?> />
                <input type="text" class="nua_invitation_code" name="nua_invitation_code" />
            </p>
            <?php
        }

        /**
         *
         * @since 2.5.2
         */
        public function inv_code_alreay_exists_notification()
        {
            $class = "notice notice-error";
            $message = esc_html__(
                "Invitation Code Already Exists.",
                "new-user-approve"
            );
            printf(
                '<div class="%1$s"><p>%2$s</p></div>',
                esc_attr($class),
                esc_html($message)
            );
            delete_transient("inv_code_exists"); // No need to keep this tip after displaying notification
        }

        public function invite_code_hold($inv_id)
        {
            $inv_file = fopen($this->invite_code_lock_file($inv_id), "w+");

            if (!flock($inv_file, LOCK_EX | LOCK_NB)) {
                return false;
            }

            ftruncate($inv_file, 0);
            fwrite($inv_file, microtime(true));
            return $inv_file;
        }

        public function invite_code_release($inv_file, $inv_id)
        {
            if (is_resource($inv_file)) {
                fflush($inv_file);
                flock($inv_file, LOCK_UN);
                fclose($inv_file);
                unlink($this->invite_code_lock_file($inv_id));

                return true;
            }

            return false;
        }

        public function invite_code_lock_file($inv_id)
        {
            return apply_filters(
                "invite_code_lock_file",
                get_temp_dir() . "/invite-code" . $inv_id . ".lock",
                $inv_id
            );
        }

        public function nua_invitation_status_code_field_validation(
            $user_login,
            $user_email,
            $errors
        ) {
            $options = get_option("new_user_approve_options");
            $code_optional = apply_filters(
                "nua_invitation_code_optional",
                true
            );
            $nonce = isset($_POST["nua_invitation_code_nonce_field"])
                ? sanitize_text_field(
                    wp_unslash($_POST["nua_invitation_code_nonce_field"])
                )
                : "";
            if (!wp_verify_nonce($nonce, "nua-invitation-code-nonce")) {
                $nonce = "";
            }

            if (
                isset($_POST["nua_invitation_code"]) &&
                !empty($_POST["nua_invitation_code"])
            ) {
                // display the Error on registration form when invitation code has expired or limit exceeded
                $code = sanitize_text_field(
                    wp_unslash($_POST["nua_invitation_code"])
                );
                $is_inv_code_exist = $this->invitation_code_already_exists(
                    $code
                );
                $is_inv_code_limit = $this->invitation_code_limit_check($code);
                $is_inv_expired = $this->invitation_code_expiry_check($code);
                if (true == $is_inv_code_exist && true == $is_inv_expired) {
                    $error_message = apply_filters(
                        "nua_invitation_code_err",
                        __(
                            "<strong>ERROR</strong>: Invitation code has been expired",
                            "new-user-approve"
                        ),
                        "",
                        $errors
                    );
                    $errors->add("invcode_error", $error_message);
                    return $errors;
                } elseif (
                    true == $is_inv_code_exist &&
                    false == $is_inv_code_limit
                ) {
                    $error_message = apply_filters(
                        "nua_invitation_code_err",
                        __(
                            "<strong>ERROR</strong>: Invitation code limit_exceeded",
                            "new-user-approve"
                        ),
                        "",
                        $errors
                    );
                    $errors->add("invcode_error", $error_message);
                    return $errors;
                }

                $args = [
                    "numberposts" => -1,
                    "post_type" => $this->code_post_type,
                    "post_status" => "publish",
                    "meta_query" => [
                        "relation" => "AND",
                        [
                            [
                                "key" => $this->code_key,
                                "value" => sanitize_text_field(
                                    wp_unslash($_POST["nua_invitation_code"])
                                ),
                                "compare" => "=",
                            ],
                            [
                                "key" => $this->usage_limit_key,
                                "value" => "1",
                                "compare" => ">=",
                            ],
                            [
                                "key" => $this->expiry_date_key,
                                "value" => time(),
                                "compare" => ">=",
                            ],
                            [
                                "key" => $this->status_key,
                                "value" => "Active",
                                "compare" => "=",
                            ],
                        ],
                    ],
                ];
                $posts = get_posts($args);
                $code_inv = "";
                foreach ($posts as $post_inv) {
                    $code_inv = get_post_meta(
                        $post_inv->ID,
                        $this->code_key,
                        true
                    );

                    if ($_POST["nua_invitation_code"] == $code_inv) {
                        global $inv_file_lock;
                        $inv_file_lock = $this->invite_code_hold($post_inv->ID);
                        if ($inv_file_lock === false) {
                            $errors->add(
                                "invcode_error",
                                "<strong>Notice</strong>: Server is busy, please try again!"
                            );
                            return $errors;
                        }

                        return $errors;
                    }
                }

                $error_message = apply_filters(
                    "nua_invitation_code_err",
                    __(
                        "<strong>ERROR</strong>: The Invitation code is invalid",
                        "new-user-approve"
                    ),
                    $code_inv,
                    $errors
                );
                $errors->add("invcode_error", $error_message);
            } elseif (
                !isset($_POST["nua_invitation_code"]) ||
                (isset($_POST["nua_invitation_code"]) &&
                    empty($_POST["nua_invitation_code"]) &&
                    !empty(get_option("nua_free_invitation")) &&
                    true !== $code_optional)
            ) {
                $error_message = apply_filters(
                    "nua_invitation_code_err",
                    __(
                        "<strong>ERROR</strong>: Please add an Invitation code.",
                        "new-user-approve"
                    ),
                    "",
                    $errors
                );
                $errors->add("invcode_error", $error_message);
            }
            return $errors;
        }

        public function nua_woocommerce_invitation_code_validation(
            $username,
            $email,
            $validation_errors
        ) {
            $code_optional = apply_filters(
                "nua_invitation_code_optional",
                true
            );

            $nonce = isset($_POST["nua_invitation_code_nonce_field"])
                ? sanitize_text_field(
                    wp_unslash($_POST["nua_invitation_code_nonce_field"])
                )
                : "";
            if (!wp_verify_nonce($nonce, "nua-invitation-code-nonce")) {
                $nonce = "";
            }

            if (isset($_POST["nua_invitation_code"])) {
                $code = sanitize_text_field(
                    wp_unslash($_POST["nua_invitation_code"])
                );

                if (empty($code)) {
                    // Don't run validation if field is empty and it's optional
                    if (
                        !empty(get_option("nua_free_invitation")) &&
                        true !== $code_optional
                    ) {
                        $error_message = apply_filters(
                            "nua_invitation_code_err",
                            __(
                                "<strong>ERROR</strong>: Please add an Invitation code.",
                                "new-user-approve"
                            ),
                            "",
                            $validation_errors
                        );
                        $validation_errors->add(
                            "invcode_error",
                            $error_message
                        );
                    }
                    return $validation_errors;
                }

                $is_inv_code_exist = $this->invitation_code_already_exists(
                    $code
                );
                $is_inv_code_limit = $this->invitation_code_limit_check($code);
                $is_inv_expired = $this->invitation_code_expiry_check($code);
                if (true == $is_inv_code_exist && true == $is_inv_expired) {
                    $error_message = apply_filters(
                        "nua_invitation_code_err",
                        __(
                            "<strong>ERROR</strong>: Invitation code has been expired",
                            "new-user-approve"
                        ),
                        "",
                        $validation_errors
                    );
                    $validation_errors->add("invcode_error", $error_message);
                    return $validation_errors;
                } elseif (
                    true == $is_inv_code_exist &&
                    false == $is_inv_code_limit
                ) {
                    $error_message = apply_filters(
                        "nua_invitation_code_err",
                        __(
                            "<strong>ERROR</strong>: Invitation code limit_exceeded",
                            "new-user-approve"
                        ),
                        "",
                        $validation_errors
                    );
                    $validation_errors->add("invcode_error", $error_message);
                    return $validation_errors;
                }

                $args = [
                    "numberposts" => -1,
                    "post_type" => $this->code_post_type,
                    "post_status" => "publish",
                    "meta_query" => [
                        "relation" => "AND",
                        [
                            [
                                "key" => $this->code_key,
                                "value" => sanitize_text_field(
                                    wp_unslash($_POST["nua_invitation_code"])
                                ),
                                "compare" => "=",
                            ],
                            [
                                "key" => $this->usage_limit_key,
                                "value" => "1",
                                "compare" => ">=",
                            ],
                            [
                                "key" => $this->expiry_date_key,
                                "value" => time(),
                                "compare" => ">=",
                            ],
                            [
                                "key" => $this->status_key,
                                "value" => "Active",
                                "compare" => "=",
                            ],
                        ],
                    ],
                ];
                $posts = get_posts($args);
                $code_inv = "";
                foreach ($posts as $post_inv) {
                    $code_inv = get_post_meta(
                        $post_inv->ID,
                        $this->code_key,
                        true
                    );

                    if ($_POST["nua_invitation_code"] == $code_inv) {
                        global $inv_file_lock;
                        $inv_file_lock = $this->invite_code_hold($post_inv->ID);
                        if ($inv_file_lock === false) {
                            $validation_errors->add(
                                "invcode_error",
                                "<strong>Notice</strong>: Server is busy, please try again!"
                            );
                            return $validation_errors;
                        }

                        return $validation_errors;
                    }
                }

                $error_message = apply_filters(
                    "nua_invitation_code_err",
                    __(
                        "<strong>ERROR</strong>: The Invitation code is invalid",
                        "new-user-approve"
                    ),
                    $code_inv,
                    $validation_errors
                );
                $validation_errors->add("invcode_error", $error_message);
            }

            return $validation_errors;
        }

        public function nua_invitation_status_code($status, $user_id)
        {
            $nonce = isset($_POST["nua_invitation_code_nonce_field"])
                ? sanitize_text_field(
                    wp_unslash($_POST["nua_invitation_code_nonce_field"])
                )
                : "";
            if (!wp_verify_nonce($nonce, "nua-invitation-code-nonce")) {
                $nonce = "";
            }

            if (
                isset($_POST["nua_invitation_code"]) &&
                !empty($_POST["nua_invitation_code"])
            ) {
                $args = [
                    "numberposts" => -1,
                    "post_type" => $this->code_post_type,
                    "post_status" => "publish",
                    "meta_query" => [
                        "relation" => "AND",
                        [
                            [
                                "key" => $this->code_key,
                                "value" => sanitize_text_field(
                                    wp_unslash($_POST["nua_invitation_code"])
                                ),
                                "compare" => "=",
                            ],
                            [
                                "key" => $this->usage_limit_key,
                                "value" => "1",
                                "compare" => ">=",
                            ],
                            [
                                "key" => $this->expiry_date_key,
                                "value" => time(),
                                "compare" => ">=",
                            ],
                            [
                                "key" => $this->status_key,
                                "value" => "Active",
                                "compare" => "=",
                            ],
                        ],
                    ],
                ];

                $posts = get_posts($args);

                foreach ($posts as $post_inv) {
                    $code_inv = get_post_meta(
                        $post_inv->ID,
                        $this->code_key,
                        true
                    );

                    if (
                        sanitize_text_field(
                            wp_unslash($_POST["nua_invitation_code"])
                        ) == $code_inv
                    ) {
                        $register_user = get_post_meta(
                            $post_inv->ID,
                            $this->registered_users,
                            true
                        );

                        if (empty($register_user)) {
                            update_post_meta(
                                $post_inv->ID,
                                $this->registered_users,
                                [$user_id]
                            );
                        } else {
                            //$unserilize_array = unserialize($register_user);
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
                        // Release lock
                        global $inv_file_lock;
                        $this->invite_code_release(
                            $inv_file_lock,
                            $post_inv->ID
                        );

                        if ($current_useage == 0) {
                            update_post_meta(
                                $post_inv->ID,
                                $this->status_key,
                                "Expired"
                            );
                        }
                        $status = "approved";
                        pw_new_user_approve()->approve_user($user_id);
                        do_action("nua_invited_user", $user_id, $code_inv);
                        return $status;
                    }
                }
            }
            return $status;
        }

        public function message_above_regform($user_id)
        {
            add_filter(
                "new_user_approve_pending_message",
                [$this, "msg_on_auto_approve_invitation_callback"],
                10,
                1
            );
        }


        public function msg_on_auto_approve_invitation_callback($message)
        {
            // $opt=pw_new_user_approve_options()->option_key();
            // $id = 'nua_registration_auto_approve_complete_message';
            //require_once ( plugin_dir_path(__FILE__).'/includes/messages.php');
            $message = nua_auto_approve_message();
            $message = nua_do_email_tags($message, [
                "context" => "approved_message",
            ]);
            // $message=pw_new_user_approve_options()->auto_approve_registration_complete_message($message);
            return $message;
        }

    } // End Class
}
// phpcs:ignore
function nua_invitation_code()
{
    return NUA_Invitation_Code::instance();
}

nua_invitation_code();
