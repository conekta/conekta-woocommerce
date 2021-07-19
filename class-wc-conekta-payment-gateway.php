<?php
/**
 * Conekta Payment Gateway
 *
 * Payment Gateway through Conekta.io for Woocommerce for both credit and debit cards as well as cash payments in OXXO and monthly installments for Mexican credit cards.
 *
 * @package conekta-woocommerce
 * @link    https://wordpress.org/plugins/conekta-woocommerce/
 * @author  Conekta.io
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! class_exists( 'Conekta' ) ) {
	require_once 'lib/conekta-php/lib/Conekta.php';
}

/**
 * Title   : Conekta Payment extension for WooCommerce
 * Author  : Conekta.io
 * Url     : https://wordpress.org/plugins/conekta-woocommerce
 */
class WC_Conekta_Payment_Gateway extends WC_Conekta_Plugin {
	/**
	 * The given name of the gateway.
	 *
	 * @var string
	 */
	protected $gateway_name = 'WC_Conekta_Payment_Gateway';
	/**
	 * Determines whether testing mode is enabled.
	 *
	 * @var bool
	 */
	protected $is_sandbox = true;
	/**
	 * Current WooCommerce Order.
	 *
	 * @var WC_Order|null
	 */
	protected $order = null;
	/**
	 * Current transaction id.
	 *
	 * @var string|null
	 */
	protected $transaction_id = null;
	/**
	 * Current order id in Conekta.
	 *
	 * @var string|null
	 */
	protected $conekta_order_id = null;
	/**
	 * Error message to be shown when a payment fails.
	 *
	 * @var string|null
	 */
	protected $transaction_error_message = null;
	/**
	 * List of accepted currencies.
	 *
	 * @var array
	 */
	protected $currencies = array( 'MXN', 'USD' );

	/**
	 * Creates the Gateway.
	 *
	 * @access public
	 */
	public function __construct() {
		global $woocommerce;
		$this->id           = 'conektacard';
		$this->method_title = __( 'Conekta', 'conektacard' );
		$this->has_fields   = true;
		$this->ckpg_init_form_fields();
		$this->init_settings();

		$this->description          = '';
		$this->icon                 = $this->settings['alternate_imageurl'] ?
			$this->settings['alternate_imageurl'] : WP_PLUGIN_URL . '/' . plugin_basename( dirname( __FILE__ ) )
			. '/images/credits.png';
		$this->use_sandbox_api      = strcmp( $this->settings['debug'], 'yes' ) === 0;
		$this->enable_meses         = strcmp( $this->settings['meses'], 'yes' ) === 0;
		$this->enable_iframe        = true;
		$this->test_api_key         = $this->settings['test_api_key'];
		$this->live_api_key         = $this->settings['live_api_key'];
		$this->test_publishable_key = $this->settings['test_publishable_key'];
		$this->live_publishable_key = $this->settings['live_publishable_key'];
		$this->publishable_key      = $this->use_sandbox_api ? $this->test_publishable_key : $this->live_publishable_key;
		$this->secret_key           = $this->use_sandbox_api ? $this->test_api_key : $this->live_api_key;
		$this->lang_options         = parent::ckpg_set_locale_options()->ckpg_get_lang_options();
		$this->title                = $this->lang_options['conekta_title'];
		$this->enable_save_card     = $this->settings['enable_save_card'];
		$this->account_owner        = $this->settings['account_owner'];

		\Conekta\Conekta::setApiKey( $this->secret_key );
		\Conekta\Conekta::setApiVersion( '2.0.0' );
		\Conekta\Conekta::setPlugin( $this->name );
		\Conekta\Conekta::setPluginVersion( $this->version );
		\Conekta\Conekta::setLocale( 'es' );

		$this->ckpg_conekta_register_js_card_add_gateway();

		if ( $this->enable_meses ) {

			if ( ! is_admin() ) {

				if ( ( ! empty( $woocommerce->cart->total ) && ( intval( $woocommerce->cart->total ) < $this->settings['amount_monthly_install'] ) ) ) {
					foreach ( array_keys( $this->lang_options['monthly_installments'] ) as $monthly ) {
						unset( $this->lang_options['monthly_installments'][ $monthly ] );
					}
				} else {
					foreach ( array_keys( $this->lang_options['monthly_installments'] ) as $monthly ) {
						if ( 'no' === $this->settings[ $monthly . '_months_msi' ] && isset( $this->lang_options['monthly_installments'][ $monthly ] ) ) {
							unset( $this->lang_options['monthly_installments'][ $monthly ] );
						}
					}
				}
			} else {
				$min_amount = 300;
				switch ( $this->ckpg_find_last_month() ) {
					case '3_months_msi':
						$min_amount = 300;
						break;
					case '6_months_msi':
						$min_amount = 600;
						break;
					case '9_months_msi':
						$min_amount = 900;
						break;
					case '12_months_msi':
						$min_amount = 1200;
						break;
					case '18_months_msi':
						$min_amount = 1800;
						break;
				}
				if ( ! is_numeric( $this->settings['amount_monthly_install'] ) || $this->settings['amount_monthly_install'] < $min_amount ) {
					$this->settings['amount_monthly_install'] = '';
					update_option( 'woocommerce_conektacard_settings', $this->settings );
				}
			}
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'ckpg_payment_fields' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'admin_notices', array( &$this, 'ckpg_perform_ssl_check' ) );

		if ( ! $this->ckpg_validate_currency() ) {
			$this->enabled = false;
		}

		if ( empty( $this->secret_key ) ) {
			$this->enabled = false;
		}

		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'ckpg_thankyou_page' ) );
		add_action( 'woocommerce_email_before_order_table', array( $this, 'ckpg_email_reference' ) );
		add_action( 'woocommerce_email_before_order_table', array( $this, 'ckpg_email_instructions' ) );
		add_action( 'woocommerce_order_refunded', array( $this, 'ckpg_conekta_card_order_refunded' ), 10, 2 );
		add_action( 'woocommerce_order_partially_refunded', array( $this, 'ckpg_conekta_card_order_partially_refunded' ), 10, 2 );
		add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'ckpg_webhook_handler' ) );
	}

	/**
	 * Updates the status of the order.
	 *
	 * @access public
	 */
	public function ckpg_webhook_handler() {
		header( 'HTTP/1.1 200 OK' );
		$body = file_get_contents( 'php://input' );
		if ( false === $body ) {
			return;
		}
		$event         = json_decode( $body, true );
		$conekta_order = $event['data']['object'];

		if ( 'order' === $conekta_order['object'] && array_key_exists( 'charges', $conekta_order ) ) {
			$charge   = $conekta_order['charges']['data'][0];
			$order_id = $conekta_order['metadata']['reference_id'];
			$order    = wc_get_order( $order_id );
			if ( 'spei' === $charge['payment_method']['type'] && 'order.paid' !== strpos( $event['type'], false ) ) {
				$paid_at = gmdate( 'Y-m-d', $charge['paid_at'] );
				update_post_meta( $order->get_id(), 'conekta-paid-at', $paid_at );
				$order->payment_complete();
				$order->add_order_note( sprintf( 'Payment completed in Spei and notification of payment received' ) );
				parent::ckpg_offline_payment_notification( $order_id, $conekta_order['customer_info']['name'] );
			} elseif ( 'oxxo' === $charge['payment_method']['type'] ) {
				if ( false !== strpos( $event['type'], 'order.paid' ) ) {
					$paid_at = gmdate( 'Y-m-d', $charge['paid_at'] );
					update_post_meta( $order->get_id(), 'conekta-paid-at', $paid_at );
					$order->payment_complete();
					$order->add_order_note( sprintf( 'Payment completed in Oxxo and notification of payment received' ) );
					parent::ckpg_offline_payment_notification( $order_id, $conekta_order['customer_info']['name'] );
				} elseif ( false !== strpos( $event['type'], 'order.expired' ) ) {
					$order->update_status( 'cancelled', __( 'Oxxo payment has been expired', 'woocommerce' ) );
				} elseif ( false !== strpos( $event['type'], 'order.canceled' ) ) {
					$order->update_status( 'cancelled', __( 'Order has been canceled', 'woocommerce' ) );
				}
			} else {
				if ( false !== strpos( $event['type'], 'order.refunded' ) ) {
					$order->update_status( 'refunded', __( 'Order has been refunded', 'woocommerce' ) );
				} elseif ( false !== strpos( $event['type'], 'order.partially_refunded' ) || false !== strpos( $event['type'], 'charge.partially_refunded' ) ) {
					$refunded_amount = $conekta_order['amount_refunded'] / 100;
					$refund_args     = array(
						'amount'   => $refunded_amount,
						'reason'   => null,
						'order_id' => $order_id,
					);
					wc_create_refund( $refund_args );
				} elseif ( false !== strpos( $event['type'], 'order.canceled' ) ) {
					$order->update_status( 'cancelled', __( 'Order has been cancelled', 'woocommerce' ) );
				}
			}
		}
	}


	/**
	 * Refunds an order.
	 *
	 * @access public
	 * @param int $order_id  ID of the order to be refunded.
	 */
	public function ckpg_conekta_card_order_refunded( $order_id = null ) {
		global $woocommerce;
		include_once 'conekta-gateway-helper.php';
		\Conekta\Conekta::setApiKey( $this->secret_key );
		\Conekta\Conekta::setApiVersion( '2.0.0' );
		\Conekta\Conekta::setPlugin( $this->name );
		\Conekta\Conekta::setPluginVersion( $this->version );
		\Conekta\Conekta::setLocale( 'es' );
		if ( ! $order_id ) {
			$order_id = filter_input( INPUT_POST, 'order_id' );
		}

		$data = get_post_meta( $order_id );
		if ( 'conektacard' !== $data['_payment_method'][0] ) {
			return;
		}
		$total  = $data['_order_total'][0] * 100;
		$amount = floatval( filter_input( INPUT_POST, 'amount' ) );
		if ( isset( $amount ) ) {
			$params['amount'] = round( $amount );
		}

		try {
			$conekta_order_id = $data['conekta-order-id'][0];
			$conekta_order    = \Conekta\Order::find( $conekta_order_id );
			if ( 'paid' === $conekta_order['payment_status'] ) {
				$refund_response = $conekta_order->refund(
					array(
						'reason' => 'other',
						'amount' => $total,
					)
				);
			} elseif ( 'pre_authorized' === $conekta_order['payment_status'] ) {
				$conekta_order->void();
			}
		} catch ( \Conekta\Handler $e ) {
			$description = $e->getMessage();
			global $wp_version;
			if ( version_compare( $wp_version, '4.1', '>=' ) ) {
				wc_add_notice( __( 'Error: ', 'woothemes' ) . $description, $notice_type = 'error' );
			} else {
				$woocommerce->add_error( __( 'Error: ', 'woothemes' ) . $description );
			}
			return false;
		}
	}

	/**
	 * Called when an order is partially refunded.
	 *
	 * @access public
	 * @param int $order_id  ID of the order to be partially refunded.
	 */
	public function ckpg_conekta_card_order_partially_refunded( $order_id = null ) {
		// just to verify if the action is called.
	}

	/**
	 * Checks to see if SSL is configured and if plugin is configured in production mode - Forces use of SSL if not in testing.
	 *
	 * @access public
	 */
	public function ckpg_perform_ssl_check() {
		if ( ! $this->use_sandbox_api
			&& 'no' === get_option( 'woocommerce_force_ssl_checkout' )
			&& 'yes' === $this->enabled ) {
			echo '<div class="error"><p>'
				. esc_html(
					sprintf(
						// translators: %s is the name of the gateway.
						__(
							'%1$s sandbox testing is disabled and can performe live transactions but the <a href="%2$s">force SSL option</a> is disabled; your checkout is not secure! Please enable SSL and ensure your server has a valid SSL certificate.',
							'woothemes'
						),
						$this->gateway_name,
						admin_url( 'admin.php?page=settings' )
					)
				)
			. '</p></div>';
		}
	}

	/**
	 * Initializes the admin form fields.
	 *
	 * @access public
	 */
	public function ckpg_init_form_fields() {
		$elements = ( new WC_Order() )->get_data_keys();
		sort( $elements );
		$order_metadata = array();
		foreach ( $elements as $key => $value ) {
			$order_metadata[ $value ] = $value;
		}
		$elements = ( new WC_Order_Item_Product() )->get_data_keys();
		sort( $elements );
		$product_metadata = array();
		foreach ( $elements as $key => $value ) {
			$product_metadata[ $value ] = $value;
		}
		$this->form_fields = array(
			'enabled'                => array(
				'type'    => 'checkbox',
				'title'   => __( 'Enable/Disable', 'woothemes' ),
				'label'   => __( 'Enable Conekta Payment', 'woothemes' ),
				'default' => 'yes',
			),
			'debug'                  => array(
				'type'    => 'checkbox',
				'title'   => __( 'Testing', 'woothemes' ),
				'label'   => __( 'Turn on testing', 'woothemes' ),
				'default' => 'no',
			),
			'card_title'             => array(
				'type'        => 'text',
				'title'       => 'Card - ' . __( 'Title', 'woothemes' ),
				'description' => __( 'This controls the title which the user sees during checkout.', 'woothemes' ),
				'default'     => __( 'Pago con Tarjeta de crédito o débito', 'woothemes' ),
			),
			'oxxo_title'             => array(
				'type'        => 'text',
				'title'       => 'OXXO - ' . __( 'Title', 'woothemes' ),
				'description' => __( 'This controls the title which the user sees during checkout.', 'woothemes' ),
				'default'     => __( 'Conekta Pago en Efectivo en Oxxo Pay', 'woothemes' ),
			),
			'spei_title'             => array(
				'type'        => 'text',
				'title'       => 'SPEI - ' . __( 'Title', 'woothemes' ),
				'description' => __( 'This controls the title which the user sees during checkout.', 'woothemes' ),
				'default'     => __( 'Pago con SPEI', 'woothemes' ),
			),
			'test_api_key'           => array(
				'type'    => 'password',
				'title'   => __( 'Conekta API Test Private key', 'woothemes' ),
				'default' => '',
			),
			'test_publishable_key'   => array(
				'type'    => 'text',
				'title'   => __( 'Conekta API Test Public key', 'woothemes' ),
				'default' => '',
			),
			'live_api_key'           => array(
				'type'    => 'password',
				'title'   => __( 'Conekta API Live Private key', 'woothemes' ),
				'default' => '',
			),
			'live_publishable_key'   => array(
				'type'    => 'text',
				'title'   => __( 'Conekta API Live Public key', 'woothemes' ),
				'default' => '',
			),
			'alternate_imageurl'     => array(
				'type'    => 'text',
				'title'   => __( 'Alternate Image to display on checkout, use fullly qualified url, served via https', 'woothemes' ),
				'default' => '',
			),
			'enable_card'            => array(
				'type'    => 'checkbox',
				'title'   => __( 'Card payment method', 'woothemes' ),
				'label'   => __( 'Enable card payment method', 'woothemes' ),
				'default' => 'yes',
			),
			'enable_save_card'       => array(
				'type'        => 'checkbox',
				'title'       => __( 'Save card', 'woothemes' ),
				'label'       => __( 'Enable save card', 'woothemes' ),
				'description' => __( 'Allow users to save the card for a future purchase.', 'woothemes' ),
				'default'     => __( 'no', 'woothemes' ),
			),
			'3ds'                    => array(
				'type'    => 'checkbox',
				'title'   => __( '3DS', 'woothemes' ),
				'label'   => __( 'Enable 3DS in card payments', 'woothemes' ),
				'default' => 'no',
			),
			'enable_pre_authorize'   => array(
				'type'        => 'checkbox',
				'title'       => __( 'Pre-authorize', 'woothemes' ),
				'label'       => __( 'Enable card payments with pre-authorization', 'woothemes' ),
				'description' => __( 'Preauthorize all card payments instead of charging them inmediately.', 'woothemes' ),
				'default'     => __( 'no', 'woothemes' ),
			),
			'meses'                  => array(
				'type'    => 'checkbox',
				'title'   => __( 'Months without interest', 'woothemes' ),
				'label'   => __( 'Enable Meses sin Intereses', 'woothemes' ),
				'default' => 'no',
			),
			'3_months_msi'           => array(
				'type'    => 'checkbox',
				'label'   => __( '3 Months', 'woothemes' ),
				'default' => 'no',
			),
			'6_months_msi'           => array(
				'type'    => 'checkbox',
				'label'   => __( '6 Months', 'woothemes' ),
				'default' => 'no',
			),
			'9_months_msi'           => array(
				'type'    => 'checkbox',
				'label'   => __( '9 Months', 'woothemes' ),
				'default' => 'no',
			),
			'12_months_msi'          => array(
				'type'    => 'checkbox',
				'label'   => __( '12 Months', 'woothemes' ),
				'default' => 'no',
			),
			'18_months_msi'          => array(
				'type'    => 'checkbox',
				'label'   => __( '18 Months ( Banamex )', 'woothemes' ),
				'default' => 'no',
			),
			'amount_monthly_install' => array(
				'type'        => 'text',
				'title'       => __( 'Minimun Amount for Monthly Installments', 'woothemes' ),
				'description' => __(
					'Minimum amount for monthly installments from Conekta</br>
					- 300 MXN para 3 meses sin intereses</br>
					- 600 MXN para 6 meses sin intereses</br>
					- 900 MXN para 9 meses sin intereses</br>
					- 1200 MXN para 12 meses sin intereses</br>
					- 1800 MXN para 18 meses sin intereses</br>',
					'woothemes'
				),
			),
			'enable_cash'            => array(
				'type'    => 'checkbox',
				'title'   => __( 'OXXO payment method', 'woothemes' ),
				'label'   => __( 'Enable OXXO payment method', 'woothemes' ),
				'default' => 'yes',
			),
			'oxxo_description'       => array(
				'title'       => 'OXXO - ' . __( 'Description', 'woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
				'default'     => __( 'Por favor realiza el pago en el OXXO más cercano utilizando la referencia que se encuentra a continuación.', 'woocommerce' ),
				'desc_tip'    => true,
			),
			'oxxo_instructions'      => array(
				'title'       => 'OXXO - ' . __( 'Instructions', 'woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Instructions that will be added to the thank you page and emails.', 'woocommerce' ),
				'default'     => __( 'Por favor realiza el pago en el OXXO más cercano utilizando la referencia que se encuentra a continuación.', 'woocommerce' ),
				'desc_tip'    => true,
			),
			'enable_spei'            => array(
				'type'    => 'checkbox',
				'title'   => __( 'SPEI payment method', 'woothemes' ),
				'label'   => __( 'Enable SPEI payment method', 'woothemes' ),
				'default' => 'yes',
			),
			'account_owner'          => array(
				'type'        => 'Account owner',
				'title'       => 'SPEI - ' . __( 'Account owner', 'woothemes' ),
				'description' => __( 'This will be shown in SPEI success page as account description for CLABE reference', 'woothemes' ),
				'default'     => __( 'Conekta SPEI', 'woothemes' ),
			),
			'spei_description'       => array(
				'title'       => 'SPEI - ' . __( 'Description', 'woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
				'default'     => __( 'Por favor realiza el pago en el portal de tu banco utilizando los datos que te enviamos por correo.', 'woocommerce' ),
				'desc_tip'    => true,
			),
			'spei_instructions'      => array(
				'title'       => 'SPEI - ' . __( 'Instructions', 'woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Instructions that will be added to the thank you page and emails.', 'woocommerce' ),
				'default'     => __( 'Por favor realiza el pago en el portal de tu banco utilizando los datos que te enviamos por correo.', 'woocommerce' ),
				'desc_tip'    => true,
			),
			'expiration_time'        => array(
				'type'    => 'select',
				'title'   => __( 'Expiration Format', 'woothemes' ),
				'label'   => __( 'Days', 'woothemes' ),
				'default' => 'no',
				'options' => array(
					'days' => 'Days',
				),
			),
			'expiration'             => array(
				'type'    => 'number',
				'title'   => __( 'Expiration (# days)', 'woothemes' ),
				'default' => __( '3', 'woothemes' ),
			),
			'order_metadata'         => array(
				'title'       => __( 'Additional Order Metadata', 'woocommerce' ),
				'type'        => 'multiselect',
				'description' => __( 'More than one option can be chosen.', 'woocommerce' ),
				'options'     => $order_metadata,
			),
			'product_metadata'       => array(
				'title'       => __( 'Additional Product Metadata', 'woocommerce' ),
				'type'        => 'multiselect',
				'description' => __( 'More than one option can be chosen.', 'woocommerce' ),
				'options'     => $product_metadata,
			),
		);
	}

	/**
	 * Output for the order received page.
	 *
	 * @access public
	 * @param string $order_id  ID of the order whose information is to be shown in the thank you page.
	 */
	public function ckpg_thankyou_page( $order_id ) {
		$order = new WC_Order( $order_id );
		switch ( $order->get_payment_method_title() ) {
			case $this->settings['oxxo_title']:
				echo '<p style="font-size: 30px"><strong>' . esc_html( __( 'Referencia' ) ) . ':</strong> ' . esc_html( get_post_meta( $order->get_id(), 'conekta-referencia', true ) ) . '</p>';
				echo '<p>OXXO cobrará una comisión adicional al momento de realizar el pago.</p>';
				echo '<p>INSTRUCCIONES:' . esc_html( $this->settings['oxxo_instructions'] ) . '</p>';
				break;
			case $this->settings['spei_title']:
				echo '<p><h4><strong>' . esc_html( __( 'Clabe' ) ) . ':</strong> ' . esc_html( get_post_meta( $order->get_id(), 'conekta-clabe', true ) ) . '</h4></p>';
				echo '<p><h4><strong>' . esc_html( __( 'Beneficiario' ) ) . ':</strong> ' . esc_html( $this->account_owner ) . '</h4></p>';
				echo '<p><h4><strong>' . esc_html( __( 'Banco Receptor' ) ) . ':</strong>  Sistema de Transferencias y Pagos (STP)<h4></p>';
				break;
		}
	}

	/**
	 * Add content to the WC emails.
	 *
	 * @access public
	 * @param WC_Order $order Order whose reference is to be sent.
	 */
	public function ckpg_email_reference( $order ) {
		switch ( $order->get_payment_method_title() ) {
			case $this->settings['oxxo_title']:
				if ( get_post_meta( $order->get_id(), 'conekta-referencia', true ) !== null ) {
					echo '<p style="font-size: 30px"><strong>' . esc_html( __( 'Referencia' ) ) . ':</strong> ' . esc_html( get_post_meta( $order->get_id(), 'conekta-referencia', true ) ) . '</p>';
					echo '<p>OXXO cobrará una comisión adicional al momento de realizar el pago.</p>';
					echo '<p>INSTRUCCIONES:' . esc_html( $this->settings['oxxo_instructions'] ) . '</p>';
				}
				break;
			case $this->settings['spei_title']:
				if ( get_post_meta( $order->get_id(), 'conekta-clabe', true ) !== null ) {
					echo '<p><h4><strong>' . esc_html( __( 'Clabe' ) ) . ':</strong> ' . esc_html( get_post_meta( $order->get_id(), 'conekta-clabe', true ) ) . '</h4></p>';
					echo '<p><h4><strong>' . esc_html( __( 'Beneficiario' ) ) . ':</strong> ' . esc_html( $this->account_owner ) . '</h4></p>';
					echo '<p><h4><strong>' . esc_html( __( 'Banco Receptor' ) ) . ':</strong>  Sistema de Transferencias y Pagos (STP)<h4></p>';
				}
				break;
		}
	}

	/**
	 * Add content to the WC emails.
	 *
	 * @access public
	 * @param WC_Order $order  Order whose instructions are to be sent.
	 * @param bool     $sent_to_admin  Determines whether this email is to be sent to the admin too.
	 * @param bool     $plain_text Determines whether this email is going to be displayed as plain text.
	 */
	public function ckpg_email_instructions( $order, $sent_to_admin = false, $plain_text = false ) {
		switch ( $order->get_payment_method_title() ) {
			case $this->settings['oxxo_title']:
				if ( get_post_meta( $order->get_id(), '_payment_method', true ) === $this->id ) {
					$instructions = $this->settings['oxxo_instructions'];
					if ( $instructions && 'on-hold' === $order->get_status() ) {
						echo esc_html( wpautop( wptexturize( $instructions ) ) . PHP_EOL );
					}
				}
				break;
			case $this->settings['spei_title']:
				$instructions = $this->settings['spei_instructions'];
				if ( $instructions && 'on-hold' === $order->get_status() ) {
					echo esc_html( wpautop( wptexturize( $instructions ) ) . PHP_EOL );
				}
				break;
		}
	}

	/**
	 * Includes the admin form template.
	 *
	 * @access public
	 */
	public function admin_options() {
		include_once 'templates/admin.php';
	}

	/**
	 * Includes the payment method template.
	 *
	 * @access public
	 */
	public function payment_fields() {
		include_once 'templates/payment.php';
	}

	/**
	 * Gets the last month of the current monthly installment settings.
	 *
	 * @access public
	 * @return int
	 */
	public function ckpg_find_last_month() {

		$last_month_true = false;

		foreach ( array_keys( $this->lang_options['monthly_installments'] ) as $last_month ) {
			if ( 'yes' === $this->settings[ $last_month . '_months_msi' ] ) {
				$last_month_true = $last_month;
			}
		}
		return $last_month_true;
	}

	/**
	 * Initializes payment fields.
	 *
	 * @access public
	 */
	public function ckpg_payment_fields() {
		if ( ! is_checkout() ) {
			return;
		}
	}

	/**
	 * Adds the javascript scripts to the gateway.
	 *
	 * @access public
	 */
	public function ckpg_conekta_register_js_card_add_gateway() {
		if ( is_admin() ) {
			wp_enqueue_script( 'conekta_card_gateway', WP_PLUGIN_URL . '/' . plugin_basename( dirname( __FILE__ ) ) . '/assets/js/conekta_card_gateway.js', '', '1.0.1', true );
		}
	}

	/**
	 * Gets the current settings of expiration time.
	 *
	 * @access public
	 * @return int
	 */
	public function ckpg_expiration_payment() {
		$expiration = $this->settings['expiration_time'];
		switch ( $expiration ) {
			case 'hours':
				$expiration_cont = 24;
				$expires_time    = 3600;
				break;
			case 'days':
				$expiration_cont = 32;
				$expires_time    = 86400;
				break;
		}
		if ( $this->settings['expiration'] > 0 && $this->settings['expiration'] < $expiration_cont ) {
			$expires = time() + ( $this->settings['expiration'] * $expires_time );
		}
		return $expires;
	}

	/**
	 * Sets an order as paid in the database.
	 *
	 * @access protected
	 * @param object $current_order_data  Current order id to be updated.
	 * @return bool
	 */
	protected function ckpg_set_as_paid( $current_order_data ) {
		try {
			WC_Conekta_Plugin::ckpg_insert_conekta_unfinished_order( WC()->session->get_customer_id(), WC()->cart->get_cart_hash(), $current_order_data->order_id, $current_order_data->order_number );
			// ORDER ID IS GENERATED BY RESPONSE.
			update_post_meta( $current_order_data->order_number, 'conekta-order-id', $current_order_data->order_id );
		} catch ( Exception $e ) {
			return false;
		}
		return true;
	}

	/**
	 * Shows the error page after a failed payment.
	 *
	 * @access protected
	 * @param string $payment_type  Payment method that failed.
	 */
	protected function ckpg_mark_as_failed_payment( $payment_type ) {
		$payment_method = '';
		switch ( $payment_type ) {
			case 'card_payment':
				$payment_method = 'Credit Card';
				break;
			case 'cash_payment':
				$payment_method = 'OXXO Pay';
				break;
			case 'bank_transfer_payment':
				$payment_method = 'SPEI Payment';
				break;
		}
		$this->order->add_order_note(
			sprintf(
				'%s " . $payment_method . " Payment Failed : "%s"',
				$this->gateway_name,
				$this->transaction_error_message
			)
		);
	}

	/**
	 * Sets an order paid with card as completed.
	 *
	 * @access protected
	 */
	protected function ckpg_completeOrder() {
		global $woocommerce;
		if ( 'completed' === $this->order->get_status() ) {
			return;
		}

		// adjust stock levels and change order status.
		$this->order->payment_complete();
		$woocommerce->cart->empty_cart();

		$this->order->add_order_note(
			sprintf(
				"%s payment completed with Transaction Id of '%s'",
				$this->gateway_name,
				$this->transaction_id
			)
		);
		if ( isset( $_SESSION['order_awaiting_payment'] ) ) {
			unset( $_SESSION['order_awaiting_payment'] );
		}
	}

	/**
	 * Processes the payment once it is succesfully confirmed by the checkout.
	 *
	 * @access public
	 * @param int $order_id  Id of the newly created order.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		global $woocommerce;
		wp_delete_post( $order_id, true );
		$current_order_data = WC_Conekta_Plugin::ckpg_get_conekta_unfinished_order( WC()->session->get_customer_id(), WC()->cart->get_cart_hash() );
		$this->order        = wc_get_order( $current_order_data->order_number );
		$payment_type       = filter_input( INPUT_POST, 'conekta_payment_method' );
		$this->order->set_payment_method( WC()->payment_gateways()->get_available_payment_gateways()[ $this->id ] );
		if ( $this->ckpg_set_as_paid( $current_order_data ) ) {
			$this->transaction_id = filter_input( INPUT_POST, 'charge_id' );
			if ( 'credit' === $payment_type || 'debit' === $payment_type ) {
				$this->order->set_payment_method_title( $this->settings['card_title'] );
				$this->ckpg_completeOrder();
				update_post_meta( $this->order->get_id(), 'transaction_id', $this->transaction_id );
			} else {
				if ( 'oxxo' === $payment_type ) {
					$this->order->set_payment_method_title( $this->settings['oxxo_title'] );
					update_post_meta( $this->order->get_id(), 'conekta-referencia', filter_input( INPUT_POST, 'reference' ) );
					// Mark as on-hold (we're awaiting the notification of payment).
					$this->order->update_status( 'on-hold', __( 'Awaiting the conekta OXXO payment', 'woocommerce' ) );
				} else {
					$this->order->set_payment_method_title( $this->settings['spei_title'] );
					update_post_meta( $this->order->get_id(), 'conekta-clabe', filter_input( INPUT_POST, 'clabe' ) );
					// Mark as on-hold (we're awaiting the notification of payment).
					$this->order->update_status( 'on-hold', __( 'Awaiting the conekta SPEI payment', 'woocommerce' ) );
				}
				update_post_meta( $this->order->get_id(), 'conekta-id', $this->transaction_id );
				update_post_meta( $this->order->get_id(), 'conekta-creado', time() );
				update_post_meta( $this->order->get_id(), 'conekta-expira', $this->ckpg_expiration_payment() );
				// Remove cart.
				$woocommerce->cart->empty_cart();
				if ( isset( $_SESSION['order_awaiting_payment'] ) ) {
					unset( $_SESSION['order_awaiting_payment'] );
				}
			}
			$result = array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $this->order ),
			);
			return $result;
		} elseif ( 'card_payment' === $payment_type ) {
			$this->ckpg_mark_as_failed_payment( $payment_type );
			WC()->session->reload_checkout = true;
		} else {
			$this->ckpg_mark_as_failed_payment( $payment_type );
			global $wp_version;
			if ( version_compare( $wp_version, '4.1', '>=' ) ) {
				wc_add_notice( __( 'Transaction Error: Could not complete the payment', 'woothemes' ), $notice_type = 'error' );
			} else {
				$woocommerce->add_error( __( 'Transaction Error: Could not complete the payment' ), 'woothemes' );
			}
		}
	}


	/**
	 * Checks if woocommerce has enabled available currencies for plugin
	 *
	 * @access public
	 * @return bool
	 */
	public function ckpg_validate_currency() {
		return in_array( get_woocommerce_currency(), $this->currencies, true );
	}

	/**
	 * Checks if a string is null or empty
	 *
	 * @access public
	 * @param string $string  String to be evaluated.
	 * @return bool
	 */
	public function ckpg_is_null_or_empty_string( $string ) {
		return ( ! isset( $string ) || '' === trim( $string ) );
	}

	/**
	 * Gets the current logged in customer or creates it if inexistent.
	 *
	 * @access public
	 * @param array $data  Array with the current order data.
	 * @param array $order_metadata  Array with the current order metadata.
	 * @return \Conekta\Customer
	 */
	public function ckpg_create_new_customer( $data, $order_metadata ) {
		try {
			$customer_id = parent::ckpg_get_conekta_metadata( get_current_user_id(), parent::CONEKTA_CUSTOMER_ID );
			if ( ! empty( $customer_id ) ) {
				$customer = \Conekta\Customer::find( $customer_id );
				if ( empty( $data['payment_card'] ) || ! $data['payment_card'] ) {
					$source = $customer->createPaymentSource(
						array(
							'type'     => 'card',
							'token_id' => $data['token'],
						)
					);
					$customer->update(
						array( 'default_payment_source_id' => $source->id )
					);
					$sources = parent::ckpg_get_conekta_metadata( get_current_user_id(), parent::CONEKTA_PAYMENT_SOURCES_ID );
					if ( ! empty( $sources ) ) {
						$sources .= ',' . $source->id;
					} else {
						$sources = $source->id;
					}
					parent::ckpg_update_conekta_metadata( get_current_user_id(), parent::CONEKTA_PAYMENT_SOURCES_ID, $sources );
				}
				return $customer;
			} else {
				$customer = \Conekta\Customer::create(
					array(
						'name'            => $data['customer_info']['name'],
						'email'           => $data['customer_info']['email'],
						'phone'           => $data['customer_info']['phone'],
						'metadata'        => $order_metadata,
						'payment_sources' => array(
							array(
								'type'     => 'card',
								'token_id' => $data['token'],
							),
						),
					)
				);
				parent::ckpg_update_conekta_metadata( get_current_user_id(), parent::CONEKTA_ON_DEMAND_ENABLED, true );
				parent::ckpg_update_conekta_metadata( get_current_user_id(), parent::CONEKTA_PAYMENT_SOURCES_ID, $customer->default_payment_source_id );
				parent::ckpg_update_conekta_metadata( get_current_user_id(), parent::CONEKTA_CUSTOMER_ID, $customer->id );
				return $customer;
			}
		} catch ( \Conekta\ProccessingError $error ) {
			echo esc_html( $error->getMesage() );
			return false;
		} catch ( \Conekta\ParameterValidationError $error ) {
			echo esc_html( $error->getMessage() );
			return false;
		} catch ( \Conekta\Handler $error ) {
			echo esc_html( $error->getMessage() );
			return false;
		}
	}

	/**
	 * Deletes a card payment method from the customer.
	 *
	 * @access public
	 * @return bool
	 */
	public function ckpg_delete_card_conekta_api() {
		$customer_id = parent::ckpg_get_conekta_metadata( get_current_user_id(), WC_Conekta_Plugin::CONEKTA_CUSTOMER_ID );
		$sources     = parent::ckpg_get_conekta_metadata( get_current_user_id(), WC_Conekta_Plugin::CONEKTA_PAYMENT_SOURCES_ID );
		$sources     = explode( ',', $sources );
		if ( empty( $customer_id ) ) {
			return false;
		}
		$customer = \Conekta\Customer::find( $customer_id );
		if ( empty( $customer ) ) {
			return false;
		}
		foreach ( $customer->payment_sources as $source ) {
			if ( ! in_array( $source->id, $sources, true ) ) {
				$source->delete();
			}
		}
		return true;
	}
}

/**
 * Adds the payment gateway the list of available payment gateways.
 *
 * @access public
 * @param array $methods  The list of payment methods.
 * @return array
 */
function ckpg_conekta_card_add_gateway( $methods ) {
	array_push( $methods, 'WC_Conekta_Payment_Gateway' );
	return $methods;
}

add_filter( 'woocommerce_payment_gateways', 'ckpg_conekta_card_add_gateway' );

/**
 * Deletes a card payment method from the database.
 *
 * @access public
 */
function ckpg_checkout_delete_card() {
	$payment_card_delete = filter_input( INPUT_POST, 'value' );

	$response = array();
	$result   = false;
	$sources  = WC_Conekta_Plugin::ckpg_get_conekta_metadata( get_current_user_id(), WC_Conekta_Plugin::CONEKTA_PAYMENT_SOURCES_ID );
	$sources  = explode( ',', $sources );

	if ( $sources === $payment_card_delete ) {
		WC_Conekta_Plugin::ckpg_delete_conekta_metadata( get_current_user_id(), WC_Conekta_Plugin::CONEKTA_PAYMENT_SOURCES_ID );
		$result = true;
	} elseif ( is_array( $sources ) && in_array( $payment_card_delete, $sources, true ) ) {
		$new_sources = '';
		foreach ( $sources as $source ) {
			if ( $source === $payment_card_delete ) {
				$result = true;
			} else {
				$new_sources .= $source . ',';
			}
		}

		if ( empty( $new_sources ) ) {
			WC_Conekta_Plugin::ckpg_delete_conekta_metadata( get_current_user_id(), WC_Conekta_Plugin::CONEKTA_PAYMENT_SOURCES_ID );
		} else {
			WC_Conekta_Plugin::ckpg_update_conekta_metadata( get_current_user_id(), WC_Conekta_Plugin::CONEKTA_PAYMENT_SOURCES_ID, $new_sources );
		}
	}

	$response = array(
		'response' => $result,
	);
	wp_send_json( $response );
}
add_action( 'wp_ajax_ckpg_checkout_delete_card', 'ckpg_checkout_delete_card' );

/**
 * Sets the billing data in a WooCommerce order.
 *
 * @access public
 * @param WC_Order $wc_order  The order whose data must be set.
 */
function set_billing_data( $wc_order ) {
	$wc_order->set_billing_address_1( filter_input( INPUT_POST, 'address_1' ) );
	$wc_order->set_billing_address_2( filter_input( INPUT_POST, 'address_2' ) );
	$wc_order->set_billing_city( filter_input( INPUT_POST, 'city' ) );
	$wc_order->set_billing_state( filter_input( INPUT_POST, 'state' ) );
	$wc_order->set_billing_country( filter_input( INPUT_POST, 'country' ) );
	$wc_order->set_billing_email( filter_input( INPUT_POST, 'email' ) );
	$wc_order->set_billing_first_name( filter_input( INPUT_POST, 'firstName' ) );
	$wc_order->set_billing_last_name( filter_input( INPUT_POST, 'lastName' ) );
	$wc_order->set_billing_phone( filter_input( INPUT_POST, 'phone' ) );
	$wc_order->set_billing_postcode( filter_input( INPUT_POST, 'postcode' ) );
	$wc_order->save();
}

/**
 * Creates the order in WooCommerce and reutrns an AJAX response with its data to javascript.
 *
 * @access public
 */
function ckpg_create_order() {
	global $woocommerce;
	wc_clear_notices();
	$order_id = null;
	try {
		$gateway = WC()->payment_gateways->get_available_payment_gateways()['conektacard'];
		\Conekta\Conekta::setApiKey( $gateway->secret_key );
		\Conekta\Conekta::setApiVersion( '2.0.0' );
		\Conekta\Conekta::setPlugin( $gateway->name );
		\Conekta\Conekta::setPluginVersion( $gateway->version );
		\Conekta\Conekta::setLocale( 'es' );

		$wc_user_id  = get_current_user_id();
		$customer_id = WC_Conekta_Plugin::ckpg_get_conekta_metadata( $wc_user_id, WC_Conekta_Plugin::CONEKTA_CUSTOMER_ID );
		if ( 0 !== $wc_user_id && ! empty( $customer_id ) ) {
			$customer = \Conekta\Customer::find( $customer_id );
		} else {
			$customer_data = array(
				'name'  => ( filter_input( INPUT_POST, 'firstName' ) ) . ' ' . ( filter_input( INPUT_POST, 'lastName' ) ),
				'email' => filter_input( INPUT_POST, 'email' ),
				'phone' => filter_input( INPUT_POST, 'phone' ),
			);
			$customer      = \Conekta\Customer::create( $customer_data );
			if ( 0 !== $wc_user_id ) {
				WC_Conekta_Plugin::ckpg_update_conekta_metadata( $wc_user_id, WC_Conekta_Plugin::CONEKTA_CUSTOMER_ID, $customer->id );
			}
		}
		$old_order = WC_Conekta_Plugin::ckpg_get_conekta_unfinished_order( WC()->session->get_customer_id(), WC()->cart->get_cart_hash() );
		if ( empty( $old_order ) ) {

			$checkout    = WC()->checkout();
			$posted_data = $checkout->get_posted_data();
			$order_id    = $checkout->create_order( $posted_data );
			$wc_order    = wc_get_order( $order_id );
			set_billing_data( $wc_order );
			$data           = ckpg_get_request_data( $wc_order );
			$amount         = (int) $data['amount'];
			$items          = $wc_order->get_items();
			$taxes          = $wc_order->get_taxes();
			$line_items     = ckpg_build_line_items( $items, $gateway->ckpg_get_version() );
			$discount_lines = ckpg_build_discount_lines( $data );
			$shipping_lines = ckpg_build_shipping_lines( $data );
			$tax_lines      = ckpg_build_tax_lines( $taxes );
			$order_metadata = ckpg_build_order_metadata( $wc_order, $gateway->settings );

			$allowed_installments = array();
			if ( $gateway->enable_meses && 'yes' === $gateway->settings['enable_card'] ) {
				$total = (float) WC()->cart->total;
				foreach ( array_keys( $gateway->lang_options['monthly_installments'] ) as $month ) {
					if ( ! empty( $gateway->settings['amount_monthly_install'] ) ) {
						$elegible = $total >= (int) $gateway->settings['amount_monthly_install'];
					} else {
						switch ( $month ) {
							case 3:
								$elegible = $total >= 300;
								break;
							case 6:
								$elegible = $total >= 600;
								break;
							case 9:
								$elegible = $total >= 900;
								break;
							case 12:
								$elegible = $total >= 1200;
								break;
							case 18:
								$elegible = $total >= 1800;
								break;
						}
					}
					if ( 'yes' === $gateway->settings[ $month . '_months_msi' ] && $elegible ) {
						$allowed_installments[] = $month;
					}
				}
			}

			$allowed_payment_methods = array();
			if ( 'yes' === $gateway->settings['enable_card'] ) {
				$allowed_payment_methods[] = 'card';
			}
			if ( 'yes' === $gateway->settings['enable_cash'] ) {
				$allowed_payment_methods[] = 'cash';
			}
			if ( 'yes' === $gateway->settings['enable_spei'] ) {
				$allowed_payment_methods[] = 'bank_transfer';
			}

			$checkout = array(
				'allowed_payment_methods'      => $allowed_payment_methods,
				'monthly_installments_enabled' => ! empty( $allowed_installments ),
				'monthly_installments_options' => $allowed_installments,
				'force_3ds_flow'               => ( 'yes' === $gateway->settings['3ds'] ),
				'on_demand_enabled'            => ( 'yes' === $gateway->enable_save_card ),
			);

			if ( in_array( 'cash', $allowed_payment_methods, true ) ) {
				$checkout['expires_at'] = $gateway->ckpg_expiration_payment();
			}

			$order_details = array(
				'line_items'       => $line_items,
				'shipping_lines'   => $shipping_lines,
				'tax_lines'        => $tax_lines,
				'discount_lines'   => $discount_lines,
				'shipping_contact' => array(
					'phone'    => filter_input( INPUT_POST, 'phone' ),
					'receiver' => ( ( filter_input( INPUT_POST, 'firstName' ) ) . ' ' . ( filter_input( INPUT_POST, 'lastName' ) ) ),
					'address'  => array(
						'street1'     => filter_input( INPUT_POST, 'address_1' ),
						'street2'     => filter_input( INPUT_POST, 'address_2' ),
						'country'     => filter_input( INPUT_POST, 'country' ),
						'postal_code' => filter_input( INPUT_POST, 'postcode' ),
					),
				),
				'pre_authorize'    => ( 'yes' === $gateway->settings['enable_pre_authorize'] ),
				'checkout'         => $checkout,
				'customer_info'    => array(
					'customer_id' => $customer['id'],
					'name'        => $customer['name'],
					'email'       => $customer['email'],
					'phone'       => $customer['phone'],
				),
				'metadata'         => $order_metadata,
				'currency'         => $data['currency'],
			);
			$order_details = ckpg_check_balance( $order_details, $amount );
			$order         = \Conekta\Order::create( $order_details );
			WC_Conekta_Plugin::ckpg_insert_conekta_unfinished_order( WC()->session->get_customer_id(), WC()->cart->get_cart_hash(), $order->id, $order_id );
		} else {
			$order_details = array(
				'shipping_contact' => array(
					'phone'    => filter_input( INPUT_POST, 'phone' ),
					'receiver' => ( ( filter_input( INPUT_POST, 'firstName' ) ) . ' ' . ( filter_input( INPUT_POST, 'lastName' ) ) ),
					'address'  => array(
						'street1'     => filter_input( INPUT_POST, 'address_1' ),
						'street2'     => filter_input( INPUT_POST, 'address_2' ),
						'country'     => filter_input( INPUT_POST, 'country' ),
						'postal_code' => filter_input( INPUT_POST, 'postcode' ),
					),
				),
				'customer_info'    => array(
					'name'  => ( ( filter_input( INPUT_POST, 'firstName' ) ) . ' ' . ( filter_input( INPUT_POST, 'lastName' ) ) ),
					'email' => filter_input( INPUT_POST, 'email' ),
					'phone' => filter_input( INPUT_POST, 'phone' ),
				),
			);
			$order         = \Conekta\Order::find( $old_order->order_id );
			$order->update( $order_details );
		}
		$response = array(
			'checkout_id' => $order->checkout['id'],
			'key'         => $gateway->publishable_key,
			'price'       => WC()->cart->total,
			'spei_text'   => $gateway->settings['spei_description'],
			'cash_text'   => $gateway->settings['oxxo_description'],
		);
	} catch ( \Conekta\Handler $e ) {
		$description = $e->getMessage();
		global $wp_version;
		if ( version_compare( $wp_version, '4.1', '>=' ) ) {
			wc_add_notice( __( 'Error: ', 'woothemes' ) . $description, $notice_type = 'error' );
		} else {
			$woocommerce->add_error( __( 'Error: ', 'woothemes' ) . $description );
		}
		if ( null !== $order_id ) {
			wp_delete_post( $order_id, true );
		}
		$response = array(
			'error' => $e->getMessage(),
		);
	}
	wp_send_json( $response );
}
add_action( 'wp_ajax_nopriv_ckpg_create_order', 'ckpg_create_order' );
add_action( 'wp_ajax_ckpg_create_order', 'ckpg_create_order' );
