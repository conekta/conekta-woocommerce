About this Fork
===============

This fork add support for credit card to the [Woocommerce Subscription plugin](https://woocommerce.com/products/woocommerce-subscriptions/).

This work was done to a website with very specific needs that do not use all of the features of the Subscriptions extension, so it has not been completely tested yet. 

It follows the guidelines presented in the [official documentation](https://docs.woocommerce.com/document/subscriptions/develop/payment-gateway-integration/) and borrowed code and inspiration from the [Stripe Gateway for Woocommerce](https://woocommerce.com/products/stripe/).

It does not use the subscription feature of the Conekta gateway. It simply makes recurring payments and let all the subscription management to Woocommerce (this is actually recommended by woocommerce).

What I know that works:
-----------------------

* Support for subscriptions with the Credit card payments
* automatic recurring payments for subscriptions renewals
* Subscriptions cancellation, suspension, reactivation
* Changing plans both from the customer site or from the admin
* Changing subscription dates
* Changing payment methos via admin (you can change the customer ID associated with a subscription)

What I dont konw if works (but I suppose it does, since its all handled on the woocomerce side)
-----------------------------------------------------------------------------------------------

* Free trial period
* Sign up fees
* Re-subscribe
* Change payment method (client side)
* Change plan (client side)
* what else?


Conekta Woocommerce v.0.4.3
=======================

WooCommerce Payment Gateway for Conekta.io

This is a Open Source and Free plugin. It bundles functionality to process credit cards and cash (OXXO) payments securely as well as send email notifications to your customers when they complete a successful purchase.


Features
--------
Current version features:

* Uses Conekta.js      - No PCI Compliance Issues ( Requires an SSL Certificate)
* Credit and Debit Card implemented
* Cash payments implemented

![alt tag](https://raw.github.com/cristinarandall/conekta-woocommerce/master/readme_files/form.png)

* Sandbox testing capability.
* Automatic order status management
* Email notifications on successful purchase
* Email notifications on successful in cash payment

![alt tag](https://raw.github.com/cristinarandall/conekta-woocommerce/master/readme_files/email.png)

Version Compatibility
---------------------
This plugin has been tested on Wordpress 4.5.3  WooCommerce 2.6.1

Installation
-----------

* Clone the module using git clone --recursive git@github.com:conekta/conekta-woocommerce.git
* Upload the plugin zip file in Plugins > Add New and then click "Install Now"
* Once installed, activate the plugin.
* Add your API keys in Woocommerce > Settings > Checkout from your Conekta account (admin.conekta.io) in https://admin.conekta.io#developers.keys

![alt tag](https://raw.github.com/cristinarandall/conekta-woocommerce/master/readme_files/admin_card.png)

* To manage orders for offline payments so that the status changes dynamically, you will need to add the following url as a webhook in your Conekta account:
http://tusitio.com/wc-api/WC_Conekta_Cash_Gateway

![alt tag](https://raw.github.com/cristinarandall/conekta-woocommerce/master/readme_files/webhook.png)

Replace to tusitio.com with your domain name

