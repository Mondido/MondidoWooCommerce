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
	<strong><?php _e( 'Number', 'woocommerce-gateway-mondido' ) ?>:</strong> <?php echo esc_html( $transaction['card_number'] ); ?>
	<br />
	<strong><?php _e( 'Name', 'woocommerce-gateway-mondido' ) ?>:</strong> <?php echo esc_html( $transaction['card_holder'] ); ?>
	<br />
	<strong><?php _e( 'Card', 'woocommerce-gateway-mondido' ) ?>:</strong> <?php echo esc_html( $transaction['payment_details']['card_type'] ); ?>
	<br />
	<strong><?php _e( 'Status', 'woocommerce-gateway-mondido' ) ?>: </strong> <?php echo esc_html( $transaction['status'] ); ?>
	<br />
	<?php if ($transaction['transaction_type'] === 'credit_card'): ?>
		<strong><?php _e( '3D Secure', 'woocommerce-gateway-mondido' ) ?>:</strong> <?php echo empty( $transaction['mpi_ref'] ) ? __('No', 'woocommerce') : __('Yes', 'woocommerce') ?>
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
				data-order-id="<?php echo esc_html( $order->id ); ?>">
			<?php _e( 'Capture Payment', 'woocommerce-gateway-mondido' ) ?>
		</button>
	<?php endif; ?>
</div>
