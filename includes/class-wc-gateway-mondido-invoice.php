<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Gateway_Mondido_Invoice extends WC_Gateway_Mondido_HW {
	/**
	 * Init
	 */
	public function __construct() {
		$this->id                 = 'mondido_invoice';
		$this->has_fields         = TRUE;
		$this->method_title       = __( 'Mondido Invoice', 'woocommerce-gateway-mondido' );
		$this->method_description = '';

		$this->icon     = apply_filters( 'woocommerce_mondido_invoice_icon', plugins_url( '/assets/images/invoice.png', dirname( __FILE__ ) ) );
		$this->supports = array(
			'products',
			'refunds',
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define variables
		$this->enabled     = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
		$this->title       = isset( $this->settings['title'] ) ? $this->settings['title'] : __( 'Mondido Invoice', 'woocommerce-gateway-mondido' );
		$this->description = isset( $this->settings['description'] ) ? $this->settings['description'] : '';
		$this->merchant_id = isset( $this->settings['merchant_id'] ) ? $this->settings['merchant_id'] : '';
		$this->secret      = isset( $this->settings['secret'] ) ? $this->settings['secret'] : '';
		$this->password    = isset( $this->settings['password'] ) ? $this->settings['password'] : '';
		$this->testmode    = isset( $this->settings['testmode'] ) ? $this->settings['testmode'] : 'no';
		$this->authorize   = isset( $this->settings['authorize'] ) ? $this->settings['authorize'] : 'no';
		$this->tax_status  = isset( $this->settings['tax_status'] ) ? $this->settings['tax_status'] : 'none';
		$this->tax_class   = isset( $this->settings['tax_class'] ) ? $this->settings['tax_class'] : 'standard';
		$this->logos             = isset( $this->settings['logos'] ) ? $this->settings['logos'] : array();
		$this->order_button_text = isset( $this->settings['order_button_text'] ) ? $this->settings['order_button_text'] : __( 'Pay with Mondido', 'woocommerce-gateway-mondido' );

		add_filter( 'woocommerce_mondido_form_fields', array(
			$this,
			'set_payment_method'
		), 10, 3 );

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
				'label'   => __( 'Enable Mondido Invoice', 'woocommerce-gateway-mondido' ),
				'default' => 'no'
			),
			'title'             => array(
				'title'       => __( 'Title', 'woocommerce-gateway-mondido' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-mondido' ),
				'default'     => __( 'Mondido Invoice', 'woocommerce-gateway-mondido' )
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
		echo '<h2>' . esc_html( $this->get_method_title() ) . '</h2>';
		echo wp_kses_post( wpautop( $this->get_method_description() ) );
		echo '<table class="form-table">' . $this->generate_settings_html( $this->get_form_fields(), false ) . '</table>';
	}

	/**
	 * Set Payment Method on mondido form
	 * @param array $fields
	 * @param WC_Order $order
	 * @param WC_Payment_Gateway $gateway
	 *
	 * @return array
	 */
	public function set_payment_method( $fields, $order, $gateway ) {
        if ( ! $order ) {
            return $fields;
        }

		if ( $order->get_payment_method() === $this->id) {
			$fields['payment_method'] = 'invoice';
		}

		return $fields;
	}
}

// Register Gateway
WC_Mondido_Payments::register_gateway( 'WC_Gateway_Mondido_Invoice' );
