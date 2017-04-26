<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * Compatibility Layer for WC_Order on WooCommerce < 3.0
 * @see https://woocommerce.wordpress.com/2017/04/04/say-hello-to-woocommerce-3-0-bionic-butterfly/
 *
 * @method int get_id()
 * @method string get_billing_first_name( string $context )
 * @method string get_billing_last_name( string $context )
 * @method string get_billing_company( string $context )
 * @method string get_billing_address_1( string $context )
 * @method string get_billing_address_2( string $context )
 * @method string get_billing_city( string $context )
 * @method string get_billing_state( string $context )
 * @method string get_billing_postcode( string $context )
 * @method string get_billing_country( string $context )
 * @method string get_billing_email( string $context )
 * @method string get_billing_phone( string $context )
 * @method string get_shipping_first_name( string $context )
 * @method string get_shipping_last_name( string $context )
 * @method string get_shipping_company( string $context )
 * @method string get_shipping_address_1( string $context )
 * @method string get_shipping_address_2( string $context )
 * @method string get_shipping_city( string $context )
 * @method string get_shipping_state( string $context )
 * @method string get_shipping_postcode( string $context )
 * @method string get_shipping_country( string $context )
 * @method string get_payment_method( string $context )
 * @method string get_payment_method_title( string $context )
 * @method string get_transaction_id( string $context )
 * @method string get_customer_ip_address( string $context )
 * @method string get_customer_user_agent( string $context )
 * @method string get_created_via( string $context )
 * @method string get_customer_note( string $context )
 *
 */
class WC_Order_Compatibility_Mondido {
	/** @var  WC_Abstract_Order $obj */
	protected $obj;

	/**
	 * Get the order if ID is passed, otherwise the order is new and empty.
	 * This class should NOT be instantiated, but the get_order function or new WC_Order_Factory.
	 * should be used. It is possible, but the aforementioned are preferred and are the only.
	 * methods that will be maintained going forward.
	 *
	 * @param  int|object|WC_Order $order Order to init.
	 */
	public function __construct( $the_order ) {
		global $post;
		if ( FALSE === $the_order ) {
			$the_order = $post;
		} elseif ( is_numeric( $the_order ) ) {
			$the_order = get_post( $the_order );
		} elseif ( $the_order instanceof WC_Order ) {
			$the_order = get_post( $the_order->id );
		}

		if ( ! $the_order || ! is_object( $the_order ) ) {
			return FALSE;
		}

		$order_id  = absint( $the_order->ID );
		$post_type = $the_order->post_type;

		if ( $order_type = wc_get_order_type( $post_type ) ) {
			$classname = $order_type['class_name'];
		} else {
			$classname = FALSE;
		}

		$this->obj = new $classname( $the_order );
	}

	/**
	 * Magic method: __call
	 *
	 * @param $name
	 * @param $arguments
	 *
	 * @return mixed
	 */
	public function __call( $name, $arguments ) {
		if ( substr( $name, 0, 4 ) === 'get_' && ! method_exists( $this->obj, $name ) ) {
			$property = substr( $name, 4, strlen( $name ) - 4 );

			return $this->obj->$property;
		}

		return call_user_func_array( array( $this->obj, $name ), $arguments );
	}

	/**
	 * Magic method: __set
	 *
	 * @param $name
	 * @param $value
	 */
	public function __set( $name, $value ) {
		$this->obj->$name = $value;
	}

	/**
	 * Magic method: __get
	 *
	 * @param $name
	 *
	 * @return mixed
	 */
	public function __get( $name ) {
		return $this->obj->$name;
	}

	/**
	 * Magic method: __isset
	 *
	 * @param $name
	 *
	 * @return bool
	 */
	public function __isset( $name ) {
		return isset( $this->obj->$name );
	}

	/**
	 * Magic method: __unset
	 *
	 * @param $name
	 */
	public function __unset( $name ) {
		unset( $this->obj->$name );
	}

	/**
	 * Gets a prop for a getter method.
	 *
	 * @since  3.0.0
	 *
	 * @param  string $prop    Name of prop to get.
	 * @param  string $address billing or shipping.
	 * @param  string $context What the value is for. Valid values are view and edit.
	 *
	 * @return mixed
	 */
	protected function get_address_prop( $prop, $address = 'billing', $context = 'view' ) {
		$property = $address . '_' . $prop;

		return $this->obj->$property;
	}

	/**
	 * Gets a prop for a getter method.
	 *
	 * Gets the value from either current pending changes, or the data itself.
	 * Context controls what happens to the value before it's returned.
	 *
	 * @since  3.0.0
	 *
	 * @param  string $prop    Name of prop to get.
	 * @param  string $context What the value is for. Valid values are view and edit.
	 *
	 * @return mixed
	 */
	protected function get_prop( $prop, $context = 'view' ) {
		return $this->obj->$prop;
	}

	/**
	 * Get date_completed.
	 *
	 * @param  string $context
	 *
	 * @return WC_DateTime|NULL object if the date is set or null if there is no date.
	 */
	public function get_date_completed() {
		return $this->obj->completed_date;
	}

	/**
	 * Get date_paid.
	 *
	 * @param  string $context
	 *
	 * @return WC_DateTime|NULL object if the date is set or null if there is no date.
	 */
	public function get_date_paid() {
		return $this->obj->paid_date;
	}

	/**
	 * Returns true if the order has a shipping address.
	 * @return boolean
	 */
	public function has_shipping_address() {
		return $this->get_shipping_address_1() || $this->get_shipping_address_2();
	}

	/**
	 * Override Class
	 *
	 * @param $classname
	 * @param $post_type
	 * @param $order_id
	 * @param $the_order
	 *
	 * @return string
	 */
	public static function order_class( $classname, $post_type, $order_id, $the_order ) {
		$payment_method = get_post_meta( $order_id, '_payment_method', TRUE );
		if ( empty( $payment_method ) || $payment_method !== 'mondido_hw' ) {
			return $classname;
		}

		return self::class;
	}
}

// Hook for Order Class override
add_filter( 'woocommerce_order_class', 'WC_Order_Compatibility_Mondido::order_class', 10, 4 );
