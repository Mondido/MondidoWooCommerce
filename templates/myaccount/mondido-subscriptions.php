<?php
/** @var $subscriptions */
?>
<style type="text/css">
    .my_account_orders .button.mondido-cancel::after {
        content: "\f057" !important;
    }
</style>
<script>
    jQuery(document).ready(function ($) {
        $(document).on('click', '.mondido-cancel', function (e) {
            e.preventDefault();
            var id = $(this).data('id');
            var nonce = $(this).data('nonce');
            $.ajax({
                url: "<?php echo admin_url( 'admin-ajax.php' ); ?>",
                type: 'POST',
                data: {
                    action: 'mondido_cancel_subscription',
                    nonce: nonce,
                    id: id
                },
                success: function (result) {
                    self.location.href = location.href;
                }
            });
        });
    });
</script>
<div class="mondido-dashboard">
	<table class="shop_table shop_table_responsive my_account_orders">
		<thead>
		<tr>
			<th>
				<span class="nobr">
					<?php _e( 'Subscription', 'woocommerce-gateway-mondido' ); ?>
				</span>
			</th>
            <th>&nbsp;</th>
		</tr>
		</thead>
		<tbody>
		<?php foreach ($subscriptions as $subscription): ?>
			<?php if ($subscription['status'] === 'active'): ?>
			<tr>
				<td>
					<?php echo WC_Mondido_Subscriptions_Account::formatSubscriptionDescription( $subscription ); ?>
				</td>
				<td>
                    <a href="#" data-id="<?php echo esc_attr( $subscription['id'] ); ?>" data-nonce="<?php echo wp_create_nonce( 'mondido_subscriptions' ); ?>" class="button view mondido-cancel"><?php _e( 'Cancel', 'woocommerce-gateway-mondido' ); ?></a>
				</td>
			</tr>
			<?php endif; ?>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>