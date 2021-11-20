/* Simple Product Discounts Frontend jQuery */

// Display current discounted price and calculate total on product page

jQuery(document).ready(function($) {

	$('.cart .quantity input').on('change input', function(e) {
		var currentVal = $(this).val();
		if (isNaN(currentVal) || currentVal < 1 || currentVal.indexOf('.') > 0) {
			$(this).val(1);
		}
		var currentVal = $(this).val();
		var currentPrc = parseFloat($('ins .woocommerce-Price-amount').text().replace(',','.'));
		var dfc = $('.discount_fields_count').val();
		if ($.isNumeric(dfc)) {
			$( ".discount_price_field" ).each(function( index ) {
				dq = Number($(this).find('.discount_price_qty').text().replace(/\D+/g,''));
				dp = parseFloat($(this).find('.discount_price_prc').text().match(/([0-9]*[.])?[0-9]+/));
				if (!isNaN(dq) && !isNaN(dp)) {
					if (currentVal >= dq) currentPrc = dp;
				}
			});
		}

		$('.price_price').html( currentPrc );
		$('.total_price').html( currentVal * currentPrc );
	});

});