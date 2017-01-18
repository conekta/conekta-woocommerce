<?php
/*
 * Title   : Conekta Payment extension for WooCommerce
 * Author  : Cristina Randall
 * Url     : https://www.conekta.io/es/docs/plugins/woocommerce
 */
?>
<div class="clear"></div>
<style>.hidden{display:none}</style>
<div class="form-row form-row-wide <?php if(!get_user_meta(get_current_user_id(), 'conekta_id', true)) echo 'hidden'; ?>">
  <input id="latest_card" class="input-radio" type="radio" name="latest_card" value="<?php echo get_user_meta(get_current_user_id(), 'conekta_id', true);?>" />
  <label for="latest_card" style="display:inline"><?php echo $this->lang_options["latest_card"]; ?><?php echo get_user_meta(get_current_user_id(), 'conekta_card_brand', true);?> - <?php echo get_user_meta(get_current_user_id(), 'conekta_card_last4', true);?></label>

</div>
<div class="form-row form-row-wide <?php if(!get_user_meta(get_current_user_id(), 'conekta_id', true)) echo 'hidden'; ?>">
  <input id="new_card" checked="checked" class="input-radio" type="radio" name="latest_card" value="false" />
  <label for="new_card" style="display:inline"><?php echo $this->lang_options["new_card"]; ?></label>
</div>
<span style="width: 100%; float: left; color: red;" class='payment-errors required'></span>
<div class="form-row form-row-wide new-card">
  <label for="conekta-card-number"><?php echo $this->lang_options["card_number"]; ?><span class="required">*</span></label>
  <input id="conekta-card-number" class="input-text" type="text" data-conekta="card[number]" />
</div>

<div class="form-row form-row-wide new-card">
  <label for="conekta-card-name"> <?php echo $this->lang_options["card_name"]; ?><span class="required">*</span></label>
  <input id="conekta-card-name" type="text" data-conekta="card[name]" class="input-text" />
</div>

<div class="clear new-card"></div>

<p class="form-row form-row-first new-card">
    <label for="card_expiration"><?php echo $this->lang_options["month_options"] ?> <span class="required">*</span></label>
    <select id="card_expiration" data-conekta="card[exp_month]" class="month" autocomplete="off">
             <option selected="selected" value=""><?php echo $this->lang_options["month"] ?></option>
             <?php foreach($this->lang_options["card_expiration"] as $month => $description): ?>
              <option value="<?php echo $month; ?>"><?php echo $description; ?></option>
             <?php endforeach; ?>
    </select>
</p>
<p class="form-row form-row-last new-card">
    <label><?php echo $this->lang_options["year_options"] ?><span class="required">*</span></label>
    <select id="card_expiration_yr" data-conekta="card[exp_year]" class="year" autocomplete="off">
              <option selected="selected" value=""> <?php echo $this->lang_options["year"] ?></option>
              <?php 
              $start_year = (integer) date("Y"); 
              $end_year = (integer) date("Y", strtotime("+10 years")); 
              for($i = $start_year; $i <= $end_year; $i++): ?>
                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
              <?php endfor; ?>
    </select>
</p>

<!--<div class="form-row form-row-wide">
  <label for="conekta-card-expiration"><?php echo $this->lang_options["expiration"]; ?> (MM/YY) <span class="required">*</span></label>
  <input id="conekta-card-expiration" data-conekta="card[expiration]" class="input-text" type="text" autocomplete="off" placeholder="MM / YY" />
</div>-->

<div class="clear new-card"></div>

<p class="form-row form-row-first new-card">
    <label for="conekta-card-cvc">CVC <span class="required">*</span></label>
    <input id="conekta-card-cvc" class="input-text" type="text" maxlength="4" data-conekta="card[cvc]" value=""  style="border-radius:6px"/>
</p>

<?php if ($this->enablemeses): ?>
<p class="form-row form-row-last">
  <label><?php echo $this->lang_options["payment_type"] ?><span class="required">*</span></label>
  <select id="monthly_installments" name="monthly_installments" autocomplete="off">
    <option selected="selected" value="1"><?php echo $this->lang_options["single_payment"] ?></option>
    <?php foreach($this->lang_options["monthly_installments"] AS $months => $description): ?>
      <option value="<?php echo $months; ?>"><?php echo $description; ?></option>
    <?php endforeach; ?>
  </select>
</p>

<?php endif; ?>
<div class="clear"></div>
