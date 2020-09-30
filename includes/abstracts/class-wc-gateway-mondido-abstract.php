<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

abstract class WC_Gateway_Mondido_Abstract extends WC_Payment_Gateway {
	protected $transaction;

	public function add_dependencies(WC_Mondido_Transaction $transaction) {
		$this->transaction = $transaction;
	}

	/**
	 * Debug Log
	 *
	 * @param $message
	 * @param $level
	 *
	 * @return void
	 */
	public function log( $message, $level  = WC_Log_Levels::NOTICE ) {
		// Get Logger instance
		$log = new WC_Logger();

		// Write message to log
		if ( ! is_string( $message ) ) {
			$message = var_export( $message, true );
		}

		$log->log( $level, $message, array( 'source' => $this->id, '_legacy' => true ) );
	}

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
	public function add_order_fee($fee, &$order, $qty = 1) {
		if ($qty > 1) {
			$fee->amount = $fee->amount / $qty;
		}
		for ($count = 0; $count < $qty; $count++) {
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
			} else {
				$order->add_fee( $fee );
			}
		}
	}

	public function lookupTransaction( $transaction_id ) {
		$transaction = $this->transaction->get($transaction_id);
		if (is_wp_error($transaction)) {
			wc_add_notice( $transaction->get_error_message(), 'error' );
			return false;
		}
		return json_decode(json_encode($transaction), true);
	}

	public function lookupTransactionByOrderId( $order_id ) {
		$transaction = $this->transaction->get_by_reference($order_id);
		if (is_wp_error($transaction)) {
			wc_add_notice( $transaction->get_error_message(), 'error' );
			return false;
		}
		return json_decode(json_encode($transaction), true);
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
		$result = $this->transaction->capture($transaction_id, $amount);
		if (is_wp_error($result)) {
			return $result;
		}

		return json_decode(json_encode($result), true);
	}

	/**
	 * Get Subscription Plans
	 * @return array|mixed|object
	 * @throws \Exception
	 */
	public function getSubscriptionPlans() {
		$result = wp_remote_get( 'https://api.mondido.com/v1/plans', array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( "{$this->merchant_id}:{$this->password}" )
			)
		) );

		if ( is_a( $result, 'WP_Error' ) ) {
			throw new Exception( implode( $result->errors['http_request_failed'] ) );
		}

		if ( $result['response']['code'] != 200 ) {
			throw new Exception( $result['body'] );
		}

		return json_decode( $result['body'], TRUE );
	}

	/**
	 * Get Subscription Plan
	 * @param $plan_id
	 *
	 * @return array|mixed|object
	 * @throws Exception
	 */
	public function getSubscriptionPlan( $plan_id ) {
		$result = wp_remote_get( 'https://api.mondido.com/v1/plans/' . $plan_id, array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( "{$this->merchant_id}:{$this->password}" )
			)
		) );

		if ( is_a( $result, 'WP_Error' ) ) {
			throw new Exception( implode( $result->errors['http_request_failed'] ) );
		}

		if ( $result['response']['code'] != 200 ) {
			throw new Exception( $result['body'] );
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

			if ($product_id) {
				$product = wc_get_product($product_id);

				$amount = (float) $incoming_item['amount'];
				$vat = \WC_Tax::calc_tax($amount, [['rate' => (float) $incoming_item['vat'], 'label' => 'Tax', 'shipping' => 'yes', 'compound' => 'no']], true);
				$vat = $vat[0];

				if ($product) {
					$order->add_product($product, $incoming_item['qty'], [
						'subtotal' => $amount - $vat,
						'subtotal_tax' => $vat,
						'total' => $amount - $vat,
						'total_tax' => $vat,
					]);
				} else {
					$order->add_product(null, $incoming_item['qty'], [
						'name'         => $incoming_item['description'],
						'tax_class'    => $this->tax_class,
						'product_id'   => $product_id,
						'subtotal' => $amount - $vat,
						'total' => $amount - $vat,
						'total_tax' => $vat,
					]);
				}
				continue;
			}

			// Skip product if fee already applied
			if ( in_array(
				strtolower( $incoming_item['description'] ),
				array_map( 'mb_strtolower', $fee_names ) )
			) {
				continue;
			}

			// There are can be products by Mondido like Invoice fee, discounts etc
			// Apple fee
			$amount = (float) $incoming_item['amount'];
			//$tax    = (float) $incoming_item['vat'];
			$tax = 0;

			// Calculate taxes
			$taxable   = $this->tax_status === 'taxable';
			$tax_class = $this->tax_class;
			if ( $taxable ) {
				// Mondido prices include tax
				$tax = $this->get_tax_total( $amount, $tax_class, true );
				$amount -= $tax;
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
			'timeout' => 40,
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( "{$this->merchant_id}:{$this->password}" )
			),
			'body'    => array(
				'transaction_id' => $transaction_id,
				'amount'         => number_format( $amount, 2, '.', '' ),
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

	/**
	 * @param WC_Order $order
	 * @param array $transaction_data
	 *
	 * @throws \Exception
	 * @return void
	 */
	public function handle_transaction( $order, $transaction_data ) {
		$order_id = $order->get_id();
		$transaction_id = $transaction_data['id'];
		$status = $transaction_data['status'];

		// Clean order data cache
		clean_post_cache( $order_id );

		// Check transaction was processed
		$current_transaction_id = $order->get_transaction_id();
		$current_status = get_post_meta( $order_id, '_mondido_transaction_status', true );
		if ( $current_transaction_id === $transaction_id && $current_status === $status ) {
			throw new \Exception( "Transaction already applied. Order ID: {$order_id}. Transaction ID: {$transaction_id}. Transaction status: {$status}" );
		}

		// Save Transaction
		delete_post_meta( $order_id, '_transaction_id' );
		update_post_meta( $order_id, '_transaction_id', $transaction_id );

		delete_post_meta( $order_id, '_mondido_transaction_status' );
		update_post_meta( $order_id, '_mondido_transaction_status', $status );

		delete_post_meta( $order_id, '_mondido_transaction_data' );
		update_post_meta( $order_id, '_mondido_transaction_data', $transaction_data );

		switch ( $status ) {
			case 'pending':
				$this->updateOrderWithIncomingProducts( $order, $transaction_data );
				$order->update_status( 'on-hold', sprintf( __( 'Payment pending. Transaction Id: %s', 'woocommerce-gateway-mondido' ), $transaction_id ) );
				WC()->cart->empty_cart();
				break;
			case 'approved':
				$this->updateOrderWithIncomingProducts( $order, $transaction_data );
				$order->add_order_note( sprintf( __( 'Payment completed. Transaction Id: %s', 'woocommerce-gateway-mondido' ), $transaction_id ) );
				$order->payment_complete( $transaction_id );
				WC()->cart->empty_cart();
				break;
			case 'authorized':
				$this->updateOrderWithIncomingProducts( $order, $transaction_data );
				$order->update_status( 'on-hold', sprintf( __( 'Payment authorized. Transaction Id: %s', 'woocommerce-gateway-mondido' ), $transaction_id ) );
				WC()->cart->empty_cart();
				break;
			case 'declined':
				$order->update_status( 'failed', __( 'Payment declined.', 'woocommerce-gateway-mondido' ) );
				break;
			case 'failed':
				$order->update_status( 'failed', __( 'Payment failed.', 'woocommerce-gateway-mondido' ) );
				break;
		}

        // Extract address
        $address = array();
        if ( isset( $transaction_data['payment_details'] ) && ! empty( $transaction_data['payment_details']['country_code'] ) ) {
            $details = $transaction_data['payment_details'];
            $address = array(
                'first_name' => $details['first_name'],
                'last_name'  => $details['last_name'],
                'company'    => '',
                'email'      => $details['email'],
                'phone'      => $details['phone'],
                'address_1'  => $details['address_1'],
                'address_2'  => $details['address_2'],
                'city'       => $details['city'],
                'state'      => '',
                'postcode'   => $details['zip'],
                'country'    => $this->get_country_alpha2( $details['country_code'] ),
            );
            update_post_meta( $order_id, '_mondido_invoice_address', $address );
        }

        // Define address for Mondido Checkout
        if ( (bool) get_post_meta( $order_id, '_mondido_checkout', TRUE ) ) {
            $order->set_address( $address, 'billing' );

            if ( $order->needs_shipping_address() ) {
                $order->set_address( $address, 'shipping' );
            }
        }

		switch ( $transaction_data['transaction_type'] ) {
			case 'invoice':
				// Save invoice address
				// Format address
				$formatted = '';
				$fields    = WC()->countries->get_default_address_fields();
				foreach ( $address as $key => $value ) {
					if ( ! isset( $fields[ $key ] ) || empty( $value ) ) {
						continue;
					}
					$formatted .= $fields[ $key ]['label'] . ': ' . $value . "\n";
				}

				$order->add_order_note( sprintf( __( 'Invoice Address: %s', 'woocommerce-gateway-mondido' ), "\n" . $formatted ) );

				// Override shipping address
				$order->set_address( $address, 'shipping' );
				break;
			default:
				//
		}
	}

	/**
	 * Get Tax Total
	 * @param      $cost
	 * @param      $tax_class
	 * @param bool $price_incl_tax
	 *
	 * @return float|int
	 */
	public function get_tax_total( $cost, $tax_class, $price_incl_tax = false ) {
		$_tax  = new WC_Tax();

		// Get tax rates
		$tax_rates    = $_tax->get_rates( $tax_class );
		$add_on_taxes = $_tax->calc_tax( $cost, $tax_rates, $price_incl_tax );

		return array_sum( $add_on_taxes );
	}

	/**
	 * Check is Order locked
	 * @param $order_id
	 *
	 * @return bool
	 */
	public function is_order_locked( $order_id ) {
		return (bool) get_transient( 'mondido_order_lock_' . $order_id );
	}

	/**
	 * Lock Order
	 * @param $order_id
	 *
	 * @return void
	 */
	public function lock_order( $order_id ) {
		if ( ! $this->is_order_locked($order_id) ) {
			set_transient( 'mondido_order_lock_' . $order_id, true, 5 * MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Unlock Order
	 * @param $order_id
	 *
	 * @return void
	 */
	public function unlock_order( $order_id ) {
		delete_transient( 'mondido_order_lock_' . $order_id );
	}

	/**
	 * Get Customer Reference
	 * @param WC_Order $order
	 *
	 * @return string
	 * @throws Exception
	 */
	public function getCustomerReference( $order ) {
		global $wpdb;

		$user_id = (int) $order->get_user_id();
		$email   = $order->get_billing_email();

		$customer_reference = $wpdb->get_var( $wpdb->prepare(
			"SELECT customer_reference FROM {$wpdb->prefix}mondido_customers WHERE user_id = %s AND email = %s;",
			$user_id,
			$email
		) );

		if ( ! $customer_reference ) {
			$customer_reference = substr( str_shuffle( implode('', array_merge( range('a', 'z' ), range( '0', '9') ) ) ), 0, 6 );

			$attempts = 0;
			do {
				$result = $wpdb->insert($wpdb->prefix . 'mondido_customers', array(
					'customer_reference' => $customer_reference,
					'user_id'            => $user_id,
					'email'              => $email
				));
				$attempts++;
				if ($attempts >= 5) {
					throw new Exception('Failed to create customer reference');
				}
			} while ( ! $result );
		}

		return $customer_reference;
	}

	/**
	 * Get Customer References
	 * @param $user_id
	 *
	 * @return array
	 */
	public function getCustomerReferences( $user_id ) {
		global $wpdb;

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}mondido_customers WHERE user_id = %s;",
			$user_id
		), ARRAY_A );

		if ( is_array( $results ) ) {
			return $results;
		}

		return array();
	}

	/**
	 * Get Mondido Customer ID by Reference
	 * @param $customer_reference
	 *
	 * @return bool
	 */
	public function getMondidoCustomerId( $customer_reference ) {
		$result = wp_remote_get( 'https://api.mondido.com/v1/customers?filter[ref]=' . $customer_reference, array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( "{$this->merchant_id}:{$this->password}" )
			)
		) );

		$result = json_decode( $result['body'], TRUE );
		if (isset($result[0])) {
			return $result[0]['id'];
		}

		return false;
	}

	/**
	 * Get Mondido Subscriptions by Customer Id
	 * @param $customer_id
	 *
	 * @return array|mixed|object
	 * @throws Exception
	 */
	public function getMondidoSubscriptions( $customer_id ) {
		$result = wp_remote_get( 'https://api.mondido.com/v1/customers/' . $customer_id . '/subscriptions', array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( "{$this->merchant_id}:{$this->password}" )
			)
		) );

		if ( is_a( $result, 'WP_Error' ) ) {
			throw new Exception( 'Failed to get subscriptions' );
		}

		$subscriptions = json_decode( $result['body'], TRUE );
		if (isset($subscriptions['name']) && $subscriptions['name'] === 'errors.customer.not_found') {
			throw new Exception( 'Customer not found' );
		}

		return $subscriptions;
	}

	/**
	 * Cancel Mondido Subscription
	 * @param $subscription_id
	 *
	 * @return array
	 * @throws Exception
	 */
	public function cancelMondidoSubscription( $subscription_id ) {
		$result = wp_remote_request( 'https://api.mondido.com/v1/subscriptions/' . $subscription_id, array(
			'method'  => 'PUT',
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( "{$this->merchant_id}:{$this->password}" )
			),
			'body'    => array(
				'status' => 'cancelled'
			)
		) );

		if ( is_a( $result, 'WP_Error' ) ) {
			throw new Exception( 'Failed to cancel subscription' );
		}

		return json_decode( $result['body'], TRUE );
	}

	public function get_payment_method_name($value, $order, $default_value)
	{
		$transaction = get_post_meta( $order->get_id(), '_mondido_transaction_data', TRUE );

		if (!$transaction) {
			if ($order->get_transaction_id()) {
				$transaction = $this->lookupTransaction($order->get_transaction_id());
			} else {
				$transaction = $this->lookupTransactionByOrderId($order->get_id());
			}
		}

		if (is_array($transaction) && !empty($transaction['transaction_type'])) {
			switch ($transaction['transaction_type']) {
				case 'after_pay': return __('After Pay', 'woocommerce-gateway-mondido');
				case 'amex': return __('American Express', 'woocommerce-gateway-mondido');
				case 'bank': return __('Bank', 'woocommerce-gateway-mondido');
				case 'credit_card': return __('Card', 'woocommerce-gateway-mondido');
				case 'diners': return __('Diners', 'woocommerce-gateway-mondido');
				case 'discover': return __('Discover', 'woocommerce-gateway-mondido');
				case 'e_payment': return __('E-payment', 'woocommerce-gateway-mondido');
				case 'e_payments': return __('E-Payments', 'woocommerce-gateway-mondido');
				case 'invoice': return __('Invoice', 'woocommerce-gateway-mondido');
				case 'jcb': return __('JCB', 'woocommerce-gateway-mondido');
				case 'manual_invoice': return __('Manual Invoice', 'woocommerce-gateway-mondido');
				case 'mastercard': return __('Mastercard', 'woocommerce-gateway-mondido');
				case 'mobile_pay': return __('Mobile Pay', 'woocommerce-gateway-mondido');
				case 'payment': return __('Payment', 'woocommerce-gateway-mondido');
				case 'paypal': return __('PayPal', 'woocommerce-gateway-mondido');
				case 'recurring': return __('Recurring', 'woocommerce-gateway-mondido');
				case 'siirto': return __('Siirto', 'woocommerce-gateway-mondido');
				case 'stored_card': return __('Stored card', 'woocommerce-gateway-mondido');
				case 'swish': return __('Swish', 'woocommerce-gateway-mondido');
				case 'vipps': return __('Vipps', 'woocommerce-gateway-mondido');
				case 'visa': return __('Visa', 'woocommerce-gateway-mondido');
			}
		}

		return $default_value;
	}

	private function get_country_alpha2($code)
	{
		$map = new League\ISO3166\ISO3166();
		try {
			$country = $map->alpha2($code);
			return $country['alpha2'];
		} catch (\Exception $error) {
			$country = $map->alpha3($code);
			return $country['alpha2'];
		}
	}
}
