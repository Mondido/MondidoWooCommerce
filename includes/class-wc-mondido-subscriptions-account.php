<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Mondido_Subscriptions_Account {
    private $api;
    private $gateway;
	/**
	 * Constructor
	 */
	public function __construct($api, $gateway) {
        $this->api = $api;
        $this->gateway = $gateway;

		add_action( 'init', array( $this, 'add_endpoints' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'menu_items' ) );
		add_filter( 'the_title', array( $this, 'endpoint_title' ) );
		add_action( 'woocommerce_account_mondido-subscriptions_endpoint', array( $this, 'endpoint_content' ) );
		add_action( 'after_switch_theme', array( $this, 'flush_rewrite_rules' ) );

		add_action( 'wp_ajax_mondido_cancel_subscription', array( $this, 'cancel_subscription' ) );
	}

	/**
	 * Register new endpoint to use inside My Account page
	 */
	public function add_endpoints() {
		add_rewrite_endpoint( 'mondido-subscriptions', EP_ROOT | EP_PAGES );
	}

	/**
	 * Add new query var
	 *
	 * @param array $vars
	 *
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'mondido-subscriptions';

		return $vars;
	}

	/**
	 * Flush rewrite rules on plugin activation
	 */
	public function flush_rewrite_rules() {
		flush_rewrite_rules();
	}

	/**
	 * Insert the new endpoint into the My Account menu
	 *
	 * @param array $items
	 *
	 * @return array
	 */
	public function menu_items( $items ) {
		if (count($this->get_subscriptions()) > 0) {
			$items['mondido-subscriptions'] = __( 'Mondido Subscriptions', 'woocommerce-gateway-mondido' );
		}

		return $items;
	}

	/**
	 * Change endpoint title.
	 *
	 * @param $title
	 *
	 * @return string
	 */
	public function endpoint_title( $title ) {
		global $wp_query;

		$is_endpoint = isset( $wp_query->query_vars['mondido-subscriptions'] );

		if ( $is_endpoint && ! is_admin() && is_main_query() && in_the_loop() && is_account_page() ) {
			// New page title.
			$title = __( 'Mondido Subscriptions', 'woocommerce-gateway-mondido' );

			// unhook after we've returned our title to prevent it from overriding others
			remove_filter( 'the_title', array( $this, __FUNCTION__ ), 11 );
		}

		return $title;
	}

	/**
	 * Endpoint HTML content
	 */
	public function endpoint_content() {
		wc_get_template(
			'myaccount/mondido-subscriptions.php',
			array(
				'user_id'   => $user_id,
				'subscriptions'  => array_reverse($this->get_subscriptions()),
			),
			'',
			dirname( __FILE__ ) . '/../templates/'
		);
	}

	/**
	 * Cancel Mondido Subscription
	 */
	public function cancel_subscription() {
		if ( !is_user_logged_in() || ! check_ajax_referer( 'mondido_subscriptions', 'nonce', false ) ) {
			exit( 'No naughty business' );
		}

		$customer_subscriptions = $this->get_subscriptions();

		foreach ($customer_subscriptions as $subscription) {
			if ($subscription->id === (int) $_POST['id']) {
				$result = $this->api->cancel_subscription( $subscription->id );
				if (!is_wp_error($result) && $result->stauts === 'cancelled') {
					wp_send_json_success( __( 'Success', 'woocommerce-gateway-mondido' ) );
					return;
				} else {
					break;
				}
			}
		}

		wp_send_json_error( __( 'Failed to cancel subscription', 'woocommerce-gateway-mondido' ) );
	}

	private function get_subscriptions() {
		if (!is_user_logged_in()) {
			return [];
		}
		$user_id = get_current_user_id();

		$subscriptions = [];
		$references = $this->gateway->getCustomerReferences( $user_id );
		foreach ( $references as $reference ) {
			$customer_id = $this->gateway->getMondidoCustomerId( $reference['customer_reference'] );
			if ( ! $customer_id ) {
				continue;
			}

			$result = $this->api->list_customer_subscriptions( $customer_id );
			if (is_wp_error($result)) {
				return [];
			}
			$subscriptions = array_merge($subscriptions, $result);
		}

		return $subscriptions;
	}
}
