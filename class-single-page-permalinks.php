<?php
/*
Available filters:
singlepagepermalinks_the_content
singlepagepermalinks_template
*/

// Exit if accessed directly.
defined('ABSPATH') || exit;

if (!class_exists('Halftheory_Helper_Plugin')) {
	@include_once(dirname(__FILE__).'/class-halftheory-helper-plugin.php');
}

if (!class_exists('Single_Page_Permalinks') && class_exists('Halftheory_Helper_Plugin')) :
final class Single_Page_Permalinks extends Halftheory_Helper_Plugin {

	public static $plugin_basename;
	public static $prefix;
	public static $active = false;

	/* setup */

	public function init($plugin_basename = '', $prefix = '') {
		parent::init($plugin_basename, $prefix);
		self::$active = $this->get_option(static::$prefix, 'active', false);
	}

	protected function setup_actions() {
		parent::setup_actions();

		// stop if not active
		if (empty(self::$active)) {
			return;
		}

		// admin
		add_action('edit_form_after_title', array($this,'edit_form_after_title'), 10);

		// actions
		if ($this->is_front_end()) {
			$this->enqueue_scripts = false;
			add_action('template_redirect', array($this,'template_redirect'), 20);
			add_action('wp_enqueue_scripts', array($this,'wp_enqueue_scripts'), 20);
			add_filter('the_content', array($this,'the_content'), 20);
			add_filter('post_link', array($this,'post_link'), 20, 3);
			add_filter('page_link', array($this,'post_link'), 20, 3);
			add_filter('post_type_link', array($this,'post_link'), 20, 3);
			// ajax
        	add_action('wp_ajax_'.static::$prefix.'_get_post', array($this, 'ajax_get_post'));
        	add_action('wp_ajax_nopriv_'.static::$prefix.'_get_post', array($this, 'ajax_get_post'));
		}
	}

	/* admin */

	public function menu_page() {
 		global $title;
		?>
		<div class="wrap">
			<h2><?php echo $title; ?></h2>
		<?php
 		$plugin = new static(static::$plugin_basename, static::$prefix, false);

		if ($plugin->save_menu_page()) {
        	$save = function() use ($plugin) {
				// get values
				$options_arr = $plugin->get_options_array();
				$options = array();
				foreach ($options_arr as $value) {
					$name = $plugin::$prefix.'_'.$value;
					if (!isset($_POST[$name])) {
						continue;
					}
					if ($this->empty_notzero($_POST[$name])) {
						continue;
					}
					$options[$value] = $_POST[$name];
				}
				// save it
	            $updated = '<div class="updated"><p><strong>Options saved.</strong></p></div>';
	            $error = '<div class="error"><p><strong>Error: There was a problem.</strong></p></div>';
				if (!empty($options)) {
		            if ($plugin->update_option($plugin::$prefix, $options)) {
		            	echo $updated;
		            }
		        	else {
		        		// where there changes?
		        		$options_old = $plugin->get_option($plugin::$prefix, null, array());
		        		ksort($options_old);
		        		ksort($options);
		        		if ($options_old !== $options) {
		            		echo $error;
		            	}
		            	else {
			            	echo $updated;
		            	}
		        	}
				}
				else {
		            if ($plugin->delete_option($plugin::$prefix)) {
		            	echo $updated;
		            }
		        	else {
		            	echo $updated;
		        	}
				}
			};
			$save();
        } // save

		// show the form
		$options_arr = $plugin->get_options_array();
		$options = $plugin->get_option($plugin::$prefix, null, array());
		$options = array_merge( array_fill_keys($options_arr, null), $options );
		?>
	    <form id="<?php echo $plugin::$prefix; ?>-admin-form" name="<?php echo $plugin::$prefix; ?>-admin-form" method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
		<?php
		// Use nonce for verification
		wp_nonce_field($plugin::$plugin_basename, $plugin->plugin_name.'::'.__FUNCTION__);
		?>
	    <div id="poststuff">

        <p><label for="<?php echo $plugin::$prefix; ?>_active"><input type="checkbox" id="<?php echo $plugin::$prefix; ?>_active" name="<?php echo $plugin::$prefix; ?>_active" value="1"<?php checked($options['active'], 1); ?> /> <?php echo $plugin->plugin_title; ?> <?php _e('active?'); ?></label></p>

        <div class="postbox">
        	<div class="inside">
	            <h4>URLs</h4>

	            <?php
	            if (empty($options['home_url'])) {
	            	$options['home_url'] = network_home_url('/');
	            }
	            ?>
	            <p><label for="<?php echo $plugin::$prefix; ?>_home_url" style="display: inline-block; width: 10em; max-width: 25%;"><?php _e('Home URL'); ?></label>
	            <input type="text" name="<?php echo $plugin::$prefix; ?>_home_url" id="<?php echo $plugin::$prefix; ?>_home_url" style="width: 50%;" value="<?php echo esc_attr($options['home_url']); ?>" /><br />
	        	<span class="description small" style="margin-left: 10em;"><?php _e('The base URL for all Single Page Permalinks. Defaults to network_home_url("/").'); ?></span></p>

		        <p><label for="<?php echo $plugin::$prefix; ?>_redirect_urls"><input type="checkbox" id="<?php echo $plugin::$prefix; ?>_redirect_urls" name="<?php echo $plugin::$prefix; ?>_redirect_urls" value="1"<?php checked($options['redirect_urls'], 1); ?> /> <?php _e('Redirect normal permalinks to Single Page Permalinks?'); ?></label></p>

        	</div>
        </div>

        <div class="postbox">
        	<div class="inside">
	            <h4><?php _e('Allowed Post Types'); ?></h4>
	            <p><span class="description"><?php _e('Single Page Permalinks will only be active on the following post types.'); ?></span></p>
	            <?php
	            $post_types = array();
	            $arr = get_post_types(array('public' => true), 'objects');
	            foreach ($arr as $key => $value) {
	            	$post_types[$key] = $value->label;
	            }
	            $options['post_types'] = $plugin->make_array($options['post_types']);
	            foreach ($post_types as $key => $value) {
					echo '<label style="display: inline-block; width: 50%;"><input type="checkbox" name="'.$plugin::$prefix.'_post_types[]" value="'.$key.'"';
					if (in_array($key, $options['post_types'])) {
						checked($key, $key);
					}
					echo '> '.$value.'</label>';
	            }
	            ?>
        	</div>
        </div>

		<div class="postbox">
			<div class="inside">
				<h4><?php _e('Loading Behaviors'); ?></h4>

				<?php
				$animation_open = array(
					'slideInFromTop',
					'slideInFromRight',
					'slideInFromBottom',
					'slideInFromLeft',
				);
				?>
	            <p><label for="<?php echo $plugin::$prefix; ?>_behavior_open" style="display: inline-block; width: 10em; max-width: 25%;"><?php _e('Open Post'); ?></label>
				<select id="<?php echo $plugin::$prefix; ?>_behavior_open" name="<?php echo $plugin::$prefix; ?>_behavior_open">
					<option value=""><?php _e('&mdash;&mdash;'); ?></option>
					<?php foreach ($animation_open as $value) : ?>
						<option value="<?php echo esc_attr($value); ?>"<?php selected($value, $options['behavior_open']); ?>><?php echo esc_html($value); ?></option>
					<?php endforeach; ?>
				</select></p>

				<?php
				$animation_close = array(
					'slideOutToTop',
					'slideOutToRight',
					'slideOutToBottom',
					'slideOutToLeft',
				);
				?>
	            <p><label for="<?php echo $plugin::$prefix; ?>_behavior_close" style="display: inline-block; width: 10em; max-width: 25%;"><?php _e('Close Post'); ?></label>
				<select id="<?php echo $plugin::$prefix; ?>_behavior_close" name="<?php echo $plugin::$prefix; ?>_behavior_close">
					<option value=""><?php _e('&mdash;&mdash;'); ?></option>
					<?php foreach ($animation_close as $value) : ?>
						<option value="<?php echo esc_attr($value); ?>"<?php selected($value, $options['behavior_close']); ?>><?php echo esc_html($value); ?></option>
					<?php endforeach; ?>
				</select></p>

		        <p><label for="<?php echo $plugin::$prefix; ?>_behavior_escape"><input type="checkbox" id="<?php echo $plugin::$prefix; ?>_behavior_escape" name="<?php echo $plugin::$prefix; ?>_behavior_escape" value="1"<?php checked($options['behavior_escape'], 1); ?> /> <?php _e('Escape key closes currently displayed post?'); ?></label></p>

			</div>
		</div>

        <?php submit_button(__('Update'), array('primary','large'), 'save'); ?>

        </div><!-- poststuff -->
    	</form>

		</div><!-- wrap -->
		<?php
	}

 	public function edit_form_after_title($post) {
		if ($url = $this->singlepagepermalink($post)) {
	 		?><div class="inside" style="padding: 0 10px; color: #666;"><strong><?php _e('Single Page Permalink:'); ?></strong> <a href="<?php echo esc_url($url); ?>" target="_blank"><?php echo $url; ?></a></div><?php
		}
 	}

	/* actions */

	public function template_redirect() {
		// redirect urls - can't get location.hash here - check in js
		if ($url = $this->singlepagepermalink()) {
			$current_url = $this->get_current_uri();
			$redirect_urls = $this->get_option(static::$prefix, 'redirect_urls', false);
			if ($current_url == $this->get_home_url()) {
				$this->enqueue_scripts = true;
			}
			elseif (!empty($redirect_urls) && $current_url != $this->get_home_url()) {
				if (wp_redirect($url)) {
					exit;
				}
			}
		}
	}

	public function wp_enqueue_scripts() {
		if (!$this->enqueue_scripts) {
			return;
		}
		wp_enqueue_script(static::$prefix.'-init', plugins_url('/assets/js/single-page-permalinks-init.min.js', __FILE__), array('jquery'), self::get_plugin_version(), true);
		$data = array(
			'ajaxurl' => esc_url(admin_url().'admin-ajax.php'),
		    'prefix' => static::$prefix,
		    'home_url' => $this->get_home_url(),
		    'behavior_open' => $this->get_option(static::$prefix, 'behavior_open', 'slideInFromBottom'),
		    'behavior_close' => $this->get_option(static::$prefix, 'behavior_close', 'slideOutToBottom'),
		    'behavior_escape' => $this->get_option(static::$prefix, 'behavior_escape', false),
		);
		wp_localize_script(static::$prefix.'-init', static::$prefix, $data);
		wp_enqueue_style(static::$prefix, plugins_url('/assets/css/single-page-permalinks.css', __FILE__), array(), self::get_plugin_version());
	}

	public function the_content($str = '') {
		if (!$this->the_content_conditions($str)) {
			return $str;
		}
		if (is_search()) {
			return $str;
		}
		if (is_home()) {
			return $str;
		}
		// start html
		$str = apply_filters('singlepagepermalinks_the_content', $str, $this);
		if (current_filter() == 'the_content') {
			return $str;
		}
		else {
			echo $str;
		}
	}

	public function post_link($permalink, $post_or_ID = 0, $leavename_or_sample = false) {
		if (current_filter() == 'page_link') {
			$post = get_post($post_or_ID);
			if (empty($post) || is_wp_error($post)) {
				return $permalink;
			}
		}
		else {
			$post = $post_or_ID;
		}
		if ($url = $this->singlepagepermalink($post)) {
			$permalink = $url;
		}
		return $permalink;
	}

	/* ajax */

	public function ajax_get_post() {
	    if (!isset($_REQUEST['post_name'])) {
	    	wp_die();
	    }
		$args = array(
			'no_found_rows' => true,
			'nopaging' => true,
			'ignore_sticky_posts' => true,
			'post_status' => 'publish,inherit',
			'post_type' => $this->get_option(static::$prefix, 'post_types', array()),
			'orderby' => 'title',
			'order' => 'ASC',
			'suppress_filters' => false,
        );
		if (is_numeric($_REQUEST['post_name']) && $_REQUEST['post_name'] == 0) {
			$args['include'] = get_option('page_on_front');
		}
		elseif (!empty($_REQUEST['post_name'])) {
			$args['name'] = $_REQUEST['post_name'];
		}
		else {
			wp_die();
		}
		// posts
		$posts = query_posts($args);
		if (empty($posts)) {
			wp_reset_query();
			wp_die();
		}
		$str = '';
		ob_start();
		while (have_posts()) { // Start the loop.
			the_post();
			global $post;
			$template = false;
			$template = apply_filters('singlepagepermalinks_template', $template, $post, $args);
			if (empty($template)) {
				$template = self::get_template();
			}
			if ($template) {
				load_template($template, false);
			}
			else {
				load_template(get_stylesheet_directory().'/index.php', false);
			}
		} // End the loop.
		$str = ob_get_clean();
		wp_reset_query();
		if (empty($str)) {
			wp_die();
		}
		if (strpos(current_action(), 'wp_ajax_') !== false) {
		    echo $str;
	        wp_die();
		}
		return $str;
	}

    /* functions */

    private function get_options_array() {
		return array(
			'active',
			'home_url',
			'redirect_urls',
			'post_types',
			'behavior_open',
			'behavior_close',
			'behavior_escape',
		);
    }

    private function get_home_url() {
		return trailingslashit(set_url_scheme($this->get_option(static::$prefix, 'home_url', network_home_url('/'))));
    }

	private function get_post_ID_post() {
		global $post;
		$post_ID = $post->ID;

		if (empty($post_ID) && is_singular()) {
			// some plugins (buddypress) hide the real post_id in queried_object_id
			global $wp_query;
			if (isset($wp_query->queried_object_id)) {
				if (!empty($wp_query->queried_object_id)) {
					$post_ID = $wp_query->queried_object_id;
					if (isset($wp_query->queried_object)) {
						$post = $wp_query->queried_object;
					}
				}
			}
		}
		return array($post_ID, $post);
	}

	private function post_is_singlepage($post = 0) {
		if (empty($post) || !is_object($post)) {
			if ($this->is_front_end()) {
				list($post_ID, $post) = $this->get_post_ID_post();
			}
			else {
				global $post;
			}
			if (empty($post) || !is_object($post)) {
				return false;
			}
		}
		$post_types = $this->get_option(static::$prefix, 'post_types', array());
		if (!in_array($post->post_type, $post_types)) {
			return false;
		}
		return $post;
	}

	private function singlepagepermalink($post = 0) {
		$post = $this->post_is_singlepage($post);
		if ($post === false) {
			return false;
		}
		// home
		if ($post->post_type == 'page') {
			if ('page' == get_option('show_on_front') && $post->ID == get_option('page_on_front')) {
				return home_url('/');
			}
		}
		return $this->get_home_url().'#'.$post->post_name;
	}

}
endif;
?>