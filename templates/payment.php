<?php
/**
 * Conekta Payment Gateway
 *
 * Payment Gateway through Conekta.io for Woocommerce for both credit and debit cards as well as cash payments in OXXO and monthly installments for Mexican credit cards.
 *
 * @package conekta-woocommerce
 * @link    https://wordpress.org/plugins/conekta-woocommerce/
 * @author  Conekta.io
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

?>

<div class="clear"></div>
<span style="width: 100%; float: left; color: red;" class='payment-errors required'></span>
<?php $order_correct = ( ( (float) WC()->cart->total ) >= parent::MINIMUM_ORDER_AMOUNT ); ?>
	<p id="conektaBillingFormErrorMessage">
		<?php
		if ( $order_correct ) {
			echo esc_html( $this->lang_options['enter_customer_details'] );
		} else {
			echo esc_html( $this->lang_options['order_too_little'] ) . esc_html( parent::MINIMUM_ORDER_AMOUNT ) . esc_html( ' $' );
		}
		?>
	</p>
<?php if ( $order_correct ) : ?>
	<div id="conektaIframeContainer" style="width: 100%;"></div>
<?php endif ?>
<script>
	var order_btn_card = document.getElementById("place_order");
    var radiogroup = document.checkout.payment_method
    if(radiogroup.length > 1){
        radiogroup.forEach((method) => {
            method.addEventListener("change", function(){
                if(order_btn_card)
                    order_btn_card.style.display = (radiogroup.value == "conektacard") ? "none" : "block";
            });
        });
    }
    if(order_btn_card)
            order_btn_card.style.display = (radiogroup.value == "conektacard") ? "none" : "block";
</script>
<div class="clear"></div> 
