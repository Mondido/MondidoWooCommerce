<?php
/**
 * Mondido payment actions file
 *
 * @author Mondido
 * @package mondido
 **/

/**
 *  Call class variables WC_Order
 *
 * @var WC_Order $order
 */

/**
 *  Call variables $transaction
 *
 * @var array $transaction
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly
?>

<div>
	<strong><?php esc_html_e( 'Payment Info', 'woocommerce-gateway-mondido' ); ?></strong>
	<br />
	<strong><?php esc_html_e( 'Type', 'woocommerce-gateway-mondido' ); ?>:</strong> <?php echo esc_html( $transaction['transaction_type'] ); ?>
	<br />
	<strong><?php esc_html_e( 'Status', 'woocommerce-gateway-mondido' ); ?>: </strong> <?php echo esc_html( $transaction['status'] ); ?>
	<br />
	<?php if ( 'credit_card' === $transaction ['transaction_type'] ) : ?>
		<strong><?php esc_html_e( 'Number', 'woocommerce-gateway-mondido' ); ?>:</strong> <?php echo esc_html( $transaction['card_number'] ); ?>
		<br />
		<strong><?php esc_html_e( 'Name', 'woocommerce-gateway-mondido' ); ?>:</strong> <?php echo esc_html( $transaction['card_holder'] ); ?>
		<br />
		<strong><?php esc_html_e( 'Card', 'woocommerce-gateway-mondido' ); ?>:</strong> <?php echo esc_html( $transaction['payment_details']['card_type'] ); ?>
		<br />
		<strong><?php esc_html_e( '3D Secure', 'woocommerce-gateway-mondido' ); ?>:</strong> <?php echo empty( $transaction ['mpi_ref'] ) ? esc_html_e( 'No', 'woocommerce' ) : esc_html_e( 'Yes', 'woocommerce' ); ?>
		<br />
	<?php endif; ?>
	<?php if ( 'invoice' === $transaction ['transaction_type'] ) : ?>
		<strong><?php esc_html_e( 'Social Security Number', 'woocommerce-gateway-mondido' ); ?>:</strong> <?php echo esc_html( $transaction['payment_details']['ssn'] ); ?>
		<br />
		<strong><?php esc_html_e( 'First name', 'woocommerce-gateway-mondido' ); ?>:</strong> <?php echo esc_html( $transaction['payment_details']['first_name'] ); ?>
		<br />
		<strong><?php esc_html_e( 'Last name', 'woocommerce-gateway-mondido' ); ?>:</strong> <?php echo esc_html( $transaction['payment_details']['last_name'] ); ?>
		<br />
		<strong><?php esc_html_e( 'Email', 'woocommerce-gateway-mondido' ); ?>:</strong> <?php echo esc_html( $transaction['payment_details']['email'] ); ?>
		<br />
		<strong><?php esc_html_e( 'Phone', 'woocommerce-gateway-mondido' ); ?>:</strong> <?php echo esc_html( $transaction['payment_details']['phone'] ); ?>
		<br />
		<strong><?php esc_html_e( 'Address 1', 'woocommerce-gateway-mondido' ); ?>:</strong> <?php echo esc_html( $transaction['payment_details']['address_1'] ); ?>
		<br />
		<strong><?php esc_html_e( 'Address 2', 'woocommerce-gateway-mondido' ); ?>:</strong> <?php echo esc_html( $transaction['payment_details']['address_2'] ); ?>
		<br />
		<strong><?php esc_html_e( 'City', 'woocommerce-gateway-mondido' ); ?>:</strong> <?php echo esc_html( $transaction['payment_details']['city'] ); ?>
		<br />
		<strong><?php esc_html_e( 'Postal Code', 'woocommerce-gateway-mondido' ); ?>:</strong> <?php echo esc_html( $transaction['payment_details']['zip'] ); ?>
		<br />
		<strong><?php esc_html_e( 'Country', 'woocommerce-gateway-mondido' ); ?>:</strong> <?php echo esc_html( $transaction['payment_details']['country_code'] ); ?>
		<br />
	<?php endif; ?>
	<?php if ( 'bank' === $transaction['transaction_type'] ) : ?>
		<strong><?php esc_html_e( 'Bank name', 'woocommerce-gateway-mondido' ); ?>:</strong> <?php echo esc_html( $transaction['payment_details']['bank_name'] ); ?>
		<br />
		<strong><?php esc_html_e( 'Last digits of account', 'woocommerce-gateway-mondido' ); ?>:</strong> <?php echo esc_html( $transaction['payment_details']['bank_acc_lastdigits'] ); ?>
		<br />
	<?php endif; ?>
	<?php if ( 'swish' === $transaction['transaction_type'] ) : ?>
		<strong><?php esc_html_e( 'Swish number', 'woocommerce-gateway-mondido' ); ?>:</strong> <?php echo esc_html( $transaction['payment_details']['swish_number'] ); ?>
		<br />
	<?php endif; ?>
	<a href="<?php echo esc_url( $transaction['href'] ); ?>" target="_blank"><?php esc_html_e( 'Payment Link', 'woocommerce-gateway-mondido' ); ?></a>
	<br />
	<a href="<?php echo esc_url( 'https://admin.mondido.com/transactions/' . $transaction['id'] ); ?>" target="_blank"><?php esc_html_e( 'View at Mondido', 'woocommerce-gateway-mondido' ); ?></a>
	<br />

	<?php if ( 'authorized' === $transaction ['status'] ) : ?>
		<button id="mondido_capture"
				data-nonce="<?php echo esc_attr( wp_create_nonce( 'mondido' ) ); ?>"
				data-transaction-id="<?php echo esc_attr( $transaction['id'] ); ?>"
				data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
			<?php esc_html_e( 'Capture Payment', 'woocommerce-gateway-mondido' ); ?>
		</button>
	<?php endif; ?>
</div>
