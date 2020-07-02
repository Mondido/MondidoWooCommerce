<?php
/** @var WC_Payment_Gateway $gateway */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly
?>

<?php if ( $description = $gateway->get_description() ): ?>
	<?php echo wpautop( wptexturize( $description ) ); ?>
<?php endif; ?>

<?php $logos = $gateway->get_active_payment_logos(); ?>
<?php if ( count( $logos ) > 0 ): ?>
    <ul class="mondido-logos">
		<?php foreach ( $logos as $logo ): ?>
            <li class="mondido-logo">
				<?php
				$image_url = plugins_url( '/assets/images/' . $logo . '.png', dirname( __FILE__ ) . '/../../../' );
				$method    = $gateway->form_fields['logos']['options'][$logo];
				?>
                <img src="<?php echo esc_url( $image_url ) ?>" alt="<?php echo esc_html( sprintf( __( 'Pay with %s on Mondido', 'woocommerce-gateway-mondido' ), $method ) ); ?>">
            </li>
		<?php endforeach; ?>
    </ul>
<?php endif; ?>
