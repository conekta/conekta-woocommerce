<?php
if (!class_exists('Conekta')) {
    require_once("lib/conekta-php/lib/Conekta.php");
}
/*
 * Title   : Conekta Payment extension for WooCommerce
 * Author  : Conekta.io
 * Url     : https://wordpress.org/plugins/conekta-woocommerce
 */

class WC_Conekta_Cash_Gateway extends WC_Conekta_Plugin
{
    protected $GATEWAY_NAME               = "WC_Conekta_Cash_Gateway";
    protected $usesandboxapi              = true;
    protected $order                      = null;
    protected $transactionId              = null;
    protected $transactionErrorMessage    = null;
    protected $conektaTestApiKey          = '';
    protected $conektaLiveApiKey          = '';
    protected $publishable_key            = '';

    public function __construct()
    {
        $this->id              = 'conektaoxxopay';
        $this->method_title    = __( 'Conekta Oxxo Pay', 'woocommerce' );
        $this->has_fields      = true;
        $this->ckpg_init_form_fields();
        $this->init_settings();
        $this->title              = $this->settings['title'];
        $this->description        = '';
        $this->icon               = $this->settings['alternate_imageurl'] ? $this->settings['alternate_imageurl']  : WP_PLUGIN_URL . "/" . plugin_basename( dirname(__FILE__)) . '/images/oxxopay.png';
        $this->usesandboxapi      = strcmp($this->settings['debug'], 'yes') == 0;
        $this->testApiKey         = $this->settings['test_api_key'];
        $this->liveApiKey         = $this->settings['live_api_key'];
        $this->secret_key         = $this->usesandboxapi ? $this->testApiKey : $this->liveApiKey;

        if (empty($this->secret_key)){
            $this->enabled = false;
        }
        add_action('woocommerce_update_options_payment_gateways_' . $this->id , array($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'ckpg_thankyou_page'));
        add_action('woocommerce_email_before_order_table', array($this, 'ckpg_email_instructions'));
        add_action('woocommerce_email_before_order_table', array($this, 'ckpg_email_reference'));
        add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'ckpg_webhook_handler'));
    }

    /**
     * Updates the status of the order.
     * Webhook needs to be added to Conekta account tusitio.com/wc-api/WC_Conekta_Cash_Gateway
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

        if (strpos($event['type'], "order.paid") !== false && $charge['payment_method']['type'] === "oxxo")
            {
                update_post_meta($order->id, 'conekta-paid-at', $paid_at);
                $order->payment_complete();
                $order->add_order_note(sprintf("Payment completed in Oxxo and notification of payment received"));

                parent::ckpg_offline_payment_notification($order_id, $conekta_order['customer_info']['name']);
            }
    }

    public function ckpg_init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'type'        => 'checkbox',
                'title'       => __('Enable/Disable', 'woothemes'),
                'label'       => __('Enable Conekta Oxxo Pay Payment', 'woothemes'),
                'default'     => 'yes'
            ),
            'debug' => array(
                'type'        => 'checkbox',
                'title'       => __('Testing', 'woothemes'),
                'label'       => __('Turn on testing', 'woothemes'),
                'default'     => 'no'
            ),
            'title' => array(
                'type'        => 'text',
                'title'       => __('Title', 'woothemes'),
                'description' => __('This controls the title which the user sees during checkout.', 'woothemes'),
                'default'     => __('Oxxo Pay Payment', 'woothemes')
            ),
            'test_api_key' => array(
                'type'        => 'password',
                'title'       => __('Conekta API Test Private key', 'woothemes'),
                'default'     => __('', 'woothemes')
            ),
            'live_api_key' => array(
                'type'        => 'password',
                'title'       => __('Conekta API Live Private key', 'woothemes'),
                'default'     => __('', 'woothemes')
            ),
            'expiration_days' => array(
                'type'        => 'text',
                'title'       => __('Expiration time (in days) for the reference', 'woothemes'),
                'default'     => __('30', 'woothemes')
            ),
            'alternate_imageurl' => array(
                'type'        => 'text',
                'title'       => __('Alternate Image to display on checkout, use fullly qualified url, served via https', 'woothemes'),
                'default'     => __('', 'woothemes')
            ),
            'description' => array(
                'title' => __( 'Description', 'woocommerce' ),
                'type' => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'woocommerce'),
                'default' =>__('Por favor realiza el pago en el OXXO más cercano utilizando la referencia que se encuentra a continuación.', 'woocommerce' ),
                'desc_tip' => true,
            ),
            'instructions' => array(
                'title' => __( 'Instructions', 'woocommerce' ),
                'type' => 'textarea',
                'description' => __('Instructions that will be added to the thank you page and emails.', 'woocommerce'),
                'default' =>__('Por favor realiza el pago en el OXXO más cercano utilizando la referencia que se encuentra a continuación.', 'woocommerce'),
                'desc_tip' => true,
            ),
        );
    }

    /**
     * Output for the order received page.
     * 
     * @param string $order_id
     */

    // this echo's may were safe of validation, because there are proveided by os
    function ckpg_thankyou_page($order_id) {
        $order = new WC_Order( $order_id );

        echo '<p style="font-size: 30px"><strong>'.__('Referencia').':</strong> ' . get_post_meta( esc_html($order->id), 'conekta-referencia', true ). '</p>';
        echo '<p>OXXO cobrará una comisión adicional al momento de realizar el pago.</p>';
                echo '<p>INSTRUCCIONES:<ol><li>Acude a la tienda OXXO más cercana.</li><li>Inidica en caja que quieres realizar un pago de <b>OXXOPay</b>.</li><li>Dicta al cajero el número de referencia en esta ficha para que la tecleé directamente en la pantalla de venta.</li><li>Realiza el pago correspondiente con dinero en efectivo.</li><li>Al confirmar tu pago, el cajero te entregará un comprobante impreso. <b>En él podrás verificar que se haya realizado correctamente</b>. Conserva este comprobante de pago.</li></ol>';

    }

    /**
     * Add content to the WC emails.
     *
     * @access public
     * @param WC_Order $order
     */

    function ckpg_email_reference($order) {
        if (get_post_meta( $order->id, 'conekta-referencia', true ) != null)
            {
                echo '<p style="font-size: 30px"><strong>'.__('Referencia').':</strong> ' . get_post_meta( $order->id, 'conekta-referencia', true ). '</p>';
                echo '<p>OXXO cobrará una comisión adicional al momento de realizar el pago.</p>';
                echo '<p>INSTRUCCIONES:<ol><li>Acude a la tienda OXXO más cercana.</li><li>Inidica en caja que quieres realizar un pago de <b>OXXOPay</b>.</li><li>Dicta al cajero el número de referencia en esta ficha para que la tecleé directamente en la pantalla de venta.</li><li>Realiza el pago correspondiente con dinero en efectivo.</li><li>Al confirmar tu pago, el cajero te entregará un comprobante impreso. <b>En él podrás verificar que se haya realizado correctamente</b>. Conserva este comprobante de pago.</li></ol>';
            }
    }

    /**
     * Add content to the WC emails.
     *
     * @access public
     * @param WC_Order $order
     * @param bool $sent_to_admin
     * @param bool $plain_text
     */
    public function ckpg_email_instructions( $order, $sent_to_admin = false, $plain_text = false ) {
        if (get_post_meta( $order->id, '_payment_method', true ) === $this->id){
            $instructions = $this->form_fields['instructions'];
            if ( $instructions && 'on-hold' === $order->status ) {
                echo wpautop( wptexturize( $instructions['default'] ) ) . PHP_EOL;
            }
        }
    }

    public function ckpg_admin_options()
    {
        include_once('templates/cash_admin.php');
    }

    public function payment_fields()
    {
        include_once('templates/cash.php');
    }

    protected function ckpg_send_to_conekta()
    {
        global $woocommerce;
        include_once('conekta_gateway_helper.php');
        \Conekta\Conekta::setApiKey($this->secret_key);
        \Conekta\Conekta::setApiVersion('2.0.0');
        \Conekta\Conekta::setPlugin('WooCommerce');
        \Conekta\Conekta::setLocale('es');

        $data             = ckpg_getRequestData($this->order);
        $amount           = $data['amount'];
        $items            = $this->order->get_items();
        $taxes            = $this->order->get_taxes();
        $line_items       = ckpg_build_line_items($items, parent::ckpg_get_version());
        $discount_lines   = ckpg_build_discount_lines($data);
        $shipping_lines   = ckpg_build_shipping_lines($data);
        $shipping_contact = ckpg_build_shipping_contact($data);
        $tax_lines        = ckpg_build_tax_lines($taxes);
        $customer_info    = ckpg_build_customer_info($data);
        $order_metadata   = ckpg_build_order_metadata($data);
        $order_details    = array(
            'currency'         => $data['currency'],
            'line_items'       => $line_items,
            'customer_info'    => $customer_info,
            'shipping_lines'   => $shipping_lines,
            'discount_lines'   => $discount_lines,
            'tax_lines'        => $tax_lines
        );

        if (!empty($shipping_contact)) {
            $order_details = array_merge($order_details, array('shipping_contact' => $shipping_contact));
        }

        if (!empty($order_metadata)) {
            $order_details = array_merge($order_details, array('metadata' => $order_metadata));
        }

        $order_details = ckpg_check_balance($order_details, $amount);

        try {
            $conekta_order_id = get_post_meta($this->order->id, 'conekta-order-id', true);
            if (!empty($conekta_order_id)) {
                $order = \Conekta\Order::find($conekta_order_id);
                $order->update($order_details);
            } else {
                $order = \Conekta\Order::create($order_details);
            }

            update_post_meta($this->order->id, 'conekta-order-id', $order->id);

            $expires_at = time() + ($this->settings['expiration_days'] * 86400);
            $charge_details = array(
                'payment_method' => array(
                    'type'       => 'oxxo_cash',
                    'expires_at' => $expires_at
                ),
                'amount'         => $amount
            );

            $charge = $order->createCharge($charge_details);

            $this->transactionId = $charge->id;
            update_post_meta($this->order->id, 'conekta-id',         $charge->id);
            update_post_meta($this->order->id, 'conekta-creado',     $charge->created_at);
            update_post_meta($this->order->id, 'conekta-expira',     $charge->payment_method->expiry_date);
            update_post_meta($this->order->id, 'conekta-referencia', $charge->payment_method->reference);

            return true;
        } catch(\Conekta\Handler $e) {
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

    public function process_payment($order_id)
    {
        global $woocommerce;
        $this->order        = new WC_Order($order_id);
        if ($this->ckpg_send_to_conekta())
            {
                // Mark as on-hold (we're awaiting the notification of payment)
                $this->order->update_status('on-hold', __( 'Awaiting the conekta OXOO payment', 'woocommerce' ));

                // Remove cart
                $woocommerce->cart->empty_cart();
                unset($_SESSION['order_awaiting_payment']);
                $result = array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($this->order)
                );
                return $result;
            }
        else
            {
                $this->ckpg_markAsFailedPayment();
                global $wp_version;
                if (version_compare($wp_version, '4.1', '>=')) {
                    wc_add_notice(__('Transaction Error: Could not complete the payment', 'woothemes'), $notice_type = 'error');
                } else {
                    $woocommerce->add_error(__('Transaction Error: Could not complete the payment'), 'woothemes');
                }
            }
    }

    protected function ckpg_markAsFailedPayment()
    {
        $this->order->add_order_note(
            sprintf(
                "%s Oxxo Pay Payment Failed : '%s'",
                $this->GATEWAY_NAME,
                $this->transactionErrorMessage
            )
        );
    }

    protected function ckpg_completeOrder()
    {
        global $woocommerce;

        if ($this->order->status == 'completed')
            return;

        $this->order->payment_complete();
        $woocommerce->cart->empty_cart();
        $this->order->add_order_note(
            sprintf(
                "%s payment completed with Transaction Id of '%s'",
                $this->GATEWAY_NAME,
                $this->transactionId
            )
        );

        unset($_SESSION['order_awaiting_payment']);
    }

}

function ckpg_conekta_cash_order_status_completed($order_id = null)
{
    global $woocommerce;
    if (!$order_id){
        $order_id = sanitize_text_field((string) $_POST['order_id']);
    }

    $data = get_post_meta( $order_id );
    $total = $data['_order_total'][0] * 100;

    $amount = floatval($_POST['amount']);
    if(isset($amount))
    {
        $params['amount'] = round($amount);
    }
}

function ckpg_conektacheckout_add_cash_gateway($methods)
{
    array_push($methods, 'WC_Conekta_Cash_Gateway');
    return $methods;
}

add_filter('woocommerce_payment_gateways',                      'ckpg_conektacheckout_add_cash_gateway');
add_action('woocommerce_order_status_processing_to_completed',  'ckpg_conekta_cash_order_status_completed' );
