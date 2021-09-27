<?php
use League\ISO3166\ISO3166;

class WC_Mondido_Transaction {
	private $api;

	public function __construct(WC_Mondido_Api $api) {
		$this->api = $api;
	}

	public function create(
		$order,
		$merchant_id,
		$testmode,
		$authorize,
		$successful_url,
		$failure_url,
		$payment_callback_url,
		$secret,
		$payment_method,
		$customer_reference,
		$store_card
	) {
		$payment_reference = $order->get_id();
		$items = $this->get_items($order);
		$amount = $this->format_number((float) $order->get_total());
		$currency = $order->get_currency();
		$customer_data = $this->get_customer_data($order, $payment_mode);

		$subscriptions = $this->get_subscriptions($order);

		if (is_wp_error($subscriptions)) {
			return $subscriptions;
		}

		return $this->api->create_transaction(array_merge([
			'amount' => $amount,
			'vat_amount' => 0,
			'merchant_id' => $merchant_id,
			'currency' => $currency,
			'customer_ref' => $customer_reference,
			'payment_ref' => $payment_reference,
			'success_url' => $successful_url,
			'error_url' => $failure_url,
			'test' => $this->format_bool($testmode),
			'store_card' => $this->format_bool($store_card),
			'authorize' => $this->format_bool($authorize),
			'process' => $this->format_bool(false),
			'items' => $items,
			'webhook' => [
				'url' => $payment_callback_url,
				'trigger' => 'payment',
				'http_method' => 'post',
				'data_format' => 'json',
				'type' => 'CustomHttp',
			],
			'payment_method' => $payment_method,
			'hash' => md5(implode([
				$merchant_id,
				$payment_reference,
				$customer_reference,
				$amount,
				strtolower($currency),
				$testmode === true ? 'test' : '',
				$secret
			])),
			'metadata' => $this->get_metadata($order, $customer_reference, $customer_data, $items, $payment_mode, $payment_view),
			'payment_details' => $this->map_payment_details($customer_data),
		], $subscriptions));
	}

	public function update(
		$transaction_id,
		$order,
		$merchant_id,
		$testmode,
		$authorize,
		$secret,
		$payment_method,
		$customer_reference,
		$store_card
	) {
		$payment_reference = $order->get_id();
		$items = $this->get_items($order);
		$amount = $this->format_number((float) $order->get_total());
		$currency = $order->get_currency();
		$customer_data = $this->get_customer_data($order, $payment_mode);

		$subscriptions = $this->get_subscriptions($order);

		if (is_wp_error($subscriptions)) {
			return $subscriptions;
		}

		return $this->api->update_transaction($transaction_id, array_merge([
			'amount' => $amount,
			'vat_amount' => 0,
			'merchant_id' => $merchant_id,
			'currency' => $currency,
			'customer_ref' => $customer_reference,
			'payment_ref' => $payment_reference,
			'test' => $this->format_bool($testmode),
			'store_card' => $this->format_bool($store_card),
			'authorize' => $this->format_bool($authorize),
			'process' => $this->format_bool(false),
			'items' => $items,
			'payment_method' => $payment_method,
			'hash' => md5(implode([
				$merchant_id,
				$payment_reference,
				$customer_reference,
				$amount,
				strtolower($currency),
				$testmode === true ? 'test' : '',
				$secret
			])),
			'metadata' => $this->get_metadata($order, $customer_reference, $customer_data, $items, $payment_mode, $payment_view),
			'payment_details' => $this->map_payment_details($customer_data),
		], $subscriptions));
	}

	public function get($id) {
		return $this->api->get_transaction($id);
	}

	public function get_by_reference($reference) {
		return $this->api->get_transaction_by_reference($reference);
	}

    public function capture($id, $amount)
    {
		return $this->api->capture_transaction($id, $amount);
    }

	private function get_items($order) {
		$items = array_map(function($item) use ($order) {
			$product = wc_get_product($item->get_product_id());
			$data = $item->get_data();

			return (object) [
				'artno' => !empty($product->get_sku()) ? $product->get_sku() : 'product_id' . $product->get_id(),
				'description' => $item->get_name(),
				'amount' => $this->format_number($order->get_line_subtotal($item, true, false)),
				'qty' => $item->get_quantity(),
				'vat' => $this->get_vat((float) $data['subtotal'], (float) $data['subtotal_tax']),
				'discount' => 0,
			];
		}, $order->get_items());

		if ((float) $order->get_shipping_total() > 0) {
			$items[] = (object) [
				'artno'       => 'shipping',
				'description' => $order->get_shipping_method(),
				'amount'      => $this->format_number($order->get_shipping_total() + $order->get_shipping_tax()),
				'qty'         => 1,
				'vat'         => $this->get_vat($order->get_shipping_total(), $order->get_shipping_tax()),
				'discount'    => 0,
			];
		}

		$items = array_merge($items, array_map(function($fee) {
			return (object) [
				'artno'       => 'fee',
				'description' => $fee->get_name(),
				'amount'      => $this->format_number($fee->get_total() + $fee->get_total_tax()),
				'qty'         => 1,
				'vat'         => $this->get_vat($fee->get_total(), $fee->get_total_tax()),
				'discount'    => 0,
			];
		}, $order->get_fees()));


		$item_amount = array_sum(array_column($items, 'amount'));
		if ($item_amount > $order->get_total()) {
			$items[] = (object) [
				'artno'       => 'discount',
				'description' => __('Discount', 'woocommerce-gateway-mondido'),
				'amount'      => $this->format_number(- 1 * ($item_amount - (float) $order->get_total())),
				'qty'         => 1,
				'vat'         => 0,
				'discount'    => 0,
			];
		}

		return $items;

	}

	private function get_vat($price, $vat) {
		if ((string) $price === '0') {
			return $this->format_number(0);
		}
		return $this->format_number(round($vat / $price * 100));
	}

	private function format_number($number) {
		return number_format($number, 2, '.', '' );

	}

	private function format_bool($bool) {
		return $bool === true ? 'true' : 'false';
	}

	private function get_shipping_method_or_default($shipping_method) {
		if (empty($shipping_method)) {
			return reset(\WC()->shipping->get_shipping_methods())->get_name();
		}

		return $shipping_method;
	}

	private function get_customer_data($order) {
		$order_country = $order->get_billing_country();
		if (!empty($order_country)) {
			$order_country = $this->get_country_alpha3($order_country);
		}
		return [
			'user_id' => $order->get_user_id(),
			'firstname' => $order->get_billing_first_name(),
			'lastname' => $order->get_billing_last_name(),
			'address1' => $order->get_billing_address_1(),
			'address2' => $order->get_billing_address_2(),
			'postcode' => $order->get_billing_postcode(),
			'phone' => $order->get_billing_phone(),
			'city' => $order->get_billing_city(),
			'country' => $order_country,
			'state' => $order->get_billing_state(),
			'email' => $order->get_billing_email(),
			'company_name' => $order->get_billing_company(),
		];
	}

	private function map_payment_details($customer_data) {
		return [
			'email' => $customer_data['email'],
			'phone' => $customer_data['phone'],
			'first_name' => $customer_data['firstname'],
			'last_name' => $customer_data['lastname'],
			'zip' => $customer_data['postcode'],
			'address_1' => $customer_data['address1'],
			'address_2' => $customer_data['address2'],
			'city' => $customer_data['city'],
			'country_code' => $customer_data['country'],
			'company_name' => $customer_data['company_name'],
		];
	}

	private function get_metadata($order, $customer_reference, $customer_data, $items) {
		$metadata = [
			'store_order' => ['id' => $order->get_id()],
			'customer_reference' => $customer_reference,
			'products' => $items,
			'customer' => $customer_data,
			'analytics' => [
				'referrer' => isset($_COOKIE['m_ref_str']) ? $_COOKIE['m_ref_str'] : null,
				'google' => [
					'ad_code' => isset($_COOKIE['m_ad_code']) ? $_COOKIE['m_ad_code'] : null,
				]
			],
			'platform' => [
				'type' => 'woocommerce',
				'version' => WC()->version,
				'language_version' => phpversion(),
				'plugin_version' => $this->get_plugin_version(),
			],
		];
		$metadata['extra'] = apply_filters('woocommerce_mondido_get_metadata_extra', [], $metadata);
		return $metadata;
	}

	private function get_subscriptions($order) {
		$plan_id = null;
		$quantity = 0;
		$subscription_items = [];

		foreach ($order->get_items('line_item') as $item) {
			$product_plan_id = get_post_meta( $item->get_product_id(), '_mondido_plan_id', TRUE );
			$include_product = get_post_meta( $item->get_product_id(), '_mondido_plan_include', TRUE );
			if (!$product_plan_id) {
				continue;
			}

			if ($plan_id === null) {
				$plan_id = $product_plan_id;
			}

			if ($plan_id !== $product_plan_id) {
				return new WP_Error(
					'multiple subscription plans',
					__('Order with multiple subscription plans is not supported', 'woocommerce-gateway-mondido')
				);
			}

			if ($include_product) {
				$product = wc_get_product($item->get_product_id());
				$data = $item->get_data();

				$subscription_items[] = (object) [
					'artno' => !empty($product->get_sku()) ? $product->get_sku() : 'product_id' . $product->get_id(),
					'description' => $item->get_name(),
					'amount' => $this->format_number($order->get_line_subtotal($item, true, false)),
					'qty' => $item->get_quantity(),
					'vat' => $this->get_vat((float) $data['subtotal'], (float) $data['subtotal_tax']),
					'discount' => 0,
				];
			}

			$quantity += $item->get_quantity();
		}

		if ($plan_id !== null) {
			return [
				'plan_id' => $plan_id,
				'subscription_quantity' => $quantity,
				'subscription_items' => $subscription_items,
			];
		}

		return [];
	}

	private function get_country_alpha3($code) {
		$map = new ISO3166();
		try {
			$country = $map->alpha2($code);
			return $country['alpha3'];
		} catch (\Exception $error) {
			$country = $map->alpha3($code);
			return $country['alpha3'];
		}
	}

	private function get_plugin_version() {
		$plugin_version = get_file_data(
			__DIR__ . '/../woocommerce-gateway-mondido.php',
			array( 'Version' ),
			'woocommerce-gateway-mondido'
		);

		return isset( $plugin_version[0] ) ? $plugin_version[0] : '';
	}
}
