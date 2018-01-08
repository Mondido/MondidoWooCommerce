<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** @var string $payment_url */
?>
<style type="text/css">
    .order_details {
        display: none;
    }
</style>
<iframe id="mondido-iframe"
        src="<?php echo esc_html($payment_url); ?>"
        frameborder="0" scrolling="no" style="height: 860px; width: 100%;">
</iframe>
<script>
    //iFrameResize( [], '#mondido-iframe' );
</script>
