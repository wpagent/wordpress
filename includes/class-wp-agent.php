<?php
class WP_Agent {
    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        register_activation_hook(WP_AGENT_PLUGIN_DIR . 'wp-agent.php', array($this, 'plugin_activation'));
        register_deactivation_hook(WP_AGENT_PLUGIN_DIR . 'wp-agent.php', array($this, 'plugin_deactivation'));

        add_action('plugins_loaded', array($this, 'init_plugin'));
        add_action('post_updated', array($this, 'handle_post_update'), 10, 3);
    }

    public function init_plugin() {
        new WP_Agent_Admin();
        new WP_Agent_API();
    }

    public function plugin_activation() {
        add_option('wp_agent_activation_redirect', true);
        $this->add_custom_capability();
        $user = new WP_Agent_User();
        $user->create_user_if_not_exists();
        $user->get_or_create_application_password();
    }

    public static function plugin_deactivation() {
        delete_option('wp_agent_activation_redirect');
        global $wpdb;
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_wp_agent_%'");
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_wp_agent_%'");
    }

    private function add_custom_capability() {
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('manage_wp_agent');
        }
    }

    public static function remove_custom_capability() {
        $role = get_role('administrator');
        if ($role) {
            $role->remove_cap('manage_wp_agent');
        }
    }

    public function handle_post_update($post_id, $post_after, $post_before) {
        if (!in_array($post_after->post_type, array('post', 'page'))) {
            return;
        }

        $api = new WP_Agent_API();
        $api->notify_post_update($post_id);
    }
}
