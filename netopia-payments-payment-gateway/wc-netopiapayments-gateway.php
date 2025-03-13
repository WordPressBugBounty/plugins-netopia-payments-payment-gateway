<?php
class netopiapayments extends WC_Payment_Gateway {
	// Dynamic property
	public $notify_url;
	public $environment;
	public $default_status;

	// Dynamic Key Setting
	public $sms_setting; // Should be keep, becuse maybe some of Merchants already have it and in Upgrade will recive WARNING
	public $key_setting;
	public $account_id;
	public $live_cer;
	public $live_key;
	public $sandbox_cer;
	public $sandbox_key;
	public $agreement;

	/**
	 * Netopia Payment Method like SMS,.. is removed form v1.4
	 * The $payment_methods, $sms_setting, $service_id
	 * was related to this ,...
	 */
	// Dynamic payment Method
	public $payment_methods;

	// Dynamic SMS properties
	// public $sms_setting;
	public $service_id;

	// Setup our Gateway's id, description and other values
	function __construct() {
		$this->id = "netopiapayments";
		$this->method_title = __( "NETOPIA Payments", 'netopia-payments-payment-gateway' );
		$this->method_description = __( "NETOPIA Payments Payment Gateway Plug-in for WooCommerce", 'netopia-payments-payment-gateway' );
		$this->title = __( "NETOPIA", 'netopia-payments-payment-gateway' );
		$this->icon = NTP_PLUGIN_DIR . 'img/netopiapayments.gif';
		$this->has_fields = true;
		$this->notify_url        	= WC()->api_request_url( 'netopiapayments' );

		// Supports the default credit card form
		$this->supports = array(
	               'products',
	            //    'refunds'
	               );
		
		$this->init_form_fields();
		
		$this->init_settings();
		
		// Turn these settings into variables we can use
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}
		
		/**
		 * Define Action for IPN. base on WooCommerce api
		 */
		// Can be use if there is no permtion to use API
		// add_action('init', array(&$this, 'check_netopiapayments_response'));
		add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_netopiapayments_response' ) );

		// Save settings
		if ( is_admin() ) {
			// Versions over 2.0
			// Save our administration options. Since we are not going to be doing anything special
			// we have not defined 'process_admin_options' in this class so the method in the parent
			// class will be used instead

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			// if(get_option( 'woocommerce_netopiapayments_certifications' ) === 'verify-and-regenerate') {	
			// 	if($this->account_id) {	
			// 		$this->certificateVerifyRegenerate($this->account_id);	
			// 		delete_option( 'woocommerce_netopiapayments_certifications' );// delete Option after executed one time	
			// 	}	
			// }
		
		
			// define .key and .cer as valid file for Wordpress
			add_filter('upload_mimes', function ($mimes) {
				$mimes['key'] = 'text/plain'; // MIME type for .key
				$mimes['cer'] = 'text/plain'; // MIME type for .cer
				return $mimes;
			});	

		}

		add_action('woocommerce_receipt_netopiapayments', array(&$this, 'receipt_page'));
	} 	

	// Build the administration fields for this specific Gateway
	public function init_form_fields() {
		$this->form_fields = array(			
			'enabled' => array(
				'title'		=> __( 'Enable / Disable', 'netopia-payments-payment-gateway' ),
				'label'		=> __( 'Enable this payment gateway', 'netopia-payments-payment-gateway' ),
				'type'		=> 'checkbox',
				'default'	=> 'no',
			),
			'environment' => array(
				'title'		=> __( 'NETOPIA Payments Test Mode', 'netopia-payments-payment-gateway' ),
				'label'		=> __( 'Enable Test Mode', 'netopia-payments-payment-gateway' ),
				'type'		=> 'checkbox',
				'description' => __( 'Place the payment gateway in test mode.', 'netopia-payments-payment-gateway' ),
				'default'	=> 'no',
			),
			'title' => array(
				'title'		=> __( 'Title', 'netopia-payments-payment-gateway' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Payment title the customer will see during the checkout process.', 'netopia-payments-payment-gateway' ),
				'default'	=> __( 'NETOPIA Payments', 'netopia-payments-payment-gateway' ),
			),
			'description' => array(
				'title'		=> __( 'Description', 'netopia-payments-payment-gateway' ),
				'type'		=> 'textarea',
				'desc_tip'	=> __( 'Payment description the customer will see during the checkout process.', 'netopia-payments-payment-gateway' ),
				'css'		=> 'max-width:350px;',
			),
			'default_status' => array(
				'title'		=> __( 'Default status', 'netopia-payments-payment-gateway' ),
				'type'		=> 'select',
				'desc_tip'	=> __( 'Default status of transaction.', 'netopia-payments-payment-gateway' ),
				'default'	=> 'processing',
				'options' => array(
					'completed' => __('Completed', 'netopia-payments-payment-gateway'),
					'processing' => __('Processing', 'netopia-payments-payment-gateway'),
				),
				'css'		=> 'max-width:350px;',
			),
			'key_setting' => array(
                'title'       => __( 'Login to NETOPIA Platform and go to <i>"Puncte de vânzare"</i> -> <i>"Optiuni"</i> (iconita cu 3 puncte) -> <i>"Setari tehnice"</i>', 'netopia-payments-payment-gateway' ),
                'type'        => 'title',
                'description' => '',
            ),
			'account_id' => array(
				'title'		=> __( 'Account Signature', 'netopia-payments-payment-gateway' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Signature is an unique ID generated for each seller account (cont de comerciant)', 'netopia-payments-payment-gateway' ),
			),
            'live_cer' => array(
                'title'		=> __( 'Live public key: ', 'netopia-payments-payment-gateway' ),
                'type'		=> 'file',
                'desc_tip'	=> is_null($this->get_option('live_cer')) ?  __( 'Download the Certificat digital mobilPay™ from Netopia and upload here', 'netopia-payments-payment-gateway' ) : $this->get_option('live_cer'),
            ),
            'live_key' => array(
                'title'		=> __( 'Live private key: ', 'netopia-payments-payment-gateway' ),
                'type'		=> 'file',
                'desc_tip'	=> is_null($this->get_option('live_key')) ? __( 'Download the Certificat merchant account / Privated key™ from Netopia and upload here', 'netopia-payments-payment-gateway' ) : $this->get_option('live_key'),
            ),
            'sandbox_cer' => array(
                'title'		=> __( 'Sandbox public key: ', 'netopia-payments-payment-gateway' ),
                'type'		=> 'file',
                'desc_tip'	=> is_null($this->get_option('sandbox_cer')) ? __( 'Download the Sandbox Certificat digital mobilPay™ from Netopia and upload here', 'netopia-payments-payment-gateway' ) : $this->get_option('sandbox_cer'),
            ),
            'sandbox_key' => array(
                'title'		=> __( 'Sandbox private key: ', 'netopia-payments-payment-gateway' ),
                'type'		=> 'file',
                'desc_tip'	=> is_null($this->get_option('sandbox_key')) ? __( 'Download the Sandbox Certificat merchant account / Privated key™ from Netopia and upload here', 'netopia-payments-payment-gateway' ) : $this->get_option('sandbox_key'),
            ),
			/**
			 * Netopia Payment Method like SMS,.. is removed form v1.4
			 * The payment_methods, sms_setting, service_id
			 * was related to this ,...
			 */
			'payment_methods'   => array(
		        'title'       => __( 'Payment methods', 'netopia-payments-payment-gateway' ),
		        'type'        => 'multiselect',
		        'description' => __( 'Select which payment methods to accept.', 'netopia-payments-payment-gateway' ),
		        'default'     => array('credit_card'),
		        'options'     => array(
		          'credit_card'	      => __( 'Credit Card', 'netopia-payments-payment-gateway' ),
				//   'bitcoin'  => __( 'Bitcoin', 'netopia-payments-payment-gateway' )
		        //   'sms'			        => __('SMS' , 'netopia-payments-payment-gateway' ),
		        //   'bank_transfer'		      => __( 'Bank Transfer', 'netopia-payments-payment-gateway' ),
		          ),
		    ),
			// 'sms_setting' => array(
			// 	'title'       => __( 'For SMS Payment', 'netopia-payments-payment-gateway' ),
			// 	'type'        => 'title',
			// 	'description' => '',
			// ),	
			// 'service_id' => array(
			// 	'title'		=> __( 'Product/service code: ', 'netopia-payments-payment-gateway' ),
			// 	'type'		=> 'text',
			// 	'desc_tip'	=> __( 'This is Service Code provided by Netopia when you signed up for an account.', 'netopia-payments-payment-gateway' ),
			// 	'description' => __( 'Login to Netopia and go to Admin -> Conturi de comerciant -> Produse si servicii -> Semnul plus', 'netopia-payments-payment-gateway' ),
			// ),
		);		
	}

	/**
	 * Netopia Payment Method like SMS,.. is removed form v1.4
	 * This part was related to this ,...
	 */
	function payment_fields() {
		// Description of payment method from settings
      	if ( $this->description ) { ?>
        	<p><?php echo esc_html($this->description); ?></p>
  		<?php }else {
			?><p><?php echo esc_html("Plata online prin NETOPIA Payments"); ?></p><?php
		}

  		if ( $this->payment_methods ) {  
  			$payment_methods = $this->payment_methods;	
  		}else{
  			 $payment_methods = array();
  		}
		$checked ='';
  		$name_methods = array(
		          'credit_card'	      => __( 'Credit Card', 'netopia-payments-payment-gateway' ),
		        //   'bitcoin'  => __( 'Bitcoin', 'netopia-payments-payment-gateway' )
				//   'sms'			        => __('SMS' , 'netopia-payments-payment-gateway' ),
		        //   'bank_transfer'		      => __( 'Bank Transfer', 'netopia-payments-payment-gateway' ),
		          );
  		?>
  		
	  		<?php
			switch (true) {
				case empty($payment_methods):
					?><div id="netopia-methods">Nu este setata nicio metoda de plata!</div><?php
					break;
				case count($payment_methods) == 1:
					// Default is Credit/Debit Card (the ZERO index)
					?><input type="hidden" name="netopia_method_pay" class="netopia-method-pay" id="netopia-method-<?php echo esc_attr($payment_methods[0]); ?>" value="<?php echo esc_attr($payment_methods[0]); ?>"/><?php
					break;
				case count($payment_methods) > 1:
					?><div id="netopia-methods">
						<ul><?php
							foreach ($payment_methods as $method) {
								// Verify if the payment method is available in the list.
								if(array_key_exists($method, $name_methods)) {
									$checked = $method == 'credit_card' ? 'checked="checked"' : '';
									?>
										<li>
											<input type="radio" name="netopia_method_pay" class="netopia-method-pay" id="netopia-method-<?php echo esc_attr($method); ?>" value="<?php echo esc_attr($method); ?>" <?php echo esc_attr($checked); ?> /><label for="inspire-use-stored-payment-info-yes" style="display: inline;"><?php echo esc_html($name_methods[$method]); ?></label>
										</li>
									<?php
									}
								}
						?></ul>
					</div><?php
					break;
			}
		?>
	  		
  		

  		<style type="text/css">
  			#netopia-methods{display: inline-block;}
  			#netopia-methods ul{margin: 0;}
  			#netopia-methods ul li{list-style-type: none;}
		</style>
		<script type="text/javascript">
			jQuery(document).ready(function($){				
				var method_ = $('input[name=netopia_method_pay]:checked').val();
				if(method_!='sms'){
					$('.billing-shipping').show('slow');
				}else{
					$('.billing-shipping').hide('slow');
				}

				//console.log('method_: ',method_);
				$('.netopia-method-pay').click(function(){
					var method = $(this).val();
					//console.log('method: ',method);
					if(method!='sms'){
						$('.billing-shipping').show('slow');
					}else{
						$('.billing-shipping').hide('slow');
					}					
				});
			});
		</script>
  		<?php
  	}
	/**/

  	// Submit payment
	public function process_payment( $order_id ) {
		global $woocommerce;

		// Retrieve the selected payment method
		$method = isset($_POST['netopia_method_pay']) ? sanitize_text_field(wp_unslash($_POST['netopia_method_pay'])) : ''; // Should be have this value in both classic & WooCommerce Blocks

		$order = new WC_Order( $order_id );	

		if ( version_compare( WOOCOMMERCE_VERSION, '2.1.0', '>=' ) ) {
			/* 2.1.0 */
			$checkout_payment_url = $order->get_checkout_payment_url( true );
		} else {
			/* 2.0.0 */
			$checkout_payment_url = get_permalink( get_option ( 'woocommerce_pay_page_id' ) );
		}

		return array(
			'result' => 'success', 
			'redirect' => add_query_arg(
				'method', 
				$method, 
				add_query_arg(
					'key', 
					$order->get_order_key(), 
					$checkout_payment_url						
				)
			)
		);
    }

	// Validate fields
	// Because we have diffrent payment method , so is necessary to validate it
	public function validate_fields() {
		$method_pay            = $this->get_post( 'netopia_method_pay' );
		// Check card number
		if ( empty( $method_pay ) ) {
			wc_add_notice( __( 'Alege metoda de plata.', 'netopia-payments-payment-gateway' ), $notice_type = 'error' );
			return false;
		}
		return true;
	}

  	/**
	* Receipt Page
	**/
	function receipt_page($order){
		$customer_order = new WC_Order( $order );
		$order_amount = sprintf('%.2f',$customer_order->get_total());
		echo '<p>'.esc_html(__('Multumim pentru comanda, te redirectionam in pagina de plata NETOPIA payments.', 'netopia-payments-payment-gateway')).'</p>';
		echo '<p><strong>'.esc_html(__('Total', 'netopia-payments-payment-gateway').": ".sanitize_text_field($customer_order->get_total()).' '.sanitize_text_field($customer_order->get_currency())).'</strong></p>';
		
		// Output of sanitized form 
		$generatedNetopiaForm = $this->generate_netopia_form($order);
		if(!empty($generatedNetopiaForm)) {
			$allowed_tags = array(
				'div' => array(
					'class' => true,
					'id' => true,
					'style' => true,
					'data-*' => true, // Allow any data-* attributes
				),
				'span' => array(
					'class' => true,
					'id' => true,
					'style' => true,
				),
				'p' => array(
					'class' => true,
					'style' => true,
				),
				'strong' => array(), // Allow bold text
				'img' => array(
					'src' => true,
					'title' => true,
					'alt' => true,
					'style' => true,
					'width' => true,
					'height' => true,
				),
				'script' => array(
					'type' => true, // Optional: Allow specifying script type
				),
				'a' => array(
					'href' => true,
					'title' => true,
					'class' => true,
				),
				'form' => array(
					'action' => true,
					'method' => true,
					'id' => true,
					'class' => true,
				),
				'input' => array(
					'type' => true,
					'name' => true,
					'value' => true,
					'class' => true,
					'id' => true,
				),
				'select' => array(
					'name' => true,
					'id' => true,
					'class' => true,
				),
				'option' => array(
					'value' => true,
				),
				'ul' => array(
					'class' => true,
				),
				'li' => array(
					'class' => true,
				),
				'table' => array(
					'class' => true,
					'style' => true,
				),
				'tr' => array(
					'class' => true,
					'style' => true,
				),
				'td' => array(
					'class' => true,
					'style' => true,
				),
				'th' => array(
					'class' => true,
					'style' => true,
				),
				'button' => array(
					'type' => true,
					'class' => true,
					'id' => true,
				),
			);			
			echo wp_kses($generatedNetopiaForm, $allowed_tags);
		}
	}

	/**
	* Generate payment button link
	**/
	function generate_netopia_form($order_id){
		global $woocommerce;
		// Get this Order's information so that we know
		// who to charge and how much
		$customer_order = new WC_Order( $order_id );

		$user = new WP_User( $customer_order->get_user_id());
		
		$paymentUrl = ( $this->environment == 'yes' ) 
						   ? 'https://sandboxsecure.mobilpay.ro/'
						   : 'https://secure.mobilpay.ro/';
		if ($this->environment == 'yes') {
			// $x509FilePath = plugin_dir_path( __FILE__ ).'netopia/certificate/sandbox.'.$this->account_id.'.public.cer';
			$x509FileContent = get_option('woocommerce_netopiapayments_sandbox_cer_content', false);
		}
		else {
			// $x509FilePath = plugin_dir_path( __FILE__ ).'netopia/certificate/live.'.$this->account_id.'.public.cer';
			$x509FileContent = get_option('woocommerce_netopiapayments_live_cer_content', false);
		}
		
		require_once 'netopia/Payment/Request/Abstract.php';		
		require_once 'netopia/Payment/Invoice.php';
		require_once 'netopia/Payment/Address.php';

		// Chosen Payment METHOD of NETOPIA (BTC , CARD ,...)
		$method = sanitize_text_field($this->get_post( 'method' ));
		
		$name_methods = array(
		          'credit_card' => __( 'Credit Card', 'netopia-payments-payment-gateway' ),
		        //   'bitcoin' => __( 'Bitcoin', 'netopia-payments-payment-gateway' )
				//   'sms' => __('SMS' , 'netopia-payments-payment-gateway' ),
		        //   'bank_transfer' => __( 'Bank Transfer', 'netopia-payments-payment-gateway' ),
		          );
		switch ($method) {
			case 'sms':		
				require_once 'netopia/Payment/Request/Sms.php';
				$objPmReq = new Netopia_Payment_Request_Sms();	
				$objPmReq->service 		= $this->service_id;	
				break;
			case 'bank_transfer':
				require_once 'netopia/Payment/Request/Transfer.php';
				$objPmReq = new Netopia_Payment_Request_Transfer();	
				$paymentUrl .= '/transfer';
				break;
			// case 'bitcoin':	
			// 	require_once 'netopia/Payment/Request/Bitcoin.php';
			// 	$objPmReq = new Netopia_Payment_Request_Bitcoin();			
			// 	$paymentUrl = 'https://secure.mobilpay.ro/bitcoin'; //for both sanbox and live
			// 	break;
			default: // credit_card
				require_once 'netopia/Payment/Request/Card.php';
				$objPmReq = new Netopia_Payment_Request_Card();
				break;
		}
		
		$objPmReq->signature 			= $this->account_id;
		$objPmReq->orderId 				= md5(uniqid(wp_rand(0, 1000000)));
		$objPmReq->confirmUrl 			= $this->notify_url;
		$objPmReq->returnUrl 			= htmlentities(WC_Payment_Gateway::get_return_url( $customer_order ));
		
		if($method != 'sms'){
			$objPmReq->invoice = new Netopia_Payment_Invoice();
			$objPmReq->invoice->currency	= $customer_order->get_currency();
			$objPmReq->invoice->amount		= sprintf('%.2f',$customer_order->get_total());
			// $objPmReq->invoice->details		= 'Plata pentru comanda cu ID: '.$order_id.' with '.$name_methods[$method];
			$objPmReq->invoice->details		= 'Plata pentru comanda cu ID: '.$order_id;

			$billingAddress 				= new Netopia_Payment_Address();
			$billingAddress->type			= 'person';
			$billingAddress->firstName		= $customer_order->get_billing_first_name();
			$billingAddress->lastName		= $customer_order->get_billing_last_name();
			$billingAddress->address		= $customer_order->get_billing_address_1();
			$billingAddress->email			= $customer_order->get_billing_email();
			$billingAddress->city           = $customer_order->get_billing_city();
            $billingAddress->zipCode        = $customer_order->get_billing_postcode();
			$billingAddress->mobilePhone	= $customer_order->get_billing_phone();
			$objPmReq->invoice->setBillingAddress($billingAddress);

			$shippingAddress 				= new Netopia_Payment_Address();
			$shippingAddress->type			= 'person';
			$shippingAddress->firstName		= $customer_order->get_shipping_first_name();
			$shippingAddress->lastName		= $customer_order->get_shipping_last_name();
			$shippingAddress->address		= $customer_order->get_shipping_address_1();
			$shippingAddress->email			= $customer_order->get_billing_email();
			$shippingAddress->mobilePhone	= $customer_order->get_billing_phone();
			$objPmReq->invoice->setShippingAddress($shippingAddress);
		}		
		
		
		$customer_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

		$objPmReq->params = array(	
			'order_id'		=> $order_id,	
			'customer_id'	=> $customer_order->get_user_id(),	
			'customer_ip'	=> $customer_ip,	
			'method'		=> $method,	
			'cartSummary' 	=> $this->getCartSummary(),	
			'ntpPlugin' 	=> $this->getNtpPluginInfo(),
			'wordpress' 	=> $this->getWpInfo(),	
			'wooCommerce' 	=> $this->getWooInfo()	
		);
		try {	
		// $objPmReq->encrypt($x509FilePath);
		$objPmReq->encrypt($x509FileContent);
		return '<form action="'.esc_url($paymentUrl).'" method="post" id="frmPaymentRedirect">
				<input type="hidden" name="env_key" value="'.esc_attr($objPmReq->getEnvKey()).'"/>
				<input type="hidden" name="data" value="'.esc_attr($objPmReq->getEncData()).'"/>
				<input type="hidden" name="cipher" value="'.esc_attr($objPmReq->getCipher()).'"/>
				<input type="hidden" name="iv" value="'.esc_attr($objPmReq->getIv()).'"/>
				<input type="submit" class="button-alt" id="submit_netopia_payment_form" value="'.__('Plateste prin NETOPIA payments', 'netopia-payments-payment-gateway').'" /> <a class="button cancel" href="'.esc_url($customer_order->get_cancel_order_url()).'">'.__('Anuleaza comanda &amp; goleste cosul', 'netopia-payments-payment-gateway').'</a>
				<script type="text/javascript">
				jQuery(function(){
				jQuery("body").block({
					message: "'.esc_js(__('Iti multumim pentru comanda. Te redirectionam catre NETOPIA payments pentru plata.', 'netopia-payments-payment-gateway')).'",
					overlayCSS: {
						background		: "#fff",
						opacity			: 0.6
					},
					css: {
						padding			: 20,
						textAlign		: "center",
						color			: "#555",
						border			: "3px solid #aaa",
						backgroundColor	: "#fff",
						cursor			: "wait",
						lineHeight		: "32px"
					}
				});
				jQuery("#submit_netopia_payment_form").click();});
				</script>
			</form>';
		} catch (\Exception $e) {
			echo'<p><i style="color:red">'. esc_html('Asigura-te ca ai incarcat toate cele 4 chei de securitate, 2 pentru mediul live, 2 pentru mediul sandbox! Citeste cu atentie instructiunile din manual!').'</i></p>';
			echo '<p style="font-size:small">'.esc_html('Ai in continuare probleme? Trimite-ne doua screenshot-uri pe email la departamentul de implementare, unul cu setarile metodei de plata din adminul wordpress si unul cu locatia in care ai incarcat cheile (de preferat sa se vada denumirea completa a cheilor si calea completa a locatiei)').'</p>';
		}
	}	

	/**
	* Check for valid NETOPIA server callback
	**/
	function check_netopiapayments_response(){
		if (is_admin()) {
			return;
		}

		global $woocommerce;

		require_once 'netopia/Payment/Request/Abstract.php';

		require_once 'netopia/Payment/Request/Card.php';
		require_once 'netopia/Payment/Request/Sms.php';
		require_once 'netopia/Payment/Request/Transfer.php';
		// require_once 'netopia/Payment/Request/Bitcoin.php';

		require_once 'netopia/Payment/Request/Notify.php';
		require_once 'netopia/Payment/Invoice.php';
		require_once 'netopia/Payment/Address.php';

		$errorCode 		= 0;
		$errorType		= Netopia_Payment_Request_Abstract::CONFIRM_ERROR_TYPE_NONE;
		$errorMessage	= '';
		$env_key 	= isset($_POST['env_key']) ? sanitize_text_field(wp_unslash($_POST['env_key'])) : '';
		$data    	= isset($_POST['data']) ? $_POST['data'] : ''; // Don't sanitize encrypted data
		$cipher     = 'rc4';
		$iv         = null;
		if(array_key_exists('cipher', $_POST))
		{
			$cipher = sanitize_text_field(wp_unslash($_POST['cipher']));
			if(array_key_exists('iv', $_POST))
			{
				$iv = sanitize_text_field(wp_unslash($_POST['iv']));
			}
		}
		
		// Validate Input
		if (empty($env_key) || empty($data)) {
			die(esc_html('Missing env_key or data !'));
		}

		$msg_errors = array(
			'16'=>'card has a risk (i.e. stolen card)', 
			'17'=>'card number is incorrect',
			'18'=>'closed card',
			'19'=>'card is expired',
			'20'=>'insufficient funds',
			'21'=>'cVV2 code incorrect',
			'22'=>'issuer is unavailable',
			'32'=>'amount is incorrect',
			'33'=>'currency is incorrect',
			'34'=>'transaction not permitted to cardholder',
			'35'=>'transaction declined',
			'36'=>'transaction rejected by antifraud filters',
			'37'=>'transaction declined (breaking the law)',
			'38'=>'transaction declined',
			'48'=>'invalid request',
			'49'=>'duplicate PREAUTH',
			'50'=>'duplicate AUTH',
			'51'=>'you can only CANCEL a preauth order',
			'52'=>'you can only CONFIRM a preauth order',
			'53'=>'you can only CREDIT a confirmed order',
			'54'=>'credit amount is higher than auth amount',
			'55'=>'capture amount is higher than preauth amount',
			'56'=>'duplicate request',
			'99'=>'generic error');
		
		if ($this->environment == 'yes') {
			$privateKeyContent = get_option('woocommerce_netopiapayments_sandbox_key_content', false);
		}
		else {
			$privateKeyContent = get_option('woocommerce_netopiapayments_live_key_content', false);
		}

		if (isset($_SERVER['REQUEST_METHOD']) && strcasecmp(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])), 'post') == 0){
			try
			{
				$objPmReq = Netopia_Payment_Request_Abstract::factoryFromEncrypted($env_key, $data, $privateKeyContent, null, $cipher, $iv);
				$action = $objPmReq->objPmNotify->action;
				$params = $objPmReq->params;
				$order = new WC_Order( $params['order_id'] );
				$user = new WP_User( $params['customer_id'] );
				$transaction_id = $objPmReq->objPmNotify->purchaseId;
				if($objPmReq->objPmNotify->errorCode==0){
					switch($action)
					{
						case 'confirmed':
							#cand action este confirmed avem certitudinea ca banii au plecat din contul posesorului de card si facem update al starii comenzii si livrarea produsului
							//update DB, SET status = "confirmed/captured"
							$errorMessage = $objPmReq->objPmNotify->errorMessage;
							
							$amountorder_RON = $objPmReq->objPmNotify->originalAmount; 
							$amount_paid = is_null($objPmReq->objPmNotify->originalAmount) ? 0:$objPmReq->objPmNotify->originalAmount;
							
							//original_amount -> the original amount processed;
							//processed_amount -> the processed amount at the moment of the response. It can be lower than the original amount, ie for capturing a smaller amount or for a partial credit
							if( $order->get_status() != 'completed' ) {
								if( $amount_paid < $amountorder_RON ) {
									if($this->isAllowedToChangeStatus($order)){
										//Update the order status
										$order->update_status('on-hold', '');

										//Error Note
										$message = 'Tranzactia de plata a fost efectuata cu succes, dar suma platita nu este aceeasi cu suma totala a comenzii. <br> Comanda dvs. este in prezent in asteptare. <br> Va rugam sa ne contactati pentru mai multe informatii.';
										$message_type = 'notice';

										//Add Customer Order Note
										$order->add_order_note($message.'<br />ID Tranzactie NETOPIA: '.$transaction_id, 1);

										//Add Admin Order Note
										$order->add_order_note('Tranzactia este momentan in asteptare. <br />Motiv: Suma platita este mai mica fata de totalul comenzii.<br />Suma platita a fost de '.$amount_paid.' RON, in timp ce suma totala a comenzii este '.$amountorder_RON.' RON<br />ID Tranzactie NETOPIA: '.$transaction_id);

										// Reduce stock levels
										wc_reduce_stock_levels($order->get_id());

										// Empty cart
										wc_empty_cart();
									}
								}
							else {
								if( $order->get_status() == 'processing' ) {
									$order->add_order_note('Plata prin NETOPIA payments<br />Transaction ID: '.$transaction_id);

									//Add customer order note
									$order->add_order_note('Plata receptionata.<br />Comanda este in curs de procesare.<br />Vom face livrarea in curand.<br />ID Tranzactie NETOPIA: '.$transaction_id, 1);

									// Reduce stock levels
									wc_reduce_stock_levels($order->get_id());

									// Empty cart
									wc_empty_cart();
								}
								else {
									if( $order->has_downloadable_item() ) {

										//Update order status
										$order->update_status( 'completed', 'Plata primita, Comanda dvs. este acum completa.' );

										//Add admin order note
										$order->add_order_note('Plata prin NETOPIA payments<br />Transaction ID: '.$transaction_id);

										//Add customer order note
										$order->add_order_note('Plata primita.<br />Comanda dvs. este acum completa.<br />ID Tranzactie NETOPIA: '.$transaction_id, 1);
									}
									else {

										//Update order status
										$msgDefaultStatus = ($this->default_status == 'processing') ? 'Plata primita, Comanda dvs. este in prezent in curs de procesare.' : 'Plata primita, Comanda dvs. este acum completa.';
										$order->update_status( $this->default_status, $msgDefaultStatus );

										//Add admin order noote
										$order->add_order_note('Plata prin NETOPIA payments<br />Transaction ID: '.$transaction_id);

										//Add customer order note
										$order->add_order_note($msgDefaultStatus.'<br />ID Tranzactie NETOPIA: '.$transaction_id, 1);

										$message = 'Tranzactia a fost efectuata cu succes, plata a fost primita.<br />Comanda dvs este in prezent in procesare.';
										$message_type = 'success';
									}

									// Reduce stock levels
									wc_reduce_stock_levels($order->get_id());

									// Empty cart
									wc_empty_cart();
								}
							}
						}
						else {}
							break;
						case 'paid':
							if($this->isAllowedToChangeStatus($order)){
								//Update order status -> to be added, but on-hold should work for now
								$order->update_status( 'on-hold', 'Comanda este in prezent in curs de procesare.' );
								//Add admin order note
								$order->add_order_note('Plata acceptata prin NETOPIA, asigurati-va sa o capturati<br />ID Tranzactie: '.$transaction_id);
							}
							break;	
						case 'confirmed_pending':
							if($this->isAllowedToChangeStatus($order)){
								//Update order status
								$order->update_status( 'on-hold', 'Comanda este in prezent in curs de procesare.' );
								//Add admin order note
								$order->add_order_note('Plata in asteptare prin NETOPIA.<br />ID Tranzactie: '.$transaction_id);
							}
							break;
						case 'paid_pending':
							if($this->isAllowedToChangeStatus($order)){
								//Update order status
								$order->update_status( 'on-hold', 'Comanda este in prezent in curs de procesare.' );
								//Add admin order note
								$order->add_order_note('Plata in asteptare prin NETOPIA.<br />ID Tranzactie: '.$transaction_id);
							}
							break;
						case 'canceled':
							if($this->isAllowedToChangeStatus($order)){
								#cand action este canceled inseamna ca tranzactia este anulata. Nu facem livrare/expediere.
								//update DB, SET status = "canceled"
								$errorMessage = $objPmReq->objPmNotify->errorMessage;							

								$message = 	'Va multumim pentru cumparaturi. <br />Insa, tranzactia nu a fost efectuata cu succes, plata nu a fost primita.';
								//Add Customer Order Note
								$order->add_order_note($message.'<br />ID Tranzactie NETOPIA: '.$transaction_id, 1);

								//Add Admin Order Note
								$order->add_order_note($message.'<br />ID Tranzactie NETOPIA: '.$transaction_id);

								//Update the order status
								$order->update_status('cancelled', '');
							}
							break;
						case 'credit':
							#cand action este credit inseamna ca banii sunt returnati posesorului de card. Daca s-a facut deja livrare, aceasta trebuie oprita sau facut un reverse. 
							//update DB, SET status = "refunded"
							if ($objPmReq->invoice->currency != 'RON') {
								$rata_schimb = $objPmReq->objPmNotify->originalAmount/$objPmReq->invoice->amount;
								}
								else $rata_schimb = 1;
							$refund_amount = $objPmReq->objPmNotify->processedAmount/$rata_schimb;

							$args = array( 
								'amount' => $refund_amount,  
								'reason' => 'Netopia call',  
								'order_id' => $params['order_id'],  
								'refund_id' => null,  
								'line_items' => array(),  
								'refund_payment' => false,  
								'restock_items' => false  
									); 
									
							$refund = wc_create_refund($args);	
								
							$errorMessage = $objPmReq->objPmNotify->errorMessage;
							$message = 	'Plata rambursata.';
							//Add Customer Order Note
							$order->add_order_note($message.'<br />ID Tranzactie NETOPIA: '.$transaction_id, 1);

							//Add Admin Order Note
							$order->add_order_note($message.'<br />ID Tranzactie NETOPIA: '.$transaction_id);

							//Update the order status if fully refunded
							if ($refund_amount == $objPmReq->objPmNotify->originalAmount) {
							$order->update_status('refunded', '');
							}
							break;	
					}
				}else{
					if($this->isAllowedToChangeStatus($order)){
						//Error Note
						$message = $objPmReq->objPmNotify->errorMessage;
						if(empty($message) && isset($msg_errors[$objPmReq->objPmNotify->errorCode])) $message = $msg_errors[$objPmReq->objPmNotify->errorCode];
						$message_type = 'error';

						// Status changed to Failed
						$order->update_status('failed', $message);
						
						//Add Customer Order Note
						$order->add_order_note($message.'<br />ID Tranzactie NETOPIA: '.$transaction_id, 1);
					}						
				}					
			}catch(Exception $e)
			{
				$errorType 		= Netopia_Payment_Request_Abstract::CONFIRM_ERROR_TYPE_TEMPORARY;
				$errorCode		= $e->getCode();
				$errorMessage 	= $e->getMessage();
			}
		}else 
		{
			$errorType 		= Netopia_Payment_Request_Abstract::CONFIRM_ERROR_TYPE_PERMANENT;
			$errorCode		= Netopia_Payment_Request_Abstract::ERROR_CONFIRM_INVALID_POST_METHOD;
			$errorMessage 	= 'invalid request method for payment confirmation';
		}
		

		// Preparing Sanitized respunse 
		$errorType = htmlspecialchars(isset($errorType) ? sanitize_text_field($errorType) : '', ENT_QUOTES, 'UTF-8');
		$errorCode = isset($errorCode) && is_numeric($errorCode) ? intval($errorCode) : 0;
		$errorMessage = htmlspecialchars(isset($errorMessage) ? sanitize_textarea_field($errorMessage) : '', ENT_QUOTES, 'UTF-8');


		header('Content-type: application/xml');
		echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
		if($errorCode == 0)
		{
			echo "<crc>".esc_html($errorMessage)."</crc>";
		}
		else
		{
			echo "<crc error_type=\"".esc_html($errorType)."\" error_code=\"".esc_html($errorCode)."\">".esc_html($errorMessage)."</crc>";
			
		}
		/*
		Just Die, after display the XML
		We don't use wp_die() here , becuse we need a clear XML, without any extera data or tag ,...
		*/
		die();
	}

	/**
	 * Check if order status is allowed to be changed
	 */
	public function isAllowedToChangeStatus($orderInfo) {
		$arrStatus = array("completed", "processing");
		if (in_array($orderInfo->get_status(), $arrStatus)) {
			return false;
		}else {
			return true;
		}
		
	}


	/**
	 * Get post data if set
	 */
	private function get_post( $name ) {
		if ( isset( $_REQUEST[ $name ] ) ) {
			return sanitize_text_field(wp_unslash($_REQUEST[ $name ]));
		} elseif ( isset( $_POST[ $name ] ) ) {
			return sanitize_text_field(wp_unslash($_POST[ $name ]));
		}
		return null;
	}

    public function process_admin_options() {
        $this->init_settings();
        $post_data = $this->get_post_data();
        $cerValidation = $this->cerValidation();

        foreach ( $this->get_form_fields() as $key => $field ) {
            if ( ('title' !== $this->get_field_type( $field )) && ('file' !== $this->get_field_type( $field ))) {
                try {
                    $this->settings[ $key ] = $this->get_field_value( $key, $field, $post_data );
                } catch ( Exception $e ) {
                    $this->add_error( $e->getMessage() );
                }
            }

            if ( 'file' === $this->get_field_type( $field )) {
                    try {
                        if(isset($_FILES['woocommerce_netopiapayments_'.$key]['size']) && $_FILES['woocommerce_netopiapayments_'.$key]['size'] != 0 ) {
                            $strMessage = $cerValidation[$key]['type']. ' - ' .$cerValidation[$key]['message'];
                            $this->settings[ $key ] = $this->validate_text_field( $key, $strMessage );
                        }
                    } catch ( Exception $e ) {
                        $this->add_error( $e->getMessage() );
                    }
            }
        }
        return update_option( $this->get_option_key(), apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings ), 'yes' );
    }

    public function cerValidation() {
	    if(!$this->_canManageWcSettings()){
            wp_die(esc_html('can not manage Plugin - Permition denide.'));
        }

        $allowed_extension = array("key", "cer");
        foreach ($_FILES as $key => $fileInput){

			$sanitizedFileName = sanitize_file_name($fileInput["name"]);
            $file_extension = pathinfo($sanitizedFileName, PATHINFO_EXTENSION);
            $file_mime = sanitize_mime_type($fileInput["type"]);

            // Validate file input to check if is not empty
            if (! file_exists($fileInput["tmp_name"])) {
                $response = array(
                    "type" => esc_html("error"),
                    "message" => esc_html("Select file to upload.")
                );
            }// Validate file input to check if is with valid extension
            elseif (! in_array($file_extension, $allowed_extension)) {
                $response = array(
                    "type" => esc_html("error"),
                    "message" => esc_html("Upload valid certificate. Only .cer / .key are allowed.")
                );
            }// Validate file MIME
            else {
					if ($this->isValidFileExtension($file_extension, $key)) {
						$uploadeResult = $this->uploadCer($fileInput);
						if(!is_null($uploadeResult["filePath"])) {
							$fileContent = $this->getCertificateContent($uploadeResult["filePath"]);
							$this->updateCertificateContent($key.'_content', $fileContent);

							$response = array(
								"type" => esc_html("success"),
								"message" => esc_html("The file is uploaded and the content saved.")
							);
						} else {
							$response = array(
								"type" => esc_html("error"),
								"message" => esc_html("The File is not uploaded. There is a problem")
							);
						}
					} else {
							$response = array(
								"type" => esc_html("error"),
								"message" => esc_html("Wrong File - The file is suitable for this field!!")
							);
					}
                 }

            // Uploaded certificates
            switch ($key) {
                case "woocommerce_netopiapayments_live_cer" :
                    $certificate['live_cer'] = $response;
                    break;
                case "woocommerce_netopiapayments_live_key" :
                    $certificate['live_key'] = $response;
                    break;
                case "woocommerce_netopiapayments_sandbox_cer" :
                    $certificate['sandbox_cer'] = $response;
                    break;
                case "woocommerce_netopiapayments_sandbox_key" :
                    $certificate['sandbox_key'] = $response;
                    break;
            }
        }
        return $certificate;
    }

    public function isValidFileExtension($file_extension, $key) {
        switch ($key) {
            case "woocommerce_netopiapayments_live_cer" :
            case "woocommerce_netopiapayments_sandbox_cer" :
                if ($file_extension != 'cer')
                    return false;
                break;
            case "woocommerce_netopiapayments_live_key" :
            case "woocommerce_netopiapayments_sandbox_key" :
                if ($file_extension != 'key')
                    return false;
                break;
        }
        return true;
    }


	public function uploadCer($fileInput) {
		if (!function_exists('wp_handle_upload')) {
			require_once(ABSPATH . 'wp-admin/includes/file.php');
		}

		if (isset($fileInput['tmp_name']) && !empty($fileInput['tmp_name'])) {
			$upload_overrides = array('test_form' => false);
	
			// Handle the upload
			$uploaded_file = wp_handle_upload($fileInput, $upload_overrides);
			
			if (isset($uploaded_file['file'])) {
				$response = array(
					"type" => "success",
					"message" => "Certificate uploaded successfully.",
					"filePath" => $uploaded_file['file'],
				);
			} else {
				// Error from wp_handle_upload
				$response = array(
					"type" => "error",
					"message" => "Problem in uploading Certificate: " . $uploaded_file['error'],
					"filePath" => null
				);
			}
		} else {
			$response = array(
				"type" => "error",
				"message" => "No file provided for upload.",
				"filePath" => null
			);
		}
	
		return $response;
	}	



    private function _canManageWcSettings() {
        return current_user_can('manage_woocommerce');
	}

	public function getCertificateContent($uploadedKeyFilePath){
		if (!file_exists($uploadedKeyFilePath) || !is_readable($uploadedKeyFilePath)) {
			return 'Error: File does not exist or is not readable';
		}
		$certificateMap = $uploadedKeyFilePath;	
		$fileContent = file_get_contents($certificateMap, FILE_USE_INCLUDE_PATH);
		return $fileContent;	
	}

	public function updateCertificateContent($key,$content) {	
		update_option( $key, $content, 'yes' );	
	}

	public function getCartSummary() {	
		$cartArr = WC()->cart->get_cart();	
		$i = 0;	
		$cartSummary = array();	
		foreach ($cartArr as $key => $value ) {	
			$cartSummary[$i]['name'] 				=  $value['data']->get_name();	
			$cartSummary[$i]['price'] 			=  $value['data']->get_price();	
			$cartSummary[$i]['quantity'] 			=  $value['quantity'];	
			$cartSummary[$i]['short_description'] =  substr($value['data']->get_short_description(), 0, 100);	
			$i++;	
		}	
		return wp_json_encode($cartSummary);	
	}	

	public function getWpInfo() {	
		global $wp_version;	
		return 'Version '.$wp_version;	
	}

	public function getWooInfo() {	
		$wooCommerce_ver = WC()->version;	
		return 'Version '.$wooCommerce_ver;	
	}

	public function getNtpPluginInfo() {
		$ntpPlugin_ver ="Version 1.4.4";
		return $ntpPlugin_ver;
	}
}