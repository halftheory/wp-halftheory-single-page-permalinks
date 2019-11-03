<?php
/*
Plugin Name: Half/theory Single Page Permalinks
Plugin URI: https://github.com/halftheory/wp-halftheory-single-page-permalinks
GitHub Plugin URI: https://github.com/halftheory/wp-halftheory-single-page-permalinks
Description: Single Page Permalinks
Author: Half/theory
Author URI: https://github.com/halftheory
Version: 2.0
Network: false
*/

/*
Available filters:
singlepagepermalinks_deactivation(string $db_prefix, class $subclass)
singlepagepermalinks_uninstall(string $db_prefix, class $subclass)
*/

// Exit if accessed directly.
defined('ABSPATH') || exit;

if (!class_exists('Single_Page_Permalinks_Plugin')) :
final class Single_Page_Permalinks_Plugin {

	public function __construct() {
		@include_once(dirname(__FILE__).'/class-single-page-permalinks.php');
		if (class_exists('Single_Page_Permalinks')) {
			$this->subclass = new Single_Page_Permalinks(plugin_basename(__FILE__));
		}
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
		if ($plugin->subclass) {
			apply_filters('singlepagepermalinks_deactivation', $plugin->subclass::$prefix, $plugin->subclass);
		}
		return;
	}

	public static function uninstall() {
		$plugin = new self;
		if ($plugin->subclass) {
			$plugin->subclass->delete_option_uninstall();
			apply_filters('singlepagepermalinks_uninstall', $plugin->subclass::$prefix, $plugin->subclass);
		}
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