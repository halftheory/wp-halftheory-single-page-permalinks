<?php
/*
Plugin Name: Half/theory Single Page Permalinks
Plugin URI: https://github.com/halftheory/wp-halftheory-single-page-permalinks
GitHub Plugin URI: https://github.com/halftheory/wp-halftheory-single-page-permalinks
Description: Half/theory Single Page Permalinks Plugin.
Author: Half/theory
Author URI: https://github.com/halftheory
Version: 2.0
Network: false
*/

/*
Available filters:
singlepagepermalinks_the_content
singlepagepermalinks_template
*/

// Exit if accessed directly.
defined('ABSPATH') || exit;

if ( ! class_exists('Halftheory_Helper_Plugin', false) && is_readable(dirname(__FILE__) . '/class-halftheory-helper-plugin.php') ) {
	include_once dirname(__FILE__) . '/class-halftheory-helper-plugin.php';
}

if ( ! class_exists('Halftheory_Single_Page_Permalinks', false) && class_exists('Halftheory_Helper_Plugin', false) ) :
	final class Halftheory_Single_Page_Permalinks extends Halftheory_Helper_Plugin {

        protected static $instance;
        public static $prefix;
        public static $active = false;

        protected function setup_globals( $plugin_basename = null, $prefix = null ) {
            parent::setup_globals($plugin_basename, $prefix);

            self::$active = $this->get_options_context('db', 'active');
        }

		protected function setup_actions() {
			parent::setup_actions();

            // Stop if not active.
            if ( empty(self::$active) ) {
                return;
            }

			if ( ! $this->is_front_end() ) {
				// admin.
				add_action('edit_form_after_title', array( $this, 'edit_form_after_title' ), 10);
			} else {
				// public.
				$this->enqueue_scripts = false;
				add_action('template_redirect', array( $this, 'template_redirect' ), 20);
				add_action('wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ), 20);
				add_filter('the_content', array( $this, 'the_content' ), 20);
				add_filter('post_link', array( $this, 'post_link' ), 20, 3);
				add_filter('page_link', array( $this, 'post_link' ), 20, 3);
				add_filter('post_type_link', array( $this, 'post_link' ), 20, 3);
				// ajax
	        	add_action('wp_ajax_' . static::$prefix . '_get_post', array( $this, 'ajax_get_post' ));
	        	add_action('wp_ajax_nopriv_' . static::$prefix . '_get_post', array( $this, 'ajax_get_post' ));
			}
		}

        public static function plugin_uninstall() {
            static::$instance->delete_option_uninstall();
            parent::plugin_uninstall();
        }

        /* admin */

		public function menu_page() {
            $plugin = static::$instance;

	 		global $title;
			?>
			<div class="wrap">
            <h2><?php echo esc_html($title); ?></h2>

			<?php
			if ( $plugin->save_menu_page() ) {
	        	$save = function () use ( $plugin ) {
					// get values.
                    $options = array();
                    foreach ( array_keys($plugin->get_options_context('default')) as $value ) {
						$name = $plugin::$prefix . '_' . $value;
						if ( ! isset($_POST[ $name ]) ) {
							continue;
						}
						if ( $plugin->empty_notzero($_POST[ $name ]) ) {
							continue;
						}
						$options[ $value ] = $_POST[ $name ];
					}
					// save it.
                    $updated = '<div class="updated"><p><strong>' . esc_html__('Options saved.') . '</strong></p></div>';
                    $error = '<div class="error"><p><strong>' . esc_html__('Error: There was a problem.') . '</strong></p></div>';
                    if ( ! empty($options) ) {
                        $options = $plugin->get_options_context('input', null, array(), $options);
                        if ( $plugin->update_option($plugin::$prefix, $options) ) {
                            echo $updated;
                        } else {
                            echo $error;
                        }
                    } else {
                        if ( $plugin->delete_option($plugin::$prefix) ) {
                            echo $updated;
                        } else {
                            echo $updated;
                        }
                    }
				};
				$save();
	        } // save

            // Show the form.
            $options = $plugin->get_options_context('admin_form');
			?>

		    <form id="<?php echo esc_attr($plugin::$prefix); ?>-admin-form" name="<?php echo esc_attr($plugin::$prefix); ?>-admin-form" method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
			<?php
            // Use nonce for verification.
            wp_nonce_field($plugin->plugin_basename, $plugin->plugin_name . '::' . __FUNCTION__);
			?>
		    <div id="poststuff">

	        <p><label for="<?php echo esc_attr($plugin::$prefix); ?>_active"><input type="checkbox" id="<?php echo esc_attr($plugin::$prefix); ?>_active" name="<?php echo esc_attr($plugin::$prefix); ?>_active" value="1"<?php checked($options['active'], true); ?> /> <?php echo esc_html($plugin->plugin_title); ?> <?php esc_html_e('active?'); ?></label></p>

	        <div class="postbox">
	        	<div class="inside">
		            <h4><?php esc_html_e('URLs'); ?></h4>
		            <p><label for="<?php echo esc_attr($plugin::$prefix); ?>_home_url" style="display: inline-block; width: 10em; max-width: 25%;"><?php esc_html_e('Home URL'); ?></label>
		            <input type="text" name="<?php echo esc_attr($plugin::$prefix); ?>_home_url" id="<?php echo esc_attr($plugin::$prefix); ?>_home_url" style="width: 50%;" value="<?php echo esc_attr($options['home_url']); ?>" /><br />
		        	<span class="description small" style="margin-left: 10em;"><?php esc_html_e('The base URL for all Single Page Permalinks. Defaults to network_home_url("/").'); ?></span></p>

			        <p><label for="<?php echo esc_attr($plugin::$prefix); ?>_redirect_urls"><input type="checkbox" id="<?php echo esc_attr($plugin::$prefix); ?>_redirect_urls" name="<?php echo esc_attr($plugin::$prefix); ?>_redirect_urls" value="1"<?php checked($options['redirect_urls'], true); ?> /> <?php esc_html_e('Redirect normal permalinks to Single Page Permalinks?'); ?></label></p>

	        	</div>
	        </div>

	        <div class="postbox">
	        	<div class="inside">
		            <h4><?php esc_html_e('Allowed Post Types'); ?></h4>
		            <p><span class="description"><?php esc_html_e('Single Page Permalinks will only be active on the following post types.'); ?></span></p>
		            <?php
		            $post_types = array();
		            $arr = get_post_types(array( 'public' => true ), 'objects');
		            foreach ( $arr as $key => $value ) {
		            	$post_types[ $key ] = $value->label;
		            }
		            foreach ( $post_types as $key => $value ) {
						echo '<label style="display: inline-block; width: 50%;"><input type="checkbox" name="' . esc_attr($plugin::$prefix) . '_post_types[]" value="' . esc_attr($key) . '"';
						if ( in_array($key, $options['post_types'], true) ) {
							checked($key, $key);
						}
						echo '> ' . esc_html($value) . '</label>';
		            }
		            ?>
	        	</div>
	        </div>

			<div class="postbox">
				<div class="inside">
					<h4><?php esc_html_e('Loading Behaviors'); ?></h4>

					<?php
					$animation_open = array(
						'slideInFromTop',
						'slideInFromRight',
						'slideInFromBottom',
						'slideInFromLeft',
					);
					?>
		            <p><label for="<?php echo esc_attr($plugin::$prefix); ?>_behavior_open" style="display: inline-block; width: 10em; max-width: 25%;"><?php esc_html_e('Open Post'); ?></label>
					<select id="<?php echo esc_attr($plugin::$prefix); ?>_behavior_open" name="<?php echo esc_attr($plugin::$prefix); ?>_behavior_open">
						<option value=""><?php esc_html_e('&mdash;&mdash;'); ?></option>
						<?php foreach ( $animation_open as $value ) : ?>
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
		            <p><label for="<?php echo esc_attr($plugin::$prefix); ?>_behavior_close" style="display: inline-block; width: 10em; max-width: 25%;"><?php esc_html_e('Close Post'); ?></label>
					<select id="<?php echo esc_attr($plugin::$prefix); ?>_behavior_close" name="<?php echo esc_attr($plugin::$prefix); ?>_behavior_close">
						<option value=""><?php esc_html_e('&mdash;&mdash;'); ?></option>
						<?php foreach ( $animation_close as $value ) : ?>
							<option value="<?php echo esc_attr($value); ?>"<?php selected($value, $options['behavior_close']); ?>><?php echo esc_html($value); ?></option>
						<?php endforeach; ?>
					</select></p>

			        <p><label for="<?php echo esc_attr($plugin::$prefix); ?>_behavior_escape"><input type="checkbox" id="<?php echo esc_attr($plugin::$prefix); ?>_behavior_escape" name="<?php echo esc_attr($plugin::$prefix); ?>_behavior_escape" value="1"<?php checked($options['behavior_escape'], true); ?> /> <?php esc_html_e('Escape key closes currently displayed post?'); ?></label></p>

				</div>
			</div>

	        <?php submit_button(__('Update'), array( 'primary', 'large' ), 'save'); ?>

	        </div><!-- poststuff -->
	    	</form>

			</div><!-- wrap -->
			<?php
		}

	 	public function edit_form_after_title( $post ) {
			if ( $url = $this->singlepagepermalink($post) ) {
		 		?>
		 		<div class="inside" style="padding: 0 10px; color: #666;"><strong><?php esc_html_e('Single Page Permalink:'); ?></strong> <a href="<?php echo esc_url($url); ?>" target="_blank"><?php echo esc_html($url); ?></a></div>
		 		<?php
			}
	 	}

		/* public */

		public function template_redirect() {
			// redirect urls - can't get location.hash here - check in js.
			if ( $url = $this->singlepagepermalink() ) {
				$current_url = $this->get_current_uri();
				if ( $current_url === $this->get_home_url() ) {
					$this->enqueue_scripts = true;
				} elseif ( $this->get_options_context('db', 'redirect_urls') && $current_url !== $this->get_home_url() ) {
					if ( wp_redirect($url) ) {
						exit;
					}
				}
			}
		}

		public function wp_enqueue_scripts() {
			if ( ! $this->enqueue_scripts ) {
				return;
			}
			wp_enqueue_script(static::$prefix . '-init', plugins_url('/assets/js/single-page-permalinks-init.min.js', __FILE__), array( 'jquery' ), $this->get_plugin_version(), true);
			$data = array(
				'ajaxurl' => esc_url(admin_url() . 'admin-ajax.php'),
			    'prefix' => static::$prefix,
			    'home_url' => $this->get_home_url(),
			    'behavior_open' => $this->get_options_context('db', 'behavior_open'),
			    'behavior_close' => $this->get_options_context('db', 'behavior_close'),
			    'behavior_escape' => $this->get_options_context('db', 'behavior_escape'),
			);
			wp_localize_script(static::$prefix . '-init', static::$prefix, $data);
			wp_enqueue_style(static::$prefix, plugins_url('/assets/css/single-page-permalinks.css', __FILE__), array(), $this->get_plugin_version());
		}

		public function the_content( $str = '' ) {
			if ( ! $this->the_content_conditions($str) ) {
				return $str;
			}
			if ( is_search() ) {
				return $str;
			}
			if ( is_home() ) {
				return $str;
			}
			// start html.
			$str = apply_filters('singlepagepermalinks_the_content', $str, $this);
			if ( current_filter() === 'the_content' ) {
				return $str;
			} else {
				echo $str;
			}
		}

		public function post_link( $permalink, $post_or_ID = 0, $leavename_or_sample = false ) {
			if ( current_filter() === 'page_link' ) {
				$post = get_post($post_or_ID);
				if ( empty($post) || is_wp_error($post) ) {
					return $permalink;
				}
			} else {
				$post = $post_or_ID;
			}
			if ( $url = $this->singlepagepermalink($post) ) {
				$permalink = $url;
			}
			return $permalink;
		}

		/* ajax */

		public function ajax_get_post() {
		    if ( ! isset($_REQUEST['post_name']) ) {
		    	wp_die();
		    }
			$args = array(
				'no_found_rows' => true,
				'nopaging' => true,
				'ignore_sticky_posts' => true,
				'post_status' => 'publish,inherit',
				'post_type' => $this->get_options_context('db', 'post_types'),
				'orderby' => 'title',
				'order' => 'ASC',
				'suppress_filters' => false,
	        );
			if ( is_numeric($_REQUEST['post_name']) && (int) $_REQUEST['post_name'] === 0 ) {
				$args['include'] = get_option('page_on_front');
			} elseif ( ! empty($_REQUEST['post_name']) ) {
				$args['name'] = $_REQUEST['post_name'];
			} else {
				wp_die();
			}
			// posts
			$posts = query_posts($args);
			if ( empty($posts) ) {
				wp_reset_query();
				wp_die();
			}
			$str = '';
			ob_start();
			// Start the loop.
			while ( have_posts() ) {
				the_post();
				global $post;
				$template = apply_filters('singlepagepermalinks_template', false, $post, $args);
				if ( empty($template) ) {
					$template = $this->get_template();
				}
				if ( $template ) {
					load_template($template, false);
				} else {
					load_template(get_stylesheet_directory() . '/index.php', false);
				}
			}
			// End the loop.
			$str = ob_get_clean();
			wp_reset_query();
			if ( empty($str) ) {
				wp_die();
			}
			if ( strpos(current_action(), 'wp_ajax_') !== false ) {
			    echo $str;
		        wp_die();
			}
			return $str;
		}

	    /* functions */

        protected function get_options_default() {
            return apply_filters(static::$prefix . '_options_default',
                array(
                    'active' => false,
                    'home_url' => network_home_url('/'),
                    'redirect_urls' => false,
                    'post_types' => array(),
                    'behavior_open' => 'slideInFromBottom',
                    'behavior_close' => 'slideOutToBottom',
                    'behavior_escape' => false,
                )
            );
        }

	    private function get_home_url() {
	    	return trailingslashit(set_url_scheme($this->get_options_context('db', 'home_url', network_home_url('/'))));
	    }

		private function get_post_ID_post() {
			global $post;
			$post_ID = $post->ID;
			$my_post = $post;

			if ( empty($post_ID) && is_singular() ) {
				// some plugins (buddypress) hide the real post_id in queried_object_id.
				global $wp_query;
				if ( isset($wp_query->queried_object_id) ) {
					if ( ! empty($wp_query->queried_object_id) ) {
						$post_ID = $wp_query->queried_object_id;
						if ( isset($wp_query->queried_object) ) {
							$my_post = $wp_query->queried_object;
						}
					}
				}
			}
			return array( $post_ID, $my_post );
		}

		private function post_is_singlepage( $post = 0 ) {
			if ( empty($post) || ! is_object($post) ) {
				if ( $this->is_front_end() ) {
					list($post_ID, $post) = $this->get_post_ID_post();
				} else {
					global $post;
				}
				if ( empty($post) || ! is_object($post) ) {
					return false;
				}
			}
            $post_types = $this->get_options_context('db', 'post_types');
			if ( ! in_array($post->post_type, $post_types, true) ) {
				return false;
			}
			return $post;
		}

		private function singlepagepermalink( $post = 0 ) {
			$post = $this->post_is_singlepage($post);
			if ( $post === false ) {
				return false;
			}
			// home.
			if ( $post->post_type === 'page' ) {
				if ( 'page' === get_option('show_on_front') && (int) $post->ID === (int) get_option('page_on_front') ) {
					return home_url('/');
				}
			}
			return $this->get_home_url() . '#' . $post->post_name;
		}
    }

	// Load the plugin.
    Halftheory_Single_Page_Permalinks::get_instance(true, plugin_basename(__FILE__));
endif;
