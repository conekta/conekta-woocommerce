jQuery(document).ready(function($) {
	Conekta.setPublishableKey(wc_conekta_params.public_key);

	var $form = $('form.checkout,form#order_review');

	var conektaErrorResponseHandler = function(response) {
		$form.find('.payment-errors').text(response.message_to_purchaser);
		$form.unblock();
	};

	var conektaSuccessResponseHandler = function(response) {
		$form.append($('<input type="hidden" name="conekta_token" />').val(response.id));
		$form.submit();
	};

	$('body').on('click', 'form#order_review input:submit', function(){
		if($('input[name=payment_method]:checked').val() != 'conektacard'){
			return true;
		}

		return false;
	});

	$('body').on('click', 'form.checkout input:submit', function(){
		$('.woocommerce_error, .woocommerce-error, .woocommerce-message, .woocommerce_message').remove();
		$('form.checkout').find('[name="conekta_token"]').remove();
	});

	$('form.checkout').bind('checkout_place_order_conektacard', function (e) {
		$form.find('.payment-errors').html('');
		$form.block({message: null, overlayCSS: {background: "#fff url(" + woocommerce_params.ajax_loader_url + ") no-repeat center", backgroundSize: "16px 16px", opacity: 0.6}});

		if ($form.find('[name="conekta_token"]').length){
			return true;
		}

		Conekta.token.create($form, conektaSuccessResponseHandler, conektaErrorResponseHandler);

		return false;
	}); 
});
