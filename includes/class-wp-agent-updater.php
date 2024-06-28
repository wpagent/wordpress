<?php

class WP_Agent_Updater
{
    private $slug;
    private $plugin_data;
    private $username;
    private $repo;
    private $plugin_file;
    private $github_response;

    public function __construct($plugin_file)
    {
        $this->plugin_file = $plugin_file;
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));
        add_filter('plugins_api', array($this, 'plugin_popup'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);

        $this->slug = plugin_basename($this->plugin_file);
        $this->plugin_data = $this->get_plugin_data();
        $this->username = 'wpagent';
        $this->repo = 'wordpress';
    }

    private function get_plugin_data()
    {
        $plugin_data = get_file_data($this->plugin_file, array(
            'Version' => 'Version',
            'Name' => 'Plugin Name',
            'PluginURI' => 'Plugin URI',
            'AuthorName' => 'Author',
            'AuthorURI' => 'Author URI',
            'Description' => 'Description'
        ));

        return $plugin_data;
    }

    private function get_repository_info()
    {
        if (is_null($this->github_response)) {
            $request_uri = sprintf('https://api.github.com/repos/%s/%s/releases/latest', $this->username, $this->repo);
            $response = wp_remote_get($request_uri);

            if (is_wp_error($response)) {
                return false;
            }

            $this->github_response = json_decode(wp_remote_retrieve_body($response));
        }
    }

    public function check_update($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        $this->get_repository_info();

        $do_update = version_compare($this->github_response->tag_name, $transient->checked[$this->slug]);

        if ($do_update == 1) {
            $package = $this->github_response->zipball_url;

            $obj = new stdClass();
            $obj->slug = $this->slug;
            $obj->new_version = $this->github_response->tag_name;
            $obj->url = $this->plugin_data['PluginURI'];
            $obj->package = $package;

            $transient->response[$this->slug] = $obj;
        }

        return $transient;
    }

    public function plugin_popup($result, $action, $args)
    {
        if ($action !== 'plugin_information') {
            return false;
        }

        if (!empty($args->slug)) {
            if ($args->slug == $this->slug) {
                $this->get_repository_info();

                $plugin = array(
                    'name'              => $this->plugin_data['Name'],
                    'slug'              => $this->slug,
                    'version'           => $this->github_response->tag_name,
                    'author'            => $this->plugin_data['AuthorName'],
                    'author_profile'    => $this->plugin_data['AuthorURI'],
                    'last_updated'      => $this->github_response->published_at,
                    'homepage'          => $this->plugin_data['PluginURI'],
                    'short_description' => $this->plugin_data['Description'],
                    'sections'          => array(
                        'Description'   => $this->plugin_data['Description'],
                        'Updates'       => $this->github_response->body,
                    ),
                    'download_link'     => $this->github_response->zipball_url
                );

                return (object) $plugin;
            }
        }

        return $result;
    }

    public function after_install($response, $hook_extra, $result)
    {
        global $wp_filesystem;

        $install_directory = plugin_dir_path($this->plugin_file);
        $wp_filesystem->move($result['destination'], $install_directory);
        $result['destination'] = $install_directory;

        if ($this->active) {
            activate_plugin($this->slug);
        }

        return $result;
    }
}
