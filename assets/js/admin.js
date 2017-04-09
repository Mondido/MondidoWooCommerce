jQuery(document).ready(function ($) {
	$(document).on('click', '#mondido_capture', function (e) {
		e.preventDefault();

		var nonce = $(this).data('nonce');
		var transaction_id = $(this).data('transaction-id');
		var order_id = $(this).data('order-id');
		var self = $(this);
		$.ajax({
			url       : Mondido_Admin.ajax_url,
			type      : 'POST',
			data      : {
				action        : 'mondido_capture',
				nonce         : nonce,
				transaction_id: transaction_id,
				order_id      : order_id
			},
			beforeSend: function () {
				self.data('text', self.html());
				self.html(Mondido_Admin.text_wait);
				self.prop('disabled', true);
			},
			success   : function (response) {
				self.html(self.data('text'));
				self.prop('disabled', false);
				if (!response.success) {
					alert(response.data);
					return false;
				}

				window.location.href = location.href;
			}
		});
	});
});
