<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** @var string $product_id */
?>
<div style="clear: both">&nbsp;</div>

<button
    id="mondido-product-btn"
    type="button"
    name="buy-mondido"
    value="<?php echo esc_html($product_id); ?>"
    class="single_add_to_cart_button button alt">
    <?php _e( 'Buy now using Mondido Checkout', 'woocommerce-gateway-mondido' ); ?>
</button>

<script>

</script>

<div style="clear: both">&nbsp;</div>
