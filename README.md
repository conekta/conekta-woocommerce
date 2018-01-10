Conekta Woocommerce v.3.0.3
================================

WooCommerce Payment Gateway for Conekta.io

This is a Open Source and Free plugin. It bundles functionality to process credit cards and cash (OXXO) payments securely as well as send email notifications to your customers when they complete a successful purchase.


Features
--------
Current version features:

* Uses Conekta.js      - No PCI Compliance Issues ( Requires an SSL Certificate)
* Credit and Debit Card implemented
* Cash payments implemented
* Custom description and instructions in offline payments (OXXO and SPEI)

![alt tag](https://raw.github.com/conekta/conekta-woocommerce/master/readme_files/admin_card.png)

* Sandbox testing capability.
* Automatic order status management
* Email notifications on successful purchase
* Email notifications on successful in cash payment

![alt tag](https://raw.github.com/conekta/conekta-woocommerce/master/readme_files/email.png)

####Example for custom instructions:
```
<ol>
	<li>Acude a la tienda OXXO más cercana.</li>
	<li>Inidica en caja que quieres realizar un pago de <b>OXXOPay</b>.</li>
	<li>Dicta al cajero el número de referencia en esta ficha para que la tecleé directamente en la pantalla de venta.</li>
	<li>Realiza el pago correspondiente con dinero en efectivo.</li>
	<li>Al confirmar tu pago, el cajero te entregará un comprobante impreso. <b>En él podrás verificar que se haya realizado correctamente</b>. Conserva este comprobante de pago.</li>
</ol>
```

Version Compatibility
---------------------
This plugin has been tested on Wordpress 4.8.1  WooCommerce 3.1.2

Installation
-----------
Method 1:
* Clone the module using git clone --recursive git@github.com:conekta/conekta-woocommerce.git
* Upload the plugin zip file in Plugins > Add New and then click "Install Now"
* Once installed, activate the plugin.

Method 2:
* Search the plugin in Plugins > Add New
* In the search bar type Conekta Payment and the click "Install Now"

* Add your API keys in Woocommerce > Settings > Checkout from your Conekta account (admin.conekta.io) in https://admin.conekta.io#developers.keys

![alt tag](https://raw.github.com/conekta/conekta-woocommerce/master/readme_files/form.png)

* To manage orders for offline payments so that the status changes dynamically, you will need to add the following url as a webhook in your Conekta account:
http://tusitio.com/wc-api/WC_Conekta_Cash_Gateway

![alt tag](https://raw.github.com/conekta/conekta-woocommerce/master/readme_files/webhook.png)

Replace to tusitio.com with your domain name

