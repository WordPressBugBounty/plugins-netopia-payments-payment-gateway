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
			'payment_methods'   => $paymentMethodArr,
			'custom_html'     => $this->tmpHtml($paymentMethodArr),
		];
	}

	public function tmpHtml($paymentMethodArr) {
		if ( is_admin() ) {
			return "";
		}
		global $wpdb;

		// Check which payment method is selected in WooCommerce Blocks
		$NtpPaymentMethod = $this->get_setting( 'payment_methods' );

		// if the plugin is not Enable/Active yet, return null
		if(!is_array($NtpPaymentMethod)) {
			return;
		}
	
		// if the plugin is not configure yet, return null
		if(count($NtpPaymentMethod)=== 0) {
			return;
		}
				
		// Output the avalible payment methods
		$html = '';
		$checked = "";
		$name_methods = array(
			'credit_card'	      => __( 'Credit Card', 'netopia-payments-payment-gateway' ),
			'bitcoin'  => __( 'Bitcoin', 'netopia-payments-payment-gateway' )
			);
		
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
		return $html;
	}
}

