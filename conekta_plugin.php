<?php

if (!class_exists('Conekta'))
{
	require_once("lib/conekta-php/lib/Conekta.php");
}

/*
* Title   : Conekta Payment extension for WooCommerce
* Author  : Conekta.io
* Url     : https://wordpress.org/plugins/conekta-woocommerce
*/

class WC_Conekta_Plugin extends WC_Payment_Gateway
{
	public $version  = "3.0.1";
	public $name = "WooCommerce 2";
	public $description = "Payment Gateway through Conekta.io for Woocommerce for both credit and debit cards as well as cash payments in OXXO and monthly installments for Mexican credit cards.";
	public $plugin_name = "Conekta Payment Gateway for Woocommerce";
	public $plugin_URI = "https://wordpress.org/plugins/conekta-woocommerce/";
	public $author = "Conekta.io";
	public $author_URI = "https://www.conekta.io";

	protected $lang;
	protected $lang_messages;

    public function ckpg_get_version()
    {
        return $this->version;
    }

	public function ckpg_set_locale_options()
	{
		if (function_exists("get_locale") && get_locale() !== "")
		{
			$current_lang = explode("_", get_locale());
			$this->lang = $current_lang[0];
			$filename = "lang/" . $this->lang . ".php";
			if (!file_exists(plugin_dir_path(__FILE__) . $filename))
				$filename = "lang/en.php";
			$this->lang_messages = require($filename);
			\Conekta\Conekta::setLocale($this->lang);
		}

		return $this;
	}

	public function ckpg_get_lang_options()
	{
		return $this->lang_messages;
	}

	public function ckpg_offline_payment_notification($order_id, $customer)
	{
   		global $woocommerce;
   		$order = new WC_Order($order_id);

   		$title = sprintf("Se ha efectuado el pago del pedido %s", $order->get_order_number());
   		$body_message = "<p style=\"margin:0 0 16px\">Se ha detectado el pago del siguiente pedido:</p><br />" . $this->ckpg_assemble_email_payment($order);

      	// Email for customer
      	$customer = esc_html($customer);
      	$customer = sanitize_text_field($customer);

      	$mail_customer = $woocommerce->mailer();
      	$message = $mail_customer->wrap_message(
        sprintf(__('Hola, %s'), $customer), $body_message);
     	  $mail_customer->send($order->get_billing_email(), $title, $message);
     	  unset($mail_customer);
     	  //Email for admin site
     	  $mail_admin = $woocommerce->mailer();
     	  $message = $mail_admin->wrap_message(
        sprintf(__('Pago realizado satisfactoriamente')), $body_message);
     	  $mail_admin->send(get_option("admin_email"), $title, $message);
     	  unset($mail_admin);
    }

    private function ckpg_assemble_email_payment($order){
    	ob_start();

    	wc_get_template( 'emails/email-order-details.php', array( 'order' => esc_html($order), 'sent_to_admin' => false, 'plain_text' => false, 'email' => '' ) );

		return ob_get_clean();
    }
}
