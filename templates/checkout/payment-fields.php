<?php
/** @var WC_Payment_Gateway $gateway */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly
?>

<?php if ( $description = $gateway->get_description() ): ?>
	<?php echo wpautop( wptexturize( $description ) ); ?>
<?php endif; ?>

<?php if ( count( $gateway->logos ) > 0 ): ?>
	<div class="mondido-logos">
		<?php foreach ( $gateway->logos as $logo ): ?>
			<?php
			$image_url = plugins_url( '/assets/images/' . $logo . '.png', dirname( __FILE__ ) . '/../../../' );
			$method    = $gateway->form_fields['logos']['options'][ $logo ];
			?>
			<img src="<?php echo esc_url( $image_url ) ?>" class="mondido-paymentmethod" alt="<?php echo esc_html( sprintf( __( 'Pay with %s on Mondido', 'woocommerce-gateway-mondido' ), $method ) ); ?>">
		<?php endforeach; ?>
	</div>
<?php endif; ?>
