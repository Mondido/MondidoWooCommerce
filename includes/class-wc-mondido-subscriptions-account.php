<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Mondido_Subscriptions_Account {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'add_endpoints' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'menu_items' ) );
		add_filter( 'the_title', array( $this, 'endpoint_title' ) );
		add_action( 'woocommerce_account_mondido-subscriptions_endpoint', array( $this, 'endpoint_content' ) );
		add_action( 'after_switch_theme', array( __CLASS__, 'flush_rewrite_rules' ) );



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
		$items['mondido-subscriptions'] = __( 'Mondido Subscriptions', 'woocommerce-gateway-mondido' );

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
		$user_id = get_current_user_id();

		$subscriptions = array();
		$references = $this->getMondidoGateway()->getCustomerReferences( $user_id );
		foreach ( $references as $reference ) {
			$customer_id = $this->getMondidoGateway()->getMondidoCustomerId( $reference['customer_reference'] );
			if ( ! $customer_id ) {
				continue;
			}

			$result = $this->getMondidoGateway()->getMondidoSubscriptions( $customer_id );
			$subscriptions = array_merge($subscriptions, $result);
		}

		wc_get_template(
			'myaccount/mondido-subscriptions.php',
			array(
				'user_id'   => $user_id,
				'subscriptions'  => $subscriptions,
			),
			'',
			dirname( __FILE__ ) . '/../templates/'
		);
	}

	/**
	 * Get Mondido Gateway Instance
	 * @return WC_Gateway_Mondido_HW
	 */
	public static function getMondidoGateway()
	{
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		return $payment_gateways['mondido_hw'];
	}

	/**
	 * Get Subscription Plan
	 * @param $plan_id
	 *
	 * @return array|bool|mixed|object
	 */
	public static function getSubscriptionPlan($plan_id)
	{
		try {
			$result = self::getMondidoGateway()->getSubscriptionPlan( $plan_id );
		} catch (Exception $e) {
			return false;
		}

		return $result;
	}

	/**
	 * Format Subscription Description
	 * @param array $subscription
	 *
	 * @return string
	 */
	public static function formatSubscriptionDescription( array $subscription )
	{
		$plan_id = $subscription['plan']['id'];
		$plan = self::getSubscriptionPlan( $plan_id );
		if ( ! $plan ) {
			return sprintf( __( 'Subscription #%s', 'woocommerce-gateway-mondido' ), $subscription['id'] );
		}

		$description = ( ! empty( $plan['description'] ) ? '(' . $plan['description'] . ')' : '');
		return sprintf( __( '%s %s (Subscription #%s)', 'woocommerce-gateway-mondido' ), $plan['name'], $description, $subscription['id'] );
	}

	/**
	 * Cancel Mondido Subscription
	 */
	public function cancel_subscription() {
		if ( ! check_ajax_referer( 'mondido_subscriptions', 'nonce', false ) ) {
			exit( 'No naughty business' );
		}

		$subscription_id = wc_clean( $_POST['id'] );
		$result = self::getMondidoGateway()->cancelMondidoSubscription( $subscription_id );
		if ($result['status'] === 'cancelled') {
			wp_send_json_success( __( 'Success', 'woocommerce-gateway-mondido' ) );
		} else {
			wp_send_json_error( __( 'Failed to cancel subscription', 'woocommerce-gateway-mondido' ) );
		}
	}
}

new WC_Mondido_Subscriptions_Account();
