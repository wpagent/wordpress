<?php

/**
 * Class WP_Agent_Updater
 *
 * Handles plugin updates from a GitHub repository.
 */
class WP_Agent_Updater
{
    /**
     * The plugin current version
     * @var string
     */
    private $current_version;

    /**
     * The plugin remote update path
     * @var string
     */
    private $update_path;

    /**
     * Plugin Slug (plugin_directory/plugin_file.php)
     * @var string
     */
    private $plugin_slug;

    /**
     * Plugin name (plugin_file)
     * @var string
     */
    private $slug;

    /**
     * GitHub username
     * @var string
     */
    private $username;

    /**
     * GitHub repository name
     * @var string
     */
    private $repo;

    /**
     * Initialize a new instance of the WordPress Auto-Update class
     * @param string $current_version
     * @param string $update_path
     * @param string $plugin_slug
     */
    public function __construct($current_version, $update_path, $plugin_slug)
    {
        // Set the class public variables
        $this->current_version = $current_version;
        $this->update_path = $update_path;
        $this->plugin_slug = $plugin_slug;
        list($t1, $t2) = explode('/', $plugin_slug);
        $this->slug = str_replace('.php', '', $t2);

        // Set GitHub username and repo
        $this->username = 'wpagent';
        $this->repo = 'wpagent';

        // Define the alternative API for updating checking
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));

        // Define the alternative response for information checking
        add_filter('plugins_api', array($this, 'check_info'), 10, 3);

        // Define the upgrader_source_selection filter
        add_filter('upgrader_source_selection', array($this, 'upgrade_source_selection'), 10, 4);
    }

    /**
     * Add our self-hosted autoupdate plugin to the filter transient
     *
     * @param $transient
     * @return object $transient
     */
    public function check_update($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        // Get the remote version
        $remote_version = $this->get_remote_version();

        // If a newer version is available, add the update
        if (version_compare($this->current_version, $remote_version, '<')) {
            $obj = new stdClass();
            $obj->slug = $this->slug;
            $obj->new_version = $remote_version;
            $obj->url = $this->update_path;
            $obj->package = $this->get_remote_package();
            $transient->response[$this->plugin_slug] = $obj;
        }

        return $transient;
    }

    /**
     * Add our self-hosted description to the filter
     *
     * @param boolean $false
     * @param array $action
     * @param object $arg
     * @return bool|object
     */
    public function check_info($false, $action, $arg)
    {
        if (isset($arg->slug) && $arg->slug === $this->slug) {
            $information = $this->get_remote_information();
            return $information;
        }
        return $false;
    }

    /**
     * Rename the folder after update
     *
     * @param string $source
     * @param string $remote_source
     * @param object $upgrader
     * @param array $hook_extra
     * @return string
     */
    public function upgrade_source_selection($source, $remote_source, $upgrader, $hook_extra)
    {
        global $wp_filesystem;

        if (isset($hook_extra['plugin']) && $hook_extra['plugin'] == $this->plugin_slug) {
            $new_source = trailingslashit($remote_source) . dirname($this->plugin_slug);
            $wp_filesystem->move($source, $new_source);
            return $new_source;
        }

        return $source;
    }

    /**
     * Get remote version
     * @return string $remote_version
     */
    public function get_remote_version()
    {
        $request = wp_remote_get($this->update_path);
        if (!is_wp_error($request) && wp_remote_retrieve_response_code($request) === 200) {
            return $request['body'];
        }
        return false;
    }

    /**
     * Get remote package
     * @return string $remote_package
     */
    public function get_remote_package()
    {
        return sprintf('https://github.com/%s/%s/archive/master.zip', $this->username, $this->repo);
    }

    /**
     * Get remote information
     * @return object $information
     */
    public function get_remote_information()
    {
        $request = wp_remote_get(sprintf('https://api.github.com/repos/%s/%s/releases/latest', $this->username, $this->repo));
        if (!is_wp_error($request) && wp_remote_retrieve_response_code($request) === 200) {
            $response = json_decode(wp_remote_retrieve_body($request));
            $information = new stdClass();
            $information->name = $this->plugin_slug;
            $information->slug = $this->slug;
            $information->version = $response->tag_name;
            $information->author = $response->author->login;
            $information->homepage = $response->html_url;
            $information->requires = '5.0';
            $information->tested = '5.7';
            $information->downloaded = 0;
            $information->last_updated = $response->published_at;
            $information->sections = array(
                'description' => $response->body,
                'changelog' => $response->body,
            );
            $information->download_link = $response->zipball_url;
            return $information;
        }
        return false;
    }
}
