<?php

/**
 * Plugin Name: WPAgent.ai
 * Description: Your WordPress AI Assistant
 * Version: 0.1.2
 * Author: WPAgent.ai
 * Author URI: https://wpagent.ai
 * GitHub Plugin URI: https://github.com/wpagent/wordpress
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_AGENT_VERSION', '0.1.2');
define('WP_AGENT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_AGENT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_AGENT_API_ENDPOINT', 'https://api.wpagent.ai/v0');
define('WP_AGENT_USERNAME', 'WPAgent.ai');

// Include required files
require_once WP_AGENT_PLUGIN_DIR . 'includes/class-wp-agent.php';
require_once WP_AGENT_PLUGIN_DIR . 'includes/class-wp-agent-admin.php';
require_once WP_AGENT_PLUGIN_DIR . 'includes/class-wp-agent-api.php';
require_once WP_AGENT_PLUGIN_DIR . 'includes/class-wp-agent-user.php';

if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'includes/github-release-updater.php';
    $updater = new GitHub_Release_Updater(
        __FILE__,
        'wpagent',
        'wpagent',
    );
}

function wp_agent_enqueue_scripts($hook)
{
    $css_file = WP_AGENT_PLUGIN_URL . 'dist/css/main.css';
    $js_file = WP_AGENT_PLUGIN_URL . 'dist/js/main.js';

    wp_enqueue_style('wp-agent-styles', $css_file, array(), WP_AGENT_VERSION);
    wp_enqueue_script('wp-agent-script', $js_file, array('wp-element', 'wp-hooks', 'wp-data'), WP_AGENT_VERSION, true);

    wp_localize_script('wp-agent-script', 'wpAgentData', array(
        'logoUrl' => plugins_url('img/logo.png', __FILE__),
        'apiEndpoint' => WP_AGENT_API_ENDPOINT,
        'apiKey' => get_option('wp_agent_api_key'),
        'wpUserId' => get_current_user_id(),
        'showModal' => get_option('wp_agent_show_modal', true),
    ));
}
add_action('admin_enqueue_scripts', 'wp_agent_enqueue_scripts');

function wp_agent_add_root_element()
{
    echo '<div id="wp-agent-root"></div>';
}
add_action('admin_footer', 'wp_agent_add_root_element');

function wp_agent()
{
    return WP_Agent::get_instance();
}

wp_agent();
