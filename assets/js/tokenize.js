jQuery(document).ready(function ($) {
  var checkout;
  var $form = $("form.checkout,form#order_review");
  var $paymentErrors = $form.find('.payment_method_conektacard .payment-errors');
  var showError = function (message) {
    $paymentErrors.text(message);
    $form.unblock();
  }

  $paymentErrors.html('');

  $("body").on("click", "form#order_review input:submit", function () {
    if ($("input[name=payment_method]:checked").val() != "conektacard") {
      return true;
    }
    return false;
  });

  $("body").on("click", "form.checkout input:submit", function () {
    $(
      ".woocommerce_error, .woocommerce-error, .woocommerce-message, .woocommerce_message"
    ).remove();
    $("form.checkout").find('[name="conekta_token"]').remove();
  });

  $("form.checkout").bind("checkout_place_order_conektacard", function (e) {
    e.preventDefault();

    var $paymentErrors = $form.find('.payment_method_conektacard .payment-errors');
    var showError = function (message) {
      $paymentErrors.text(message);
      $form.unblock();
    }

    $form.find(".payment-errors").html("");
    $form.block({
      message: null,
      overlayCSS: {
        background: "#fff url(" +
          woocommerce_params.ajax_loader_url + ") no-repeat center",
        backgroundSize: "16px 16px",
        opacity: 0.6
      }
    });

    if ($form.find('[name="conekta_token"]').length) {
      return true;
    }
    var onBuy = function (token) {
      if (token.id) {
        node = document.createElement("input");
        node.type = "hidden";
        node.name = "conekta_token";
        node.value = token.id;
        $form.append(node);
        $form.submit();
      } else {
        if (!token.card) {
          return showError('El número de tarjeta es inválido');
        } else if (!token.cvc) {
          return showError('El cvc es inválido');
        } else if (!token.date) {
          return showError('La fecha de la tarjeta es inválida');
        }
      }
    };

    cardHolderName = document.getElementById('conekta-card-name').value;
    expirationMonth = document.getElementById('card_expiration').value;
    expirationYear = document.getElementById('card_expiration_yr').value;

    checkout.tokenize({
      name: cardHolderName,
      expYear: expirationYear,
      expMonth: expirationMonth
    }, onBuy);

    return false;
  });

  $(document.body).on("updated_checkout wc-credit-card-form-init", function () {
    var tokenComponent = {
      'box-shadow': 'inset 2px 0 0 #0f834d;',
      'padding': '.6180469716em;',
      'background-color': '#f2f2f2;',
      'color': '#43454b;',
      'outline': '0;',
      'border': '0;',
      '-webkit-appearance': 'none;',
      'box-sizing': 'border-box;',
      'font-weight': '400;',
      'height': '45.65px',
      'width': '100%;',
      'font-size': '100%;',
      'margin': '0;',
      'vertical-align': 'baseline;',
      'font-family': '"Source Sans Pro",HelveticaNeue-Light,"Helvetica Neue Light","Helvetica Neue",Helvetica,Arial,"Lucida Grande",sans-serif;',
      'line-height': '1.618;',
      'text-rendering': 'optimizeLegibility;',
      'list-style': 'none !important;',
      'word-wrap': 'break-word;'
    };

    var cardComponent = {
      style: tokenComponent,
      advanceStyle: [{
        name: "valid",
        style: {
          "box-shadow": "inset 2px 0 0 #0f834d",
        }
      }],
      animation: true,
      elementID: "conekta-card-number"
    };

    var cvcComponent = {
      style: tokenComponent,
      advanceStyle: [{
        name: "valid",
        style: {
          "box-shadow": "inset 2px 0 0 #0f834d"
        }
      }],
      elementID: "conekta-card-cvc"
    };

    checkout = new ConektaDirect(
      wc_conekta_params.public_key,
      cardComponent,
      cvcComponent
    );
  });
});