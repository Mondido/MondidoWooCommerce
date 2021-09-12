<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly
?>
<div id='subscription_options' class='panel woocommerce_options_panel'>
    <div class='options_group'>
        <?php if ($error): ?>
            <p class="form-field _mondido_plan_id_field ">
                <input type="hidden" name="_mondido_plan_id" value="<?php echo esc_attr( $plan_id ); ?>" />
                <span id="message" class="error">
                    <?php echo sprintf( esc_html__( 'Mondido Error: %s', 'woocommerce-gateway-mondido' ), $error ); ?>
                    <br />
                    <?php _e( 'Please check Mondido settings.', 'woocommerce-gateway-mondido' ); ?>
                </span>
            </p>
        <?php else:
            woocommerce_wp_select(
                array(
                    'id'      => '_mondido_plan_id',
                    'value'   => $plan_id,
                    'label'   => __( 'Subscription plan', 'woocommerce-gateway-mondido' ),
                    'options' => $options
                )
            );
            woocommerce_wp_checkbox(
                array(
                    'id'          => '_mondido_plan_include',
                    'label'       => '',
                    'description' => __( 'Add product to the recurring payments', 'woocommerce-gateway-mondido' ),
                    'value'   => $include,
                )
            );
        endif; ?>
    </div>
</div>
