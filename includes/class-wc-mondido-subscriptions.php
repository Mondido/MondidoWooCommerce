<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Mondido_Subscriptions {

	private $api;

	/**
	 * Constructor
	 */
	public function __construct($api) {
		$this->api = $api;

		// Mondido Subscriptions
		add_filter( 'woocommerce_product_data_tabs', [$this, 'add_product_tabs'] );
		add_action( 'woocommerce_product_data_panels', [$this, 'subscription_options_product_tab_content'] );
		add_action( 'woocommerce_process_product_meta', [$this, 'save_subscription_field'] );
		add_filter( 'woocommerce_cart_needs_payment', [$this, 'cart_needs_payment'], 10, 2 );
		add_filter( 'woocommerce_order_needs_payment', [$this, 'order_needs_payment'], 10, 3 );
	}

	/**
	 * Add Tab to Product editor
	 *
	 * @param array $tabs
	 *
	 * @return array
	 */
	public function add_product_tabs( $tabs ) {
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
	public function subscription_options_product_tab_content() {
		global $post;
		$plans   = $this->api->list_plans();
		$error = null;

		if (is_wp_error($plans)) {
			//$error = implode( $plans->errors['http_request_failed'] );
			$plans = [];
		}

		$options = array(__( 'No subscription', 'woocommerce-gateway-mondido' ));

		foreach ( $plans as $item ) {
			if ($item->status !== 'active') {
				continue;
			}
			$options[ $item->id ] = __( $item->name, 'woocommerce-gateway-mondido' );
		}

		foreach ( $plans as $item ) {
			if ($item->status === 'active') {
				continue;
			}
			$options[ $item->id ] = __( $item->name, 'woocommerce-gateway-mondido' ) . " ($item->status)";
		}

		wc_get_template(
			'admin/product-subscription.php',
			array(
				'error' => $error,
				'plan_id' => (string) get_post_meta( get_the_ID(), '_mondido_plan_id', TRUE ),
				'include' => (string) get_post_meta( get_the_ID(), '_mondido_plan_include', TRUE ),
				'options' => $options,
			),
			'',
			dirname( __FILE__ ) . '/../templates/'
		);
	}

	/**
	 * Save Handler
	 */
	public function save_subscription_field() {
		global $post_id;

		if ( empty( $post_id ) ) {
			return;
		}

		if ( isset( $_POST['_mondido_plan_id'] ) ) {
			update_post_meta( $post_id, '_mondido_plan_id', $_POST['_mondido_plan_id'] );
			update_post_meta( $post_id, '_mondido_plan_include', $_POST['_mondido_plan_include'] );
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
	public function cart_needs_payment( $needs_payment, $cart ) {
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
	public function order_needs_payment( $needs_payment, $order, $valid_order_statuses ) {
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
	 * Remove "Free" label
	 * @param string $price
	 * @param WC_Product $product
	 *
	 * @return string
	 */
	public function remove_free_price( $price, $product ) {
		$plan_id = get_post_meta( $product->get_id(), '_mondido_plan_id', TRUE );
		if ( (int) $plan_id > 0 ) {
			return '&nbsp;';
		}

		return $price;
	}
}
