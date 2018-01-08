jQuery( function( $ ) {
    'use strict';
    $( document ).on( 'click', '#mondido-checkout-btn', function (e) {
        e.preventDefault();

        var elm = $(this);

        $.ajax({
            type:		'POST',
            url:		WC_Gateway_Mondido_Checkout.place_order_url,
            //data:		$form.serialize(),
            dataType:   'json',
            success:	function( result ) {
                console.log(result);
                window.location.href = result.data.redirect_url;
            }//,
            //error:	function( jqXHR, textStatus, errorThrown ) {
            //    wc_checkout_form.submit_error( '<div class="woocommerce-error">' + errorThrown + '</div>' );
            //}
        });


        return;
    } );
} );
