<?php
/*
  Plugin Name: Mondido Payments
  Plugin URI: https://www.mondido.com/
  Description: Mondido Payment plugin for WooCommerce
  Version: 3.0
  Author: Mondido Payments
  Author URI: https://www.mondido.com
 */
// Actions
add_action('plugins_loaded', 'woocommerce_mondido_init', 0);
add_action('init', array('WC_Gateway_Mondido', 'check_mondido_response'));
add_action('valid-mondido-callback', array('WC_Gateway_Mondido', 'successful_request'));
add_action( 'add_meta_boxes', 'MY_order_meta_boxes' );
add_action( 'admin_footer', 'my_action_javascript' ); // Write our JS below here
add_action( 'wp_ajax_my_action', array('WC_Gateway_Mondido','my_action_callback'));
add_action( 'init', 'plugin_init' );
add_action( 'wp_footer', array( 'WC_Gateway_Mondido', 'marketing_footer' ) );
add_action( 'woocommerce_product_options_pricing', 'wc_rrp_product_field' );	
//subscriptions
add_action( 'woocommerce_process_product_meta', 'woo_add_custom_general_fields_save' );
add_filter( 'woocommerce_cart_needs_payment', 'filter_woocommerce_cart_needs_payment', 10, 2 ); 
add_filter( 'woocommerce_order_needs_payment', 'filter_woocommerce_order_needs_payment', 10, 3 ); 
function filter_woocommerce_order_needs_payment( $needs_payment, $instance, $valid_order_statuses ) { 
 if( $needs_payment == false ) 
     {
        global $woocommerce;
        $prods = $woocommerce->cart->cart_contents;
        foreach($prods as $item)
        {
             $plan_id = get_post_meta($item['product_id'], '_plan_id', true );
             if( intval($plan_id) > 0 )
             {
                 return true;
             }
        }
        return $needs_payment; //true if payment should always be visible
     }
     else
     {
         return $needs_payment;
     }
}; 
function filter_woocommerce_cart_needs_payment( $this_total_0, $instance ) 
{ 
    // if cart amount > 0 OR product has _plan_id return true else return $this_total_0
     if( $this_total_0 == false ) 
     {
        global $woocommerce;
        $prods = $woocommerce->cart->cart_contents;
        foreach($prods as $item)
        {
             $plan_id = get_post_meta($item['product_id'], '_plan_id', true );
             if( intval($plan_id) > 0 )
             {
                 return true;
             }
        }
        return $this_total_0; //true if payment should always be visible
     }
     else
     {
         return $this_total_0;
     }
}
function wc_rrp_product_field() 
{
    $mondido = new WC_Gateway_Mondido();
    $plans = $mondido->fetch_plans_from_API();
   $options = Array();
   $plan_id = get_post_meta( get_the_ID(), '_plan_id', true );
    $options[0] = 'No subscription';
     foreach($plans as $item){
        $options[$item['id']] = __( $item['name'], 'woocommerce' );
     }
    woocommerce_wp_select( 
        array( 
            'id'      => '_plan_id', 
            'value' => (string) $plan_id,
            'label'   => __( 'Subscription plan', 'woocommerce' ), 
            'options' => $options
            )
        );	
}
function woo_add_custom_general_fields_save( $post_id )
{
	// update subscription plan id
	$woocommerce_select = $_POST['_plan_id'];
	if( !empty( $woocommerce_select ) )
    {
		update_post_meta( $post_id, '_plan_id', esc_attr( $woocommerce_select ) );
    }
}
function plugin_init() {
    // localization in the init action for WPML support
    load_plugin_textdomain( 'mondido', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
function my_action_javascript() {
    global $woocommerce;
    $id = 0;
    if(isset($_GET["post"])){
        $id = $_GET["post"];
    }  
    ?><script type="text/javascript" >
        jQuery(document).ready(function($) {
            var data = {
                'action': 'my_action',
                'id': '<?=$id?>'
            };
            $('#mondido_capture').on('click',function(e){
                e.preventDefault();
                jQuery.post(ajaxurl, data, function(response) {
                    alert('Mondido capture response: ' + response);
                });
            });
        });
    </script><?php
}
function MY_order_meta_boxes()
{
    add_meta_box(
        'woocommerce-order-YOUR-UNIQUE-REF',
        __( 'Mondido Payments' ),
        'order_meta_box_YOURCONTENT',
        'shop_order',
        'side',
        'default'
    );
}
function order_meta_box_YOURCONTENT()
{
    global $woocommerce;
    $mondido = new WC_Gateway_Mondido();
    $id = $_GET['post'];
    $t = $mondido->get_transaction($id);
    if($t != null && $t['status'] == 'authorized'){
        echo '<button style="margin-bottom: 20px;" id="mondido_capture">Capture Payment</button>';
    }
    if ($t != null){
        $has_3ds = 'No';
        if($t['mpi_ref'] != ''){
            $has_3ds = 'Yes';
        }
        $txt =<<<EOT
        <div><strong>Payment Info:</strong></div>
<div><strong>Type:</strong> {$t['transaction_type']}</div>
<div><strong>Number:</strong> {$t['card_number']}</div>
<div><strong>Name:</strong> {$t['card_holder']}</div>
<div><strong>Card:</strong> {$t['payment_details']['card_type']}</div>
<div><strong>Status: </strong> {$t['status']}</div>
<div><strong>3D Secure:</strong> {$has_3ds}</div>
<div><a href="{$t['href']}" target="_blank">Payment Link</a></div>
<p>
<div><a href="https://admin.mondido.com/transactions/{$t['id']}" target="_blank">View at Mondido</a></div>
</p>
EOT;
        echo $txt;
    }
    return;
}
function woocommerce_mondido_init() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }
    class WC_Gateway_Mondido extends WC_Payment_Gateway {
        public function __construct()
        {
            $this->view_transaction_url = 'https://admin.mondido.com/transactions/%s';
            $this->supports = array(
                        'products',
                        'refunds',
                    );
            global $woocommerce;
            $this->selected_currency = '';
            $this->plugin_version = "3.0";
            // Currency
            if ( isset($woocommerce->session->client_currency) ) {
                // If currency is set by WPML
                $this->selected_currency = $woocommerce->session->client_currency;
            } elseif ( class_exists( 'WC_Aelia_CurrencySwitcher' ) && defined('AELIA_CS_USER_CURRENCY') ) {
                // If currency is set by WooCommerce Currency Switcher (http://dev.pathtoenlightenment.net/shop)
                $plugin_instance = WC_Aelia_CurrencySwitcher::instance();
                $this->selected_currency = strtoupper($plugin_instance->get_selected_currency());
            } else {
                // WooCommerce selected currency
                $this->selected_currency = get_option('woocommerce_currency');
            }
            $this->id = 'mondido';
            $this->icon = "https://cdn-02.mondido.com/www/img/wp-mondido.png";
            $this->has_fields = false;
            $this->method_title = 'Mondido';
            $this->method_description = __('', 'mondido');
            $this->order_button_text = __('Proceed to Mondido', 'woocommerce');
            $this->liveurl = 'https://pay.mondido.com/v1/form';
            // Load forms and settings
            $this->init_form_fields();
            $this->init_settings();
            // Get from users settings
            $payment_options = '';
            if($this->settings['visa-mc'] == 'yes'){
                $payment_options = 'Visa, MasterCard';
            }
            if($this->settings['amex'] == 'yes'){
                $payment_options = $payment_options.', Amex';
            }
            if($this->settings['diners'] == 'yes'){
                $payment_options = $payment_options.', Diners';
            }
            if($this->settings['swish'] == 'yes'){
                $payment_options = $payment_options.', Swish';
            }
            if($this->settings['bank'] == 'yes'){
                $payment_options = $payment_options.', Bank';
            }
            if($this->settings['invoice'] == 'yes'){
                $payment_options = $payment_options.', Faktura';
            }
            if(substr( $payment_options, 0, 2 ) === ", "){
                $payment_options = substr($payment_options, 2);
            }
            $this->title = $payment_options;
            $this->description = __('Pay securely by Credit or Debit card through Mondido.', 'mondido');
            $this->merchant_id = $this->settings['merchant_id'];
            $this->secret = $this->settings['secret'];
            $this->password = $this->settings['password'];
            $this->currency = $this->selected_currency; //pick currency from shop
            $test = 'false';
            if($this->settings['test'] == 'yes'){
                $test = 'true';
            }
            $this->test = $test;
            $authorize = 'false';
            if($this->settings['authorize'] == 'yes'){
                $authorize = 'true';
            }
            $this->authorize = $authorize;
            // Actions
            add_action('woocommerce_api_' . strtolower(get_class()), array($this, 'check_mondido_response'));
            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
            }
            add_action('woocommerce_receipt_mondido', array($this, 'receipt_page'));
        }
        /*
         * Function for get settings variabler in hash functions.
         */
        function fetch_plans_from_API(){
            $response = $this->CallAPI('GET','https://api.mondido.com/v1/plans',null,$this->get_merchant_id().':'.$this->get_password());
            if($response['error']){
                $log = new WC_Logger();
                $log->add( 'mondido get plans error', $response );
                return null;
            }else{
                $json_data = json_decode($response['body'],true);
                return $json_data;
            }
            return null;
        }
        function fetch_transaction_from_API($transaction_id, $post_id){
            $response = $this->CallAPI('GET','https://api.mondido.com/v1/transactions/'.$transaction_id,null,$this->get_merchant_id().':'.$this->get_password());
            if($response['error']){
                $log = new WC_Logger();
                $log->add( 'mondido get transaction error', $response );
                return null;
            }else{
                $this->store_transaction($post_id,$response['body']);
                $json_data = json_decode($response['body'],true);
                return $json_data;
            }
            return null;
        }
        function store_transaction($id, $json){
            update_post_meta( $id, 'mondido-transaction-data', $json );
        }
        function get_transaction($id){
            $t = get_post_meta( $id, 'mondido-transaction-data' );
            if(count($t) > 0){
                return json_decode($t[0],true);
            }
            return null;
        }
        public function get_secret() {
            return $this->secret;
        }
        public function get_password() {
            return $this->password;
        }
        public function get_merchant_id() {
            return $this->merchant_id;
        }
        public function get_currency() {
            return $this->currency;
        }
        public function get_test() {
            return $this->test;
        }
        public function get_authorize() {
            return $this->authorize;
        }
        /*
         * Initialise settings form fields
         */
        function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'mondido'),
                    'type' => 'checkbox',
                    'label' => __('Enable mondido Payment Module.', 'mondido'),
                    'default' => 'no'),
                'merchant_id' => array(
                    'title' => __('Merchant ID', 'mondido'),
                    'type' => 'text',
                    'description' => __('Merchant ID for Mondido')),
                'secret' => array(
                    'title' => __('Secret', 'mondido'),
                    'type' => 'text',
                    'description' => __('Given secret code from Mondido', 'mondido'),
                ),
                'password' => array(
                    'title' => __('API Password', 'mondido'),
                    'type' => 'text',
                    'description' => __('API Password from Mondido', 'mondido').' (<a href="https://admin.mondido.com/settings">https://admin.mondido.com/settings</a>',
                ),
                'test' => array(
                    'title' => __('Test', 'mondido'),
                    'type' => 'checkbox',
                    'label' => __('Set in testmode.', 'mondido'),
                    'default' => 'no'),
                'authorize' => array(
                    'title' => __('Authorize', 'mondido'),
                    'type' => 'checkbox',
                    'label' => __('Reserve money, do not auto-capture.', 'mondido'),
                    'default' => 'yes'),
                'visa-mc' => array(
                    'title' => __('Visa, MasterCard', 'mondido'),
                    'type' => 'checkbox',
                    'label' => '',
                    'default' => 'yes'),
                'amex' => array(
                    'title' => __('American Express', 'mondido'),
                    'type' => 'checkbox',
                    'label' => '',
                    'default' => 'no'),
                'diners' => array(
                    'title' => __('Diners Club', 'mondido'),
                    'type' => 'checkbox',
                    'label' => '',
                    'default' => 'no'),
                'swish' => array(
                    'title' => __('Swish', 'mondido'),
                    'type' => 'checkbox',
                    'label' => '',
                    'default' => 'no'),
                'bank' => array(
                    'title' => __('Direktbank', 'mondido'),
                    'type' => 'checkbox',
                    'label' => '',
                    'default' => 'no'),
                'invoice' => array(
                    'title' => __('Faktura', 'mondido'),
                    'type' => 'checkbox',
                    'label' => '',
                    'default' => 'no')
            );
        }
        /*
         * Create Admin page for settings
         */
        public function admin_options() {
            echo '<h3>' . __('Mondido', 'mondido') . '</h3>';
            echo '<p>' . __('Mondido, Simple payments, smart functions', 'mondido') . '</p>';
            echo '<p>Please go to <a href="https://admin.mondido.com" target="_blank">https://admin.mondido.com</a> to sign up and get hold of your account information that you need to enter here.<br>Do not hesitate to contact support@mondido.com if you have any questions setting up your WooCommerce payment plugin.</p>';
            echo '<p>All settings below can be found at this location: <a href="https://admin.mondido.com/en/settings" target="_blank">https://admin.mondido.com/en/settings</a> after you have logged in.</p>';
            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';
        }
        /*
         *  There are no payment fields for mondido, but we want to show the description if set.
         */
        function payment_fields() {
            if ($this->description) {
                echo wpautop(wptexturize($this->description));
            }
        }
        public function my_action_callback() {
            global $wpdb; // this is how you get access to the database
            global $woocommerce;
            $mondido = new WC_Gateway_Mondido();
            $t = $mondido->get_transaction($_POST['id']);
            $order = new WC_Order((int) $_POST['id']);
            $data = array('amount' => number_format($order->order_total, 2, '.', ''));
            $response = $mondido->CallAPI('PUT','https://api.mondido.com/v1/transactions/'.$t['id'].'/capture',$data,$mondido->get_merchant_id().':'.$mondido->get_password());
            if($response['error']){
                $log = new WC_Logger();
                $log->add( 'mondido capture', $response );
                $t = $mondido->fetch_transaction_from_API($t['id'], $_POST['id']);
                if($t != null && $t['status'] == 'approved'){
                    $order->update_status('processing', __( 'Mondido payment captured!', 'woocommerce' ));
                }
                $res = json_decode($response['body'],true);
                if($res['description']){
                    echo $res['description'];
                }
                wp_die();
            }
            $mondido->store_transaction($_POST['id'],$response['body']);
            $transaction = json_decode($response['body'],true);
            if($transaction['status'] == 'approved'){
                $order->payment_complete( $transaction['id'] );
                $log = new WC_Logger();
                $log->add( 'mondido capture ','success' );
                $mondido->fetch_transaction_from_API($t['id'], $_POST['id']);
                update_post_meta( $order->id, 'mondido-transaction-status', 'approved' );
                $order->update_status('processing', __( 'Mondido payment captured!', 'woocommerce' ));
                $order->add_order_note( sprintf( __( 'Captured transaction %s ', 'woocommerce' ), $transaction['id'] ));
                echo 'Success!';
                wp_die();
            }else{
                $log = new WC_Logger();
                $log->add( 'mondido capture ',$transaction['status'] );
                echo 'Failed, Transaction is '.$transaction['status'];
                wp_die();
            }
            wp_die(); // this is required to terminate immediately and return a proper response
        }
        public function process_refund( $order_id, $amount = null,$reason = 'wocommerce refund') {
            // Do your refund here. Refund $amount for the order with ID $order_id
            $t = $this->get_transaction($order_id);
            $order = new WC_Order($order_id);
            $amount = str_replace(',', '.', $amount);
            $amount = number_format($amount,2,'.','');
            $data = array('transaction_id' => $t['id'],'amount' => $amount,'reason' => $reason);
            $response = $this->CallAPI('POST','https://api.mondido.com/v1/refunds',$data,$this->get_merchant_id().':'.$this->get_password());
            if($response['error']){
                $log = new WC_Logger();
                $log->add( 'mondido refund', $response );
                return false;
            }
            $order->add_order_note( sprintf( __( 'Refunded %s ', 'woocommerce' ), $amount ));
           // do_action( 'woocommerce_order_status_refunded', $order->id );
            return true;
        }
        /*
         * Generate Mondido button link
         */
        public function generate_mondido_form($order_id) {
            global $woocommerce;
            $order = new WC_Order($order_id);
            $products = [];
            $customer = [];
            $platform = [];
            $order_items = [];
            $items = [];
            $analytics = [];
            $google = [];
            $cart = $woocommerce->cart->cart_contents;
            $crt = $woocommerce->cart;
            $vat_amount = $crt->tax_total;
            $vat_amount = number_format($vat_amount, 2, '.', '');
            if(isset($_COOKIE['m_ad_code'])) 
            {
                $google["ad_code"] = $_COOKIE['m_ad_code'];
            }
            if(isset($_COOKIE['m_ref_str'])) {
                $analytics["referrer"] = $_COOKIE['m_ref_str'];
            }
            $analytics['google'] = $google;
            $shipping = [];
            $shipping["description"] = "Shipping";
            $shipping_total = $crt->shipping_total + $crt->shipping_tax_total;
            $shipping["amount"] = $shipping_total;
            $shipping["artno"] = 0;
            $shipping["vat"] = ($shipping_total / $crt->shipping_tax_total) *100;
            $shipping["unit_price"] = $shipping_total;
            $shipping["discount"] = 0;
            $shipping["qty"] = 0;
            array_push($items,$shipping);
            if($crt->discount_cart != ''){
                $discount = [];
                $discount["name"] = "Discount";
                $discount["amount"] = number_format(0-($crt->discount_cart + $crt->discount_cart_tax), 2, '.', '');
                array_push($items,$discount);
            }
            #vat weight attributes
            $has_plan_id = false;
            foreach($cart as $item){
                $c_item = [];
                $items_item = [];
                $c_item["id"] = $item['product_id'];
                $c_item["quantity"] = $item['quantity'];
                $c_item["total_amount"] = number_format($item['line_total'], 2, '.', '');
                if($has_plan_id == false)
                {    
                    $plan_id = get_post_meta( $item["product_id"], '_plan_id', true );
                    if(intval($plan_id) > 0)
                    {
                        $has_plan_id = true;
                        $c_item["plan_id"] = $plan_id; 
                        $c_item["product_type"] = 'recurring'; 
                    }
                    else 
                    {
                        $c_item["product_type"] = 'normal'; 
                    }
                }
                $prod = new WC_Product($item["product_id"]);
                $c_item["image"] = $this->get_img_url($prod->get_image());
                $c_item["weight"] = $prod->get_weight();
                $c_item["vat"] = number_format($item['line_tax'], 2, '.', '');
                $c_item["amount"] = $prod->price;
                $c_item["shipping_class"] = $prod->shipping_class;
                $c_item["name"] = $prod->post->post_title;
                $c_item["url"] = $prod->post->guid;
               //invoice item
                $items_item["artno"] = $prod->get_sku();
                $price_inc_tax = $prod->get_price_including_tax();
                $price_ex_tax = $prod->get_price_excluding_tax();
                $tax = $price_inc_tax - $price_ex_tax;
                $tax_perc = ($tax / $price_inc_tax) * 100;
                $qty = $item['quantity'];
                $items_item["vat"] = number_format($tax_perc, 2, '.', '');
                $items_item["amount"] = number_format($price_inc_tax * $qty, 2, '.', '');
                $items_item["description"] = $prod->post->post_title;
                $items_item["qty"] = $item['quantity'];
                $items_item["unit_price"] = number_format($price_inc_tax, 2, '.', '');
                $items_item["discount"] = 0;
                array_push($products,$c_item);
                array_push($items,$items_item);
            }
            $platform["type"] = "wocoomerce";
            $platform["version"] = $woocommerce->version;
            $platform["language_version"] = phpversion();
            $platform["plugin_version"] = $this->plugin_version;
            $order = new WC_Order( $order_id );
            $customer["id"] = get_current_user_id();
            $customer["country"] = $order->billing_country;
            $customer["city"] = $order->billing_city;
            $customer["zip"] = $order->billing_postcode;
            $customer["state"] = $order->billing_state;
            $customer["address_1"] = $order->billing_address_1;
            $customer["address_2"] = $order->billing_address_2;
            $customer["email"] = $order->billing_email;
            $customer["first_name"] = $order->billing_first_name;
            $customer["last_name"] = $order->billing_last_name;
            $customer["phone"] = $order->billing_phone;
            $coupons = [];
            foreach($crt->applied_coupons as $coupon){
                $coup_item = [];
                $coup_item["code"] = $coupon;
                array_push($coupons,$coup_item);
            }
            $order_items["coupons"] = $coupons;
            $order_items["discount"] = $crt->discount_cart;
            $order_items["discount_vat"] = $crt->discount_cart_tax;
            $md = [
                "products" => $products,
                "customer" => $customer,
                "platform" => $platform,
                "order" => $order_items,
                "analytics" => $analytics
            ];
            $metadata = json_encode($md);
            $amount = number_format($order->order_total, 2, '.', '');
            $merchant_id = trim($this->merchant_id);
            $currency = trim($this->currency);
            $customer_id = $order->get_user_id();
            if($customer_id == '0'){
                $customer_id = '';
            }
            $hash = generate_mondido_hash($order_id);
            $mondido_args = array(
                'amount' => $amount,
                'vat_amount' => $vat_amount,
                'merchant_id' => $merchant_id,
                'currency' => $currency,
                'customer_ref' => $customer_id,
                'payment_ref' => $order_id,
                'hash' => $hash,
                'success_url' => $this->get_return_url($order),
                'error_url' => $order->get_cancel_order_url(),
                'metadata' => $metadata,
                'test' => $this->test,
                'authorize' => $this->authorize,
                'plan_id' => $plan_id,
                'items' => json_encode($items)
            );
            $mondido_args_array = array();
            foreach ($mondido_args as $key => $value) {
                $mondido_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
            }
            return '<form action="' . $this->liveurl . '" method="post" id="mondido_payment_form">
            ' . implode('', $mondido_args_array) . '
				<div class="payment_buttons">
					<input type="submit" class="button alt" id="submit_mondido_payment_form" value="' . __('Pay via Mondido', 'mondido') . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __('Cancel order &amp; restore cart', 'mondido') . '</a>
				</div>
            </form><script>if(mondido_submit)
            {
            document.getElementById("mondido_payment_form").submit();
            }</script>';
        }
        /*
         * Process the payment and return the result
         */
        public function process_payment($order_id) {
            global $woocommerce;
            $order = new WC_Order($order_id);
            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            );
        }
        /*
         * Receipt Page
         */
        function receipt_page($order) {
$js = <<< HTML
<script>
var mondido_submit = false;
if(window.location.hash != '#paying'){
    mondido_submit = true;
    window.location.hash = 'paying';
}else{
    window.history.go(-2);
}
</script>
HTML;
            $spinner = '<img src="https://mondido.s3.amazonaws.com/www/img/ring-alt.gif" style="position:fixed;top:50%;left:50%;transform:translate(-50%, -50%);">';
            echo '<div style="position:fixed;z-index:1000;top:0px;left:0px;height:100%;width:100%;background-color:#e8e8e8;">' .$spinner. '</div>'.$js;
            echo $this->generate_mondido_form($order);
        }
        public function get_address_from_transaction($transaction)
        {
            $cust = $transaction['metadata']['customer'];
            $address = array(
                'first_name' => $cust['first_name'],
                'last_name'  => $cust['last_name'],
                'company'    => '',
                'email'      => $cust['email'],
                'phone'      => $cust['phone'],
                'address_1'  => $cust['address_1'],
                'address_2'  => $cust['address_2'],
                'city'       => $cust['city'],
                'state'      => $cust['state'],
                'postcode'   => $cust['zip'],
                'country'    => $cust['country']
            );
            return $address;
        }
        public function parse_webhook($transaction, $mondido){
            $trans = $mondido->get_transaction($transaction["payment_ref"]);
            //check if this is already done!
            //check if we have the same transaction
            if($trans != null && $transaction['id'] == $trans['id'])
            {
                $hash = generate_mondido_hash($transaction["payment_ref"],true, $transaction['status']);
                if($hash == $transaction['response_hash']){ //hash is valid
                    //check status updates
                    $status = $transaction['status'];
                    $order = new WC_Order((int) $transaction["payment_ref"]);
                    if($status == 'approved' || $status == 'authorized' ){
                        $order->add_order_note( sprintf( __( 'Webhook callback transaction approved %s ', 'woocommerce' ), $transaction['id'] ));
                        $order->payment_complete();
                        $order->update_status( 'completed' );
                    }elseif($status == 'declined'){
                        $order->update_status('failed', __( 'Mondido payment declined!', 'woocommerce' ));
                        $order->add_order_note( sprintf( __( 'Webhook callback transaction declined %s ', 'woocommerce' ), $transaction['id'] ));
                    }elseif($status == 'failed'){
                        $order->update_status('failed', __( 'Mondido payment failed!', 'woocommerce' ));
                        $order->add_order_note( sprintf( __( 'Webhook callback transaction failed %s ', 'woocommerce' ), $transaction['id'] ));
                    }elseif($status == 'pending'){
                        $order->update_status('on-hold', __( 'Mondido payment pending!', 'woocommerce' ));
                        $order->add_order_note( sprintf( __( 'Webhook callback transaction pending %s ', 'woocommerce' ), $transaction['id'] ));
                    }elseif($status == 'authorized'){
                        $order->update_status('on-hold', __( 'Mondido payment authorized!', 'woocommerce' ));
                        $order->add_order_note( sprintf( __( 'Webhook callback transaction authorized %s ', 'woocommerce' ), $transaction['id'] ));
                    }
                    //check for new delivery address
                    if($transaction['payment_details']['city'] && $transaction['payment_details']['zip']){
                        $address = $this->get_address_from_transaction($transaction);
                        $order->set_address( $address, 'shipping' );
                        $order->add_order_note( sprintf( __( 'Webhook callback updated shipping address %s ', 'woocommerce' ), $transaction['id'] ));
                    }
                }
            }
            else
            {
                if($transaction['transaction_type'] == 'recurring')
                {
                    $status = $transaction['status'];
                    $ts = 'failed';
                    if( $status == 'approved' )
                    {
                        $ts = 'completed';
                    }
                    $customer_id = $transaction['metadata']['customer']['id'];
                     $order_data = array(
                        'status'        => $ts,
                        'customer_id'   => $customer_id,
                        'customer_note' => '',
                        'total'         => $transaction['amount'],
                        'created_via'   => 'Mondido',
                    );
                    //get recurringproduct id
                    //get subtotal and total
                    $pid = 129;
                    $_SERVER['REMOTE_ADDR'] = '127.0.0.1'; // Required, else wc_create_order throws an exception
                    $order  = wc_create_order( $order_data );
                    add_post_meta($order->id, '_payment_method', 'mondido' );
                    $order->add_order_note( sprintf( __( 'Webhook callback created recurring order %s ', 'woocommerce' ), $transaction['id'] ));
                    $price_params = array( 'totals' => array( 'subtotal' => $transaction['amount'], 'total' => $transaction['amount'] ) );
                    $address = $this->get_address_from_transaction($transaction);
                    $order->set_address( $address, 'shipping' );
                    $order->add_product( get_product( $pid ), 1, $price_params ); 
                    $order->set_total($transaction['amount'], 'total');
                    $this->store_transaction($order->id,json_encode($transaction));
                    if(floatval($transaction['amount']) > 0)
                    {
                        WC()->mailer()->emails['WC_Email_Customer_Processing_Order']->trigger($order->id);
                    }
                    WC()->mailer()->emails['WC_Email_New_Order']->trigger($order->id);
                }
            }
            echo 'ok';
            http_response_code(200);
            die();
        }
        public static function marketing_footer($msg=null){
            if(isset($_REQUEST['mondido_msg_holder'])){
                echo $_REQUEST['mondido_msg_holder'];
            }else{
                echo '<script type="text/javascript" src="https://cdn-02.mondido.com/www/js/os-shop-v1.js"></script>';
            }
        }   
        public static function notification($msg){
            $output = str_replace(array("\r", "\n"), "", $msg);
            if(preg_match("/access denied/i", $msg, $matches)){
                 $output =  $output.', probably is the Mondido API password not correct in the settings.';
            }    
            $str = '<script type="text/javascript">alert("'.$output.'");</script>';
            $_REQUEST['mondido_msg_holder'] = $str;
        }           
        /*
         * Check for valid mondido server callback
         */
        public static function check_mondido_response() {
            $raw_body = file_get_contents("php://input");
            $array = json_decode($raw_body,true);
            if($array['response_hash']){
                $mondido = new WC_Gateway_Mondido();
                $mondido->parse_webhook($array,$mondido);
            }
            $_GET = stripslashes_deep($_GET);
            $expected_fields = array(
                "hash",
                "payment_ref",
                "transaction_id"
            );
            foreach($expected_fields as $field){
                if(!isset($_GET[$field])) return;
            }
            do_action("valid-mondido-callback", $_GET);
        }
        public function CallAPI($method, $url, $data = false, $username_pass){
            $headers = array(
                "Authorization" => "Basic " . base64_encode($username_pass),
                "Content-type" => "application/x-www-form-urlencoded"
            );
            $result = wp_remote_post( $url, array(
                    'method' => $method,
                    'headers' => $headers,
                    'body' => $data
                )
            );
            if ( $result['response']['code'] != 200 ) {
                $this->notification($result['body']);
                return array('error' => true, 'status' => $result['headers']['status'], 'body' => $result['body']);
            }
            return array('error' => false, 'status' => $result['headers']['status'], 'body' => $result['body']);
        }
        function get_img_url($html){
            $doc = new DOMDocument();
            $doc->loadHTML($html);
            $xpath = new DOMXPath($doc);
            $src = $xpath->evaluate("string(//img/@src)");
            return $src;
        }
        /*
         * Successful Payment
         */
         // when thank you page loads
        public static function successful_request($posted) {
            //start
            global $woocommerce;
            $mondido = new WC_Gateway_Mondido();
            $transaction = $mondido->fetch_transaction_from_API($posted['transaction_id'],$posted["payment_ref"]);
            // If payment was success
            $status = $posted['status'];
            //store transaction here !!!!
            if ($status == 'approved'  || $status == 'authorized' ) {
                $order = new WC_Order((int) $posted["payment_ref"]);
//                do_action('woocommerce_order_status_pending_to_completed', $order->id );
                // if order not exists, die()
                if($order->post == null) return;
                $stored_status = get_post_meta( $order->id, 'mondido-transaction-status' );
                if(count($stored_status) > 0){
                    if($stored_status[0] == 'approved' || $stored_status[0] == 'authorized'){
                        return;
                    }
                }
                $is_bad = true;
                if($order->post_status == "wc-pending" || $order->post_status == 'wc-failed')
                {
                    $is_bad = false;
                }
                // if order is not pending, die()
                if($is_bad)
                {
                    $message = __(
                        sprintf("Invalid redirect for Order #%s."
                            . " Status is not pending."
                            . " Customer may have reloaded the page or pressed F5.",
                            $posted["payment_ref"]
                        ),
                        'woocommerce'
                    );
                    $log = new WC_Logger();
                    $log->add( 'mondido', $message );
                    return;
                }
                // Check whether payment is correct
                $hash = generate_mondido_hash(
                    (int) $posted["payment_ref"],
                    true,
                    $posted["status"]
                );
                if ($hash == $posted['hash']) {
                    $order->add_order_note(
                        __(
                            sprintf("Success: Redirect completed."
                                . " Transaction ID #%s.",
                                $posted['transaction_id']
                            ),
                            'woocommerce'
                        )
                    );
                    if($status == 'authorized'){
                        update_post_meta( $order->id, 'mondido-transaction-status', 'authorized' );
                        $order->update_status('on-hold', __( 'Awaiting Mondido payment', 'woocommerce' ));
                        $order->reduce_order_stock();
                    }elseif($status == 'approved'){
                        // Payment Complete
                        // Reduce stock levels
                        update_post_meta( $order->id, 'mondido-transaction-status', 'approved' );
                        $order->reduce_order_stock();
                        $order->payment_complete($posted['transaction_id']);
                        if(floatval($transaction['amount']) > 0)
                        {
                            WC()->mailer()->emails['WC_Email_Customer_Processing_Order']->trigger($order->id);
                        }
                        WC()->mailer()->emails['WC_Email_New_Order']->trigger($order->id);
                    }
                    update_post_meta( $order->id, 'mondido-transaction-id', $posted['transaction_id'] );
                    // Remove cart
                    $woocommerce->cart->empty_cart();
                } else {
                    $order->update_status('failed');
                    $order->add_order_note(
                        __(
                            sprintf( "Failure. Callback completed. "
                                . "Transaction #%s."
                                . " Incorrect hash -- fake payment?",
                                $posted['transaction_id']
                            ),
                            'woocommerce'
                        )
                    );
                }
            }
            //end
        }
    }
    /*
     * Generate Mondido Hash
     */
    function generate_mondido_hash($order_id, $callback = false, $status = "") {
        $order = new WC_Order((int) $order_id);
        $mondido = new WC_Gateway_Mondido();
        $amount = number_format($order->order_total, 2, '.', '');
        $merchant_id = trim($mondido->get_merchant_id());
        $secret = trim($mondido->get_secret());
        $customer_id = $order->get_user_id();
        if($customer_id == '0'){
                $customer_id = '';
        }
        $currency = strtolower($mondido->get_currency());
        $test = ((string)$mondido->get_test() == 'true') ? 'test' : '';
        if ($callback) {
            $str = $merchant_id . $order_id . $customer_id . $amount . $currency . strtolower($status) . $secret;
        } else {
            $str = $merchant_id . $order_id . $customer_id . $amount . $currency . $test . $secret;
        }
        return MD5($str);
    }
    add_filter('generate_mondido_hash', 'generate_mondido_hash');
    /*
     * Add the Gateway to WooCommerce
     */
    function woocommerce_add_mondido_gateway($methods) {
        $methods[] = 'WC_Gateway_Mondido';
        return $methods;
    }
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_mondido_gateway');
    function init_mondido_gateway() {
        $plugin_dir = basename(dirname(__FILE__));
        load_plugin_textdomain('mondido', false, $plugin_dir . '/languages/');
    }
    add_action('plugins_loaded', 'init_mondido_gateway');
    function WC_Gateway_Mondido() {
        return new WC_Gateway_Mondido();
    }
    if (is_admin()) {
        add_action('load-post.php', 'WC_Gateway_Mondido');
    }
}?>