=== Conekta Payment Gateway ===
Contributors: cristinarandall, eduardoconekta, jovalo
Tags: free, oxxo, conekta, mexico, payment gateway
Requires at least: 3.5.2
Tested up to: 4.8.1
Stable tag: 3.0.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

WooCommerce Payment Gateway for Conekta.io

This bundles functionality to process credit cards and cash (OXXO) payments securely as well as send email notifications to your customers when they complete a successful purchase.

== Description ==

Current version features:

* Uses Conekta.js      - No PCI Compliance Issues ( Requires an SSL Certificate)
* Credit and Debit Card implemented
* Cash payments implemented
* Sandbox testing capability.
* Automatic order status management
* Email notifications on successful purchase

== Installation ==
Please note, v. 3.0.1 requires WooCommerce 3.x.
You can download an older version [here.](https://wordpress.org/plugins/conekta-payment-gateway/advanced/)
Make sure that you have at least PHP Version 5.6 since the Conekta PHP library requires this version.

###Automatic
Log in to your  Wordpress Dashboard, navigate to the plugins menu and click Add New, in the search field type “Conekta Payment Gateway” and click Search Plugins. Once you’ve found our plugin you can view details about it, you can install it by simply clicking “Install Now”.

###Manual
* Upload the plugin zip file in Plugins > Add New and then click "Install Now"
* Once installed, activate the plugin.
* Add your API keys in Woocommerce > Settings > Checkout from your Conekta account (admin.conekta.io) in https://admin.conekta.io#developers.keys
* To manage orders for offline payments so that the status changes dynamically, you will need to add the following url as a webhook in your Conekta account:
http://tusitio.com/wc-api/WC_Conekta_Cash_Gateway

Replace to tusitio.com with your domain name

== Screenshots ==
1. In your Woocommerce admin in Settings > Checkout, you will need to add the API Keys from your Conekta.io account
`/assets/screenshot-1.png`
2. Also, you will need o configure webhooks correctly in your conekta account adding http://tusitio.com/wc-api/WC_Conekta_Cash_Gateway so that the order status changes dynamically
`/assets/screenshot-3.png`
3. Once the user pays with the reference the order status in your Woocommerce admin will automatically change
`/assets/screenshot-2.png`
4. You will need to configure SSL since the user will be entering their credit card information directly in the checkout. They will not be redirected to another page.

== Changelog ==
= 3.0.2 =
* Fix: Order info in email templates
* Feature: Custom instructions and description for Oxxo and Spei payment

= 3.0.1 =
* Fix: Fix library issues.

= 3.0.0 =
* Changed: Access correctly to order properties (Support WooCommerce 3.x).

= 2.0.14 =
* Style: Correction in typo for email

= 2.0.13 =
* name the SPEI account owner in admin input section[NEW FEATURE]
* new marketplace review validations

= 2.0.12 =
* Fixes round adjustment for non-integer values
* sends plugin version in line items tag.

= 2.0.11 =
* Fixes issue of token_id missing.

= 2.0.10 =
* Fix monthly installments

= 2.0.9 =
Fix tax line empty description

= 2.0.8 =
change name of translation inside comment.

= 2.0.7 =
Fix sku less than 0 characters

= 2.0.6 =
Fix shipping for card and spei

= 2.0.5 =
* Fix shipping validations for virtual products
* Fix rounding for non-integer cents
* Add expiration date for oxxo pay
* Fix webhooks for paid orders

= 2.0.0 =
* Added taxes
* Added discounts
* Added shipping
* Added orders
* Oxxo pay
* Fix webhooks for paid orders (previously paid charges)
* Remove Banorte

= 0.3.0 =
* Added additional parameters required for more robust anti-fraude service for card payments

= 0.2.0 =
* Added option for difered payments for 3, 6, and 12 months
* Enable or disable difered payments from the admin

= 0.1.1 =
* Offline payments
* Barcode sent in mail and displayed in order the confirmation page
* Order Status changed dynamically once webhook is added in Conekta.io Account

= 0.1.0 =
* Online payments
* Sandbox testing capability.
* Option to save customer profile.
* Card validation at Conekta's servers so you don't have to be PCI.
* Client side validation for credit cards.