<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly


if ( ! function_exists( 'WC' ) || version_compare( WC()->version, '8.2', '<' ) ) {
    exit;
} // Exit if WooCommerce is not activated or version is less than 8.2

use Automattic\WooCommerce\Utilities\OrderUtil;

abstract class OrderStorage {
    public static function current(): OrderStorage {
		if (OrderUtil::custom_orders_table_usage_is_enabled()) {
			return new HPOSOrderStorage();
		} else {
			return new CPTOrderStorage();
		}
	}

	public abstract function get_meta_data($order, $meta_key, $meta_value);
	public abstract function add_meta_data($order, $meta_key, $meta_value);
	public abstract function update_meta_data($order, $meta_key, $meta_value);
	public abstract function delete_meta_data($order, $meta_key);
	public abstract function save($order);
}

class HPOSOrderStorage implements OrderStorage {
    public function get_meta_data($order, $meta_key, $meta_value) {
        return $order->get_meta($meta_key, $meta_value);
    }

    public function add_meta_data($order, $meta_key, $meta_value) {
        $order->add_meta_data($meta_key, $meta_value);
    }

    public function update_meta_data($order, $meta_key, $meta_value) {
        $order->update_meta_data($meta_key, $meta_value);
    }

    public function delete_meta_data($order, $meta_key) {
        $order->delete_meta_data($meta_key);
    }

    public function save($order) {
        $order->save();
    }
}

class CPTOrderStorage implements OrderStorage {
    public function get_meta_data($order, $meta_key, $meta_value) {
        return get_post_meta($order->get_id(), $meta_key, $meta_value);
    }

    public function add_meta_data($order, $meta_key, $meta_value) {
        add_post_meta($order->get_id(), $meta_key, $meta_value);
    }

    public function update_meta_data($order, $meta_key, $meta_value) {
        update_post_meta($order->get_id(), $meta_key, $meta_value);
    }

    public function delete_meta_data($order, $meta_key) {
        delete_post_meta($order->get_id(), $meta_key);
    }

    public function save($order) {
        return; // Do nothing, CPT doesn't use save
    }
}