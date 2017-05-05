<?php
/** @var WC_Order $order */
/** @var array $transaction */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly
?>

<div>
	<strong><?php _e( 'Payment Info', 'woocommerce-gateway-mondido' ) ?></strong>
	<br />
	<strong><?php _e( 'Type', 'woocommerce-gateway-mondido' ) ?>:</strong> <?php echo esc_html( $transaction['transaction_type'] ); ?>
	<br />
	<strong><?php _e( 'Status', 'woocommerce-gateway-mondido' ) ?>: </strong> <?php echo esc_html( $transaction['status'] ); ?>
	<br />
	<?php if ($transaction['transaction_type'] === 'credit_card'): ?>
		<strong><?php _e( 'Number', 'woocommerce-gateway-mondido' ) ?>:</strong> <?php echo esc_html( $transaction['card_number'] ); ?>
		<br />
		<strong><?php _e( 'Name', 'woocommerce-gateway-mondido' ) ?>:</strong> <?php echo esc_html( $transaction['card_holder'] ); ?>
		<br />
		<strong><?php _e( 'Card', 'woocommerce-gateway-mondido' ) ?>:</strong> <?php echo esc_html( $transaction['payment_details']['card_type'] ); ?>
		<br />
		<strong><?php _e( '3D Secure', 'woocommerce-gateway-mondido' ) ?>:</strong> <?php echo empty( $transaction['mpi_ref'] ) ? __('No', 'woocommerce') : __('Yes', 'woocommerce') ?>
		<br />
	<?php endif; ?>
	<?php if ($transaction['transaction_type'] === 'invoice'): ?>
		<strong><?php _e( 'Social Security Number', 'woocommerce-gateway-mondido' ) ?>:</strong> <?php echo esc_html( $transaction['payment_details']['ssn'] ); ?>
		<br />
		<strong><?php _e( 'First name', 'woocommerce-gateway-mondido' ) ?>:</strong> <?php echo esc_html( $transaction['payment_details']['first_name'] ); ?>
		<br />
		<strong><?php _e( 'Last name', 'woocommerce-gateway-mondido' ) ?>:</strong> <?php echo esc_html( $transaction['payment_details']['last_name'] ); ?>
		<br />
		<strong><?php _e( 'Email', 'woocommerce-gateway-mondido' ) ?>:</strong> <?php echo esc_html( $transaction['payment_details']['email'] ); ?>
		<br />
		<strong><?php _e( 'Phone', 'woocommerce-gateway-mondido' ) ?>:</strong> <?php echo esc_html( $transaction['payment_details']['phone'] ); ?>
		<br />
		<strong><?php _e( 'Address 1', 'woocommerce-gateway-mondido' ) ?>:</strong> <?php echo esc_html( $transaction['payment_details']['address_1'] ); ?>
		<br />
		<strong><?php _e( 'Address 2', 'woocommerce-gateway-mondido' ) ?>:</strong> <?php echo esc_html( $transaction['payment_details']['address_2'] ); ?>
		<br />
		<strong><?php _e( 'City', 'woocommerce-gateway-mondido' ) ?>:</strong> <?php echo esc_html( $transaction['payment_details']['city'] ); ?>
		<br />
		<strong><?php _e( 'Postal Code', 'woocommerce-gateway-mondido' ) ?>:</strong> <?php echo esc_html( $transaction['payment_details']['zip'] ); ?>
		<br />
		<strong><?php _e( 'Country', 'woocommerce-gateway-mondido' ) ?>:</strong> <?php echo esc_html( $transaction['payment_details']['country_code'] ); ?>
		<br />
	<?php endif; ?>
	<?php if ($transaction['transaction_type'] === 'bank'): ?>
		<strong><?php _e( 'Bank name', 'woocommerce-gateway-mondido' ) ?>:</strong> <?php echo esc_html( $transaction['payment_details']['bank_name'] ); ?>
		<br />
		<strong><?php _e( 'Last digits of account', 'woocommerce-gateway-mondido' ) ?>:</strong> <?php echo esc_html( $transaction['payment_details']['bank_acc_lastdigits'] ); ?>
		<br />
	<?php endif; ?>
	<?php if ($transaction['transaction_type'] === 'swish'): ?>
		<strong><?php _e( 'Swish number', 'woocommerce-gateway-mondido' ) ?>:</strong> <?php echo esc_html( $transaction['payment_details']['swish_number'] ); ?>
		<br />
	<?php endif; ?>
	<a href="<?php echo esc_url( $transaction['href'] ); ?>" target="_blank"><?php _e( 'Payment Link', 'woocommerce-gateway-mondido' ) ?></a>
	<br />
	<a href="<?php echo esc_url( 'https://admin.mondido.com/transactions/' . $transaction['id'] ); ?>" target="_blank"><?php _e( 'View at Mondido', 'woocommerce-gateway-mondido' ) ?></a>
	<br />

	<?php if ( $transaction['status'] === 'authorized' ): ?>
		<button id="mondido_capture"
				data-nonce="<?php echo wp_create_nonce( 'mondido' ); ?>"
				data-transaction-id="<?php echo esc_html( $transaction['id'] ); ?>"
				data-order-id="<?php echo esc_html( $order->get_id() ); ?>">
			<?php _e( 'Capture Payment', 'woocommerce-gateway-mondido' ) ?>
		</button>
	<?php endif; ?>
</div>
