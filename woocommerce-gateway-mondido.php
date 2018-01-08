<?php
/*
Plugin Name: WooCommerce Mondido Payments Gateway
Plugin URI: https://www.mondido.com/
Description: Provides a Payment Gateway through Mondido for WooCommerce.
Version: 4.1.1
Author: Mondido
Author URI: https://www.mondido.com
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Requires at least: 3.1
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

		// Actions
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array(
			$this,
			'plugin_action_links'
		) );
		add_action( 'plugins_loaded', array( $this, 'init' ), 0 );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_scripts' ) );
		add_filter( 'woocommerce_payment_gateways', array(
			$this,
			'register_gateway'
		) );

		add_action( 'add_meta_boxes', __CLASS__ . '::add_meta_boxes' );

		// Add scripts and styles for admin
		add_action( 'admin_enqueue_scripts', __CLASS__ . '::admin_enqueue_scripts' );

		// Add Capture
		add_action( 'wp_ajax_mondido_capture', array(
			$this,
			'ajax_mondido_capture'
		) );

		// Mondido Subscriptions
		add_filter( 'woocommerce_product_data_tabs', __CLASS__ . '::add_product_tabs' );
		add_action( 'woocommerce_product_data_panels', __CLASS__ . '::subscription_options_product_tab_content' );
		add_action( 'woocommerce_process_product_meta', __CLASS__ . '::save_subscription_field' );
		add_filter( 'woocommerce_cart_needs_payment', __CLASS__ . '::cart_needs_payment', 10, 2 );
		add_filter( 'woocommerce_order_needs_payment', __CLASS__ . '::order_needs_payment', 10, 3 );
		add_filter( 'woocommerce_mondido_form_fields', array(
			$this,
			'add_recurring_items'
		), 9, 3 );
		add_filter( 'woocommerce_free_price_html', __CLASS__ . '::remove_free_price', 10, 2 );

		// Add Marketing script
		add_action( 'wp_footer', __CLASS__ . '::marketing_script' );

		// WC_Order Compatibility for WC < 3.0
        add_action( 'woocommerce_init', __CLASS__ . '::add_compatibility' );

        // Mondido Checkout
        add_action( 'wp_ajax_mondido_place_order', array( $this, 'ajax_mondido_place_order' ) );
        add_action( 'wp_ajax_nopriv_mondido_place_order', array( $this, 'ajax_mondido_place_order' ) );

        add_action( 'wp_ajax_mondido_buy_product', array( $this, 'ajax_mondido_buy_product' ) );
        add_action( 'wp_ajax_nopriv_mondido_buy_product', array( $this, 'ajax_mondido_buy_product' ) );
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
	 * Init localisations and files
	 */
	public function init() {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}

		// Localization
		load_plugin_textdomain( 'woocommerce-gateway-mondido', FALSE, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		// Includes
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-mondido-abstract.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-mondido-hw.php' );
        include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-mondido-checkout.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-mondido-invoice.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-mondido-bank.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-mondido-swish.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-mondido-paypal.php' );
	}

	/**
	 * Register the gateways for use
	 */
	public function register_gateway( $methods ) {
		$methods[] = 'WC_Gateway_Mondido_HW';
        $methods[] = 'WC_Gateway_Mondido_Checkout';
		$methods[] = 'WC_Gateway_Mondido_Invoice';
		$methods[] = 'WC_Gateway_Mondido_Bank';
		$methods[] = 'WC_Gateway_Mondido_Swish';
		$methods[] = 'WC_Gateway_Mondido_PayPal';

		return $methods;
	}

	/**
	 * Add Scripts
	 */
	public function add_scripts() {
		wp_enqueue_style( 'wc-gateway-mondido', plugins_url( '/assets/css/style.css', __FILE__ ), array(), FALSE, 'all' );
        wp_enqueue_script( 'iframe-resizer', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/assets/js/iframe-resizer/iframeResizer.min.js', array(), NULL, FALSE );

        wp_register_script( 'wc-gateway-mondido-checkout', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/assets/js/checkout.js', array( 'jquery' ), NULL, TRUE );

        // Localize the script with new data
        $translation_array = array(
            'place_order_url' => add_query_arg( 'action', 'mondido_place_order', admin_url( 'admin-ajax.php' ) ),
            'buy_product_url' => add_query_arg( 'action', 'mondido_buy_product', admin_url( 'admin-ajax.php' ) )
        );
        wp_localize_script( 'wc-gateway-mondido-checkout', 'WC_Gateway_Mondido_Checkout', $translation_array );

        if ( is_product() ) {
            wp_enqueue_script( 'wc-gateway-mondido-product', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/assets/js/product.js', array( 'wc-gateway-mondido-checkout' ), NULL, TRUE );
        }

        // Enqueued script with localized data.
        wp_enqueue_script( 'wc-gateway-mondido-checkout' );
	}

	/**
	 * Add meta boxes in admin
	 * @return void
	 */
	public static function add_meta_boxes() {
		global $post_id;
		$order = wc_get_order( $post_id );
		if ( $order && strpos( $order->get_payment_method(), 'mondido' ) !== false ) {
			$transaction = get_post_meta( $order->get_id(), '_mondido_transaction_data', TRUE );
			if ( ! empty( $transaction ) ) {
				add_meta_box(
					'mondido_paymnt_actions',
					__( 'Mondido Payments', 'woocommerce-gateway-mondido' ),
					__CLASS__ . '::order_meta_box_payment_actions',
					'shop_order',
					'side',
					'default'
				);
			}
		}
	}

	/**
	 * MetaBox for Payment Actions
	 * @return void
	 */
	public static function order_meta_box_payment_actions() {
		global $post_id;
		$order       = wc_get_order( $post_id );
		$transaction = get_post_meta( $order->get_id(), '_mondido_transaction_data', TRUE );

		wc_get_template(
			'admin/payment-actions.php',
			array(
				'order'       => $order,
				'transaction' => $transaction,
			),
			'',
			dirname( __FILE__ ) . '/templates/'
		);
	}

	/**
	 * Enqueue Scripts in admin
	 *
	 * @param $hook
	 *
	 * @return void
	 */
	public static function admin_enqueue_scripts( $hook ) {
		if ( $hook === 'post.php' ) {
			// Scripts
			wp_register_script( 'mondido-admin-js', plugin_dir_url( __FILE__ ) . 'assets/js/admin.js' );

			// Localize the script
			$translation_array = array(
				'ajax_url'  => admin_url( 'admin-ajax.php' ),
				'text_wait' => __( 'Please wait...', 'woocommerce-gateway-mondido' ),
			);
			wp_localize_script( 'mondido-admin-js', 'Mondido_Admin', $translation_array );

			// Enqueued script with localized data
			wp_enqueue_script( 'mondido-admin-js' );
		}
	}

	/**
	 * Capture Payment
	 * @return void
	 */
	public function ajax_mondido_capture() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'mondido' ) ) {
			exit( 'No naughty business' );
		}

		$transaction_id = (int) $_REQUEST['transaction_id'];
		$order_id       = (int) $_REQUEST['order_id'];

		$order   = wc_get_order( $order_id );
		$gateway = new WC_Gateway_Mondido_HW();

		$result = $gateway->captureTransaction( $transaction_id, $order->get_total() );
		if ( is_a( $result, 'WP_Error' ) ) {
			$error = implode( $result->errors['http_request_failed'] );
			wp_send_json_error( sprintf( __( 'Error: %s', 'woocommerce-gateway-mondido' ), $error ) );

			return;
		}

		if ( $result['response']['code'] != 200 ) {
			wp_send_json_error( sprintf( __( 'Response: %s', 'woocommerce-gateway-mondido' ), $result['body'] ) );

			return;
		}

		$transaction = json_decode( $result['body'], TRUE );
		if ( ! isset( $transaction['id'] ) && isset( $transaction['description'] ) ) {
			wp_send_json_error( sprintf( __( 'Error: %s', 'woocommerce-gateway-mondido' ), $transaction['description'] ) );

			return;
		}

		if ( $transaction['status'] === 'approved' ) {
			// Save Transaction
			update_post_meta( $order->get_id(), '_transaction_id', $transaction['id'] );
			update_post_meta( $order->get_id(), '_mondido_transaction_status', $transaction['status'] );
			update_post_meta( $order->get_id(), '_mondido_transaction_data', $transaction );

			$order->add_order_note( sprintf( __( 'Payment captured. Transaction Id: %s', 'woocommerce-gateway-mondido' ), $transaction['id'] ) );
			$order->payment_complete( $transaction['id'] );

			wp_send_json_success( array(
				'transaction_id' => $transaction['id']
			) );
		}
	}

	/**
	 * Add Tab to Product editor
	 *
	 * @param array $tabs
	 *
	 * @return array
	 */
	public static function add_product_tabs( $tabs ) {
		$tabs['mondido_subscription'] = array(
			'label'  => __( 'Mondido Subscription', 'woocommerce-gateway-mondido' ),
			'target' => 'subscription_options',
			'class'  => array( 'show_if_simple' ),
		);

		return $tabs;
	}

	/**
	 * Tab Content
	 */
	public static function subscription_options_product_tab_content() {
		global $post;
		$plan_id = get_post_meta( get_the_ID(), '_mondido_plan_id', TRUE );
		$gateway = new WC_Gateway_Mondido_HW();
		?>
		<div id='subscription_options' class='panel woocommerce_options_panel'>
			<div class='options_group'>
				<?php
				try {
					$plans   = $gateway->getSubscriptionPlans();
					$options    = array();
					$options[0] = __( 'No subscription', 'woocommerce-gateway-mondido' );
					if ( $plans ) {
						foreach ( $plans as $item ) {
							$options[ $item['id'] ] = __( $item['name'], 'woocommerce-gateway-mondido' );
						}
					}

					woocommerce_wp_select(
						array(
							'id'      => '_mondido_plan_id',
							'value'   => (string) $plan_id,
							'label'   => __( 'Subscription plan', 'woocommerce-gateway-mondido' ),
							'options' => $options
						)
					);
				} catch (Exception $e) {
					?>
					<p class="form-field _mondido_plan_id_field ">
						<input type="hidden" name="_mondido_plan_id" value="<?php echo esc_attr( $plan_id ); ?>" />
						<span id="message" class="error">
					<?php echo sprintf( esc_html__( 'Mondido Error: %s', 'woocommerce-gateway-mondido' ), $e->getMessage() ); ?>
							<br />
							<?php _e( 'Please check Mondido settings.', 'woocommerce-gateway-mondido' ); ?>
				</span>
					</p>
					<?php
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Save Handler
	 */
	public static function save_subscription_field() {
		global $post_id;

		if ( empty( $post_id ) ) {
			return;
		}

		if ( isset( $_POST['_mondido_plan_id'] ) ) {
			update_post_meta( $post_id, '_mondido_plan_id', $_POST['_mondido_plan_id'] );
		}
	}

	/**
	 * Check is Cart need payment
	 *
	 * @param bool    $needs_payment
	 * @param WC_Cart $cart
	 *
	 * @return mixed
	 */
	public static function cart_needs_payment( $needs_payment, $cart ) {
		if ( $needs_payment === FALSE ) {
			$products = $cart->get_cart();
			foreach ( $products as $id => $product ) {
				$plan_id = get_post_meta( $product['product_id'], '_mondido_plan_id', TRUE );
				if ( (int) $plan_id > 0 ) {
					return TRUE;
				}
			}
		}

		return $needs_payment;
	}

	/**
	 * Check is Order need payment
	 *
	 * @param bool     $needs_payment
	 * @param WC_Order $order
	 * @param array    $valid_order_statuses
	 *
	 * @return bool
	 */
	public static function order_needs_payment( $needs_payment, $order, $valid_order_statuses ) {
		if ( $needs_payment === FALSE ) {
			foreach ( $order->get_items( 'line_item' ) as $order_item ) {
				if ( version_compare( WC()->version, '3.0', '>=' ) ) {
					$plan_id = get_post_meta( $order_item->get_product_id(), '_mondido_plan_id', TRUE );
				} else {
					$plan_id = get_post_meta( $order_item['product_id'], '_mondido_plan_id', TRUE );
				}

				if ( (int) $plan_id > 0 ) {
					return TRUE;
				}
			}
		}

		return $needs_payment;
	}

	/**
	 * Add Recurring fields
	 *
	 * @param array              $fields
	 * @param WC_Order           $order
	 * @param WC_Payment_Gateway $gateway
	 *
	 * @return mixed
	 */
	public function add_recurring_items( $fields, $order, $gateway ) {
	    if ( ! $order ) {
            return $fields;
        }

		foreach ( $order->get_items( 'line_item' ) as $order_item ) {
			if ( version_compare( WC()->version, '3.0', '>=' ) ) {
				$plan_id = get_post_meta( $order_item->get_product_id(), '_mondido_plan_id', TRUE );
			} else {
				$plan_id = get_post_meta( $order_item['product_id'], '_mondido_plan_id', TRUE );
			}

			if ( (int) $plan_id > 0 ) {
				$fields['plan_id']               = $plan_id;
				$fields['subscription_quantity'] = $order_item['qty'];

				return $fields;
			}
		}

		return $fields;
	}

	/**
	 * Remove "Free" label
	 * @param string $price
	 * @param WC_Product $product
	 *
	 * @return string
	 */
	public static function remove_free_price( $price, $product ) {
		$plan_id = get_post_meta( $product->get_id(), '_mondido_plan_id', TRUE );
		if ( (int) $plan_id > 0 ) {
			return '&nbsp;';
		}

		return $price;
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
     * Place Order
     * @return void
     */
    public function ajax_mondido_place_order()
    {
        define( 'WOOCOMMERCE_CHECKOUT', TRUE );
        WC()->cart->get_cart_from_session();

        try {
            // @todo WC 2.6 support
            $data = array(
                'payment_method' => 'mondido_checkout',
                'customer_id'    => get_current_user_id(),
                'status'         => 'pending'
            );

            $order_id = WC()->checkout()->create_order( $data );
            if ( is_wp_error( $order_id ) ) {
                throw new Exception( $order_id->get_error_message() );
            }

            $order = wc_get_order( $order_id );
            do_action( 'woocommerce_checkout_order_processed', $order_id, $data, $order );
        } catch ( Exception $e ) {
            wp_send_json_error( $e->getMessage() );

            return;
        }

        // Mondido Checkout flag
        update_post_meta( $order_id, '_mondido_checkout', TRUE );

        wp_send_json_success( array(
            'order_id'     => $order_id,
            'redirect_url' => $order->get_checkout_payment_url( TRUE )
        ) );
    }

    public function ajax_mondido_buy_product()
    {
        define( 'WOOCOMMERCE_CART', TRUE );
        $product_id = stripslashes( $_POST['product_id'] );
        $qty = stripslashes( $_POST['qty'] );

        // @todo Variations

        WC()->cart->empty_cart( TRUE );
        WC()->cart->add_to_cart($product_id, $qty);
        WC()->cart->calculate_totals();

        wp_send_json_success();
    }
}

new WC_Mondido_Payments();

