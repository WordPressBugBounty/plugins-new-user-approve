<?php

// Exit if accessed directly
if (!defined("ABSPATH")) {
    exit();
}

class NUA_Role_Editor_API
{
    public static $instance;

    /**
     * WordPress built-in capabilities that cannot be deleted (only toggled).
     */
    private $core_caps = [
        'read', 'edit_posts', 'edit_others_posts', 'edit_published_posts',
        'publish_posts', 'delete_posts', 'delete_others_posts',
        'delete_published_posts', 'delete_private_posts', 'edit_private_posts',
        'read_private_posts', 'manage_categories', 'manage_links',
        'moderate_comments', 'upload_files', 'unfiltered_html',
        'edit_pages', 'edit_others_pages', 'edit_published_pages',
        'publish_pages', 'delete_pages', 'delete_others_pages',
        'delete_published_pages', 'delete_private_pages', 'edit_private_pages',
        'read_private_pages', 'manage_options', 'list_users', 'create_users',
        'edit_users', 'delete_users', 'promote_users', 'remove_users',
        'add_users', 'install_plugins', 'activate_plugins', 'edit_plugins',
        'delete_plugins', 'update_plugins', 'install_themes', 'switch_themes',
        'edit_themes', 'delete_themes', 'update_themes', 'update_core',
        'edit_dashboard', 'export', 'import', 'manage_privacy_options',
        'erase_others_personal_data', 'export_others_personal_data',
        'customize', 'manage_widgets', 'edit_theme_options',
    ];

    /**
     * New User Approve plugin-specific capabilities.
     */
    private $nua_caps = [
        'nua_main_menu',
        'nua_users_cap',
        'nua_view_invitation_tab',
        'nua_auto_approve_cap',
        'nua_integration_cap',
        'nua_settings_cap',
        'nua_mobile_app_cap',
        'nua_role_editor_cap',
    ];

    /**
     * Dynamically detect plugin capabilities from all registered roles.
     * Returns caps that are not in core_caps or nua_caps, grouped by source.
     */
    private function get_plugin_caps() {
        $all_role_caps = [];
        foreach ( wp_roles()->roles as $role ) {
            foreach ( $role['capabilities'] as $cap => $granted ) {
                $all_role_caps[ $cap ] = true;
            }
        }

        $excluded = array_merge( $this->core_caps, $this->nua_caps );

        $detected = array_values( array_diff(
            array_keys( $all_role_caps ),
            $excluded
        ) );

        sort( $detected );

        return [
            'nua'    => $this->nua_caps,
            'others' => $detected,
        ];
    }

    public static function instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new NUA_Role_Editor_API();
        }
        return self::$instance;
    }

    public function __construct()
    {
        add_action("rest_api_init", [$this, "register_routes"]);

        // Keep the Administrator role synchronized with every capability known
        // to the role editor, including capabilities created before synchronization.
        $this->sync_administrator_custom_caps();
    }


    public function register_routes()
    {
        $namespace = "nua-request/v1";

        register_rest_route($namespace, "/re/get-roles", [
            "methods"             => WP_REST_Server::READABLE,
            "callback"            => [$this, "get_roles"],
            "permission_callback" => [$this, "permission_check"],
        ]);

        register_rest_route($namespace, "/re/create-role", [
            "methods"             => WP_REST_Server::CREATABLE,
            "callback"            => [$this, "create_role"],
            "permission_callback" => [$this, "permission_check"],
        ]);

        register_rest_route($namespace, "/re/delete-role", [
            "methods"             => WP_REST_Server::CREATABLE,
            "callback"            => [$this, "delete_role"],
            "permission_callback" => [$this, "permission_check"],
        ]);

        register_rest_route($namespace, "/re/rename-role", [
            "methods"             => WP_REST_Server::CREATABLE,
            "callback"            => [$this, "rename_role"],
            "permission_callback" => [$this, "permission_check"],
        ]);

        register_rest_route($namespace, "/re/update-role-caps", [
            "methods"             => WP_REST_Server::CREATABLE,
            "callback"            => [$this, "update_role_caps"],
            "permission_callback" => [$this, "permission_check"],
        ]);

        register_rest_route($namespace, "/re/get-capabilities", [
            "methods"             => WP_REST_Server::READABLE,
            "callback"            => [$this, "get_capabilities"],
            "permission_callback" => [$this, "permission_check"],
        ]);

        register_rest_route($namespace, "/re/add-custom-cap", [
            "methods"             => WP_REST_Server::CREATABLE,
            "callback"            => [$this, "add_custom_cap"],
            "permission_callback" => [$this, "permission_check"],
        ]);

        register_rest_route($namespace, "/re/update-custom-cap", [
            "methods"             => WP_REST_Server::CREATABLE,
            "callback"            => [$this, "update_custom_cap"],
            "permission_callback" => [$this, "permission_check"],
        ]);

        register_rest_route($namespace, "/re/delete-custom-cap", [
            "methods"             => WP_REST_Server::CREATABLE,
            "callback"            => [$this, "delete_custom_cap"],
            "permission_callback" => [$this, "permission_check"],
        ]);
    }

    public function permission_check()
    {
        return current_user_can('manage_options') || current_user_can('nua_role_editor_cap');
    }

    /**
     * Get all WordPress roles with their capabilities.
     */
    public function get_roles(WP_REST_Request $request)
    {
        global $wp_roles;

        $this->sync_administrator_custom_caps();

        $page         = max(1, (int) ($request->get_param('page') ?: 1));
        $limit        = max(1, (int) ($request->get_param('limit') ?: 10));
        $search       = sanitize_text_field($request->get_param('search') ?: '');
        $custom_roles = $this->get_custom_roles_list();
        $roles_data   = [];

        foreach ($wp_roles->roles as $role_slug => $role_data) {
            $display_name = translate_user_role($role_data['name']);

            if ($search !== '' && stripos($display_name, $search) === false && stripos($role_slug, $search) === false) {
                continue;
            }

            $user_count   = count(get_users(['role' => $role_slug, 'fields' => 'ID', 'number' => -1]));
            $roles_data[] = [
                'slug'         => $role_slug,
                'display_name' => $display_name,
                'capabilities' => $role_data['capabilities'],
                'is_custom'    => in_array($role_slug, $custom_roles, true),
                'user_count'   => $user_count,
            ];
        }

        $total  = count($roles_data);
        $offset = ($page - 1) * $limit;
        $paged  = array_slice($roles_data, $offset, $limit);

        return rest_ensure_response([
            'roles'  => $paged,
            'totals' => $total,
        ]);
    }

    /**
     * Create a new custom role.
     */
    public function create_role(WP_REST_Request $request)
    {
        $display_name = sanitize_text_field($request->get_param("display_name"));
        $role_slug    = sanitize_key($request->get_param("role_slug"));

        if (empty($display_name) || empty($role_slug)) {
            return new WP_Error("missing_params", __("Role name and slug are required.", "new-user-approve"), ["status" => 400]);
        }

        if (preg_match('/^\d+$/', $display_name) || preg_match('/^\d+$/', $role_slug)) {
            return new WP_Error(
                "invalid_role_name",
                __("Role name and slug must contain at least one letter.", "new-user-approve"),
                ["status" => 400]
            );
        }

        if (get_role($role_slug)) {
            return new WP_Error("role_exists", __("A role with this slug already exists.", "new-user-approve"), ["status" => 409]);
        }

        $role = add_role($role_slug, $display_name, ["read" => true]);

        if (null === $role) {
            return new WP_Error("create_failed", __("Failed to create role.", "new-user-approve"), ["status" => 500]);
        }

        // Track as custom role
        $custom_roles   = $this->get_custom_roles_list();
        $custom_roles[] = $role_slug;
        $this->update_role_options('custom_roles', array_unique($custom_roles));

        return rest_ensure_response([
            "success"      => true,
            "slug"         => $role_slug,
            "display_name" => $display_name,
            "capabilities" => ["read" => true],
            "is_custom"    => true,
        ]);
    }

    /**
     * Rename / re-slug a role. Slug can only change for custom roles.
     */
    public function rename_role(WP_REST_Request $request)
    {
        global $wp_roles;

        $old_slug     = sanitize_key($request->get_param('old_slug'));
        $display_name = sanitize_text_field($request->get_param('display_name'));
        $new_slug     = sanitize_key($request->get_param('new_slug'));

        if (empty($old_slug) || empty($display_name) || empty($new_slug)) {
            return new WP_Error('missing_params', __('Role name and slug are required.', 'new-user-approve'), ['status' => 400]);
        }

        if (!isset($wp_roles->roles[$old_slug])) {
            return new WP_Error('role_not_found', __('Role not found.', 'new-user-approve'), ['status' => 404]);
        }

        $custom_roles = $this->get_custom_roles_list();
        $is_custom    = in_array($old_slug, $custom_roles, true);
        $current_caps = $wp_roles->roles[$old_slug]['capabilities'];

        if ($new_slug !== $old_slug) {
            // Slug change: only allowed for custom roles
            if (!$is_custom) {
                return new WP_Error('not_custom', __('Cannot change the slug of a built-in role.', 'new-user-approve'), ['status' => 403]);
            }
            if (isset($wp_roles->roles[$new_slug])) {
                return new WP_Error('role_exists', __('A role with this slug already exists.', 'new-user-approve'), ['status' => 409]);
            }

            // Migrate users, remove old role, add new role
            $users = get_users(['role' => $old_slug]);
            remove_role($old_slug);
            add_role($new_slug, $display_name, $current_caps);
            foreach ($users as $user) {
                $user->set_role($new_slug);
            }

            // Update custom roles tracking
            $custom_roles = array_map(fn($r) => $r === $old_slug ? $new_slug : $r, $custom_roles);
            $this->update_role_options('custom_roles', array_values($custom_roles));

            $final_slug = $new_slug;
        } else {
            // Name-only change
            $wp_roles->roles[$old_slug]['name']              = $display_name;
            $wp_roles->role_objects[$old_slug]->name         = $display_name;
            update_option($wp_roles->role_key, $wp_roles->roles);
            $final_slug = $old_slug;
        }

        return rest_ensure_response([
            'success'      => true,
            'slug'         => $final_slug,
            'display_name' => $display_name,
            'capabilities' => $current_caps,
            'is_custom'    => $is_custom,
        ]);
    }

    /**
     * Delete a custom role (only custom roles can be deleted).
     */
    public function delete_role(WP_REST_Request $request)
    {
        $role_slug = sanitize_key($request->get_param("role_slug"));

        if (empty($role_slug)) {
            return new WP_Error("missing_params", __("Role slug is required.", "new-user-approve"), ["status" => 400]);
        }

        $custom_roles = $this->get_custom_roles_list();
        if (!in_array($role_slug, $custom_roles, true)) {
            return new WP_Error("not_custom", __("Only custom roles can be deleted.", "new-user-approve"), ["status" => 403]);
        }

        // Move users with this role to subscriber
        $users = get_users(["role" => $role_slug]);
        foreach ($users as $user) {
            $user->set_role("subscriber");
        }

        remove_role($role_slug);

        // Remove from custom roles list
        $custom_roles = array_values(array_filter($custom_roles, fn($r) => $r !== $role_slug));
        $this->update_role_options('custom_roles', $custom_roles);

        // Remove from auto-approve role list if present
        $nua_auto_approve = get_option("nua_auto_approve", []);
        if ( ! empty($nua_auto_approve['nua_auto_approve_role_list']) && is_array($nua_auto_approve['nua_auto_approve_role_list']) ) {
            $nua_auto_approve['nua_auto_approve_role_list'] = array_values(
                array_filter($nua_auto_approve['nua_auto_approve_role_list'], fn($r) => $r !== $role_slug)
            );
            update_option("nua_auto_approve", $nua_auto_approve);
        }

        return rest_ensure_response(["success" => true]);
    }

    /**
     * Update capabilities for a role.
     */
    public function update_role_caps(WP_REST_Request $request)
    {
        $role_slug    = sanitize_key($request->get_param("role_slug"));
        $capabilities = $request->get_param("capabilities");

        if (empty($role_slug) || !is_array($capabilities)) {
            return new WP_Error("missing_params", __("Role slug and capabilities are required.", "new-user-approve"), ["status" => 400]);
        }

        $role = get_role($role_slug);
        if (!$role) {
            return new WP_Error("role_not_found", __("Role not found.", "new-user-approve"), ["status" => 404]);
        }

        // Sanitize and apply caps
        foreach ($capabilities as $cap => $granted) {
            $cap     = sanitize_key($cap);
            $granted = (bool) $granted;

            if ($granted) {
                $role->add_cap($cap);
            } else {
                $role->remove_cap($cap);
            }
        }

        // Checkbox changes on the capabilities screen cannot remove Administrator capabilities.
        if ($role_slug === 'administrator') {
            $this->sync_administrator_custom_caps();
        }

        // Return the updated role caps
        $updated_role = get_role($role_slug);
        return rest_ensure_response([
            "success"      => true,
            "capabilities" => $updated_role->capabilities,
        ]);
    }

    /**
     * Get all available capabilities (core + custom).
     */
    public function get_capabilities()
    {
        $this->sync_administrator_custom_caps();
        $custom_caps = $this->get_custom_caps_list();

        return rest_ensure_response([
            "core_caps"   => $this->core_caps,
            "plugin_caps" => $this->get_plugin_caps(),
            "custom_caps" => $custom_caps,
        ]);
    }

    /**
     * Add a new custom capability.
     */
    public function add_custom_cap(WP_REST_Request $request)
    {
        $cap_name = sanitize_key($request->get_param("cap_name"));

        if (empty($cap_name)) {
            return new WP_Error("missing_params", __("Capability name is required.", "new-user-approve"), ["status" => 400]);
        }

        if (preg_match('/^\d+$/', $cap_name)) {
            return new WP_Error(
                "invalid_cap_name",
                __("Capability name must contain at least one letter.", "new-user-approve"),
                ["status" => 400]
            );
        }

        $custom_caps = $this->get_custom_caps_list();

        if (in_array($cap_name, $custom_caps, true) || in_array($cap_name, $this->core_caps, true)) {
            return new WP_Error("cap_exists", __("This capability already exists.", "new-user-approve"), ["status" => 409]);
        }

        $custom_caps[] = $cap_name;
        $this->update_role_options('custom_capabilities', array_unique($custom_caps));

        // New capabilities are available to administrators by default.
        $this->sync_administrator_custom_caps();

        return rest_ensure_response(["success" => true, "cap_name" => $cap_name]);
    }

    /**
     * Rename a custom capability.
     */
    public function update_custom_cap(WP_REST_Request $request)
    {
        $old_name = sanitize_key($request->get_param("old_name"));
        $new_name = sanitize_key($request->get_param("new_name"));

        if (empty($old_name) || empty($new_name)) {
            return new WP_Error("missing_params", __("Old and new capability names are required.", "new-user-approve"), ["status" => 400]);
        }

        if (preg_match('/^\d+$/', $new_name)) {
            return new WP_Error(
                "invalid_cap_name",
                __("Capability name must contain at least one letter.", "new-user-approve"),
                ["status" => 400]
            );
        }

        $custom_caps = $this->get_custom_caps_list();

        if (!in_array($old_name, $custom_caps, true)) {
            return new WP_Error("not_custom", __("Only custom capabilities can be edited.", "new-user-approve"), ["status" => 403]);
        }

        if (in_array($new_name, $custom_caps, true) || in_array($new_name, $this->core_caps, true)) {
            return new WP_Error("cap_exists", __("A capability with this name already exists.", "new-user-approve"), ["status" => 409]);
        }

        // Rename in list
        $custom_caps   = array_map(fn($c) => $c === $old_name ? $new_name : $c, $custom_caps);
        $this->update_role_options('custom_capabilities', $custom_caps);

        // Rename on all roles
        global $wp_roles;
        foreach ($wp_roles->roles as $slug => $data) {
            if (isset($data["capabilities"][$old_name])) {
                $role    = get_role($slug);
                $granted = $role->capabilities[$old_name];
                $role->remove_cap($old_name);
                $role->add_cap($new_name, $granted);
            }
        }

        $this->sync_administrator_custom_caps();

        return rest_ensure_response(["success" => true, "new_name" => $new_name]);
    }

    /**
     * Delete a custom capability.
     */
    public function delete_custom_cap(WP_REST_Request $request)
    {
        $cap_name = sanitize_key($request->get_param("cap_name"));

        if (empty($cap_name)) {
            return new WP_Error("missing_params", __("Capability name is required.", "new-user-approve"), ["status" => 400]);
        }

        $custom_caps = $this->get_custom_caps_list();

        if (!in_array($cap_name, $custom_caps, true)) {
            return new WP_Error("not_custom", __("Only custom capabilities can be deleted.", "new-user-approve"), ["status" => 403]);
        }

        // Remove from list
        $custom_caps = array_values(array_filter($custom_caps, fn($c) => $c !== $cap_name));
        $this->update_role_options('custom_capabilities', $custom_caps);

        // Remove from all roles
        global $wp_roles;
        foreach ($wp_roles->roles as $slug => $data) {
            if (isset($data["capabilities"][$cap_name])) {
                $role = get_role($slug);
                $role->remove_cap($cap_name);
            }
        }

        return rest_ensure_response(["success" => true]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function get_role_options()
    {
        return (array) get_option('nua_role_options', []);
    }

    private function update_role_options($key, $value)
    {
        $opts       = $this->get_role_options();
        $opts[$key] = $value;
        update_option('nua_role_options', $opts);
    }

    private function get_custom_roles_list()
    {
        $opts = $this->get_role_options();
        return (array) ($opts['custom_roles'] ?? []);
    }

    private function get_custom_caps_list()
    {
        $opts = $this->get_role_options();
        return (array) ($opts['custom_capabilities'] ?? []);
    }

    /**
     * Capabilities the Administrator role must always retain.
     *
     * This covers core, New User Approve, other plugin, and custom capabilities.
     * Checkbox state for another role does not remove these from Administrator.
     *
     * @return string[]
     */
    private function get_administrator_required_caps()
    {
        $caps = array_merge(
            $this->core_caps,
            $this->nua_caps,
            $this->get_custom_caps_list()
        );

        // Include every capability currently registered by WordPress, plugins, or themes.
        foreach (wp_roles()->roles as $role_data) {
            $caps = array_merge($caps, array_keys((array) ($role_data['capabilities'] ?? [])));
        }

        $required = [];
        foreach ($caps as $cap_name) {
            $cap_name = sanitize_key($cap_name);
            if ($cap_name !== '') {
                $required[$cap_name] = $cap_name;
            }
        }

        return array_values($required);
    }

    /**
     * Ensure administrators always have every existing and newly created capability.
     */
    private function sync_administrator_custom_caps()
    {
        global $wp_roles;

        $administrator = get_role('administrator');
        if (!$administrator || !isset($wp_roles->roles['administrator'])) {
            return;
        }

        $required = $this->get_administrator_required_caps();
        $role_caps = $administrator->capabilities;
        $changed   = false;

        foreach ($required as $cap_name) {
            if (empty($role_caps[$cap_name])) {
                $role_caps[$cap_name] = true;
                $changed = true;
            }
        }

        if ($changed) {
            $administrator->capabilities = $role_caps;
            $wp_roles->roles['administrator']['capabilities'] = $role_caps;
            update_option($wp_roles->role_key, $wp_roles->roles);
        }

        // Make synchronized caps available immediately to the current administrator.
        $current_user = wp_get_current_user();
        if ($current_user && in_array('administrator', (array) $current_user->roles, true)) {
            foreach ($required as $cap_name) {
                $current_user->allcaps[$cap_name] = true;
            }
        }
    }
}

NUA_Role_Editor_API::instance();
