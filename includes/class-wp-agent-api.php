<?php
class WP_Agent_API
{
    private $user;

    public function __construct()
    {
        $this->user = new WP_Agent_User();
        add_action('wp_ajax_wp_agent_validate_api_key', [$this, 'validate_api_key']);
    }

    public function send_existing_app_password_to_api(): bool
    {
        try {
            $user = $this->user->get_user();
            $app_password = $this->user->get_or_create_application_password();

            if ($user && $app_password) {
                return $this->send_application_password_to_api($user->ID, $app_password);
            }
        } catch (Exception $e) {
            $this->log_error($e->getMessage());
        }
        return false;
    }

    public function send_application_password_to_api(int $user_id, string $app_password): bool
    {
        $api_key = get_option('wp_agent_api_key');

        if (empty($api_key)) {
            throw new Exception('API key is not set. Cannot send application password to external API.');
        }

        $response = $this->make_api_request('/application-password', [
            'username' => WP_AGENT_USERNAME,
            'password' => $app_password,
            'user_id' => $user_id,
            'site_url' => get_site_url(),
        ], $api_key);

        $this->log_info('Successfully sent application password to external API');
        return true;
    }

    public function validate_api_key($new_value, $old_value): string
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
            $this->make_api_request('/validate-key', ['site' => get_site_url()], $api_key);
            return $new_value;
        } catch (Exception $e) {
            add_settings_error('wp_agent_api_key', 'invalid_api_key', $e->getMessage());
            return $old_value;
        }
    }

    private function make_api_request(string $endpoint, array $body, string $api_key): array
    {
        $response = wp_remote_post(WP_AGENT_API_ENDPOINT . $endpoint, [
            'body' => wp_json_encode($body),
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => "Bearer $api_key",
            ],
            'timeout' => 30,
            'sslverify' => false
        ]);

        if (is_wp_error($response)) {
            throw new Exception('Error connecting to the API: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if ($status_code !== 200 || !isset($result['success']) || $result['success'] !== true) {
            $error_message = isset($result['message']) ? $result['message'] : 'Unknown error';
            throw new Exception("API Error: $error_message");
        }

        return $result;
    }

    public function notify_post_update($post_id)
    {
        $api_key = get_option('wp_agent_api_key');

        if (empty($api_key)) {
            $this->log_error('WP Agent: API key is not set. Cannot notify external API of post update.');
            return;
        }

        $notification_data = array(
            'action' => 'post_updated',
            'data' => [
                'post_id' => $post_id,
            ],
        );

        try {
            $this->make_api_request('/wordpress/webhook', $notification_data, $api_key);
            $this->log_info('Successfully notified external API of update for post ID: ' . $post_id);
        } catch (Exception $e) {
            $this->log_error('Failed to notify external API of post update. Error: ' . $e->getMessage());
        }
    }

    public function sync_after_api_key_update($old_value, $new_value, $option)
    {
        if ($new_value === $old_value) {
            return;
        }

        try {
            $sync_result = $this->send_existing_app_password_to_api();
            if ($sync_result) {
                add_settings_error('wp_agent_api_key', 'api_key_synced', 'API key saved and synced successfully.', 'updated');
            } else {
                add_settings_error('wp_agent_api_key', 'sync_failed', 'API key saved, but sync failed. Please try manual sync.', 'error');
            }
        } catch (Exception $e) {
            add_settings_error('wp_agent_api_key', 'sync_failed', 'API key saved, but sync failed: ' . $e->getMessage(), 'error');
        }
    }

    private function log_error(string $message): void
    {
        error_log('[WP_Agent_API] ERROR: ' . $message);
    }

    private function log_info(string $message): void
    {
        error_log('[WP_Agent_API] INFO: ' . $message);
    }
}
