=== Conekta Woocommerce ===
Contributors: cristinarandall
Tags: free, oxxo, conekta, mexico, payment gateway
Requires at least: 3.5.2
Tested up to: 4.2.2
Stable tag: 2.0.12
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

* Make sure that you have at least PHP Version 5.4 since the Conekta PHP library requires this version
* Upload the plugin zip file in Plugins > Add New and then click "Install Now"
* Once installed, activate the plugin.
* Add your API keys in Woocommerce > Settings > Checkout from your Conekta account (admin.conekta.io) in https://admin.conekta.io#developers.keys
* To manage orders for offline payments so that the status changes dynamically, you will need to add the following url as a webhook in your Conekta account:
http://tusitio.com/wc-api/WC_Conekta_Cash_Gateway

Replace to tusitio.com with your domain name

== Screenshots ==
1. In your Woocommerce admin in Settings > Checkout, you will need to add the API Keys from your Conekta.io account
2. Also, you will need o configure webhooks correctly in your conekta account adding http://tusitio.com/wc-api/WC_Conekta_Cash_Gateway so that the order status changes dynamically
3. Once the user pays with the barcode or reference the order status in your Woocommerce admin will automatically change  
4. You will need to configure SSL since the user will be entering their credit card information directly in the checkout. They will not be redirected to another page.

== Changelog ==

= 0.1.0 =
* Online payments
* Sandbox testing capability.
* Option to save customer profile.
* Card validation at Conekta's servers so you don't have to be PCI.
* Client side validation for credit cards.

= 0.1.1 =
* Offline payments
* Barcode sent in mail and displayed in order the confirmation page
* Order Status changed dynamically once webhook is added in Conekta.io Account

= 0.2.0 =
* Added option for difered payments for 3, 6, and 12 months
* Enable or disable difered payments from the admin

= 0.3.0 =
* Added additional parameters required for more robust anti-fraude service for card payments 

= 2.0.0 =
* Added taxes
* Added discounts
* Added shipping
* Added orders
* Oxxo pay
* Fix webhooks for paid orders (previously paid charges)
* Remove Banorte

= 2.0.5 =
* Fix shipping validations for virtual products
* Fix rounding for non-integer cents
* Add expiration date for oxxo pay
* Fix webhooks for paid orders
