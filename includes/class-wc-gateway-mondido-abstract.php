<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

abstract class WC_Gateway_Mondido_Abstract extends WC_Payment_Gateway {
	/**
	 * Get Tax Classes
	 * @todo Use WC_Tax::get_tax_classes()
	 * @return array
	 */
	public static function getTaxClasses() {
		// Get tax classes
		$tax_classes           = array_filter( array_map( 'trim', explode( "\n", get_option( 'woocommerce_tax_classes' ) ) ) );
		$tax_class_options     = array();
		$tax_class_options[''] = __( 'Standard', 'woocommerce' );
		foreach ( $tax_classes as $class ) {
			$tax_class_options[ sanitize_title( $class ) ] = $class;
		}

		return $tax_class_options;
	}

	/**
	 * Get Plugin Version
	 * @return string
	 */
	public static function getPluginVersion() {
		$plugin_version = get_file_data(
			dirname( __FILE__ ) . '/../woocommerce-gateway-mondido.php',
			array( 'Version' ),
			'woocommerce-gateway-mondido'
		);

		return isset( $plugin_version[0] ) ? $plugin_version[0] : '';
	}

	/**
	 * Check is WooCommerce >= 3.0
	 * @return mixed
	 */
	public function is_wc3() {
		return version_compare( WC()->version, '3.0', '>=' );
	}

	/**
	 * Add Fee to Order
	 * @param stdClass $fee
	 * @param WC_Order $order
	 *
	 * @return int
	 */
	public function add_order_fee($fee, &$order) {
		if ($this->is_wc3()) {
			$item = new WC_Order_Item_Fee();
			$item->set_props( array(
				'name'      => $fee->name,
				'tax_class' => $fee->taxable ? $fee->tax_class : 0,
				'total'     => $fee->amount,
				'total_tax' => $fee->tax,
				'taxes'     => array(
					'total' => $fee->tax_data,
				),
				'order_id'  => $order->get_id(),
			) );
			$item->save();

			$order->add_item( $item );
			return $item->get_id();
		}

		return $order->add_fee( $fee );
	}

	/**
	 * Lookup transaction data
	 *
	 * @param $transaction_id
	 *
	 * @return array|bool
	 */
	public function lookupTransaction( $transaction_id ) {
		$result = wp_remote_get( 'https://api.mondido.com/v1/transactions/' . $transaction_id, array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( "{$this->merchant_id}:{$this->password}" )
			)
		) );

		if ( is_a( $result, 'WP_Error' ) ) {
			wc_add_notice( implode( $result->errors['http_request_failed'] ), 'error' );

			return FALSE;
		}

		if ( $result['response']['code'] != 200 ) {
			wc_add_notice( $result['body'], 'error' );

			return FALSE;
		}

		return json_decode( $result['body'], TRUE );
	}

	/**
	 * Capture Transaction
	 *
	 * @param $transaction_id
	 * @param $amount
	 *
	 * @return array|\WP_Error
	 */
	public function captureTransaction( $transaction_id, $amount ) {
		$result = wp_remote_get( 'https://api.mondido.com/v1/transactions/' . $transaction_id . '/capture', array(
			'method'  => 'PUT',
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( "{$this->merchant_id}:{$this->password}" )
			),
			'body'    => array(
				'amount' => number_format( $amount, 2, '.', '' )
			)
		) );

		return $result;
	}

	/**
	 * Get Subscription Plans
	 * @return array|bool|mixed|object
	 */
	public function getSubscriptionPlans() {
		$result = wp_remote_get( 'https://api.mondido.com/v1/plans', array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( "{$this->merchant_id}:{$this->password}" )
			)
		) );

		if ( is_a( $result, 'WP_Error' ) ) {
			wc_add_notice( implode( $result->errors['http_request_failed'] ), 'error' );

			return FALSE;
		}

		if ( $result['response']['code'] != 200 ) {
			wc_add_notice( $result['body'], 'error' );

			return FALSE;
		}

		return json_decode( $result['body'], TRUE );
	}

	/**
	 * Update Order with Incoming Products
	 *
	 * @param WC_Order $order
	 * @param array    $transaction
	 *
	 * @return void
	 */
	public function updateOrderWithIncomingProducts( $order, array $transaction ) {
		if ($this->is_wc3()) {
			// Get IDs of Products
			$ids = array();
			$items = $order->get_items( 'line_item' );
			foreach ($items as $item) {
				/** @var WC_Order_Item_Product $item */
				$ids[] = $item->get_product_id();
			}

			// Get Fee names
			$fee_names = array();
			$items = $order->get_fees();
			foreach ($items as $item) {
				/** @var WC_Order_Item_Fee $item */
				$fee_names[] = $item->get_name();
			}

		} else {
			// Get IDs of Products
			$items = $order->get_items( 'line_item' );
			$ids   = array_column( $items, 'product_id' );

			// Get Fee names
			$fees      = $order->get_fees();
			$fee_names = array_column( $fees, 'name' );
		}

		$incoming_items = $transaction['items'];
		foreach ( $incoming_items as $incoming_item ) {
			// Skip reserved SKUs
			if ( in_array( strtolower( $incoming_item['artno'] ), array(
				'shipping',
				'discount'
			) ) ) {
				continue;
			}

			// Extract Product Id
			if ( preg_match( '#^product_id#', $incoming_item['artno'] ) ) {
				$product_id = str_replace( 'product_id', '', $incoming_item['artno'] );
			} else {
				$product_id = wc_get_product_id_by_sku( $incoming_item['artno'] );
			}

			// Skip products which present in order
			if ( $product_id && in_array( $product_id, $ids ) ) {
				continue;
			}

			// Skip product if fee already applied
			if ( in_array(
				strtolower( $incoming_item['artno'] ),
				array_map( 'mb_strtolower', $fee_names ) )
			) {
				continue;
			}

			// There are can be products by Mondido like Invoice fee, discounts etc
			// Apple fee
			$amount = (float) $incoming_item['amount'];
			$tax    = (float) $incoming_item['vat'];

			// Calculate taxes
			$taxable   = $this->tax_status === 'taxable';
			$tax_class = $this->tax_class;
			if ( $taxable ) {
				// Mondido prices include tax
				if ( get_option( 'woocommerce_prices_include_tax' ) === 'no' ) {
					$amount -= $tax;
				}
			}

			$fee            = new stdClass();
			$fee->name      = $incoming_item['description'];
			$fee->amount    = $amount;
			$fee->taxable   = $taxable;
			$fee->tax_class = $tax_class;
			$fee->tax       = $tax;
			$fee->tax_data  = array();
			$this->add_order_fee($fee, $order);
		}

		// Calculate totals
		$order->calculate_totals();

		// Force to set total
		$order->set_total( $transaction['amount'] );
	}

	/**
	 * Can the order be refunded
	 *
	 * @param  WC_Order $order
	 *
	 * @return bool
	 */
	public function can_refund_order( $order ) {
		return $order && $order->get_transaction_id();
	}

	/**
	 * Process Refund
	 *
	 * If the gateway declares 'refunds' support, this will allow it to refund
	 * a passed in amount.
	 *
	 * @param  int    $order_id
	 * @param  float  $amount
	 * @param  string $reason
	 *
	 * @return  bool|wp_error True or false based on success, or a WP_Error object
	 */
	public function process_refund( $order_id, $amount = NULL, $reason = '' ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return FALSE;
		}

		$transaction_id = $order->get_transaction_id();

		$result = wp_remote_get( 'https://api.mondido.com/v1/refunds', array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( "{$this->merchant_id}:{$this->password}" )
			),
			'body'    => array(
				'transaction_id' => $transaction_id,
				'amount'         => number_format( $order->get_total(), 2, '.', '' ),
				'reason'         => $reason
			)
		) );

		if ( is_a( $result, 'WP_Error' ) ) {
			return $result;
		}

		$transaction = json_decode( $result['body'], TRUE );
		if ( ! isset( $transaction['id'] ) && isset( $transaction['description'] ) ) {
			return new WP_Error( 'refund', sprintf( __( 'Error: %s', 'woocommerce-gateway-mondido' ), $transaction['description'] ) );
		}

		$order->add_order_note( sprintf( __( 'Refunded: %s. Transaction ID: %s. Reason: %s', 'woocommerce-gateway-mondido' ), wc_price( $amount ), $transaction['id'], $reason ) );

		return TRUE;
	}
}
