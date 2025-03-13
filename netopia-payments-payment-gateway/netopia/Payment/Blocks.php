<?php
/**
 * Class Netopia_Blocks_Support
 * This class used for supporting wooCommerce Block
 * @copyright NETOPIA Payments
 * @author Dev Team
 * @version 1.0
 **/
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class netopiapaymentsBlocks extends AbstractPaymentMethodType {
	private $gateway;
	protected $name = 'netopiapayments';


	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_netopiapayments_settings', [] );
		$this->gateway = new netopiapaymentsBlocks();
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return filter_var( $this->get_setting( 'enabled', false ), FILTER_VALIDATE_BOOLEAN );
		// return $this->gateway->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
        	wp_register_script(
				'netopiapayments-block-integration',
				plugin_dir_url(__FILE__) . '../../blocks/index.js',
				array(
					'wc-blocks-registry',
					'wc-settings',
					'wp-element',
					'wp-html-entities',
					'wp-i18n'
				),
				null,
				true
			);
		return [ 'netopiapayments-block-integration' ];
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$paymentMethodArr = $this->get_setting( 'payment_methods' );

		return [
			'title'       		=> $this->get_setting( 'title' ),
			'description' 		=> $this->get_setting( 'description' ),
			'supports'    		=> $this->get_supported_features(),
			'payment_methods'   => is_array($paymentMethodArr) ? $paymentMethodArr : array(),
			'custom_html'     => $this->tmpHtml($paymentMethodArr),
		];
	}

	public function tmpHtml($paymentMethodArr) {
		if ( is_admin() ) {
			return "";
		}
		global $wpdb;
		
		/*
		* if the plugin is not return array, the payment methos must be array
		* Check the configuration
		*/
		if(empty($paymentMethodArr)) {
			wc_add_notice( __( 'NETOPIA Payment method is not configured correctly!', 'netopia-payments-payment-gateway' ), $notice_type = 'error' );
			return false;
		}

		
		// Output the avalible payment methods
		$html = '';
		$checked = "";
		$name_methods = array(
			'credit_card'	      => __( 'Credit Card', 'netopia-payments-payment-gateway' ),
			// 'bitcoin'  => __( 'Bitcoin', 'netopia-payments-payment-gateway' )
			);
		

			switch (true) {
				case empty($paymentMethodArr):
					$html .=  '<div id="netopia-methods">Nu este setata nicio metoda de plata!</div>';
				break;
				case count($paymentMethodArr) === 1:
					// Default is Credit/Debit Card (the ZERO index)
					$html .=  '<input type="hidden" name="netopia_method_pay" class="netopia-method-pay" id="netopia-method-'.esc_attr($paymentMethodArr[0]).'" value="'.esc_attr($paymentMethodArr[0]).'"/>';
				break;
				case count($paymentMethodArr) > 1:
					foreach ($paymentMethodArr as $method) {
						// Verify if the payment method is available in the list.
						if(array_key_exists($method, $name_methods)) {
							$checked = ($method == 'credit_card') ? 'checked="checked"' : "" ;
							$html .=  '<li>
											<input type="radio" name="netopia_method_pay" class="netopia-method-pay" id="netopia-method-'.$method.'" value="'.$method.'" '.$checked.' />
											<label for="netopia-method-' . $method . '" style="display: inline;">' . $name_methods[$method] . '</label>
										</li>';
						}
					}
				break;
				}
		return $html;
	}
}

