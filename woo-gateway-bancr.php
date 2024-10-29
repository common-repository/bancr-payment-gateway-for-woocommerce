<?php
/*
 * Plugin Name: Bancr Payment Gateway for WooCommerce
 * Plugin URI: https://bancr.io/about-us/
 * Description: Process payments on your store using Bancr Gateway.
 * Author: Bancr
 * Author URI: https://bancr.io/
 * Version: 1.0.0
 * Requires at least: 4.4
 * Tested up to: 5.7
 * WC requires at least: 2.5
 * WC tested up to: 5.3
 * Text Domain: woo-gateway-bancr
 *
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if (!class_exists('WC_Bancr')) {

    class WC_Bancr {

        var $version = '1.0';
        var $php_version = '2.5.0';
        var $wc_version = '2.5.0';
        var $plugin_url;
        var $plugin_path;
        var $notices = array();

        function __construct() {
            $this->define_constants();
            $this->includes();
            $this->loader_operations();
            //Handle any db install and upgrade task
            add_action( 'admin_init', array( $this, 'check_environment' ) );
            add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );
            add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
        }
        /**
		 * Required minimums and constants
		 */
        function define_constants() {
            define('WC_BANCR_VERSION', $this->version);
            define( 'WC_BANCR_MIN_PHP_VER', $this->php_version );
            define( 'WC_BANCR_MIN_WC_VER', $this->wc_version );
            define('WC_BANCR_PLUGIN_URL', $this->plugin_url());
            define('WC_BANCR_PLUGIN_PATH', $this->plugin_path());
        }

        function includes() {
        	include_once( dirname( __FILE__ ) . '/includes/class-wc-utility-bancr.php' );
        }

        function loader_operations() {
            add_action('plugins_loaded', array(&$this, 'plugins_loaded_handler')); //plugins loaded hook		
        }

        function plugins_loaded_handler() {
            //Runs when plugins_loaded action gets fired
			include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-bancr.php' );
            add_filter('woocommerce_payment_gateways', array(&$this, 'init_bancr_gateway'));
        }
        

        function check_environment() {
            if (  WC_BANCR_VERSION !== get_option( 'wc_bancr_version' ) )  {
				$this->_update_plugin_version();
			}
			$environment_warning = $this->get_environment_warning();

			if ( $environment_warning && is_plugin_active( plugin_basename( __FILE__ ) ) ) {
				$this->add_admin_notice( 'bad_environment', 'error', $environment_warning );
			}
			$options = get_option( 'woocommerce_bancr_settings' );
			//$secret = $options['merchant_id'];
            if ((isset($options['merchant_id']) && empty( $options['merchant_id'])) && ! ( isset( $_GET['page'], $_GET['section'] ) && 'wc-settings' === $_GET['page'] && 'bancr' === $_GET['section'] ) ) {
				$setting_link = $this->get_setting_link();
				$this->add_admin_notice( 'prompt_connect', 'notice notice-warning', sprintf( __( 'Bancr is almost ready. To get started, <a href="%s">set your Bancr merchant ID</a>.', 'woo-gateway-bancr' ), $setting_link ) );
			}
        }

        private static function _update_plugin_version() {
			delete_option( 'wc_bancr_version' );
			update_option( 'wc_bancr_version', WC_BANCR_VERSION );
			return true;
		}

        function plugin_url() {
            if ($this->plugin_url)
                return $this->plugin_url;
            return $this->plugin_url = plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__));
        }

        function plugin_path() {
            if ($this->plugin_path)
                return $this->plugin_path;
            return $this->plugin_path = untrailingslashit(plugin_dir_path(__FILE__));
        }



        function plugin_action_links($links){
           $setting_link = $this->get_setting_link();

			$plugin_links = array(
				'<a href="' . $setting_link . '">' . __( 'Settings', 'woo-gateway-bancr' ) . '</a>',
				'<a href="https://bancr.io/">' . __( 'Docs', 'woo-gateway-bancr' ) . '</a>',
				'<a href="https://bancr.io/">' . __( 'Support', 'woo-gateway-bancr' ) . '</a>',
			);
			return array_merge( $plugin_links, $links );        
        }

        function get_setting_link() {
			$use_id_as_section = function_exists( 'WC' ) ? version_compare( WC()->version, '2.6', '>=' ) : false;

			$section_slug = $use_id_as_section ? 'bancr' : strtolower( 'WC_Gateway_Bancr' );

			return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $section_slug );
		}
        
        function init_bancr_gateway($methods) {        
			$methods[] = 'WC_Gateway_Bancr';			
			return $methods;
        }
        /**
		 * Allow this class and other classes to add slug keyed notices (to avoid duplication)
		 */
		function add_admin_notice( $slug, $class, $message ) {
			$this->notices[ $slug ] = array(
				'class'   => $class,
				'message' => $message,
			);
		}
		/**
		 * Display any notices we've collected thus far (e.g. for connection, disconnection)
		 */
	    function admin_notices() {
			foreach ( (array) $this->notices as $notice_key => $notice ) {
				echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
				echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
				echo '</p></div>';
			}
		}
        /**
		 * Checks the environment for compatibility problems.  Returns a string with the first incompatibility
		 * found or false if the environment has no problems.
		 */
		function get_environment_warning() {
			if ( version_compare( phpversion(), WC_BANCR_MIN_PHP_VER, '<' ) ) {
				$message = __( 'WooCommerce Bancr - The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'woo-gateway-bancr' );

				return sprintf( $message, WC_BANCR_MIN_PHP_VER, phpversion() );
			}

			if ( ! defined( 'WC_VERSION' ) ) {
				return __( 'WooCommerce Bancr requires WooCommerce to be activated to work.', 'woo-gateway-bancr' );
			}

			if ( version_compare( WC_VERSION, WC_BANCR_MIN_WC_VER, '<' ) ) {
				$message = __( 'WooCommerce Bancr - The minimum WooCommerce version required for this plugin is %1$s. You are running %2$s.', 'woo-gateway-bancr' );

				return sprintf( $message, WC_BANCR_MIN_WC_VER, WC_VERSION );
			}

			if ( ! function_exists( 'curl_init' ) ) {
				return __( 'WooCommerce Bancr - cURL is not installed.', 'woo-gateway-bancr' );
			}

			return false;
		}
		/**
		* load admin script for setting page
		*/
		public function admin_scripts() {
    		$screen    = get_current_screen();
    		$screen_id = $screen ? $screen->id : '';
    
    		if ( 'woocommerce_page_wc-settings' !== $screen_id ) {
    			return;
    		}
    
    		wp_enqueue_script( 'bancr_woocommerce_admin', $this->plugin_url() . '/assets/js/bancr-script.js', array(), WC_BANCR_VERSION, true );
    	}

    }

    //End of plugin class
}//End of class not exists check

$GLOBALS['WC_Bancr'] = new WC_Bancr();
