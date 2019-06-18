<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Gateway_Mondido_HW extends WC_Gateway_Mondido_Abstract {
	/**
	 * Init
	 */
	public function __construct() {
		$this->id                 = 'mondido_hw';
		$this->has_fields         = true;
		$this->method_title       = __( 'Mondido', 'woocommerce-gateway-mondido' );
		$this->method_description = '';

		$this->icon     = apply_filters( 'woocommerce_mondido_hw_icon', plugins_url( '/assets/images/mondido.png', dirname( __FILE__ ) ) );
		$this->supports = array(
			'products',
			'refunds',
			'tokenization',
			// @todo Implement WC Subscription support
			//'subscriptions',
			//'subscription_cancellation',
			//'subscription_suspension',
			//'subscription_reactivation',
			//'subscription_amount_changes',
			//'subscription_date_changes',
			//'subscription_payment_method_change',
			//'subscription_payment_method_change_customer',
			//'subscription_payment_method_change_admin',
			//'multiple_subscriptions',
		);

		// URL to view a transaction
		$this->view_transaction_url = 'https://admin.mondido.com/transactions/%s';

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define variables
		$this->enabled           = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
		$this->title             = isset( $this->settings['title'] ) ? $this->settings['title'] : __( 'Mondido Payments', 'woocommerce-gateway-mondido' );
		$this->description       = isset( $this->settings['description'] ) ? $this->settings['description'] : '';
		$this->merchant_id       = isset( $this->settings['merchant_id'] ) ? $this->settings['merchant_id'] : '';
		$this->secret            = isset( $this->settings['secret'] ) ? $this->settings['secret'] : '';
		$this->password          = isset( $this->settings['password'] ) ? $this->settings['password'] : '';
		$this->testmode          = isset( $this->settings['testmode'] ) ? $this->settings['testmode'] : 'no';
		$this->authorize         = isset( $this->settings['authorize'] ) ? $this->settings['authorize'] : 'no';
		$this->tax_status        = isset( $this->settings['tax_status'] ) ? $this->settings['tax_status'] : 'none';
		$this->tax_class         = isset( $this->settings['tax_class'] ) ? $this->settings['tax_class'] : 'standard';
		$this->store_cards       = isset( $this->settings['store_cards'] ) ? $this->settings['store_cards'] : 'no';
		$this->logos             = isset( $this->settings['logos'] ) ? $this->settings['logos'] : array();
		$this->order_button_text = isset( $this->settings['order_button_text'] ) ? $this->settings['order_button_text'] : __( 'Pay with Mondido', 'woocommerce-gateway-mondido' );

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );

		add_action( 'woocommerce_thankyou_' . $this->id, array(
			$this,
			'thankyou_page'
		) );

		// Payment listener/API hook
		add_action( 'woocommerce_api_wc_gateway_' . $this->id, array(
			$this,
			'notification_callback'
		) );

		// Receipt hook
		add_action( 'woocommerce_receipt_' . $this->id, array(
			$this,
			'receipt_page'
		) );

		// Payment confirmation
		add_action( 'the_post', array( &$this, 'payment_confirm' ) );

		// Add form hash
		add_filter( 'woocommerce_mondido_form_fields', array(
			$this,
			'add_form_hash_value'
		), 10, 3 );
	}

	/**
	 * Initialise Settings Form Fields
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'           => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-gateway-mondido' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Mondido Payment Module', 'woocommerce-gateway-mondido' ),
				'default' => 'no'
			),
			'title'             => array(
				'title'       => __( 'Title', 'woocommerce-gateway-mondido' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-mondido' ),
				'default'     => __( 'Mondido Payments', 'woocommerce-gateway-mondido' )
			),
			'description'       => array(
				'title'       => __( 'Description', 'woocommerce-gateway-mondido' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-mondido' ),
				'default'     => '',
			),
			'merchant_id'       => array(
				'title'       => __( 'Merchant ID', 'woocommerce-gateway-mondido' ),
				'type'        => 'text',
				'description' => __( 'Merchant ID for Mondido', 'woocommerce-gateway-mondido' ),
				'default'     => ''
			),
			'secret'            => array(
				'title'       => __( 'Secret', 'woocommerce-gateway-mondido' ),
				'type'        => 'password',
				'description' => __( 'Given secret code from Mondido', 'woocommerce-gateway-mondido' ),
				'default'     => ''
			),
			'password'          => array(
				'title'       => __( 'API Password', 'woocommerce-gateway-mondido' ),
				'type'        => 'text',
				'description' => __( 'API Password from Mondido', 'woocommerce-gateway-mondido' ) . ' (<a href="https://admin.mondido.com/settings">https://admin.mondido.com/settings</a>)',
				'default'     => ''
			),
			'testmode'          => array(
				'title'   => __( 'Test Mode', 'woocommerce-gateway-mondido' ),
				'type'    => 'checkbox',
				'label'   => __( 'Set in testmode', 'woocommerce-gateway-mondido' ),
				'default' => 'no'
			),
			'authorize'         => array(
				'title'   => __( 'Authorize', 'woocommerce-gateway-mondido' ),
				'type'    => 'checkbox',
				'label'   => __( 'Reserve money, do not auto-capture', 'woocommerce-gateway-mondido' ),
				'default' => 'no'
			),
			'tax_status'        => array(
				'title'       => __( 'Tax status for payment fees', 'woocommerce-gateway-mondido' ),
				'type'        => 'select',
				'options'     => array(
					'none'    => __( 'None', 'woocommerce-gateway-mondido' ),
					'taxable' => __( 'Taxable', 'woocommerce-gateway-mondido' )
				),
				'description' => __( 'If any payment fee should be taxable', 'woocommerce-gateway-mondido' ),
				'default'     => 'none'
			),
			'tax_class'         => array(
				'title'       => __( 'Tax class for payment fees', 'woocommerce-gateway-mondido' ),
				'type'        => 'select',
				'options'     => self::getTaxClasses(),
				'description' => __( 'If you have a fee for invoice payments, what tax class should be applied to that fee', 'woocommerce-gateway-mondido' ),
				'default'     => 'standard'
			),
			'logos'             => array(
				'title'          => __( 'Logos', 'woocommerce-gateway-mondido' ),
				'description'    => __( 'Logos on checkout', 'woocommerce-gateway-mondido' ),
				'type'           => 'multiselect',
                'css'            => 'height: 200px;',
				'options'        => array(
					'visa'       => __( 'Visa', 'woocommerce-gateway-mondido' ),
					'mastercard' => __( 'MasterCard', 'woocommerce-gateway-mondido' ),
					'amex'       => __( 'American Express', 'woocommerce-gateway-mondido' ),
					'diners'     => __( 'Diners Club', 'woocommerce-gateway-mondido' ),
					'bank'       => __( 'Direktbank', 'woocommerce-gateway-mondido' ),
					'invoice'    => __( 'Invoice/PartPayment', 'woocommerce-gateway-mondido' ),
					'paypal'     => __( 'PayPal', 'woocommerce-gateway-mondido' ),
					'mp'         => __( 'MasterPass', 'woocommerce-gateway-mondido' ),
					'swish'      => __( 'Swish', 'woocommerce-gateway-mondido' ),
				),
				'select_buttons' => true,
			),
			'order_button_text' => array(
				'title'   => __( 'Text for "Place Order" button', 'woocommerce-gateway-mondido' ),
				'type'    => 'text',
				'default' => __( 'Pay with Mondido', 'woocommerce-gateway-mondido' ),
			),
		);
	}

	/**
	 * Output the gateway settings screen
	 * @return void
	 */
	public function admin_options() {
		wc_get_template(
			'admin/admin-options.php',
			array(
				'gateway' => $this,
			),
			'',
			dirname( __FILE__ ) . '/../templates/'
		);
	}

	/**
	 * If There are no payment fields show the description if set.
	 * @return void
	 */
	public function payment_fields() {
		wc_get_template(
			'checkout/payment-fields.php',
			array(
				'gateway' => $this,
			),
			'',
			dirname( __FILE__ ) . '/../templates/'
		);

		if ( $this->store_cards === 'yes' ) {
			if ( ! is_add_payment_method_page() ) {
				$this->tokenization_script();
				$this->saved_payment_methods();
				$this->save_payment_method_checkbox();
			}
		}
	}

	/**
	 * Process Payment
	 *
	 * @param int $order_id
	 *
	 * @return array|false
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( $this->store_cards === 'yes' ) {
			$token_id = isset( $_POST['wc-mondido_hw-payment-token'] ) ? wc_clean( $_POST['wc-mondido_hw-payment-token'] ) : 'new';

			// Try to load saved token
			if ( $token_id !== 'new' ) {
				$token = new WC_Payment_Token_Mondido( $token_id );
				if ( ! $token->get_id() ) {
					wc_add_notice( __( 'Failed to load token.', 'woocommerce-gateway-mondido' ), 'error' );

					return false;
				}

				// Check access
				if ( $token->get_user_id() !== $order->get_user_id() ) {
					wc_add_notice( __( 'Access denied.', 'woocommerce-gateway-mondido' ), 'error' );

					return false;
				}

				update_post_meta( $order_id, '_mondido_use_store_card', $token->get_id() );
			} elseif ( isset( $_POST['wc-mondido_hw-new-payment-method'] ) && $_POST['wc-mondido_hw-new-payment-method'] === 'true' ) {
				update_post_meta( $order_id, '_mondido_store_card', 1 );
			}
		}

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( TRUE )
		);
	}

	/**
	 * Validate Frontend Fields
	 * @return bool|void
	 */
	public function validate_fields() {
		//
	}

	/**
	 * Receipt Page
	 *
	 * @param int $order_id
	 *
	 * @return void
	 */
	public function receipt_page( $order_id ) {
		$order = wc_get_order( $order_id );

		// Get Stored Card
		$store_card = get_post_meta( $order_id, '_mondido_store_card', true );

		// Prepare Order Items
		$items = $this->getOrderItems( $order );

		// Prepare Metadata
        $metadata = $this->getMetaData( $order );

		// Prepare WebHook
		$webhook = array(
			'url'         => add_query_arg( 'wp_hook', '1', WC()->api_request_url( __CLASS__ ) ),
			'trigger'     => 'payment',
			'http_method' => 'post',
			'data_format' => 'json',
			'type'        => 'CustomHttp'
		);

		$amount = array_sum( array_column( $items, 'amount' ) );

		// Prepare fields
		$fields = array(
			'amount'       => number_format( $amount, 2, '.', '' ),
			'vat_amount'   => 0,
			'merchant_id'  => $this->merchant_id,
			'currency'     => $order->get_currency(),
			'customer_ref' => $this->getCustomerReference( $order ),
			'payment_ref'  => $order->get_id(),
			'success_url'  => $this->get_return_url( $order ),
			'error_url'    => $order->get_cancel_order_url_raw(),
			'metadata'     => $metadata,
			'test'         => $this->testmode === 'yes' ? 'true' : 'false',
			'store_card'   => $store_card ? 'true' : 'false',
			'authorize'    => $this->authorize === 'yes' ? 'true' : '',
			'items'        => $items,
			'webhook'      => $webhook
		);

		wc_get_template(
			'checkout/mondido-form.php',
			array(
				'fields'  => apply_filters( 'woocommerce_mondido_form_fields', $fields, $order, $this ),
				'order'   => $order,
				'gateway' => $this,
			),
			'',
			dirname( __FILE__ ) . '/../templates/'
		);
	}

	/**
	 * Thank you page
	 *
	 * @param $order_id
	 *
	 * @return void
	 */
	public function thankyou_page( $order_id ) {
		//
	}

	/**
	 * Payment confirm action
	 * @return void
	 */
	public function payment_confirm() {
		if ( ! is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}

		if ( empty( $_GET['key'] ) ) {
			return;
		}

		// Validate Payment Method
		$order_id = wc_get_order_id_by_order_key( $_GET['key'] );
		if ( ! $order_id ) {
			return;
		}

		/** @var WC_Order $order */
		$order = wc_get_order( $order_id );
		if ( $order && $order->get_payment_method() !== $this->id ) {
			return;
		}

		if ( $order->is_paid() ) {
            return;
        }

		$transaction_id = wc_clean( $_GET['transaction_id'] );
		$payment_ref    = wc_clean( $_GET['payment_ref'] );
		$status         = wc_clean( $_GET['status'] );

		// Transaction ID value failback
		//if ( $_GET['transaction_id'] === true ) {
			//$matches = array();
			//preg_match( '/[\?&]transaction_id=([^&#]*)/m', $_SERVER['REQUEST_URI'], $matches );
			//if ( isset( $matches[1] ) ) {
				//$transaction_id = $matches[1];
			//}
		//}

		$this->log( "Incoming Payment confirmation. Transaction ID: {$transaction_id}. Status: {$status}. Order ID: {$payment_ref}" );
		$this->log( "Request URI: {$_SERVER['REQUEST_URI']}" );

		// Verify Payment Reference
		if ( $payment_ref != $order_id ) {
			wc_add_notice( __( 'Payment Reference verification failed', 'woocommerce-gateway-mondido' ), 'error' );
			return;
		}

		// Use transient to prevent multiple requests
		if ( get_transient( 'mondido_transaction_' . $transaction_id . $status ) !== false ) {
			$this->log( "Payment confirmation rejected. Transaction ID: {$transaction_id}. Status: {$status}" );
			return;
		}
		set_transient( 'mondido_transaction_' . $transaction_id . $status, true, MINUTE_IN_SECONDS );

		try {
			// Lookup transaction
			$transaction_data = $this->lookupTransaction( $transaction_id );
			if ( ! $transaction_data ) {
				throw new Exception( __( 'Failed to verify transaction', 'woocommerce-gateway-mondido' ) );
			}

			// Verify hash
			$hash = md5( sprintf( '%s%s%s%s%s%s%s',
				$this->merchant_id,
				$payment_ref,
				$this->getCustomerReference( $order ),
				number_format( $transaction_data['amount'], 2, '.', '' ), // instead $order->get_total()
				strtolower( $order->get_currency() ),
				$status,
				$this->secret
			) );
			if ( $hash !== wc_clean( $_GET['hash'] ) ) {
				throw new Exception( __( 'Hash verification failed', 'woocommerce-gateway-mondido' ) );
			}

			// Process transaction
			$this->handle_transaction( $order, $transaction_data );
		} catch (Exception $e) {
			$this->log( __CLASS__  . '::' . __METHOD__ . ' Exception: ' . $e->getMessage() );
			wc_add_notice( sprintf( __( 'Error: %s', 'woocommerce-gateway-mondido' ), $e->getMessage() ), 'error' );
		}

		// Unlock order processing
		$this->unlock_order( $payment_ref );
	}

	/**
	 * Notification Callback
	 * ?wc-api=WC_Gateway_Mondido_HW
	 * @return void
	 */
	public function notification_callback() {
		set_time_limit( 0 );
		@ob_clean();

		try {
			$logger   = new WC_Logger();
			$raw_body = file_get_contents( 'php://input' );
			$data     = @json_decode( $raw_body, TRUE );
			if ( ! $data ) {
				throw new \Exception( 'Invalid data' );
			}

			if ( isset( $_GET['store_card'] ) ) {
				$paymentToken = $data['token'];
				$card_holder  = $data['card_holder'];
				$card_number  = $data['card_number'];
				$card_type    = $data['card_type'];
				$expires      = strtotime( $data['expires'] );
				$transaction  = @json_decode( $data['transaction'], TRUE );

				// Check is Token already exists
				$tokens = WC_Payment_Tokens::get_tokens( array(
					'type' => 'Mondido',
				) );

				$tokens = array_filter( $tokens, function( $token, $id ) use ($paymentToken) {
					if ( $token->get_token() === $paymentToken ) {
						return true;
					}

					return false;
				}, ARRAY_FILTER_USE_BOTH );

				if ( count( $tokens ) > 0 ) {
					$message = 'This Credit Card already stored: ' . $card_number;
					header( sprintf( '%s %s %s', 'HTTP/1.1', '200', 'OK' ), TRUE, '200' );
					$logger->add( $this->id, sprintf( '[%s] IPN: %s', 'SUCCESS', $message ) );
					echo sprintf( 'IPN: %s', $message );
				}

				// Get Order
				$store_order = $transaction['metadata']['store_order']['id'];
				$order       = wc_get_order( $store_order );

				// Create Credit Card
				$token = new WC_Payment_Token_Mondido();
				$token->set_gateway_id( $this->id );
				$token->set_token( $paymentToken );
				$token->set_last4( substr( $card_number, -4 ) );
				$token->set_expiry_year( date( 'Y', $expires ) );
				$token->set_expiry_month( date( 'm', $expires ) );
				$token->set_card_type( strtolower( $card_type ) );
				$token->set_user_id( $order->get_customer_id() );
				$token->set_masked_number( $card_number );
				$token->set_card_holder( $card_holder );
				$token->save();

				$order->add_payment_token( $token );

				// Success
				$message = 'Stored Credit Card: ' . $card_number;
				header( sprintf( '%s %s %s', 'HTTP/1.1', '200', 'OK' ), TRUE, '200' );
				$logger->add( $this->id, sprintf( '[%s] IPN: %s', 'SUCCESS', $message ) );
				echo sprintf( 'IPN: %s', $message );
				return;
			}

			$logger->add( $this->id, var_export($data, true) );

			if ( empty( $data['id'] ) ) {
				throw new \Exception( 'Invalid transaction ID' );
			}

			// Log transaction details
			$logger->add( $this->id, 'Incoming Transaction: ' . var_export( json_encode( $data, true ), true) );

			// Wait for unlock
			$times = 0;
			while ( $this->is_order_locked( $data['payment_ref'] ) ) {
				sleep( 10 );
				$times ++;
				if ( $times > 6 ) {
					break;
				}
			}

			// Lock order processing
			$this->lock_order( $data['payment_ref'] );

			// Clean order data cache
			clean_post_cache( $data['payment_ref'] );

			// Lookup transaction
			$transaction_data = $this->lookupTransaction( $data['id'] );
			if ( ! $transaction_data ) {
				throw new \Exception('Failed to lookup transaction');
			}

			switch ( $transaction_data['transaction_type'] ) {
				case 'recurring':
					// Recurring Transactions
					// Please note:
					// Configure permanent webhook http://yourshop.local/?wc-api=WC_Gateway_Mondido_HW

					// Create Order
					$order = wc_create_order( array(
						'customer_id'   => isset( $transaction_data['metadata']['customer']['user_id'] ) ? $transaction_data['metadata']['customer']['user_id'] : '',
						'customer_note' => '',
						'total'         => $transaction_data['amount'],
						'created_via'   => 'mondido',
					) );
					add_post_meta( $order->get_id(), '_payment_method', $this->id );
					update_post_meta( $order->get_id(), '_transaction_id', $transaction_data['id'] );
					update_post_meta( $order->get_id(), '_mondido_transaction_status', $transaction_data['status'] );
					update_post_meta( $order->get_id(), '_mondido_transaction_data', $transaction_data );
					update_post_meta( $order->get_id(), '_mondido_subscription_id', $transaction_data['subscription']['id'] );

					// Add address
					$order->set_address( $transaction_data['metadata']['customer'], 'billing' );
					$order->set_address( $transaction_data['metadata']['customer'], 'shipping' );

					// Add note
					$order->add_order_note( sprintf( __( 'Created recurring order by WebHook. Transaction Id %s', 'woocommerce-gateway-mondido' ), $transaction_data['id'] ) );

					// Add Recurring product as Payment Fee
					$fee            = new stdClass();
					$fee->name      = sprintf( __( 'Subscription #%s ', 'woocommerce-gateway-mondido' ), $transaction_data['subscription']['id'] );
					$fee->amount    = $transaction_data['amount'];
					$fee->taxable   = FALSE;
					$fee->tax_class = '';
					$fee->tax       = 0;
					$fee->tax_data  = array();
					$this->add_order_fee($fee, $order);

					// Calculate totals
					$order->calculate_totals();

					// Force to set total
					$order->set_total( $transaction_data['amount'] );

					// Process transaction
					$this->handle_transaction( $order, $transaction_data );
					break;
				default:
					// Payment Transactions
					$order = wc_get_order( $transaction_data['payment_ref'] );
					if ( ! $order ) {
						throw new \Exception( "Failed to find order {$transaction_data['payment_ref']}" );
					}

					$transaction_id = $data['id'];
					$payment_ref    = $data['payment_ref'];
					$status         = $data['status'];

					// Use transient to prevent multiple requests
					if ( get_transient( 'mondido_transaction_' . $transaction_id . $status ) !== false ) {
						$this->log( "[IPN] Payment confirmation rejected. Transaction ID: {$transaction_id}. Status: {$status}" );
						header( sprintf( '%s %s %s', 'HTTP/1.1', '200', 'OK' ), TRUE, '200' );
						echo "[IPN] Payment confirmation rejected. Transaction ID: {$transaction_id}. Status: {$status}";
						return;
					}
					set_transient( 'mondido_transaction_' . $transaction_id . $status, true, MINUTE_IN_SECONDS );

					// Verify hash
					$hash = md5( sprintf( '%s%s%s%s%s%s%s',
						$this->merchant_id,
						$payment_ref,
						$this->getCustomerReference( $order ),
						number_format( $transaction_data['amount'], 2, '.', '' ), // instead $order->get_total()
						strtolower( $order->get_currency() ),
						$status,
						$this->secret
					) );
					if ( $hash !== wc_clean( $data['response_hash'] ) ) {
						throw new \Exception( 'Hash verification failed' );
					}

					// Process transaction
					$this->handle_transaction( $order, $transaction_data );
					break;
			}
		} catch (\Exception $e) {
			// Unlock order processing
			if ( is_array($data) && isset($data['payment_ref']) ) {
				$this->unlock_order( $data['payment_ref'] );
			}

			// Failure
			$this->log( __CLASS__  . '::' . __METHOD__ . ' IPN: Exception: ' . $e->getMessage() );
			header( sprintf( '%s %s %s', 'HTTP/1.1', '400', 'FAILURE' ), TRUE, '400' );
			echo sprintf( 'IPN: %s', $e->getMessage() );
			exit();
		}

		// Unlock order processing
		$this->unlock_order( $data['payment_ref'] );

		// Get Message
		if ( $data['transaction_type'] === 'recurring' ) {
			$message = "Recurring Order was placed by WebHook. Order ID: {$data['payment_ref']}. Transaction Id: {$data['id']}";
		} else {
			$message = "Order confirmed by WebHook. Order ID: {$data['payment_ref']}. Transaction ID: {$data['id']}. Transaction status: {$data['status']}";
		}

		// Success
		header( sprintf( '%s %s %s', 'HTTP/1.1', '200', 'OK' ), TRUE, '200' );
		$logger->add( $this->id, sprintf( '[%s] IPN: %s', 'SUCCESS', $message ) );
		echo sprintf( 'IPN: %s', $message );
		exit();
	}

	/**
	 * Add hash
	 *
	 * @param array              $fields
	 * @param WC_Order           $order
	 * @param WC_Payment_Gateway $gateway
	 *
	 * @return array
	 */
	public function add_form_hash_value( $fields, $order, $gateway ) {
		// Make hash
		$fields['hash'] = md5( sprintf(
			'%s%s%s%s%s%s%s',
			$fields['merchant_id'],
			$fields['payment_ref'],
			$fields['customer_ref'],
			$fields['amount'],
			strtolower( $fields['currency'] ),
			$gateway->testmode === 'yes' ? 'test' : '',
			$gateway->secret
		) );

		return $fields;
	}
}

// Register Gateway
WC_Mondido_Payments::register_gateway( 'WC_Gateway_Mondido_HW' );
