<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove plugin options
delete_option('wp_agent_api_key');
delete_option('wp_agent_activation_redirect');
delete_option('wp_agent_show_modal');

// Delete all transients
global $wpdb;
$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_wp_agent_%'");
$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_wp_agent_%'");
