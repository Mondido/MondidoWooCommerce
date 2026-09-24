(function ($) {
	'use strict';

	function toggleSsnRequirement() {
		var isDirectBank = $('input[name="payment_method"]:checked').val() === 'mondido_bank';
		var $field = $('#billing_ssn_field');
		var $input = $('#billing_ssn');
		var $label = $field.find('label');

		$input.prop('required', isDirectBank).attr('aria-required', isDirectBank ? 'true' : 'false');
		$field.toggleClass('validate-required', isDirectBank);
		$label.find('.optional, abbr.required').remove();

		if (isDirectBank) {
			$label.append(' <abbr class="required" title="required">*</abbr>');
		}
	}

	$(document.body).on('change', 'input[name="payment_method"]', toggleSsnRequirement);
	$(document.body).on('updated_checkout', toggleSsnRequirement);
	$(toggleSsnRequirement);
}(jQuery));
