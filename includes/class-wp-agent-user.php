<?php

class WP_Agent_User
{
    private $username;

    public function __construct($username = WP_AGENT_USERNAME)
    {
        $this->username = $username;
    }

    public function get_user()
    {
        return get_user_by('login', $this->username);
    }

    public function get_or_create_application_password()
    {
        $user = $this->get_user();
        if (!$user) {
            return false;
        }

        $existing_password = $this->get_application_password();
        if ($existing_password) {
            return $existing_password;
        }

        return $this->create_application_password($user->ID);
    }

    private function get_application_password()
    {
        $user = $this->get_user();
        if (!$user || !class_exists('WP_Application_Passwords')) {
            return false;
        }

        $app_passwords = WP_Application_Passwords::get_user_application_passwords($user->ID);
        if (empty($app_passwords)) {
            return false;
        }

        $password = get_user_meta($user->ID, 'wp_agent_app_password', true);
        if ($password) {
            return $password;
        }

        return false;
    }

    private function create_application_password($user_id)
    {
        if (!class_exists('WP_Application_Passwords')) {
            return false;
        }

        $app_password_details = WP_Application_Passwords::create_new_application_password($user_id, array(
            'name' => WP_AGENT_USERNAME,
        ));

        if (is_wp_error($app_password_details)) {
            error_log('Failed to generate application password: ' . $app_password_details->get_error_message());
            return false;
        }

        list($app_password, $app_password_data) = $app_password_details;

        update_user_meta($user_id, 'wp_agent_app_password', $app_password);

        return $app_password;
    }

    public function create_user_if_not_exists()
    {
        if (!username_exists($this->username)) {
            $user_id = wp_create_user($this->username, wp_generate_password(), 'agent@wpagent.ai');
            wp_update_user(array('ID' => $user_id, 'role' => 'editor'));
            return $user_id;
        }
        return false;
    }
}
