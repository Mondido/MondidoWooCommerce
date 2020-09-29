<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Mondido_Admin_Actions {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', __CLASS__ . '::add_meta_boxes' );

		// Add scripts and styles for admin
		add_action( 'admin_enqueue_scripts', __CLASS__ . '::admin_enqueue_scripts' );

		// Add Capture
		add_action( 'wp_ajax_mondido_capture', array(
			$this,
			'ajax_mondido_capture'
		) );
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
					'mondido_payment_actions',
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
			dirname( __FILE__ ) . '/../templates/'
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
			wp_register_script( 'mondido-admin-js', plugin_dir_url( __FILE__ ) . '../assets/js/admin.js' );

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

		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		$gateway = $gateways[$order->get_payment_method()];

		$transaction = $gateway->captureTransaction( $transaction_id, $order->get_total() );
		if ( is_wp_error( $transaction ) ) {
			$error = implode( $transaction->errors['http_request_failed'] );
			wp_send_json_error( sprintf( __( 'Error: %s', 'woocommerce-gateway-mondido' ), $error ) );

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
}

new WC_Mondido_Admin_Actions();
