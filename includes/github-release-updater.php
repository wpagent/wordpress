<?php
/**
 * GitHub Release Updater
 *
 * @package WPGitHubUpdater
 */

class GitHub_Release_Updater {
    private $file;
    private $plugin;
    private $basename;
    private $active;
    private $github_username;
    private $github_repo;
    private $github_api_key;
    private $github_response;

    public function __construct($file, $github_username, $github_repo, $github_api_key = null) {
        $this->file = $file;
        $this->github_username = $github_username;
        $this->github_repo = $github_repo;
        $this->github_api_key = $github_api_key;

        add_action('admin_init', array($this, 'set_plugin_properties'));

        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));
        add_filter('plugins_api', array($this, 'get_plugin_info'), 10, 3);
        add_filter('upgrader_source_selection', array($this, 'upgrader_source_selection'), 10, 4);
    }

    public function set_plugin_properties() {
        $this->plugin   = get_plugin_data($this->file);
        $this->basename = plugin_basename($this->file);
        $this->active   = is_plugin_active($this->basename);
    }

    private function get_github_release() {
        if ($this->github_response === null) {
            $request_uri = sprintf('https://api.github.com/repos/%s/%s/releases/latest', $this->github_username, $this->github_repo);

            $args = array();
            if ($this->github_api_key) {
                $args['headers']['Authorization'] = "token " . $this->github_api_key;
            }

            $response = wp_remote_get($request_uri, $args);

            if (is_wp_error($response)) {
                return false;
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body);

            if ($data === null || !isset($data->tag_name)) {
                return false;
            }

            $this->github_response = $data;
        }

        return $this->github_response;
    }

    public function check_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $release = $this->get_github_release();
        if ($release === false) {
            return $transient;
        }

        $doUpdate = version_compare($release->tag_name, $this->plugin["Version"], 'gt');

        if ($doUpdate) {
            $package = $release->zipball_url;

            if ($this->github_api_key) {
                $package = add_query_arg(array("access_token" => $this->github_api_key), $package);
            }

            $obj = new stdClass();
            $obj->slug = $this->basename;
            $obj->new_version = $release->tag_name;
            $obj->url = $this->plugin["PluginURI"];
            $obj->package = $package;

            $transient->response[$this->basename] = $obj;
        }

        return $transient;
    }

    public function get_plugin_info($false, $action, $response) {
        if ($action !== 'plugin_information') {
            return $false;
        }

        if (!isset($response->slug) || $response->slug !== $this->basename) {
            return $false;
        }

        $release = $this->get_github_release();
        if ($release === false) {
            return $false;
        }

        $response->name = $this->plugin["Name"];
        $response->slug = $this->basename;
        $response->version = $release->tag_name;
        $response->author = $this->plugin["AuthorName"];
        $response->homepage = $this->plugin["PluginURI"];
        $response->requires = $this->plugin["RequiresWP"];
        $response->tested = $this->plugin["TestedUpTo"];
        $response->downloaded = 0;
        $response->last_updated = $release->published_at;
        $response->sections = array(
            'description' => $this->plugin["Description"],
            'changelog' => $release->body
        );
        $response->download_link = $release->zipball_url;

        return $response;
    }

    public function upgrader_source_selection($source, $remote_source, $upgrader, $hook_extra = null) {
        global $wp_filesystem;

        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->basename) {
            return $source;
        }

        $path = $remote_source;
        $newPath = trailingslashit($path) . dirname($this->basename);

        if (trailingslashit($source) === $newPath) {
            return $source;
        }

        if (!$wp_filesystem->move($source, $newPath)) {
            return new WP_Error('rename_failed', 'Unable to rename the update to match the existing plugin directory.');
        }

        return trailingslashit($newPath);
    }
}
