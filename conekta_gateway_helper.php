<?php

/*
 * Title   : Conekta Payment Extension for WooCommerce
 * Author  : Cristina Randall
 * Url     : https://www.conekta.io/es/docs/plugins/woocommerce
 */

function ckpg_check_balance($order, $total) {
    $amount = 0;

    foreach ($order['line_items'] as $line_item) {
        $amount = $amount + ($line_item['unit_price'] * $line_item['quantity']);
    }

    foreach ($order['shipping_lines'] as $shipping_line) {
        $amount = $amount + $shipping_line['amount'];
    }

    foreach ($order['discount_lines'] as $discount_line) {
        $amount = $amount - $discount_line['amount'];
    }

    foreach ($order['tax_lines'] as $tax_line) {
        $amount = $amount + $tax_line['amount'];
    }

    if ($amount != $total) {
        $adjustment = $total - $amount;

        $order['tax_lines'][0]['amount'] =
            $order['tax_lines'][0]['amount'] + intval($adjustment);

        if (empty($order['tax_lines'][0]['description'])) {
            $order['tax_lines'][0]['description'] = 'Round Adjustment';
        }
    }

    return $order;
}


/**
 * Build the line items hash
 * @param array $items
 */
function ckpg_build_order_metadata($data)
{
    $metadata = array(
        'reference_id' => $data['order_id']
    );

    if (!empty($data['customer_message'])) {
        $metadata = array_merge(
            $metadata, array(
                'customer_message' => $data['customer_message'])
        );
    }

    return $metadata;
}

function ckpg_build_line_items($items, $version)
{
    $line_items = array();

    foreach ($items as $item) {

        $subTotal    = floatval($item['line_subtotal']) * 1000;
        $subTotal    = $subTotal / floatval($item['qty']);
        $productmeta = new WC_Product($item['product_id']);
        $sku         = $productmeta->get_sku();
        $unit_price  = $subTotal;
        $itemName    = itemNameValidation($item['name']);
        $unitPrice   = intval(round(floatval($unit_price) / 10), 2);
        $quantity    = intval($item['qty']);


        $line_item_params = array(
            'name'        => $itemName,
            'unit_price'  => $unitPrice,
            'quantity'    => $quantity,
            'tags'        => ['WooCommerce', "Conekta ".$version],
            'metadata'    => array('soft_validations' => true)
        );

        if (!empty($sku)) {
            $line_item_params = array_merge(
                $line_item_params, 
                array(
                    'sku' => $sku
                )
            );
        }

        $line_items = array_merge($line_items, array($line_item_params));
    }

    return $line_items;
}

function ckpg_build_tax_lines($taxes)
{
    $tax_lines = array();

    foreach ($taxes as $tax) {


        $tax_amount = floatval($tax['tax_amount']) * 1000;
        $taxName    = (string)$tax['label'];
        $taxName    = esc_html($taxName);


        $tax_lines  = array_merge($tax_lines, array(
            array(
                'description' => $taxName,
                'amount'      => intval(round(floatval($tax_amount) / 10), 2)
            )
        ));

        if (isset($tax['shipping_tax_amount'])) {
            $tax_amount = floatval($tax['shipping_tax_amount']) * 1000;
            $amount     = intval(round(floatval($tax_amount) / 10), 2);
            $tax_lines  = array_merge(
                $tax_lines, array(
                    array(
                        'description' => 'Shipping tax',
                        'amount'      => $amount
                    )
                )
            );
        }
    }

    return $tax_lines;
}

function ckpg_build_shipping_lines($data)
{
    $shipping_lines = array();

    if(!empty($data['shipping_lines'])) {
        $shipping_lines = $data['shipping_lines'];
    }

    return $shipping_lines;
}

function ckpg_build_discount_lines($data)
{
    $discount_lines = array();

    if (!empty($data['discount_lines'])) {
        $discount_lines = $data['discount_lines'];
    }

    return $discount_lines;
}

function ckpg_build_shipping_contact($data)
{
    $shipping_contact = array();

    if (!empty($data['shipping_contact'])) {
        $shipping_contact = array_merge($data['shipping_contact'], array('metadata' => array('soft_validations' => true)));

    }

    return $shipping_contact;
}

function ckpg_build_customer_info($data)
{
    $customer_info = array_merge($data['customer_info'], array('metadata' => array('soft_validations' => true)));

    return $customer_info;
}

/**
* Bundle and format the order information
* @param WC_Order $order
* Send as much information about the order as possible to Conekta
*/
function ckpg_getRequestData($order)
{
    $token = "";
    $monthly_installments = "";
    if ($order AND $order != null)
    {
        // Discount Lines
        $order_coupons  = $order->get_items('coupon');
        $discount_lines = array();

        foreach($order_coupons as $index => $coupon) {
            $discount_lines = array_merge($discount_lines, 
                array(
                    array(
                        'code'   => $coupon['name'],
                        'type'   => $coupon['type'],
                        'amount' => $coupon['discount_amount'] * 100
                    )
                )
            );
        }
        
        //PARAMS VALIDATION
        $amountShipping = amountValidation($order->get_total_shipping());

        // Shipping Lines
        $shipping_method = $order->get_shipping_method();
        if (!empty($shipping_method)) {
            $shipping_lines  = array(
                array(
                    'amount'  => $amountShipping,
                    'carrier' => $shipping_method,
                    'method'  => $shipping_method
                )
            );


            //PARAM VALIDATION
            $name      = stringValidation($order->shipping_first_name);
            $last      = stringValidation($order->shipping_last_name);
            $address1  = stringValidation($order->shipping_address_1);
            $address2  = stringValidation($order->shipping_address_2);
            $city      = stringValidation($order->shipping_city);
            $state     = stringValidation($order->shipping_state);
            $country   = stringValidation($order->shipping_country);
            $postal    = postCodeValidation($order->shipping_postcode);


            $shipping_contact = array(
            'phone'    => $order->billing_phone,
            'receiver' => sprintf('%s %s', $name, $last),
            'address' => array(
                'street1'     => $address1,
                'street2'     => $address2,
                'city'        => $city,
                'state'       => $state,
                'country'     => $country,
                'postal_code' => $postal
            ),
        );
        } else {
            $shipping_lines  = array(
                array(
                    'amount'   => 0,
                    'carrier'  => 'carrier',
                    'method'   => 'pickup'
                )
            );
        }

         //PARAM VALIDATION   
        $customer_name = sprintf('%s %s', $order->billing_first_name, $order->billing_last_name);
        $phone         = sanitize_text_field($order->billing_phone);

        // Customer Info
        $customer_info = array(
            'name'  => $customer_name,
            'phone' => $phone,
            'email' => $order->billing_email
        );

        //PARAMS VALIDATION
        $token                = stringValidation($_POST['conekta_token']);
        $monthly_installments = intValidation($_POST['monthly_installments']);
        $amount               = validateTotal($order->get_total());
        $currency             = get_woocommerce_currency();

        $data = array(
            'order_id'             => $order->id,
            'amount'               => $amount,
            'token'                => $token,
            'monthly_installments' => $monthly_installments,
            'currency'             => $currency,
            'description'          => sprintf('Charge for %s', $order->billing_email),
            'customer_info'        => $customer_info,
            'shipping_lines'       => $shipping_lines
        );



        if (!empty($order->shipping_address_1)) {
            $data = array_merge($data, array('shipping_contact' => $shipping_contact));
        }

        if (!empty($order->customer_message)) {
            $data = array_merge($data, array('customer_message' => $order->customer_message));
        }

        if(!empty($discount_lines)) {
            $data = array_merge($data, array('discount_lines' => $discount_lines));
        }

        return $data;
    }

    return false;
}
function amountValidation($amount='')
{
    if(is_numeric($amount)){
     $amount = (float) $amount * 100;
    }
    return $amount;
}
function itemNameValidation($item='')
{
    if((string) $item == true){
      return sanitize_text_field($item);   
    } 
    return $item;
}
function stringValidation($string='')
{
    if((string) $string == true ){
        return  esc_html($string);
    }   
    return $string;
}
function postCodeValidation($postCode='')
{
    if(strlen($$postCode) > 5){
        return substr($postCode,0, 5);       
    }   
    return $postCode;
}
function intValidation($inputField)
{
    if(is_numeric($inputField)){
        return intval($inputField);
    }
    return $inputField;
}
function validateTotal($total='')
{
    if(is_numeric($total)){
        return (float) $total * 100;
    }
    return total;
}