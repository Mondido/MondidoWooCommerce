<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Gateway_Mondido_Card extends WC_Gateway_Mondido_Preselect {
	public function __construct() {
		parent::__construct('credit_card', 'Card', 'Pay with Card');
	}

	public function init_form_fields() {
		parent::init_form_fields();
		$this->form_fields['logos'] = array(
			'title'          => __( 'Logos', 'woocommerce-gateway-mondido' ),
			'description'    => __( 'Logos on checkout', 'woocommerce-gateway-mondido' ),
			'type'           => 'multiselect',
			'options'        => array(
				'visa'       => __( 'Visa', 'woocommerce-gateway-mondido' ),
				'mastercard' => __( 'MasterCard', 'woocommerce-gateway-mondido' ),
				'amex'       => __( 'American Express', 'woocommerce-gateway-mondido' ),
				'diners'     => __( 'Diners Club', 'woocommerce-gateway-mondido' ),
			),
			'select_buttons' => true,
		);
	}
}
