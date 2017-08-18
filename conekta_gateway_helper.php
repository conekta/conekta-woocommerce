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

        $sub_total   = floatval($item['line_subtotal']) * 1000;
        $sub_total   = $sub_total / floatval($item['qty']);
        $productmeta = new WC_Product($item['product_id']);
        $sku         = $productmeta->get_sku();
        $unit_price  = $sub_total;
        $item_name   = item_name_validation($item['name']);
        $unit_price  = intval(round(floatval($unit_price) / 10), 2);
        $quantity    = intval($item['qty']);


        $line_item_params = array(
            'name'        => $item_name,
            'unit_price'  => $unit_price,
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
        $tax_name    = (string)$tax['label'];
        $tax_name    = esc_html($tax_name);


        $tax_lines  = array_merge($tax_lines, array(
            array(
                'description' => $tax_name,
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
function ckpg_get_request_data($order)
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
        $amountShipping = amount_validation($order->get_total_shipping());

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
            $name      = string_validation($order->get_shipping_first_name());
            $last      = string_validation($order->get_shipping_last_name());
            $address1  = string_validation($order->get_shipping_address_1());
            $address2  = string_validation($order->get_shipping_address_2());
            $city      = string_validation($order->get_shipping_city());
            $state     = string_validation($order->get_shipping_state());
            $country   = string_validation($order->get_shipping_country());
            $postal    = post_code_validation($order->get_shipping_postcode());


            $shipping_contact = array(
            'phone'    => $order->get_billing_phone(),
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
        $customer_name = sprintf('%s %s', $order->get_billing_first_name(), $order->get_billing_last_name());
        $phone         = sanitize_text_field($order->get_billing_phone());

        // Customer Info
        $customer_info = array(
            'name'  => $customer_name,
            'phone' => $phone,
            'email' => $order->get_billing_email()
        );
        //PARAMS VALIDATION
        if (!empty($_POST['conekta_token'])) {
            $token = string_validation($_POST['conekta_token']);
        }

        if (!empty($_POST['monthly_installments'])) {
            $monthly_installments = int_validation($_POST['monthly_installments']);
        }

        $amount               = validate_total($order->get_total());
        $currency             = get_woocommerce_currency();

        $data = array(
            'order_id'             => $order->get_id(),
            'amount'               => $amount,
            'token'                => $token,
            'monthly_installments' => $monthly_installments,
            'currency'             => $currency,
            'description'          => sprintf('Charge for %s', $order->get_billing_email()),
            'customer_info'        => $customer_info,
            'shipping_lines'       => $shipping_lines
        );

        if (!empty($order->get_shipping_address_1())) {
            $data = array_merge($data, array('shipping_contact' => $shipping_contact));
        }

        if (!empty($order->get_customer_note())) {
            $data = array_merge($data, array('customer_message' => $order->get_customer_note()));
        }

        if(!empty($discount_lines)) {
            $data = array_merge($data, array('discount_lines' => $discount_lines));
        }

        return $data;
    }

    return false;
}

function amount_validation($amount='')
{
    if(is_numeric($amount)){
     $amount = (float) $amount * 100;
    }

    return $amount;
}

function item_name_validation($item='')
{
    if((string) $item == true){
      return sanitize_text_field($item);
    }

    return $item;
}

function string_validation($string='')
{
    if((string) $string == true ){
        return  esc_html($string);
    }

    return $string;
}

function post_code_validation($post_code='')
{
    if(strlen($post_code) > 5){
        return substr($post_code,0, 5);
    }

    return $post_code;
}

function int_validation($input_field)
{
    if(is_numeric($input_field)){
        return intval($input_field);
    }

    return $input_field;
}

function validate_total($total='')
{
    if(is_numeric($total)){
        return (float) $total * 100;
    }

    return total;
}
