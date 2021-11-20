/* Simple Product Discounts admin jQuery */

jQuery(document).ready(function($) {

	$('.discount_add_field_button').click(function () {
		$(".loaderimage").show();
		var form = $(this).closest(".discount_group");
		var datastring = form.find("input").serialize();
		datastring = datastring + "&post_ID=" + $("#post_ID").val();
		console.log(datastring);
		$.ajax({
			type: "POST",
			url: ajaxurl+"?action=pdq_ajax_add_field",
			cache: false,
			data: datastring,
			dataType: 'json',
			success: function (data) {
				$(".loaderimage").hide();
				if (data.code == "success") {
					$(".discount_field_buttons").before(data.insert);
					$("#_fields_count").val(data.count);
				}
			},
			error: function (a,b,c) {console.log(a,b,c);}
		});
		return false;
	});
	$('.discount_upd_field_button').click(function () {
		$(".loaderimage").show();
		var form = $(this).closest(".discount_group");
		var datastring = form.find("input").serialize();
		datastring = datastring + "&post_ID=" + $("#post_ID").val();
		$.ajax({
			type: "POST",
			url: ajaxurl+"?action=pdq_ajax_upd_fields",
			cache: false,
			data: datastring,
			dataType: 'json',
			success: function (data) {
				$(".loaderimage").hide();
				if (data.code == "success") {
					fc = -(+$("#_fields_count").val() - data.count);
					if (fc < 0) $("div.discount_group").find(".discount_field").slice(fc).remove();
					$("#_fields_count").val(data.count);
				}
			},
			error: function (a,b,c) {console.log(a,b,c);}
		});
		return false;
	});
	
	$('.simple_pdq-notice .notice-dismiss').click(function() {
		$.ajax({
			type: "POST",
			url: ajaxurl+"?action=simple_pdq_remove_notification",
			cache: false,
			error: function (a,b,c) {console.log(a,b,c);}
		});
	});

});