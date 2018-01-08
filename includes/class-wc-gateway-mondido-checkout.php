<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly

class WC_Gateway_Mondido_Checkout extends WC_Gateway_Mondido_HW {
    /**
     * Merchant Id
     * @var string
     */
    public $merchant_id = '';

    /**
     * Secret
     * @var string
     */
    public $secret = '';

    /**
     * Password
     * @var string
     */
    public $password = '';

    /**
     * Test Mode
     * @var string
     */
    public $testmode = 'no';

    /**
     * Authorize
     * @var string
     */
    public $authorize = 'no';

    /**
     * Tax Status
     * @var string
     */
    public $tax_status = 'none';

    /**
     * Tax Class
     * @var string
     */
    public $tax_class = 'standard';

    /**
     * Logos
     * @var array
     */
    public $logos = array();

    /**
     * Checkout button on Cart page
     * @var string
     */
    public $cart_button = 'yes';

    /**
     * Buy button on Product page
     * @var string
     */
    public $product_button = 'yes';

    /**
     * Use Mondido Checkout instead WooCommerce Checkout
     * @var string
     */
    public $instant_checkout = 'no';

    /**
     * Init
     */
    public function __construct() {
        $this->id                 = 'mondido_checkout';
        $this->has_fields         = TRUE;
        $this->method_title       = __( 'Mondido Checkout', 'woocommerce-gateway-mondido' );
        $this->method_description = '';

        $this->icon     = apply_filters( 'woocommerce_mondido_checkout_icon', plugins_url( '/assets/images/mondido.png', dirname( __FILE__ ) ) );
        $this->supports = array(
            'products',
            'refunds',
        );

        // URL to view a transaction
        $this->view_transaction_url = 'https://admin.mondido.com/transactions/%s';

        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Define variables
        $this->enabled           = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
        $this->title             = isset( $this->settings['title'] ) ? $this->settings['title'] : __( 'Mondido Checkout', 'woocommerce-gateway-mondido' );
        $this->description       = isset( $this->settings['description'] ) ? $this->settings['description'] : '';
        $this->merchant_id       = isset( $this->settings['merchant_id'] ) ? $this->settings['merchant_id'] : $this->merchant_id;
        $this->secret            = isset( $this->settings['secret'] ) ? $this->settings['secret'] : $this->secret;
        $this->password          = isset( $this->settings['password'] ) ? $this->settings['password'] : $this->password;
        $this->testmode          = isset( $this->settings['testmode'] ) ? $this->settings['testmode'] : $this->testmode;
        $this->authorize         = isset( $this->settings['authorize'] ) ? $this->settings['authorize'] : $this->authorize;
        $this->tax_status        = isset( $this->settings['tax_status'] ) ? $this->settings['tax_status'] : $this->tax_status;
        $this->tax_class         = isset( $this->settings['tax_class'] ) ? $this->settings['tax_class'] : $this->tax_class;
        $this->logos             = isset( $this->settings['logos'] ) ? (array) $this->settings['logos'] : $this->logos;
        $this->order_button_text = isset( $this->settings['order_button_text'] ) ? $this->settings['order_button_text'] : __( 'Pay with Mondido', 'woocommerce-gateway-mondido' );
        $this->cart_button       = isset( $this->settings['cart_button'] ) ? $this->settings['cart_button'] : $this->cart_button;
        $this->product_button    = isset( $this->settings['product_button'] ) ? $this->settings['product_button'] : $this->product_button;
        $this->instant_checkout  = isset( $this->settings['instant_checkout'] ) ? $this->settings['instant_checkout'] : $this->instant_checkout;

        // Actions
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
            $this,
            'process_admin_options'
        ) );

        // Add button on Cart Page
        add_action( 'woocommerce_proceed_to_checkout', array(
            $this,
            'add_button_on_cart_page'
        ) );

        // Add button to Product Page
        if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '3.0.0', '<' ) ) {
            add_action( 'woocommerce_after_add_to_cart_button', array(
                $this,
                'add_button_to_product_page'
            ), 1 );
        } else {
            add_action( 'woocommerce_after_add_to_cart_quantity', array(
                $this,
                'add_button_to_product_page'
            ), 1 );
        }

        add_filter( 'the_title', array( $this, 'override_endpoint_title' ) );

        add_filter( 'wc_get_template', array(
            $this,
            'override_checkout'
        ), 10, 5 );

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
                'label'   => __( 'Enable Mondido Checkout Module', 'woocommerce-gateway-mondido' ),
                'default' => 'no'
            ),
            'title'             => array(
                'title'       => __( 'Title', 'woocommerce-gateway-mondido' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-mondido' ),
                'default'     => __( 'Mondido Checkout', 'woocommerce-gateway-mondido' )
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
                'default'     => $this->merchant_id
            ),
            'secret'            => array(
                'title'       => __( 'Secret', 'woocommerce-gateway-mondido' ),
                'type'        => 'text',
                'description' => __( 'Given secret code from Mondido', 'woocommerce-gateway-mondido' ),
                'default'     => $this->secret
            ),
            'password'          => array(
                'title'       => __( 'API Password', 'woocommerce-gateway-mondido' ),
                'type'        => 'text',
                'description' => __( 'API Password from Mondido', 'woocommerce-gateway-mondido' ) . ' (<a href="https://admin.mondido.com/settings">https://admin.mondido.com/settings</a>)',
                'default'     => $this->password
            ),
            'testmode'          => array(
                'title'   => __( 'Test Mode', 'woocommerce-gateway-mondido' ),
                'type'    => 'checkbox',
                'label'   => __( 'Set in testmode', 'woocommerce-gateway-mondido' ),
                'default' => $this->testmode
            ),
            'authorize'         => array(
                'title'   => __( 'Authorize', 'woocommerce-gateway-mondido' ),
                'type'    => 'checkbox',
                'label'   => __( 'Reserve money, do not auto-capture', 'woocommerce-gateway-mondido' ),
                'default' => $this->authorize
            ),
            'tax_status'        => array(
                'title'       => __( 'Tax status for payment fees', 'woocommerce-gateway-mondido' ),
                'type'        => 'select',
                'options'     => array(
                    'none'    => __( 'None', 'woocommerce-gateway-mondido' ),
                    'taxable' => __( 'Taxable', 'woocommerce-gateway-mondido' )
                ),
                'description' => __( 'If any payment fee should be taxable', 'woocommerce-gateway-mondido' ),
                'default'     => $this->tax_status
            ),
            'tax_class'         => array(
                'title'       => __( 'Tax class for payment fees', 'woocommerce-gateway-mondido' ),
                'type'        => 'select',
                'options'     => self::getTaxClasses(),
                'description' => __( 'If you have a fee for invoice payments, what tax class should be applied to that fee', 'woocommerce-gateway-mondido' ),
                'default'     => $this->tax_class
            ),
            'logos'             => array(
                'title'          => __( 'Logos', 'woocommerce-gateway-mondido' ),
                'description'    => __( 'Logos on checkout', 'woocommerce-gateway-mondido' ),
                'type'           => 'multiselect',
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
                'select_buttons' => TRUE,
            ),
            'order_button_text' => array(
                'title'   => __( 'Text for "Place Order" button', 'woocommerce-gateway-mondido' ),
                'type'    => 'text',
                'default' => __( 'Pay with Mondido', 'woocommerce-gateway-mondido' ),
            ),
            'cart_button'       => array(
                'title'   => __( 'Checkout button on Cart page', 'woocommerce-gateway-mondido' ),
                'type'    => 'checkbox',
                'label'   => __( 'Checkout button on Cart page', 'woocommerce-gateway-mondido' ),
                'default' => $this->cart_button
            ),
            'product_button'     => array(
                'title'   => __( 'Buy button on Product page', 'woocommerce-gateway-mondido' ),
                'type'    => 'checkbox',
                'label'   => __( 'Buy button on Product page', 'woocommerce-gateway-mondido' ),
                'default' => $this->product_button
            ),
            'instant_checkout'    => array(
                'title'   => __( 'Mondido Checkout instead WooCommerce Checkout', 'woocommerce-gateway-mondido' ),
                'type'    => 'checkbox',
                'label'   => __( 'Mondido Checkout instead WooCommerce Checkout', 'woocommerce-gateway-mondido' ),
                'default' => $this->instant_checkout
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
     * Add Mondido Checkout button
     */
    public function add_button_on_cart_page() {
        if ( $this->enabled === 'yes' && $this->cart_button === 'yes' && $this->instant_checkout !== 'yes' ) {
            wc_get_template(
                'checkout/cart-button.php',
                array(),
                '',
                dirname( __FILE__ ) . '/../templates/'
            );
        }
    }

    /**
     * Add Mondido Checkout button
     */
    public function add_button_to_product_page() {
        if ( ! is_single() ) {
            return;
        }

        if ( $this->enabled === 'yes' && $this->product_button === 'yes' ) {
            global $post;
            $product = wc_get_product( $post->ID );
            if ( ! is_object( $product ) ) {
                return;
            }

            wc_get_template(
                'checkout/product-button.php',
                array(
                    'product_id' => $product->get_id(),
                ),
                '',
                dirname( __FILE__ ) . '/../templates/'
            );
        }
    }

    /**
     * Override Endpoint Title
     * @param $title
     *
     * @return string
     */
    public function override_endpoint_title( $title ) {
        global $wp_query;
        $is_endpoint = isset( $wp_query->query_vars[ 'order-pay' ] );
        if ( $is_endpoint && ! is_admin() && is_main_query() && in_the_loop() ) {
            // New page title.
            $title = __( 'Checkout', 'woocommerce' );
        }
        return $title;
    }

    /**
     * Override Standard Checkout template
     * @param $located
     * @param $template_name
     * @param $args
     * @param $template_path
     * @param $default_path
     *
     * @return string
     */
    public function override_checkout( $located, $template_name, $args, $template_path, $default_path ) {
        if ( $this->enabled !== 'yes' || $this->instant_checkout !== 'yes' ) {
            return $located;
        }

        if ( strpos( $located, 'checkout/form-checkout.php' ) !== false ) {
            $located = wc_locate_template(
                    'checkout/mondido-checkout.php',
                    $template_path,
                    dirname( __FILE__ ) . '/../templates/'
            );
        }

        return $located;
    }

    /**
     * Receipt Page
     *
     * @param int $order_id
     *
     * @return void
     */
    public function receipt_page( $order_id ) {
        $order = wc_get_order($order_id);

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
            'customer_ref' => $order->get_user_id() != '0' ? $order->get_user_id() : '',
            'payment_ref'  => $order->get_id(),
            'success_url'  => add_query_arg( 'goto', $this->get_return_url( $order ), WC()->api_request_url( __CLASS__ ) ),
            'error_url'    => add_query_arg( 'goto', $order->get_cancel_order_url_raw(), WC()->api_request_url( __CLASS__ ) ),
            'metadata'     => $metadata,
            'test'         => $this->testmode === 'yes' ? 'true' : 'false',
            'authorize'    => $this->authorize === 'yes' ? 'true' : '',
            'items'        => $items,
            'webhook'      => $webhook,
            'process'      => 'false',
        );

        $fields = apply_filters( 'woocommerce_mondido_form_fields', $fields, $order, $this );
        try {
            $result = wp_remote_get( 'https://api.mondido.com/v1/transactions', array(
                'method'  => 'POST',
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode( "{$this->merchant_id}:{$this->password}" )
                ),
                'body' => $fields
            ) );

            if ( is_a( $result, 'WP_Error' ) ) {
                throw new Exception( implode( $result->errors['http_request_failed'] ) );
            }

            if ( $result['response']['code'] != 200 ) {
                $error = @json_decode( $result['body'], TRUE );
                if ( is_array( $error ) && isset( $error['description'] ) ) {
                    throw new Exception( $error['description'] );
                }

                throw new Exception( $result['body'] );
            }
        } catch (Exception $e) {
            ?>
            <ul class="woocommerce-error">
                <li>
                    <?php echo sprintf( __( 'Error: %s', 'woocommerce-gateway-mondido' ), $e->getMessage() ); ?>
                </li>
            </ul>
            <?php
            return;
        }

        $transaction = json_decode( $result['body'], TRUE );

        wc_get_template(
            'checkout/mondido-iframe.php',
            array(
                'payment_url' => $transaction['href']
            ),
            '',
            dirname( __FILE__ ) . '/../templates/'
        );
    }

    /**
     * Payment confirm action
     * @return void
     */
    public function payment_confirm() {
        if ( is_wc_endpoint_url( 'order-received' ) ) {
            $_GET['transaction_id'] = TRUE;
            parent::payment_confirm();
        }
    }

    /**
     * Notification Callback
     * @return void
     */
    public function notification_callback() {
        // Redirect to page
        if ( isset( $_GET['goto'] ) ) {
            wc_get_template(
                'checkout/mondido-redirect.php',
                array(
                    'url' => urldecode( $_GET['goto'] )
                ),
                '',
                dirname( __FILE__ ) . '/../templates/'
            );
            exit();
        }

        parent::notification_callback();
    }
}
