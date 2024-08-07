<?php
/**
 * Google Auth block.
 *
 * @link          https://wpmudev.com/
 * @since         1.0.0
 *
 * @author        WPMUDEV (https://wpmudev.com)
 * @package       WPMUDEV\PluginTest
 *
 * @copyright (c) 2023, Incsub (http://incsub.com)
 */

namespace WPMUDEV\PluginTest\App\Admin_Pages;

// Abort if called directly.
defined( 'WPINC' ) || die;

use WPMUDEV\PluginTest\Base;
use WPMUDEV\PluginTest\Endpoints\V1\Auth_Confirm;

class Auth extends Base {
	/**
	 * The page title.
	 *
	 * @var string
	 */
	private $page_title;

	/**
	 * The page slug.
	 *
	 * @var string
	 */
	private $page_slug = 'wpmudev_plugintest_auth';

	/**
	 * Google auth credentials.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private $creds = array();

	/**
	 * Option name.
	 *
	 * @var string
	 */
	private $option_name = 'wpmudev_plugin_tests_auth';

	/**
	 * Page Assets.
	 *
	 * @var array
	 */
	private $page_scripts = array();

	/**
	 * Assets version.
	 *
	 * @var string
	 */
	private $assets_version = '';

	/**
	 * A unique string id to be used in markup and jsx.
	 *
	 * @var string
	 */
	private $unique_id = '';

	public function __construct() {
		register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        add_action('init', [$this, 'init']);
	}

	/**
	 * Initializes the page.
	 *
	 * @return void
	 * @since 1.0.0
	 *
	 */
	public function init() {
		$this->page_title     = __( 'Google Auth', 'wpmudev-plugin-test' );
		$this->creds          = get_option( $this->option_name, array() );
		$this->assets_version = ! empty( $this->script_data( 'version' ) ) ? $this->script_data( 'version' ) : WPMUDEV_PLUGINTEST_VERSION;
		$this->unique_id      = "wpmudev_plugintest_auth_main_wrap-{$this->assets_version}";

		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		// Add body class to admin pages.
		add_filter( 'admin_body_class', array( $this, 'admin_body_classes' ) );

		add_action( 'rest_api_init', array($this, 'wpmudev_auth_rest_endpoint_init'));
		add_action( 'rest_api_init', array($this, 'wpmudev_auth_confirm_init'));

		add_shortcode('wpmudev_login_or_message', array($this, 'wpmudev_login_or_message_shortcode'));

		add_action('admin_menu', array($this, 'wpmudev_posts_maintenance_menu'));
		add_action('wp_ajax_wpmudev_scan_posts', array($this, 'wpmudev_scan_posts'));
		add_action('wp', array($this, 'wpmudev_schedule_daily_scan'));
		add_action('wpmudev_daily_scan', array($this, 'wpmudev_run_daily_scan'));

		if (defined('WP_CLI') && WP_CLI) {
			WP_CLI::add_command('wpmudev scan_posts', array($this, 'wpmudev_scan_posts'));
		}
	}

	public function activate() {
        $this->create_db_table();
    }
    
    public function deactivate() {
        
    }
    
    private function create_db_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpmudev_auth_table';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            client_id text NOT NULL,
            client_secret text NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

	public function register_admin_page() {
		$page = add_menu_page(
			'Google Auth setup',
			$this->page_title,
			'manage_options',
			$this->page_slug,
			array( $this, 'callback' ),
			'dashicons-google',
			6
		);

		add_action( 'load-' . $page, array( $this, 'prepare_assets' ) );
	}

	/**
	 * The admin page callback method.
	 *
	 * @return void
	 */
	public function callback() {
		$this->view();
	}

	/**
	 * Prepares assets.
	 *
	 * @return void
	 */
	public function prepare_assets() {
		if ( ! is_array( $this->page_scripts ) ) {
			$this->page_scripts = array();
		}

		$handle       = 'wpmudev_plugintest_authpage';
		$src          = WPMUDEV_PLUGINTEST_ASSETS_URL . '/js/authsettingspage.min.js';
		$style_src    = WPMUDEV_PLUGINTEST_ASSETS_URL . '/css/authsettingspage.min.css';
		$dependencies = ! empty( $this->script_data( 'dependencies' ) )
			? $this->script_data( 'dependencies' )
			: array(
				'react',
				'wp-element',
				'wp-i18n',
				'wp-is-shallow-equal',
				'wp-polyfill',
			);

		$this->page_scripts[ $handle ] = array(
			'src'       => $src,
			'style_src' => $style_src,
			'deps'      => $dependencies,
			'ver'       => $this->assets_version,
			'strategy'  => true,
			'localize'  => array(
				'dom_element_id'   => $this->unique_id,
				'clientID'         => 'clientID',
				'clientSecret'     => 'clientSecret',
				'redirectUrl'      => 'redirectUrl',
				'restEndpointSave' => 'wpmudev/v1/auth/auth-url',
				'returnUrl'        => '[Replace with the /wp-json/wpmudev/v1/auth/confirm url]',
			),
		);
	}

	/**
	 * Gets assets data for given key.
	 *
	 * @param string $key
	 *
	 * @return string|array
	 */
	protected function script_data( string $key = '' ) {
		$raw_script_data = $this->raw_script_data();

		return ! empty( $key ) && ! empty( $raw_script_data[ $key ] ) ? $raw_script_data[ $key ] : '';
	}

	/**
	 * Gets the script data from assets php file.
	 *
	 * @return array
	 */
	protected function raw_script_data(): array {
		static $script_data = null;

		if ( is_null( $script_data ) && file_exists( WPMUDEV_PLUGINTEST_DIR . 'assets/js/authsettingspage.min.asset.php' ) ) {
			$script_data = include WPMUDEV_PLUGINTEST_DIR . 'assets/js/authsettingspage.min.asset.php';
		}

		return (array) $script_data;
	}

	/**
	 * Prepares assets.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! empty( $this->page_scripts ) ) {
			foreach ( $this->page_scripts as $handle => $page_script ) {
				wp_register_script(
					$handle,
					$page_script['src'],
					$page_script['deps'],
					$page_script['ver'],
					$page_script['strategy']
				);

				if ( ! empty( $page_script['localize'] ) ) {
					wp_localize_script( $handle, 'wpmudevPluginTest', $page_script['localize'] );
				}

				wp_enqueue_script( $handle );

				if ( ! empty( $page_script['style_src'] ) ) {
					wp_enqueue_style( $handle, $page_script['style_src'], array(), $this->assets_version );
				}
			}
		}
	}

	/**
	 * Prints the wrapper element which React will use as root.
	 *
	 * @return void
	 */
	protected function view() {
		echo '<div id="' . esc_attr( $this->unique_id ) . '" class="sui-wrap"></div>';
	}

	/**
	 * Adds the SUI class on markup body.
	 *
	 * @param string $classes
	 *
	 * @return string
	 */
	public function admin_body_classes( $classes = '' ) {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return $classes;
		}

		$current_screen = get_current_screen();

		if ( empty( $current_screen->id ) || ! strpos( $current_screen->id, $this->page_slug ) ) {
			return $classes;
		}

		$classes .= ' sui-' . str_replace( '.', '-', WPMUDEV_PLUGINTEST_SUI_VERSION ) . ' ';

		return $classes;
	}

	function wpmudev_get_latest_auth_data() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'wpmudev_auth_table';
		$latest_row = $wpdb->get_row("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 1", ARRAY_A);
		if (empty($latest_row)) {
			return null;
		}
	
		return $latest_row;
	}

	function wpmudev_auth_rest_endpoint_init() {
		register_rest_route( 'wpmudev/v1', '/auth/auth-url', array(
			'methods'             => 'POST',
			'callback'            => array($this, 'wpmudev_auth_rest_endpoint_callback'),
			'permission_callback' => array($this, 'wpmudev_auth_rest_endpoint_permissions'),
		));
	}

	function wpmudev_auth_confirm_init() {
		register_rest_route('wpmudev/v1', '/auth/confirm', array(
			'methods'             => 'GET',
			'callback'            => array($this, 'wpmudev_auth_confirm'),
			'permission_callback' => '__return_true',
		));
	}

	function wpmudev_auth_rest_endpoint_callback(WP_REST_Request $request) {
		$clientID = sanitize_text_field($request->get_param('clientID'));
		$clientSecret = sanitize_email($request->get_param('clientSecret'));

		if (empty($clientID) || empty($clientSecret)) {
			return new WP_Error('missing_data', 'Client ID, Client secret are required.', array('status' => 400));
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'wpmudev_auth_table';

		$result = $wpdb->insert(
			$table_name,
			array(
				'client_id' => $clientID,
				'client_secret' => $clientSecret,
				'created_at' => current_time('mysql', 1)
			),
			array(
				'%s',
				'%s',
				'%s'
			)
		);

		if ($result === false) {
			return new WP_Error('db_error', 'Failed to save data to the database.', array('status' => 500));
		}

		return new WP_REST_Response(array('message' => 'Google Auth data saved successfully.'), 200);
	}

	function wpmudev_auth_rest_endpoint_permissions($request) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_forbidden', __( 'You are not authorized to access this endpoint.', 'my-plugin' ), array( 'status' => 401 ) );
		}
	
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permission to access this endpoint.', 'my-plugin' ), array( 'status' => 403 ) );
		}
	
		return true;
	}

	function wpmudev_auth_confirm(WP_REST_Request $request) {
		$code = $request->get_param('code');
		if (empty($code)) {
			return new WP_Error('missing_code', 'Authorization code is missing.', array('status' => 400));
		}
		
		$latest_row = wpmudev_get_latest_auth_data();
		if (is_null($latest_row)) {
			return new WP_Error('token_request_failed', 'Failed to authorize.', array('status' => 500));
		}
		$response = wp_remote_post('https://oauth2.googleapis.com/token', array(
			'body' => array(
				'code' => $code,
				'client_id' => $latest_row['client_id'],
				'client_secret' => $latest_row['client_secret'],
				'redirect_uri' => 'http://localhost/wordpress/wp-json/wpmudev/v1/auth/confirm',
				'grant_type' => 'authorization_code',
			),
		));

		if (is_wp_error($response)) {
			return new WP_Error('token_request_failed', 'Failed to retrieve access token.', array('status' => 500));
		}

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if (isset($data['error'])) {
			return new WP_Error('token_error', 'Error retrieving access token: ' . $data['error'], array('status' => 500));
		}

		$access_token = $data['access_token'];

		$response = wp_remote_get('https://www.googleapis.com/oauth2/v1/userinfo?access_token=' . $access_token);

		if (is_wp_error($response)) {
			return new WP_Error('user_info_failed', 'Failed to retrieve user information.', array('status' => 500));
		}

		$body = wp_remote_retrieve_body($response);
		$user_info = json_decode($body, true);

		if (empty($user_info['email'])) {
			return new WP_Error('missing_email', 'Failed to retrieve email from Google.', array('status' => 400));
		}

		$email = sanitize_email($user_info['email']);

		$user = get_user_by('email', $email);

		if ($user) {
			wp_set_current_user($user->ID);
			wp_set_auth_cookie($user->ID);
			wp_redirect(admin_url());
			exit;
		} else {
			$random_password = wp_generate_password();
			$user_id = wp_create_user($email, $random_password, $email);

			if (is_wp_error($user_id)) {
				return new WP_Error('user_creation_failed', 'Failed to create new user.', array('status' => 500));
			}

			wp_set_current_user($user_id);
			wp_set_auth_cookie($user_id);

			wp_redirect(admin_url());
			exit;
		}
	}

	function wpmudev_google_auth_url() {
		$latest_row = wpmudev_get_latest_auth_data();
		if (is_null($latest_row)) {
			return new WP_Error('user_creation_failed', 'Failed to auth.', array('status' => 500)); 
		}
		$client_id = $latest_row['client_id'];
		$redirect_uri = urlencode('http://localhost/wordpress/wp-json/wpmudev/v1/auth/confirm');
		$scope = urlencode('https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email');
		$auth_url = "https://accounts.google.com/o/oauth2/auth?response_type=code&client_id=$client_id&redirect_uri=$redirect_uri&scope=$scope";
	
		return $auth_url;
	}
	
	function wpmudev_login_or_message_shortcode() {
		if (is_user_logged_in()) {
			$current_user = wp_get_current_user();
			return 'Hello, ' . esc_html($current_user->display_name) . '! Welcome back.';
		} else {
			$auth_url = wpmudev_google_auth_url();
			return '<a href="' . esc_url($auth_url) . '">Log in with Google</a>';
		}
	}

	function wpmudev_posts_maintenance_menu() {
		add_menu_page(
			'Posts Maintenance',
			'Posts Maintenance', 
			'manage_options',   
			'wpmudev-posts-maintenance', 
			array($this, 'wpmudev_posts_maintenance_page') 
		);
	}

	function wpmudev_posts_maintenance_page() {
		?>
		<div class="wrap">
			<h1>Posts Maintenance</h1>
			<button id="wpmudev-scan-posts-button" class="button button-primary">Scan Posts</button>
			<div id="wpmudev-scan-result"></div>
		</div>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				$('#wpmudev-scan-posts-button').on('click', function() {
					var $button = $(this);
					var $result = $('#wpmudev-scan-result');
					$button.prop('disabled', true);
					$result.html('Scanning...');
	
					function scanPosts(offset) {
						$.post(ajaxurl, {
							action: 'wpmudev_scan_posts',
							offset: offset
						}, function(response) {
							if (response.success && response.data.has_more) {
								scanPosts(response.data.offset);
							} else {
								$button.prop('disabled', false);
								$result.html('Scan complete.');
							}
						});
					}
	
					scanPosts(0);
				});
			});
		</script>
		<?php
	}

	public function wpmudev_scan_posts() {
		$post_types = array('post', 'page');
		$posts_per_page = 10;
		$offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
	
		$args = array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => $posts_per_page,
			'offset'         => $offset,
			'fields'         => 'ids',
		);
	
		$query = new WP_Query($args);
		$post_ids = $query->posts;
	
		foreach ($post_ids as $post_id) {
			update_post_meta($post_id, 'wpmudev_test_last_scan', current_time('mysql'));
		}
	
		$has_more = $query->found_posts > $offset + $posts_per_page;
		wp_send_json_success(array('has_more' => $has_more, 'offset' => $offset + $posts_per_page));
	}

	function wpmudev_schedule_daily_scan() {
		if (!wp_next_scheduled('wpmudev_daily_scan')) {
			wp_schedule_event(time(), 'daily', 'wpmudev_daily_scan');
		}
	}

	function wpmudev_run_daily_scan() {
		$offset = 0;
		$post_types = array('post', 'page');
		$posts_per_page = 10;
	
		do {
			$args = array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => $posts_per_page,
				'offset'         => $offset,
				'fields'         => 'ids',
			);
	
			$query = new WP_Query($args);
			$post_ids = $query->posts;
	
			foreach ($post_ids as $post_id) {
				update_post_meta($post_id, 'wpmudev_test_last_scan', current_time('mysql'));
			}
	
			$offset += $posts_per_page;
		} while ($query->found_posts > $offset);

		WP_CLI::success('Posts scan completed successfully.');
	}
}
