<?php
class WC_Mondido_Api {
	private $args = [];
	private $api = '';
	private $http;
	private $logger;
	private $source;

	public function __construct($merchant_id, $password, $http, $logger, $source) {
		$this->api = 'https://api.mondido.com';
		$this->args = [
			'headers' => ['Authorization' => 'Basic ' . base64_encode("{$merchant_id}:{$password}")]
		];
		$this->http = $http;
		$this->logger = $logger;
		$this->source = $source;
	}

	public function get_transaction($id) {
		return $this->get(__METHOD__, "v1/transactions/$id", ['extend' => 'customer']);
	}

	public function get_transaction_by_reference($reference) {
		$result = $this->get(__METHOD__, "v1/transactions", ['extend' => 'customer', 'filter' => ['payment_ref' => $reference]]);

		if (is_wp_error($result)) {
			return $result;
		}

		if (count($result) === 0) {
			return null;
		}
		return $result[0];
	}

	public function create_transaction($data) {
		return $this->post(__METHOD__, 'v1/transactions', $data);
	}

	public function update_transaction($id, $data) {
		return $this->put(__METHOD__, "v1/transactions/$id", $data);
	}

	public function capture_transaction($transaction_id, $amount)
	{
		return $this->put(__METHOD__, "v1/transactions/$transaction_id/capture", [
			'amount' => number_format($amount, 2, '.', '')
		]);
	}

	public function list_plans() {
		return $this->list_all(__METHOD__, "v1/plans", []);
	}

	public function list_customer_subscriptions($customer_id) {
		return $this->get(__METHOD__, "v1/customers/$customer_id/subscriptions", ['extend' => 'plan']);
	}

	public function cancel_subscription($id) {
		return $this->put(__METHOD__, "v1/subscriptions/$id", ['status' => 'cancelled']);
	}

	private function list_all($context, $path, $query) {
		$query = $query + ['limit' => 100, 'offset' => 0];
		$result = [];

		if ($query['limit'] == 0) {
			return $result;
		}

		do {
			$items = $this->send_request('GET', $path, $context, $query, []);
			if (is_wp_error($items)) {
				return $items;
			}
			$result = array_merge($result, $items);
            $query['offset'] += $query['limit'];
		} while (count($items) === $query['limit']);

		return $result;
	}

	private function get($context, $path, $query) {
		return $this->send_request('GET', $path, $context, $query, []);
	}

	private function put($context, $path, $body = [], $query = []) {
		return $this->send_request('PUT', $path, $context, $query, $body);
	}

	private function post($context, $path, $body = [], $query = []) {
		return $this->send_request('POST', $path, $context, $query, $body);
	}

	protected function send_request($method, $path, $context, $query, $body) {
		$context = Self::class . '::' . $context;
		$uri = "$this->api/$path?" . http_build_query($query);
		$response = $this->http->request($uri, ['method' => $method, 'body' => $body, 'timeout' => 40] + $this->args);
		$error = new WP_Error();
		$response_log = null;

		if (is_wp_error($response)) {
			$error = $response;
		} else {
			$response_data = json_decode($response['body']);

			if ($response['response']['code'] >= 400) {
				if ($response_data) {
					$error->add($context, $response_data->description, $response['response']['code']);
				} else {
					$error->add($context, 'bad response code', $response['response']['code']);
				}
			}

			if (json_last_error() !== JSON_ERROR_NONE) {
				$error->add($context, 'invalid response data', json_last_error_msg());
			}

			if (!$error->has_errors()) {
				return $response_data;
			}

			$response_log = [
				'code' => $response['response']['code'],
				'message' => $response['response']['message'],
				'body' => $response['body'],
			];
		}

		$this->logger->error($error->get_error_message(), [
			'source' => $this->source,
			'context' => $context,
			'request' => [
				'uri' => $uri,
				'method' => $method,
				'query' => $query,
				'body' => $body,
			],
			'response' => $response_log,
			'error' => [
				'error_codes' => $error->get_error_codes(),
				'error_messages' => $error->get_error_messages(),
			],
		]);

		return $error;
	}
}
