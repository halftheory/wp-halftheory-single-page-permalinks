<?php
/*
Plugin Name: Single Page Permalinks
Plugin URI: https://github.com/halftheory/wp-halftheory-single-page-permalinks
GitHub Plugin URI: https://github.com/halftheory/wp-halftheory-single-page-permalinks
Description: Single Page Permalinks
Author: Half/theory
Author URI: https://github.com/halftheory
Version: 1.0
Network: true
*/

/*
Available filters:
singlepagepermalinks_deactivation(string $db_prefix)
singlepagepermalinks_uninstall(string $db_prefix)
*/

// Exit if accessed directly.
defined('ABSPATH') || exit;

if (!class_exists('Single_Page_Permalinks_Plugin')) :
class Single_Page_Permalinks_Plugin {

	public function __construct() {
		@include_once(dirname(__FILE__).'/class-single-page-permalinks.php');
		$this->subclass = new Single_Page_Permalinks();
	}

	public static function init() {
		$plugin = new self;
		return $plugin;
	}

	public static function activation() {
		$plugin = new self;
		return $plugin;
	}

	public static function deactivation() {
		$plugin = new self;

		apply_filters('singlepagepermalinks_deactivation', $plugin->subclass::$prefix);
		return;
	}

	public static function uninstall() {
		$plugin = new self;

		// remove options
		global $wpdb;
		$query_options = "DELETE FROM $wpdb->options WHERE option_name LIKE '".$plugin->subclass::$prefix."_%'";
		if (is_multisite()) {
			delete_site_option($plugin->subclass::$prefix);
			$wpdb->query("DELETE FROM $wpdb->sitemeta WHERE meta_key LIKE '".$plugin->subclass::$prefix."_%'");
			$current_blog_id = get_current_blog_id();
			$sites = get_sites();
			foreach ($sites as $key => $value) {
				switch_to_blog($value->blog_id);
				delete_option($plugin->subclass::$prefix);
				$wpdb->query($query_options);
			}
			switch_to_blog($current_blog_id);
		}
		else {
			delete_option($plugin->subclass::$prefix);
			$wpdb->query($query_options);
		}
		apply_filters('singlepagepermalinks_uninstall', $plugin->subclass::$prefix);
		return;
	}

}
// Load the plugin.
add_action('init', array('Single_Page_Permalinks_Plugin', 'init'));
endif;

register_activation_hook(__FILE__, array('Single_Page_Permalinks_Plugin', 'activation'));
register_deactivation_hook(__FILE__, array('Single_Page_Permalinks_Plugin', 'deactivation'));
function Single_Page_Permalinks_Plugin_uninstall() {
	Single_Page_Permalinks_Plugin::uninstall();
};
register_uninstall_hook(__FILE__, 'Single_Page_Permalinks_Plugin_uninstall');
?>