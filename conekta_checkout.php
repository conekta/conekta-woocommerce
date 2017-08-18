<?php

/*
Plugin Name: Conekta Payment Gateway
Plugin URI: https://wordpress.org/plugins/conekta-woocommerce/
Description: Payment Gateway through Conekta.io for Woocommerce for both credit and debit cards as well as cash payments in OXXO and monthly installments for Mexican credit cards.
Version: 2.0.12
Author: Conekta.io
Author URI: https://www.conekta.io
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

/*
 * Title   : Conekta Payment Extension for WooCommerce
 * Author  : Conekta.io
 * Url     : https://www.conekta.io/es/docs/plugins/woocommerce
 */

function ckpg_conekta_checkout_init_your_gateway()
{
    if (class_exists('WC_Payment_Gateway'))
    {
        if (array_key_exists("wc-ajax", $_GET) && $_GET["wc-ajax"] === "checkout") {
            if (array_key_exists("payment_method", $_POST)) {
                include_once('conekta_gateway_helper.php');
                include_once('conekta_plugin.php');
                $payment_method = sanitize_text_field( (string)$_POST["payment_method"]);
                switch ($payment_method) {
                    case 'conektacard': default:
                        include_once('conekta_card_gateway.php');
                    break;
                    case 'conektaoxxopay':
                        include_once('conekta_cash_gateway.php');
                    break;
                    case 'conektaspei':
                        include_once('conekta_spei_gateway.php');
                    break;
                }
            }
        } else {
            include_once('conekta_gateway_helper.php');
            include_once('conekta_plugin.php');
            include_once('conekta_card_gateway.php');
            include_once('conekta_cash_gateway.php');
            include_once('conekta_spei_gateway.php');
        }

    }
}

add_action('plugins_loaded', 'ckpg_conekta_checkout_init_your_gateway', 0);
