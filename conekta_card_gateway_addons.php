<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Conekta_Card_Gateway_Addons class.
 *
 * @extends WC_Conekta_Card_Gateway
 */
class WC_Conekta_Card_Gateway_Addons extends WC_Conekta_Card_Gateway {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
			add_action( 'wcs_resubscribe_order_created', array( $this, 'delete_resubscribe_meta' ), 10 );
			//add_action( 'wcs_renewal_order_created', array( $this, 'delete_renewal_meta' ), 10 );
			add_action( 'woocommerce_subscription_failing_payment_method_updated_' . $this->id, array( $this, 'update_failing_payment_method' ), 10, 2 );

			// TODO: display the credit card used for a subscription in the "My Subscriptions" table
			// add_filter( 'woocommerce_my_subscriptions_payment_method', array( $this, 'maybe_render_subscription_payment_method' ), 10, 2 );

			// allow store managers to manually set ConektaCard as the payment method on a subscription
			add_filter( 'woocommerce_subscription_payment_meta', array( $this, 'add_subscription_payment_meta' ), 10, 2 );
			add_filter( 'woocommerce_subscription_validate_payment_meta', array( $this, 'validate_subscription_payment_meta' ), 10, 2 );
		}
        
        $this->supports = array( 
            'subscriptions', 
            'products',
            'tokenization',
            'subscription_cancellation', 
            'subscription_suspension', 
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes',
            'subscription_payment_method_change',
            'subscription_payment_method_change_admin'
        );

	}
    
	/**
	 * Is $order_id a subscription?
	 * @param  int  $order_id
	 * @return boolean
	 */
	protected function is_subscription( $order_id ) {
		return ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_is_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) ) );
	}


	/**
	 * Updates other subscription sources.
	 */
	public function save_source( $order, $token ) {
		$customer_id = parent::save_source( $order, $token );

		// Also store it on the subscriptions being purchased or paid for in the order
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order->id ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order->id );
		} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order->id ) ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order->id );
		} else {
			$subscriptions = array();
		}

		foreach( $subscriptions as $subscription ) {
			update_post_meta( $subscription->id, '_conekta_card_token', $token );
            update_post_meta( $subscription->id, '_conekta_customer_id', $customer_id );
		}
        
        return $customer_id;
        
        
	}

	/**
	 * process_subscription_payment function.
	 * @param mixed $order
	 * @param int $amount (default: 0)
	 */
	public function process_subscription_payment( $order = '', $amount = 0 ) {
		global $woocommerce;
        
		// Get source from order
		$customer_id = get_post_meta( $order->id, '_conekta_customer_id', true );
        
		// Or fail :(
		if ( ! $customer_id ) {
			return new WP_Error( 'conektacard_error', __( 'Customer not found', 'conektacard' ) );
		}

		// Make the request
		$this->order = $order;
        
        include_once('conekta_gateway_helper.php');
        Conekta::setApiKey($this->secret_key);
        Conekta::setLocale("es");
        Conekta::setApiVersion("1.0.0");
        
        $data = getRequestData($this->order);



        try {

            $line_items = array();
            $items = $this->order->get_items();
            $line_items = build_line_items($items);
            $details = build_details($data, $line_items);

            $charge = Conekta_Charge::create(array(
                "amount"      => $data['amount'],
                "currency"    => $data['currency'],
                "monthly_installments" => $data['monthly_installments'] > 1 ? $data['monthly_installments'] : null,
                "card"        => $customer_id,
                "reference_id" => $this->order->id,
                "description" => "Compra con orden # ". $this->order->id . " desde Woocommerce v" . $this->version,
                "details"     => $details,
                ));

            $this->transactionId = $charge->id;
            if ($data['monthly_installments'] > 1) {
                update_post_meta( $this->order->id, 'meses-sin-intereses', $data['monthly_installments']);
            }
            update_post_meta( $this->order->id, 'transaction_id', $this->transactionId);
            
            $this->completeOrder();

            $result = array(
                'result' => 'success',
                'redirect' => $this->get_return_url($this->order)
                );
            return $result;

        } catch(Conekta_Error $e) {
            $description = $e->message_to_purchaser;

            global $wp_version;
            if (version_compare($wp_version, '4.1', '>=')) {
                //wc_add_notice(__('Error: ', 'woothemes') . $description , $notice_type = 'error');
                $order->add_order_note( __('Error: ', 'woothemes') . $description );
            } else {
                error_log('Gateway Error:' . $description . "\n");
                $woocommerce->add_error(__('Error: ', 'woothemes') . $description);
            }
            
            $this->markAsFailedPayment();
            
            return false;
        }
        
		//return $response;
	}

	/**
	 * Don't transfer Conekta customer/token meta to resubscribe orders.
	 * @param int $resubscribe_order The order created for the customer to resubscribe to the old expired/cancelled subscription
	 */
	public function delete_resubscribe_meta( $resubscribe_order ) {
		delete_post_meta( $resubscribe_order->id, '_conekta_card_token' );
		delete_post_meta( $resubscribe_order->id, '_conekta_customer_id' );
		$this->delete_renewal_meta( $resubscribe_order );
	}

    
	/**
	 * scheduled_subscription_payment function.
	 *
	 * @param $amount_to_charge float The amount to charge.
	 * @param $renewal_order WC_Order A WC_Order object created to record the renewal payment.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		$response = $this->process_subscription_payment( $renewal_order, $amount_to_charge );

		if ( is_wp_error( $response ) ) {
			$renewal_order->update_status( 'failed', sprintf( __( 'Conekta Transaction Failed (%s)', 'conekta-card' ), $response->get_error_message() ) );
		}
	}

	/**
	 * Remove order meta
	 * @param  object $order
	 */
	public function remove_order_source_before_retry( $order ) {
		delete_post_meta( $order->id, '_conekta_card_token' );
		delete_post_meta( $order->id, '_conekta_customer_id' );
	}

	/**
	 * Update the customer_id for a subscription after using Conekta card to complete a payment to make up for
	 * an automatic renewal payment which previously failed.
	 *
	 * @access public
	 * @param WC_Subscription $subscription The subscription for which the failing payment method relates.
	 * @param WC_Order $renewal_order The order which recorded the successful payment (to make up for the failed automatic payment).
	 * @return void
	 */
	public function update_failing_payment_method( $subscription, $renewal_order ) {
		update_post_meta( $subscription->id, '_conekta_customer_id', $renewal_order->conekta_customer );
		update_post_meta( $subscription->id, '_conekta_card_token', $renewal_order->conekta_card_token );
	}

	/**
	 * Include the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
	 *
	 * @since 2.5
	 * @param array $payment_meta associative array of meta data required for automatic payments
	 * @param WC_Subscription $subscription An instance of a subscription object
	 * @return array
	 */
	public function add_subscription_payment_meta( $payment_meta, $subscription ) {
		$payment_meta[ $this->id ] = array(
			'post_meta' => array(
				'_conekta_customer_id' => array(
					'value' => get_post_meta( $subscription->id, '_conekta_customer_id', true ),
					'label' => 'Conekta Customer ID',
				)
			),
		);
		return $payment_meta;
	}

	/**
	 * Validate the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
	 *
	 * @since 2.5
	 * @param string $payment_method_id The ID of the payment method to validate
	 * @param array $payment_meta associative array of meta data required for automatic payments
	 * @return array
	 */
	public function validate_subscription_payment_meta( $payment_method_id, $payment_meta ) {
		if ( $this->id === $payment_method_id ) {

			
            if ( ! isset( $payment_meta['post_meta']['_conekta_customer_id']['value'] ) || empty( $payment_meta['post_meta']['_conekta_customer_id']['value'] ) ) {
				throw new Exception( 'A "_conekta_customer_id" value is required.' );
			} elseif ( 0 !== strpos( $payment_meta['post_meta']['_conekta_customer_id']['value'], 'cus_' ) ) {
				throw new Exception( 'Invalid customer ID. A valid "_conekta_customer_id" must begin with "cus_".' );
			}
            

		}
	}

	/**
	 * Render the payment method used for a subscription in the "My Subscriptions" table
	 *
	 * @since 1.7.5
	 * @param string $payment_method_to_display the default payment method text to display
	 * @param WC_Subscription $subscription the subscription details
	 * @return string the subscription payment method
	 
	public function maybe_render_subscription_payment_method( $payment_method_to_display, $subscription ) {
		
        TODO: display a nice label for the customer about the car he/she used (eg VISA ***1234)
        
	}
    * */
}
