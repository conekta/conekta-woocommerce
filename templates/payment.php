<?php
/*
 * Title   : Conekta Payment extension for WooCommerce
 * Author  : Cristina Randall
 * Url     : https://www.conekta.io/es/docs/plugins/woocommerce
 */
?>
<div id="conekta_pub_key" class="hidden" style="display:none" data-publishablekey="<?=$this->publishable_key ?>"> </div>
<div class="clear"></div>
<span style="width: 100%; float: left; color: red;" class='payment-errors required'></span>
<p class="form-row form-row-first">
  <label>Número de tarjeta de crédito <span class="required">*</span></label>
  <input class="input-text" type="text" size="19" maxlength="19" data-conekta="card[number]" />
</p>
<p class="form-row form-row-last">
<label> Nombre del tarjetahabiente <span class="required">*</span></label>
<input type="text" data-conekta="card[name]" class="input-text" />
</p>
<div class="clear"></div>
<p class="form-row form-row-first">
  <label>Mes de expiración <span class="required">*</span></label>
<select id="card_expiration" data-conekta="card[exp_month]" class="month" autocomplete="off">
         <option selected="selected" value=""> Mes</option>
         <option value="1">01 - January</option>
         <option value="2">02 - February</option>
         <option value="3">03 - March</option>
         <option value="4">04 - April</option>
         <option value="5">05 - May</option>
         <option value="6">06 - June</option>
         <option value="7">07 - July</option>
         <option value="8">08 - August</option>
         <option value="9">09 - September</option>
         <option value="10">10 - October</option>
         <option value="11">11 - November</option>
         <option value="12">12 - December</option>
       </select>
</p>
<p class="form-row form-row-last">
  <label>Año de expiración <span class="required">*</span></label>
<select id="card_expiration_yr" data-conekta="card[exp_year]" class="year" autocomplete="off">
          <option selected="selected" value=""> Año</option>
          <?php
            for($i=date('Y'); $i<=date('Y')+10; $i++) echo '<option value="'.$i.'">'.$i.'</option>';
          ?>
</select>
</p>
<div class="clear"></div>
<p class="form-row form-row-first">
    <label>CVC <span class="required">*</span></label>
    <input class="input-text" type="text" maxlength="4" data-conekta="card[cvc]" value=""  style="border-radius:6px"/>
</p>
<div class="clear"></div>

<script>

  var initConektaCheckout = function(){
    jQuery(function($) {
    var $form = $('form.checkout,form#order_review');

           var conektaErrorResponseHandler = function(response) {
           $form.find('.payment-errors').text(response.message);
           $form.unblock();
             };
           
    var conektaSuccessResponseHandler = function(response) {
      $form.append($('<input type="hidden" name="conektaToken" />').val(response.id));
      $form.submit();

  };

    $('body').on('click', '#place_order,form#order_review input:submit', function(){
      if(jQuery('.payment_methods input:checked').val() !== 'ConektaCard')
      {
        return true;
      }
      Conekta.setPublishableKey($('#conekta_pub_key').data('publishablekey'));
      Conekta.token.create($form, conektaSuccessResponseHandler, conektaErrorResponseHandler);
      return false;
    });


    $('body').on('click', '#place_order,form.checkout input:submit', function(){
      if(jQuery('.payment_methods input:checked').val() !== 'ConektaCard')
      {
        return true;
      }
      $('form.checkout').find('[name=conektaToken]').remove()
    })

    $('form.checkout').bind('#place_order,checkout_place_order_ConektaCard', function(e){

      if($('input[name=payment_method]:checked').val() != 'ConektaCard'){
          return true;
      }

      $form.find('.payment-errors').html('');
      $form.block({message: null,overlayCSS: {background: "#fff url(" + woocommerce_params.ajax_loader_url + ") no-repeat center",backgroundSize: "16px 16px",opacity: .6}});

      if( $form.find('[name=conektaToken]').length)
        return true;

      Conekta.setPublishableKey($('#conekta_pub_key').data('publishablekey'));
      Conekta.token.create($form, conektaSuccessResponseHandler, conektaErrorResponseHandler);
      return false;
    });
  });
};

if(typeof jQuery=='undefined')
{
    var headTag = document.getElementsByTagName("head")[0];
    var jqTag = document.createElement('script');
    jqTag.type = 'text/javascript';
    jqTag.src = 'https://ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js';
    jqTag.onload = initConektaCheckout;
    headTag.appendChild(jqTag);
} else {
   initConektaCheckout()
}
</script>
