<?php
// if (!defined('WP_UNINSTALL_PLUGIN')) {
//     exit;
// }

// // Remove plugin options
// delete_option('wp_agent_api_key');
// delete_option('wp_agent_activation_redirect');

// // Remove WP Agent user
// $user = get_user_by('login', 'WPAgent.ai');
// if ($user) {
//     require_once(ABSPATH . 'wp-admin/includes/user.php');
//     wp_delete_user($user->ID);
// }

// // Remove custom capability
// WP_Agent::remove_custom_capability();

// // Delete all transients
// global $wpdb;
// $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_wp_agent_%'");
// $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_wp_agent_%'");
