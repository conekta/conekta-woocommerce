jQuery(document).ready(function($) {
	$('body').on('change', 'input[name="latest_card"]',  function(){
		console.log( $(this).val()=="false");
		if( $(this).val()=="false"){
			$(".payment_box.payment_method_conektacard .new-card").show();
		}
		else{
			$(".payment_box.payment_method_conektacard .new-card").hide();
		}
	});
	
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

	$('form.checkout').bind('checkout_place_order', function (e) {

		if ($('input[name="payment_method"]:checked').val() != 'conektacard') {
			return true;
		}
		$form.find('.payment-errors').html('');
		$form.block({message: null, overlayCSS: {background: "#fff url(" + woocommerce_params.ajax_loader_url + ") no-repeat center", backgroundSize: "16px 16px", opacity: 0.6}});

		if ($form.find('[name="conekta_token"]').length){
			return true;
		}

		if($('input[name="latest_card"]:checked').val() == 'false'){
			Conekta.token.create($form, conektaSuccessResponseHandler, conektaErrorResponseHandler);
		}
		else{
			$form.append($('<input type="hidden" name="conekta_token" />').val($('input[name="latest_card"]:checked').val()));
			$form.submit();
		}

		return false;
	}); 
});
