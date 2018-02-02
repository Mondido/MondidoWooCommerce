jQuery(function ($) {
    'use strict';
    $(document).on('click', '#mondido-product-btn', function (e) {
        e.preventDefault();

        var elm = $(this),
            product_id = elm.closest('form.cart').find("[name='add-to-cart']").first().val(),
            qty = elm.closest('form.cart').find("[name='quantity']").first().val();

        elm.prop('disabled', true);

        var xhr = $.ajax({
            type: 'POST',
            url: WC_Gateway_Mondido_Checkout.buy_product_url,
            data: {
                product_id: product_id, qty: qty
            },
            dataType: 'json',
            success: function (result) {
                console.log(result);
                //window.location.href = result.data.redirect_url;
                $.ajax({
                    type:		'POST',
                    url:		WC_Gateway_Mondido_Checkout.place_order_url,
                    dataType:   'json',
                    success:	function( result ) {
                        console.log(result);
                        window.location.href = result.data.redirect_url;
                    }
                });
            }
        }).always(function (response) {
            elm.prop('disabled', false);
        });
    });

});
