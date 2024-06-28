<?php

class WP_Agent_Admin
{
    private $api;

    public function __construct()
    {
        $this->api = new WP_Agent_API();

        add_action('admin_menu', array($this, 'create_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'redirect_to_settings_page'));
        add_action('admin_post_wp_agent_sync_state', array($this, 'handle_sync_state'));

        add_filter('pre_update_option_wp_agent_api_key', array($this, 'validate_api_key'), 10, 2);
        add_action('update_option_wp_agent_api_key', array($this, 'sync_after_api_key_update'), 10, 3);
    }


    public function create_admin_menu()
    {
        add_menu_page(
            'WP Agent Settings',
            'WP Agent',
            'manage_wp_agent',
            'wp-agent-settings',
            array($this, 'render_settings_page'),
            'dashicons-admin-links',
            20
        );
    }

    public function enqueue_admin_scripts()
    {
        wp_enqueue_style('tailwindcss', WP_AGENT_PLUGIN_URL . 'css/tailwind-output.css', array(), WP_AGENT_VERSION);
        wp_enqueue_script('persistent-chat-modal-js', WP_AGENT_PLUGIN_URL . 'dist/main.js', array(), WP_AGENT_VERSION, true);
    }

    public function register_settings()
    {
        register_setting('wp_agent_settings_group', 'wp_agent_api_key', array(
            'sanitize_callback' => array($this, 'sanitize_api_key'),
        ));

        register_setting('wp_agent_settings_group', 'wp_agent_show_modal', array(
            'type' => 'boolean',
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
        ));

        add_settings_section(
            'wp_agent_settings_section',
            'API Settings',
            null,
            'wp-agent-settings'
        );

        add_settings_field(
            'wp_agent_api_key',
            'API Key',
            array($this, 'render_api_key_field'),
            'wp-agent-settings',
            'wp_agent_settings_section'
        );

        register_setting('wp_agent_settings_group', 'wp_agent_show_modal', array(
            'type' => 'boolean',
            'default' => true,
        ));

        add_settings_field(
            'wp_agent_show_modal',
            'Show WP Agent Modal',
            array($this, 'render_show_modal_field'),
            'wp-agent-settings',
            'wp_agent_settings_section'
        );
    }

    public function sanitize_api_key($value)
    {
        return sanitize_text_field($value);
    }

    public function sanitize_checkbox($input)
    {
        return (isset($input) && true == $input) ? true : false;
    }

    public function redirect_to_settings_page()
    {
        if (get_option('wp_agent_activation_redirect', false)) {
            delete_option('wp_agent_activation_redirect');
            if (!isset($_GET['activate-multi'])) {
                wp_safe_redirect(admin_url('admin.php?page=wp-agent-settings'));
                exit;
            }
        }
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_wp_agent')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $api_key = get_option('wp_agent_api_key');
        $show_modal = get_option('wp_agent_show_modal', true);
?>
        <div class="wrap">
            <h1 class="tw-text-2xl tw-font-bold tw-mb-6">WP Agent Settings</h1>
            <?php settings_errors(); ?>
            <form method="post" action="options.php" class="tw-bg-white tw-shadow-md tw-rounded tw-px-8 tw-pt-6 tw-pb-8 tw-mb-4">
                <?php settings_fields('wp_agent_settings_group'); ?>

                <div class="tw-mb-4">
                    <label class="tw-block tw-text-gray-700 tw-text-sm tw-font-bold tw-mb-2" for="wp_agent_api_key">
                        API Key
                    </label>
                    <input class="tw-shadow tw-appearance-none tw-border tw-rounded tw-w-full tw-py-2 tw-px-3 tw-text-gray-700 tw-leading-tight tw-focus:outline-none tw-focus:shadow-outline" id="wp_agent_api_key" name="wp_agent_api_key" type="text" value="<?php echo esc_attr($api_key); ?>">
                </div>

                <div class="tw-mb-4">
                    <label class="tw-flex tw-items-center tw-cursor-pointer">
                        <input type="checkbox" id="wp_agent_show_modal" name="wp_agent_show_modal" class="tw-h-6 tw-w-6 tw-text-blue-600" <?php checked($show_modal, true); ?>>
                        <span class="tw-ml-3 tw-text-gray-700 tw-font-medium">Show WP Agent Modal</span>
                    </label>
                    <p class="tw-text-gray-600 tw-text-xs tw-italic tw-mt-1 tw-ml-9">When enabled, the WP Agent modal button will be visible on the bottom right of the page.</p>
                </div>

                <?php submit_button('Save Settings', 'tw-bg-blue-500 tw-hover:bg-blue-700 tw-text-white tw-font-bold tw-py-2 tw-px-4 tw-rounded tw-focus:outline-none tw-focus:shadow-outline'); ?>
            </form>

            <?php if ($api_key) : ?>
                <div class="tw-bg-white tw-shadow-md tw-rounded tw-px-8 tw-pt-6 tw-pb-8 tw-mb-4">
                    <h2 class="tw-text-xl tw-font-bold tw-mb-4">Sync State</h2>
                    <p class="tw-mb-4">Click the button below if you're encountering any issues. This will reset the connection with WPAgent's API.</p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('wp_agent_sync_state', 'wp_agent_sync_nonce'); ?>
                        <input type="hidden" name="action" value="wp_agent_sync_state">
                        <button type="submit" class="tw-bg-gray-500 tw-hover:bg-gray-700 tw-text-white tw-font-bold tw-py-2 tw-px-4 tw-rounded tw-focus:outline-none tw-focus:shadow-outline">
                            Sync State
                        </button>
                    </form>
                </div>
            <?php else : ?>
                <p class="tw-text-red-500 tw-font-bold">Please set your API key to enable sync functionality.</p>
            <?php endif; ?>
        </div>
    <?php
    }

    public function render_api_key_field()
    {
        $api_key = get_option('wp_agent_api_key');
    ?>
        <input type="text" name="wp_agent_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text">
<?php
    }

    public function handle_sync_state()
    {
        if (!isset($_POST['wp_agent_sync_nonce']) || !wp_verify_nonce($_POST['wp_agent_sync_nonce'], 'wp_agent_sync_state')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_wp_agent')) {
            wp_die('You do not have sufficient permissions to perform this action.');
        }

        $result = $this->api->send_existing_app_password_to_api();

        if ($result) {
            add_settings_error('wp_agent_messages', 'wp_agent_sync_success', 'State synced successfully!', 'updated');
        } else {
            add_settings_error('wp_agent_messages', 'wp_agent_sync_error', 'Failed to sync state. Please try again.', 'error');
        }

        wp_redirect(admin_url('admin.php?page=wp-agent-settings'));
        exit;
    }

    public function after_api_key_update($old_value, $new_value, $option)
    {
        if ($new_value === $old_value) {
            return;
        }

        try {
            $this->api->validate_api_key($new_value);
            $sync_result = $this->api->send_existing_app_password_to_api();
            if ($sync_result) {
                add_settings_error('wp_agent_api_key', 'api_key_synced', 'API key saved and synced successfully.', 'updated');
            } else {
                add_settings_error('wp_agent_api_key', 'sync_failed', 'API key saved, but sync failed. Please try manual sync.', 'error');
            }
        } catch (Exception $e) {
            add_settings_error('wp_agent_api_key', 'invalid_api_key', $e->getMessage());
            update_option('wp_agent_api_key', $old_value);
        }
    }

    public function validate_api_key($new_value, $old_value)
    {
        if ($new_value === $old_value) {
            return $old_value;
        }

        $api_key = sanitize_text_field($new_value);

        if (empty($api_key)) {
            add_settings_error('wp_agent_api_key', 'empty_api_key', 'API key cannot be empty.');
            return $old_value;
        }

        try {
            $this->api->validate_api_key($api_key);
            return $new_value;
        } catch (Exception $e) {
            add_settings_error('wp_agent_api_key', 'invalid_api_key', $e->getMessage());
            return $old_value;
        }
    }
}
