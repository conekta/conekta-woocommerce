jQuery(document).ready(function ($) {

	var $form = $('form.checkout,form#order_review');

	$form.bind('checkout_place_order_conektacard', function (e) {
		if ($form.find('[name="conekta_token"]').length) return true;

		var $paymentErrors = $form.find('.payment_method_conektacard .payment-errors');
		var showError = function (message) {
			$paymentErrors.text(message);
			$form.unblock();
		}

		$paymentErrors.html('');

		$form.block({
			message: null,
			overlayCSS: {
				background: "#fff url(" + woocommerce_params.ajax_loader_url + ") no-repeat center",
				backgroundSize: "16px 16px",
				opacity: 0.6
			}
		});

		function callBack(token) {
			if (!token.id) {
				if (!token.card) {
					return showError('El número de tarjeta es inválido');
				} else if (!token.cvc) {
					return showError('El cvc es inválido');
				} else if (!token.date) {
					return showError('La fecha de la tarjeta es inválida');
				}
			} else {
				if($('<input type="hidden" name="conekta_token" />').length > 0)
					$('<input type="hidden" name="conekta_token" />').val(token.id);
				else
					$form.append($('<input type="hidden" name="conekta_token" />').val(token.id));
				$form.submit();
			}
		}

		createToken('conekta-card-number', callBack, {
			name: $('#conekta-card-name').val(),
			expMonth: $('#card_expiration').val(),
			expYear: $('#card_expiration_yr').val()
		});

		return false;
	});

	$form.on('click', ':submit', function () {
		$('.woocommerce_error, .woocommerce-error, .woocommerce-message, .woocommerce_message').remove();
		$form.find('[name="conekta_token"]').remove();
	});

	$(document.body).on('updated_checkout wc-credit-card-form-init', function () {
		var inputStyle = {
			'padding': '.6180469716em',
			'background-color': 'rgb(242, 242, 242)',
			'color': 'rgba(67, 69, 75, 1)',
			'box-shadow': 'inset 0 1px 1px rgba(0,0,0,.125)',
			'box-sizing': 'border-box',
			'font-weight': '400',
			'line-height': '1.618',
			'font-size': '100%',
			'margin': '0',
			'vertical-align': 'baseline',
			'border': '0',
		};
		var cardComponent = {
			style: inputStyle, 
			idElement: 'conekta-card-number',
			advanceStyle:[
				{name: 'valid', style: {'box-shadow': 'inset 2px 0 0 #0f834d'}}
			],
			animation: true
		};
		var cvcComponent = {
			style: inputStyle,
			advanceStyle:[
				{name: 'valid', style: {'box-shadow': 'inset 2px 0 0 #0f834d'}}
			],
			idElement: 'conekta-card-cvc'
		}

		renderComponents(wc_conekta_params.public_key, cardComponent, cvcComponent);
	});
});