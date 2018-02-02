<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

wc_print_notices();
?>

<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">
    &nbsp;
</form>
<script>
    jQuery(function ($) {
        'use strict';
        $(document).ready(function () {
            $('form.woocommerce-checkout').block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });

            $.ajax({
                type:		'POST',
                url:		WC_Gateway_Mondido_Checkout.place_order_url,
                dataType:   'json',
                success:	function( result ) {
                    window.location.href = result.data.redirect_url;
                }
            });
        });
    });
</script>
