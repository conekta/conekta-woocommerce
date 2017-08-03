<?php
/*
 * Title   : Conekta Payment extension for WooCommerce
 * Author  : Cristina Randall
 * Url     : https://www.conekta.io/es/docs/plugins/woocommerce
 */
?>
<!-- NC fix -->
<script type="text/javascript" src="https://conektaapi.s3.amazonaws.com/v0.3.2/js/conekta.js"></script>
<script>
var alreadyTokenize = false;
var firsTime = true;
jQuery(document).ready(function () {
  Conekta.setPublishableKey('**Insert public Key test or live, you can retrieve it from PHP with a var inside a script inside an echo**');
  jQuery('form.checkout').submit(function(event){
    /* This work with my current theme but its the same if you have it in a tab or something like that*/
    var seleccionado = jQuery('input.input-radio:checked');
    if(jQuery(seleccionado).attr('id') != 'payment_method_conektacard'){
      /* This is because conekta form for credit cards, sends an error if none of their fields are completed, since you're not gonna use them in this option we can remove the div */
      jQuery('.payment_method_conektacard').remove();
    }
    else{
      if(firsTime){
        /* If is the selected option, because we're assuming is not our default option, we show it */
        event.preventDefault();
        jQuery('.payment_method_conektacard').each(function(){
          jQuery(this).show();
        });
        firsTime = false;
      }
      else{
        if(!alreadyTokenize){
          event.preventDefault();
          var $form = jQuery(this);
          jQuery('#place_order').prop('disabled', true);
          return Conekta.token.create($form, conektaSuccessResponseHandler, conektaErrorResponseHandler);
        }
      }
    }
  });
});



var conektaSuccessResponseHandler = function(response){
  alreadyTokenize = true;
  jQuery('form.checkout').append(jQuery('<input type="hidden" name="conekta_token" />').val(response.id));
  jQuery('#place_order').prop('disabled', false);
  jQuery('input#place_order').click(); 
  return true;
};

var conektaErrorResponseHandler = function(response){
  alert(response.message);
  jQuery('#place_order').prop('disabled', false);
  return false;
};
</script>
<!-- //NC FIX -->




<div class="clear"></div>
<span style="width: 100%; float: left; color: red;" class='payment-errors required'></span>
<div class="form-row form-row-wide">
  <label for="conekta-card-number"><?php echo esc_html($this->lang_options["card_number"]); ?><span class="required">*</span></label>
  <input id="conekta-card-number" class="input-text" type="text" data-conekta="card[number]" />
</div>

<div class="form-row form-row-wide">
  <label for="conekta-card-name"> <?php echo esc_html($this->lang_options["card_name"]); ?><span class="required">*</span></label>
  <input id="conekta-card-name" type="text" data-conekta="card[name]" class="input-text" />
</div>

<div class="clear"></div>

<p class="form-row form-row-first">
    <label for="card_expiration"><?php echo esc_html($this->lang_options["month_options"]) ?> <span class="required">*</span></label>
    <select id="card_expiration" data-conekta="card[exp_month]" class="month" autocomplete="off">
             <option selected="selected" value=""><?php echo esc_html($this->lang_options["month"]) ?></option>
             <?php foreach($this->lang_options["card_expiration"] as $month => $description): ?>
              <option value="<?php echo esc_html($month); ?>"><?php echo esc_html($description); ?></option>
             <?php endforeach; ?>
    </select>
</p>
<p class="form-row form-row-last">
    <label><?php echo esc_html($this->lang_options["year_options"]) ?><span class="required">*</span></label>
    <select id="card_expiration_yr" data-conekta="card[exp_year]" class="year" autocomplete="off">
              <option selected="selected" value=""> <?php echo esc_html($this->lang_options["year"]) ?></option>
              <?php 
              $start_year = (integer) date("Y"); 
              $end_year = (integer) date("Y", strtotime("+10 years")); 
              for($i = $start_year; $i <= $end_year; $i++): ?>
                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
              <?php endfor; ?>
    </select>
</p>

<!--<div class="form-row form-row-wide">
  <label for="conekta-card-expiration"><?php echo esc_html($this->lang_options["card_expiration"]); ?> (MM/YY) <span class="required">*</span></label>
  <input id="conekta-card-expiration" data-conekta="card[expiration]" class="input-text" type="text" autocomplete="off" placeholder="MM / YY" />
</div>-->

<div class="clear"></div>

<p class="form-row form-row-first">
    <label for="conekta-card-cvc">CVC <span class="required">*</span></label>
    <input id="conekta-card-cvc" class="input-text" type="text" maxlength="4" data-conekta="card[cvc]" value=""  style="border-radius:6px"/>
</p>

<?php if ($this->enablemeses): ?>
<p class="form-row form-row-last">
  <label><?php echo esc_html($this->lang_options["payment_type"]) ?><span class="required">*</span></label>
  <select id="monthly_installments" name="monthly_installments" autocomplete="off">
    <option selected="selected" value="1"><?php echo esc_html($this->lang_options["single_payment"]) ?></option>
    <?php foreach($this->lang_options["monthly_installments"] AS $months => $description): ?>
      <option value="<?php echo esc_html($months); ?>"><?php echo esc_html($description); ?></option>
    <?php endforeach; ?>
  </select>
</p>

<?php endif; ?>
<div class="clear"></div>
