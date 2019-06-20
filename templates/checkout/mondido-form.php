<?php
/**
 * Mondido checkout mondido form
 *
 * @author Mondido
 * @package mondido
 **/


/** @var array $fields */
/** @var WC_Order $order */
/** @var WC_Payment_Gateway $gateway */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly
?>
<div class="mondido-overlay">
	<img src="<?php echo plugins_url( '/assets/images/ring-alt.gif', dirname( __FILE__ ) . '/../../../' ); ?>">
</div>

<form action="https://pay.mondido.com/v1/form" method="post" id="mondido_form" style="display: none;">
	<?php foreach ( $fields as $key => $value ) : ?>
		<input type="hidden" name="<?php echo esc_html( $key ); ?>" value="<?php echo esc_html( is_array( $value ) ? json_encode( $value ) : $value ); ?>">
	<?php endforeach; ?>
</form>

<script>
	window.onload = function () {
		document.getElementById('mondido_form').submit();
	};
</script>
