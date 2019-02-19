<?php
/** @var WC_Payment_Gateway $gateway */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly
?>

<h2><?php esc_html( $gateway->get_method_title() ); ?></h2>
<?php wp_kses_post( wpautop( $gateway->get_method_description() ) ); ?>
<p><?php _e('Mondido, Simple payments, smart functions', 'woocommerce-gateway-mondido'); ?></p>
<p>
	<?php
	echo sprintf(
		__('Please go to <a href="%s" target="_blank">%s</a> to sign up and get hold of your account information that you need to enter here.', 'woocommerce-gateway-mondido'),
		'https://admin.mondido.com',
		'https://admin.mondido.com'
	);
	?>
	<br>
	<?php _e('Do not hesitate to contact support@mondido.com if you have any questions setting up your WooCommerce payment plugin.', 'woocommerce-gateway-mondido'); ?>
</p>
<p>
	<?php
	echo sprintf(
		__('All settings below can be found at this location: <a href="%s" target="_blank">%s</a> after you have logged in.', 'woocommerce-gateway-mondido'),
		'https://admin.mondido.com/en/settings',
		'https://admin.mondido.com/en/settings'
	);
	?>
</p>
<p>
	<?php
	echo sprintf(
		__('Please setup WebHooks in <a href="%s" target="_blank">Mondido Dashboard</a>.', 'woocommerce-gateway-mondido'),
		'https://admin.mondido.com/en/webhook_templates'
	);
	?>
	<br>
	<?php
	echo sprintf(
		__('WebHook URL: <a href="%s" target="_blank">%s</a>. Type: JSON. Method: POST. Event: "After a success payment"', 'woocommerce-gateway-mondido'),
		WC()->api_request_url( get_class( $gateway ) ),
		WC()->api_request_url( get_class( $gateway ) )
	);
	?>
    <br>
	<?php
	echo sprintf(
		__('WebHook URL: <a href="%s" target="_blank">%s</a>. Type: JSON. Method: POST. Event: "When a credit card is stored"', 'woocommerce-gateway-mondido'),
		add_query_arg( 'store_card', 'true', WC()->api_request_url( get_class( $gateway ) ) ),
		add_query_arg( 'store_card', 'true', WC()->api_request_url( get_class( $gateway ) ) )
	);
	?>
</p>
<table class="form-table">
	<?php $gateway->generate_settings_html( $gateway->get_form_fields(), true ); ?>
</table>
