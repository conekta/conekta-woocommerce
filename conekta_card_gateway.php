<?php

if (!class_exists('Conekta')) {
    require_once("lib/conekta-php/lib/Conekta.php");
}

/*
 * Title   : Conekta Payment extension for WooCommerce
 * Author  : Cristina Randall
 * Url     : https://wordpress.org/plugins/conekta-woocommerce
*/
class WC_Conekta_Card_Gateway extends WC_Conekta_Plugin
{
    protected $GATEWAY_NAME              = "WC_Conekta_Card_Gateway";
    protected $is_sandbox                = true;
    protected $order                     = null;
    protected $transaction_id            = null;
    protected $conekta_order_id          = null;
    protected $transaction_error_message = null;
    protected $currencies                = array('MXN', 'USD');

    public function __construct()
    {
        global $woocommerce;
        $this->id = 'conektacard';
        $this->method_title = __('Conekta Card', 'conektacard');
        $this->has_fields = true;
        $this->ckpg_init_form_fields();
        $this->init_settings();

        $this->title       = $this->settings['title'];
        $this->description = '';
        $this->icon        = $this->settings['alternate_imageurl'] ?
            $this->settings['alternate_imageurl'] : WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__))
            . '/images/credits.png';
        $this->use_sandbox_api      = strcmp($this->settings['debug'], 'yes') == 0;
        $this->enable_meses         = strcmp($this->settings['meses'], 'yes') == 0;
        $this->enable_iframe        = strcmp($this->settings['iframe'], 'yes') == 0;
        $this->test_api_key         = $this->settings['test_api_key'];
        $this->live_api_key         = $this->settings['live_api_key'];
        $this->test_publishable_key = $this->settings['test_publishable_key'];
        $this->live_publishable_key = $this->settings['live_publishable_key'];
        $this->publishable_key      = $this->use_sandbox_api ?  $this->test_publishable_key : $this->live_publishable_key;
        $this->secret_key           = $this->use_sandbox_api ?  $this->test_api_key : $this->live_api_key;
        $this->lang_options         = parent::ckpg_set_locale_options()->ckpg_get_lang_options();
        $this->enable_save_card = $this->settings['enable_save_card'];

        \Conekta\Conekta::setApiKey($this->secret_key);
        \Conekta\Conekta::setApiVersion('2.0.0');
        \Conekta\Conekta::setPlugin($this->name);
        \Conekta\Conekta::setPluginVersion($this->version);
        \Conekta\Conekta::setLocale('es');

        $this->ckpg_conekta_register_js_card_add_gateway();
        
        if( $this->enable_meses ) {

            if(  !is_admin() ) {
                
                if( (  !empty($woocommerce->cart->total) && ( intval($woocommerce->cart->total) < $this->settings['amount_monthly_install'] ) ) ) {
                    foreach(array_keys($this->lang_options['monthly_installments'] ) as $monthly) {
                        unset($this->lang_options['monthly_installments'][$monthly]);
                    }
                    
                } else {
                    
                    foreach(array_keys($this->lang_options['monthly_installments'] ) as $monthly) {
                        
                        if( $this->settings[$monthly .'_months_msi'] == 'no' && isset( $this->lang_options['monthly_installments'][$monthly] ) ) {
                            unset($this->lang_options['monthly_installments'][$monthly]);
                        }
                    }
                }
                
            } else {
                $min_amount = 300;
                switch( $this->ckpg_find_last_month() ) {
                    case '3_months_msi' : $min_amount = 300; break;
                    case '6_months_msi' : $min_amount = 600; break;
                    case '9_months_msi' : $min_amount = 900; break;
                    case '12_months_msi' : $min_amount = 1200; break;
                    case '18_months_msi' : $min_amount = 1800; break;
                }
                if( !is_numeric($this->settings['amount_monthly_install']) || $this->settings['amount_monthly_install'] < $min_amount ) {
                    $this->settings['amount_monthly_install'] = '';
                    update_option('woocommerce_conektacard_settings',$this->settings);
                }
            }
        }

        add_action('wp_enqueue_scripts', array($this, 'ckpg_payment_fields'));
        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            array($this, 'process_admin_options')
        );
        add_action('admin_notices', array(&$this, 'ckpg_perform_ssl_check'));

        if (!$this->ckpg_validate_currency()) {
            $this->enabled = false;
        }

        if (empty($this->secret_key)) {
            $this->enabled = false;
        }

	add_action('woocommerce_order_refunded',  array($this, 'ckpg_conekta_card_order_refunded'), 10,2);
    add_action( 'woocommerce_order_partially_refunded', array( $this, 'ckpg_conekta_card_order_partially_refunded'), 10,2);
        add_action(
            'woocommerce_api_' . strtolower(get_class($this)),
            array($this, 'ckpg_webhook_handler')
        );
    }

    /**
     * Updates the status of the order.
     * Webhook needs to be added to Conekta account tusitio.com/wc-api/WC_Conekta_Card_Gateway
     */
    public function ckpg_webhook_handler()
    {
        header('HTTP/1.1 200 OK');
        $body          = @file_get_contents('php://input');
        $event         = json_decode($body, true);
        $conekta_order = $event['data']['object'];
        $charge        = $conekta_order['charges']['data'][0];
        $order_id      = $conekta_order['metadata']['reference_id'];
        $paid_at       = date("Y-m-d", $charge['paid_at']);
        $order         = new WC_Order($order_id);

         if(strpos($event['type'], "order.refunded") !== false)  { 
            $order->update_status('refunded', __( 'Order has been refunded', 'woocommerce' ));
        } elseif(strpos($event['type'], "order.partially_refunded") !== false || strpos($event['type'], "charge.partially_refunded") !== false) {
            $refunded_amount = $conekta_order['amount_refunded'] / 100;
            $refund_args = array('amount' => $refunded_amount, 'reason' => null, 'order_id' => $order_id );
            $refund = wc_create_refund($refund_args);
        } elseif(strpos($event['type'], "order.canceled") !== false) {
	        $order->update_status('cancelled', __( 'Order has been cancelled', 'woocommerce' ));
	    } 
        
    }


    public function ckpg_conekta_card_order_refunded($order_id = null)
    {
        global $woocommerce;
        include_once('conekta_gateway_helper.php');
        \Conekta\Conekta::setApiKey($this->secret_key);
        \Conekta\Conekta::setApiVersion('2.0.0');
        \Conekta\Conekta::setPlugin($this->name);
        \Conekta\Conekta::setPluginVersion($this->version);
        \Conekta\Conekta::setLocale('es');
        
		if (!$order_id){
		    $order_id = sanitize_text_field((string) $_POST['order_id']);
    	}

        $data = get_post_meta( $order_id );
		if($data['_payment_method'][0] != 'conektacard') {
			return;
		}
		$total = $data['_order_total'][0] * 100;
        $amount = floatval($_POST['amount']);
		if(isset($amount))
		{
		    $params['amount'] = round($amount);
		}
        
		try {
        	$conekta_order_id = $data['conekta-order-id'][0];
            $conekta_order = \Conekta\Order::find($conekta_order_id);
            if($conekta_order['payment_status'] == "paid") {
                $refund_response = $conekta_order->refund([
                    'reason' => 'other',
                    'amount' => $total,
                ]);
            } 
		} catch (\Conekta\Handler $e) {
			$description = $e->getMessage();
            global $wp_version;
            if (version_compare($wp_version, '4.1', '>=')) {
                wc_add_notice(__('Error: ', 'woothemes') . $description , $notice_type = 'error');
            } else {
                error_log('Gateway Error:' . $description . "\n");
                $woocommerce->add_error(__('Error: ', 'woothemes') . $description);
            }
            return false;
		} 
    }

    public function ckpg_conekta_card_order_partially_refunded($order_id = null)
    {
        //just to verify if the action is called
        error_log("partially refunded");
    }

    /**
    * Checks to see if SSL is configured and if plugin is configured in production mode
    * Forces use of SSL if not in testing
    */
    public function ckpg_perform_ssl_check()
    {
        ///
        if (!$this->use_sandbox_api
          && get_option('woocommerce_force_ssl_checkout') == 'no'
          && $this->enabled == 'yes') {
            echo '<div class="error"><p>'
                . sprintf(
                    __('%s sandbox testing is disabled and can performe live transactions'
                        . ' but the <a href="%s">force SSL option</a> is disabled; your checkout'
                        . ' is not secure! Please enable SSL and ensure your server has a valid SSL'
                        . ' certificate.', 'woothemes'),
                    $this->GATEWAY_NAME,
                    admin_url('admin.php?page=settings')
                )
                . '</p></div>';
        }
    }

    public function ckpg_init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'type'        => 'checkbox',
                'title'       => __('Enable/Disable', 'woothemes'),
                'label'       => __('Enable Credit Card Payment', 'woothemes'),
                'default'     => 'yes'
            ),
            'meses' => array(
                'type'        => 'checkbox',
                'title'       => __('Months without interest', 'woothemes'),
                'label'       => __('Enable months without interest', 'woothemes'),
                'default'     => 'no'
            ),
            '3_months_msi' => array(
                'type'        => 'checkbox',
                'label'       => __('3 Months', 'woothemes'),
                'default'     => 'no'
            ),
            '6_months_msi' => array(
                'type'        => 'checkbox',
                'label'       => __('6 Months', 'woothemes'),
                'default'     => 'no'
            ),
            '9_months_msi' => array(
                'type'        => 'checkbox',
                'label'       => __('9 Months', 'woothemes'),
                'default'     => 'no'
            ),
            '12_months_msi' => array(
                'type'        => 'checkbox',
                'label'       => __('12 Months', 'woothemes'),
                'default'     => 'no'
            ),
            '18_months_msi' => array(
                'type'        => 'checkbox',
                'label'       => __('18 Months ( Banamex )', 'woothemes'),
                'default'     => 'no'
            ),
            'amount_monthly_install' => array(
                'type'        => 'text',
                'title'       => __('Minimun Amount for Monthly Installments', 'woothemes'),
                'description' => __('Minimum amount for monthly installments from Conekta</br>
                - 300 MXN para 3 meses sin intereses</br>
                - 600 MXN para 6 meses sin intereses</br>
                - 900 MXN para 9 meses sin intereses</br>
                - 1200 MXN para 12 meses sin intereses</br>
                - 1800 MXN para 18 meses sin intereses</br>', 'woothemes'),
            ),
            'debug' => array(
                'type'        => 'checkbox',
                'title'       => __('Testing', 'woothemes'),
                'label'       => __('Turn on testing', 'woothemes'),
                'default'     => 'no'
            ),
            'iframe' => array(
                'type'        => 'checkbox',
                'title'       => __('Iframe', 'woothemes'),
                'label'       => __('Enable iframe (beta)', 'woothemes'),
                'default'     => 'no'
            ),
            'title' => array(
                'type'        => 'text',
                'title'       => __('Title', 'woothemes'),
                'description' => __('This controls the title which the user sees during checkout.', 'woothemes'),
                'default'     => __('Credit Card Payment', 'woothemes')
            ),
            'test_api_key' => array(
                'type'        => 'password',
                'title'       => __('Conekta API Test Private key', 'woothemes'),
                'default'     => __('', 'woothemes')
            ),
            'test_publishable_key' => array(
                'type'        => 'text',
                'title'       => __('Conekta API Test Public key', 'woothemes'),
                'default'     => __('', 'woothemes')
            ),
            'live_api_key' => array(
                'type'        => 'password',
                'title'       => __('Conekta API Live Private key', 'woothemes'),
                'default'     => __('', 'woothemes')
            ),
            'live_publishable_key' => array(
                'type'        => 'text',
                'title'       => __('Conekta API Live Public key', 'woothemes'),
                'default'     => __('', 'woothemes')
            ),
            'alternate_imageurl' => array(
                'type'        => 'text',
                'title'       => __('Alternate Image to display on checkout, use fullly qualified url, served via https', 'woothemes'),
                'default'     => __('', 'woothemes')
            ),
            'enable_save_card' => array(
                'type'        => 'checkbox',
                'title'       => __('Save card', 'woothemes'),
                'label'       => __('Enable save card', 'woothemes'),
                'description' => __('Allow users to save the card for a future purchase.','woothemes'),
                'default'     => __('no', 'woothemes')
            ),


        );
    }

    public function admin_options()
    {
        include_once('templates/admin.php');
    }

    public function payment_fields()
    {
        if ($this->enable_iframe) {
            include_once('templates/payment.php');
        } else {
            include_once('templates/payment_legacy.php');
        }
    }
    public function ckpg_find_last_month() {

        $last_month_true = false;
        
        foreach (array_keys($this->lang_options['monthly_installments']) as $last_month ) {
            if( $this->settings[ $last_month .'_months_msi' ] == 'yes') {
                
                $last_month_true = $last_month;
            }
        }
        return $last_month_true;
    }
    public function ckpg_payment_fields()
    {
        if (!is_checkout()) {
            return;
        }

        wp_enqueue_script('conekta_js', 'https://s3.us-east-2.amazonaws.com/conektadirect/latest/index.js', '', '', true);
        wp_enqueue_script('tokenize', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/js/tokenize.js', '', '1.0', true); //check import convention

        //PCI
        $params = array(
            'public_key' => $this->publishable_key
        );

        wp_localize_script('tokenize', 'wc_conekta_params', $params);
    }
    public function ckpg_conekta_register_js_card_add_gateway(){
        if(is_admin()){
            wp_enqueue_script('conekta_card_gateway', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/js/conekta_card_gateway.js', '', '1.0', true);
        }
    }
    protected function ckpg_send_to_conekta()
    {
        global $woocommerce;
        include_once('conekta_gateway_helper.php');
        //ALL $data VAR ASSIGNATION IS FREE OF VALIDATION
        $data             = ckpg_get_request_data($this->order);
        $on_demand_enabled = $data['on_demand_enabled'];

        $customer = null;

        if( $this->enable_save_card  ) {

            if( $on_demand_enabled ) {
                
                $customer = $this->ckpg_create_new_customer($data);
            }
        }

        $amount           = (int) $data['amount'];
        $items            = $this->order->get_items();
        $taxes            = $this->order->get_taxes();
        $line_items       = ckpg_build_line_items($items, parent::ckpg_get_version());
        $discount_lines   = ckpg_build_discount_lines($data);
        $shipping_lines   = ckpg_build_shipping_lines($data);
        $shipping_contact = ckpg_build_shipping_contact($data);
        $tax_lines        = ckpg_build_tax_lines($taxes);
        $customer_info    = ($on_demand_enabled)? array('customer_id' => $customer->id ) : ckpg_build_customer_info($data);
        $order_metadata   = ckpg_build_order_metadata($data);
        $order_details    = array(
            'currency'         => $data['currency'],
            'line_items'       => $line_items,
            'customer_info'    => $customer_info,
            'shipping_lines'   => $shipping_lines,
            'discount_lines'   => $discount_lines,
            'tax_lines'        => $tax_lines,
            'on_demand_enabled' => $on_demand_enabled
        );

        if (!empty($shipping_contact)) {
            $order_details = array_merge($order_details, array('shipping_contact' => $shipping_contact));
        }

        if (!empty($order_metadata)) {
            $order_details = array_merge($order_details, array('metadata' => $order_metadata));
        }

        $order_details = ckpg_check_balance($order_details, $amount);
        
        $charge_details['payment_method']['on_demand_enabled'] =  $on_demand_enabled;
        
        return $this->ckpg_create_order($data, $amount, $customer,$order_details );
  
    }

    protected function ckpg_mark_as_failed_payment()
    {
        $this->order->add_order_note(
            sprintf(
                "%s Credit Card Payment Failed : '%s'",
                $this->GATEWAY_NAME,
                $this->transaction_error_message
            )
        );
    }

    protected function ckpg_completeOrder()
    {
        global $woocommerce;

        if ($this->order->get_status() == 'completed')
            return;

        // adjust stock levels and change order status
        $this->order->payment_complete();
        $woocommerce->cart->empty_cart();

        $this->order->add_order_note(
            sprintf(
                "%s payment completed with Transaction Id of '%s'",
                $this->GATEWAY_NAME,
                $this->transaction_id
            )
        );

        unset($_SESSION['order_awaiting_payment']);
    }

    public function process_payment($order_id)
    {
        global $woocommerce;
        $this->order        = new WC_Order($order_id);
        if ($this->ckpg_send_to_conekta()) {
            $this->ckpg_completeOrder();

            $result = array(
                'result' => 'success',
                'redirect' => $this->get_return_url($this->order)
            );
            return $result;
        } else {
            $this->ckpg_mark_as_failed_payment();
            WC()->session->reload_checkout = true;
        }
    }

    /**
     * Checks if woocommerce has enabled available currencies for plugin
     *
     * @access public
     * @return bool
     */
    public function ckpg_validate_currency()
    {
        return in_array(get_woocommerce_currency(), $this->currencies);
    }

    public function ckpg_is_null_or_empty_string($string)
    {
        return (!isset($string) || trim($string) === '');
    }

    public function ckpg_create_new_customer($data) {

        try {
            $customer_id = parent::ckpg_get_conekta_metadata(get_current_user_id(),'conekta_customer_id');
            if (!empty($customer_id)) {
                $customer = \Conekta\Customer::find($customer_id);
                $source = $customer->createPaymentSource([
                    'type'     => 'card',
                    'token_id' => $data['token']
                ]);
                $sources = parent::ckpg_get_conekta_metadata(get_current_user_id(), WC_Conekta_Plugin::CONEKTA_ENABLE_SAVE_CARD);
                if( !empty($sources)){
                    $sources .= ',' . $source;
                } else{
                    $sources = $source;
                }
                parent::ckpg_update_conekta_metadata(get_current_user_id(), WC_Conekta_Plugin::CONEKTA_ENABLE_SAVE_CARD,$sources);

            } else {
                $customer = \Conekta\Customer::create(
                    [
                    "name" => $data['customer_info']['name'],
                    "email" => $data['customer_info']['email'],
                    "phone" => $data['customer_info']['phone'],
                    "metadata" => $order_metadata,
                    "payment_sources" => [
                        "type" => "card",
                        'token_id' => $data['token']
                    ]
                    ]
                );
            }
            parent::ckpg_update_conekta_metadata(get_current_user_id(),parent::CONEKTA_CUSTOMER_ID, $customer->id );
            return $customer;
        } catch (\Conekta\ProccessingError $error){
        echo $error->getMesage();
        } catch (\Conekta\ParameterValidationError $error){
        echo $error->getMessage();
        } catch (\Conekta\Handler $error){
        echo $error->getMessage();
        }
        return false;
    }

    public function ckpg_create_order($data, $amount, $customer, $order_details) {
        try {
            $conekta_order_id = get_post_meta($this->order->get_id(), 'conekta-order-id', true);
            if (!empty($conekta_order_id)) {
                $order = \Conekta\Order::find($conekta_order_id);
                $order->update($order_details);
            } else {
                $order = \Conekta\Order::create($order_details);
            }
            //ORDER ID IS GENERATED BY RESPONSE
            update_post_meta($this->order->get_id(), 'conekta-order-id', $order->id);
            $charge_details = array(
                'amount' => $amount
            );

            if ( !empty($customer) && $data['on_demand_enabled'] ){
                $payment_method = [
                    'payment_method' => [
                        'type'     => 'card'
                    ]
                ];
            } else if( isset($data['payment_card']) ){
                $payment_method = [
                    'payment_method' => [
                        'type'     => 'card',
                        "payment_source_id" => $data['payment_card']
                    ]
                ];
            } else if( !$data['on_demand_enabled']  ) {
                $payment_method = [
                    'payment_method' => [
                        'type'     => 'card',
                        'token_id' => $data['token']
                    ]
                ];
            }
            $charge_details = array_merge($charge_details, $payment_method);

            $monthly_installments = $data['monthly_installments'];
            if ($monthly_installments > 1) {
                $charge_details['payment_method']['monthly_installments'] = $monthly_installments;
            }

            $charge = $order->createCharge($charge_details);

            $this->transaction_id = $charge->id;
            if ($data['monthly_installments'] > 1) {
                update_post_meta($this->order->get_id(), 'meses-sin-intereses', $data['monthly_installments']);
            }
            $this->update_card_conekta_api();
            WC_Conekta_Plugin::ckpg_update_conekta_metadata(get_current_user_id(), WC_Conekta_Plugin::CONEKTA_ENABLE_SAVE_CARD, $on_demand_enabled );
            update_post_meta($this->order->get_id(), 'transaction_id', $this->transaction_id);
            return true;
        } catch (\Conekta\Handler $e) {
            $description = $e->getMessage();
            global $wp_version;
            if (version_compare($wp_version, '4.1', '>=')) {
                wc_add_notice(__('Error: ', 'woothemes') . $description, $notice_type = 'error');
            } else {
                error_log('Gateway Error:' . $description . "\n");
                $woocommerce->add_error(__('Error: ', 'woothemes') . $description);
            }
            return false;
        }
    }

    public function update_card_conekta_api() {
        $customer_id = parent::ckpg_get_conekta_metadata(get_current_user_id(),'conekta_customer_id');
        $sources = parent::ckpg_get_conekta_metadata(get_current_user_id(),'conekta_customer_id');
        $sources = explode(',',$sources);

        $customer = \Conekta\Customer::find($customer_id);
        foreach($customer->payment_sources as $source) {
            if(!in_array($source->id,$sources) ) {
                $source->delete();
            }
        }
        return true;
    }

}

function ckpg_conekta_card_add_gateway($methods)
{
    array_push($methods, 'WC_Conekta_Card_Gateway');
    return $methods;
}

add_filter('woocommerce_payment_gateways', 'ckpg_conekta_card_add_gateway');

function ckpg_checkout_delete_card() {

    $payment_card_delete = $_POST['value'];

    $response = [];
    $result = false;
    $sources = WC_Conekta_Plugin::ckpg_get_conekta_metadata(get_current_user_id(), WC_Conekta_Plugin::CONEKTA_PAYMENT_SOURCES_ID);
    $sources = explode(',',$sources);
    
    if( $sources == $payment_card_delete ) {
        WC_Conekta_Plugin::ckpg_delete_conekta_metadata(get_current_user_id(), WC_Conekta_Plugin::CONEKTA_PAYMENT_SOURCES_ID);
        $result = true;
    } else if( is_array($sources) && in_array($payment_card_delete, $sources)) {
        $new_sources = '';
        foreach($sources as $source) {
            if($source == $payment_card_delete) {
                $result = true;
            } else {
                $new_sources.= $source . ',';
            }
        }

        if( empty($new_sources) ){
            WC_Conekta_Plugin::ckpg_delete_conekta_metadata(get_current_user_id(), WC_Conekta_Plugin::CONEKTA_PAYMENT_SOURCES_ID);
        } else {
            WC_Conekta_Plugin::ckpg_update_conekta_metadata( get_current_user_id(), WC_Conekta_Plugin::CONEKTA_PAYMENT_SOURCES_ID, $new_sources );
        }
    }

    $response = array(
        'response'  => $result,
    );
    wp_send_json($response);  
}
add_action( 'wp_ajax_ckpg_checkout_delete_card','ckpg_checkout_delete_card');