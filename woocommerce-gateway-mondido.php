<?php
/*
 * Plugin Name: WooCommerce Mondido Payments Gateway
 * Plugin URI: https://www.mondido.com/
 * Description: Provides a Payment Gateway through Mondido for WooCommerce.
 * Author: Mondido
 * Author URI: https://www.mondido.com/
 * Version: 4.3.2
 * Text Domain: woocommerce-gateway-mondido
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 3.4.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Mondido_Payments {
	/**
	 * Constructor
	 */
	public function __construct() {
        // Includes
        $this->includes();

		// Install
		$this->install();

		register_activation_hook( __FILE__, array( $this, 'flush_rewrite_rules' ) );
		register_deactivation_hook( __FILE__, array( $this, 'flush_rewrite_rules' ) );

		// Actions
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array(
			$this,
			'plugin_action_links'
		) );
		add_action( 'plugins_loaded', array( $this, 'init' ), 0 );
		add_action( 'woocommerce_loaded', array(
			$this,
			'woocommerce_loaded'
		) );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_scripts' ) );

		// Add Marketing script
		add_action( 'wp_footer', __CLASS__ . '::marketing_script' );

		// WC_Order Compatibility for WC < 3.0
        add_action( 'woocommerce_init', __CLASS__ . '::add_compatibility' );
    }

    /**
     * Load Vendors
     */
    public function includes() {
        $vendorsDir = dirname( __FILE__ ) . '/vendors';

        if ( ! class_exists( '\\League\\ISO3166\\ISO3166', FALSE ) ) {
            require_once $vendorsDir . '/league-iso3166/vendor/autoload.php';
        }
    }

	/**
	 * Add relevant links to plugins page
	 *
	 * @param  array $links
	 *
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$plugin_links = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=mondido_hw' ) ) . '">' . __( 'Settings', 'woocommerce-gateway-mondido' ) . '</a>'
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Install
	 */
	public function install() {
		global $wpdb;

		if ( ! get_option( 'woocommerce_mondido_version' ) ) {
			$query = "
CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}mondido_customers` (
  `customer_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Customer ID',
  `customer_reference` varchar(255) DEFAULT NULL COMMENT 'Customer Reference',
  `user_id` int(11) DEFAULT NULL COMMENT 'WordPress User ID',
  `email` varchar(255) DEFAULT NULL COMMENT 'Customer EMail',
  PRIMARY KEY (`customer_id`),
  UNIQUE KEY `customer_reference` (`customer_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
			$wpdb->query( $query );

			add_option( 'woocommerce_mondido_version', '4.3.2' );
		}
    }

	/**
	 * Flush rewrite rules on plugin activation
	 */
	public function flush_rewrite_rules() {
		flush_rewrite_rules();
	}

	/**
	 * Init localisations and files
	 */
	public function init() {
		// Localization
		load_plugin_textdomain( 'woocommerce-gateway-mondido', FALSE, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		// Includes
		include_once( dirname( __FILE__ ) . '/includes/class-wc-mondido-admin-actions.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-mondido-subscriptions.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-mondido-subscriptions-account.php' );
	}

	/**
	 * WooCommerce Loaded: load classes
	 */
	public function woocommerce_loaded() {
		// Includes
		include_once( dirname( __FILE__ ) . '/includes/abstracts/class-wc-gateway-mondido-abstract.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-mondido-swish.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-mondido-bank.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-mondido-hw.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-mondido-paypal.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-mondido-invoice.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-payment-token-mondido.php' );
	}

	/**
	 * Add Scripts
	 */
	public function add_scripts() {
		wp_enqueue_style( 'wc-gateway-mondido', plugins_url( '/assets/css/style.css', __FILE__ ), array(), FALSE, 'all' );
	}

	/**
	 * Add Marketing Script
	 */
	public static function marketing_script() {
		?>
		<script type="text/javascript" src="https://cdn-02.mondido.com/www/js/os-shop-v1.js"></script>
		<?php
	}

    /**
     * WC_Order Compatibility for WC < 3.0
     */
	public static function add_compatibility() {
		if ( version_compare( WC()->version, '3.0', '<' ) ) {
			include_once( dirname( __FILE__ ) . '/includes/deprecated/class-wc-order-compatibility-mondido.php' );
		}
    }

	/**
	 * Register payment gateway
	 *
	 * @param string $class_name
	 */
	public static function register_gateway( $class_name ) {
		global $mondido_gateways;

		if ( ! $mondido_gateways ) {
			$mondido_gateways = array();
		}

		if ( ! isset( $mondido_gateways[ $class_name ] ) ) {
			// Initialize instance
			if ( $gateway = new $class_name ) {
				$mondido_gateways[] = $class_name;

				// Register gateway instance
				add_filter( 'woocommerce_payment_gateways', function ( $methods ) use ( $gateway ) {
					$methods[] = $gateway;

					return $methods;
				} );
			}
		}
	}
}

new WC_Mondido_Payments();
