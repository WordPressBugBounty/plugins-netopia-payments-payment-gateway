<?php
/**
 * Netopia
 *
 * @package    Netopia_Payment_Request_Abstract
 * @copyright  Copyright (c) 2007-2023 Netopia
 * @author     Claudiu Tudose <claudiu.tudose@netopia-system.com>
 *
 * This class can be used for accessing Netopia.ro payment interface for your configured online services
 *
 * This class uses  OpenSSL
 * In order to use the OpenSSL functions you need to install the OpenSSL package.
 * Check PHP documentation for installing OpenSSL package
 */
abstract class Netopia_Payment_Request_Abstract
{
	/**
	 * payment type constants
	 */
	const PAYMENT_TYPE_SMS 			= 'sms';
	const PAYMENT_TYPE_CARD 		= 'card';
	const PAYMENT_TYPE_COD 			= 'cod';
	const PAYMENT_TYPE_ADMIN 		= 'admin';
	const PAYMENT_TYPE_INFO 		= 'info';
	const PAYMENT_TYPE_CODE 		= 'code';
	const PAYMENT_TYPE_CASH 		= 'cash';
	const PAYMENT_TYPE_TRANSFER 	= 'transfer';
	const PAYMENT_TYPE_BITCOIN 		= 'bitcoin';
	const PAYMENT_TYPE_ING_HOME_PAY = 'IngHomePay';
	const PAYMENT_TYPE_HOME_PAY 	= 'homePay';

	/**
	 * confirm error constants
	 */
	const CONFIRM_ERROR_TYPE_NONE 		= 0x00;
	const CONFIRM_ERROR_TYPE_TEMPORARY 	= 0x01;
	const CONFIRM_ERROR_TYPE_PERMANENT 	= 0x02;

	/**
	 * class-specific errors
	 */
	const ERROR_LOAD_X509_CERTIFICATE 						= 0x10000001;
	const ERROR_ENCRYPT_DATA 								= 0x10000002;
	const ERROR_PREPARE_MANDATORY_PROPERTIES_UNSET 			= 0x11000001;
	const ERROR_FACTORY_BY_XML_ORDER_ELEM_NOT_FOUND 		= 0x20000001;
	const ERROR_FACTORY_BY_XML_ORDER_TYPE_ATTR_NOT_FOUND 	= 0x20000002;
	const ERROR_FACTORY_BY_XML_INVALID_TYPE 				= 0x20000003;
	const ERROR_LOAD_FROM_XML_ORDER_ID_ATTR_MISSING 		= 0x30000001;
	const ERROR_LOAD_FROM_XML_SIGNATURE_ELEM_MISSING 		= 0x30000002;
	const ERROR_CONFIRM_LOAD_PRIVATE_KEY 					= 0x300000f0;
	const ERROR_CONFIRM_FAILED_DECODING_DATA 				= 0x300000f1;
	const ERROR_CONFIRM_FAILED_DECODING_ENVELOPE_KEY 		= 0x300000f2;
	const ERROR_CONFIRM_FAILED_DECRYPT_DATA 				= 0x300000f3;
	const ERROR_CONFIRM_INVALID_POST_METHOD 				= 0x300000f4;
	const ERROR_CONFIRM_INVALID_POST_PARAMETERS 			= 0x300000f5;
	const ERROR_CONFIRM_INVALID_ACTION 						= 0x300000f6;
	const ERROR_CONFIRM_FAILED_DECODING_IV 					= 0x300000f7;
	const ERROR_REQUIRED_CIPHER_NOT_AVAILABLE				= 0x300000f8;

	/**
	 * version constants
	 */
	const VERSION_QUERY_STRING 	= 0x01;
	const VERSION_XML 			= 0x02;

	/**
	 * signatue (Mandatory) 	- signature received from mobilpay.ro that identifies merchant account
	 *
	 * @var string(64)
	 */
	public $signature = null;

	/**
	 * service - identifier of service/product for which you're requesting a payment
	 * Mandatory for Netopia_Payment_Request_Sms
	 * Optional for Netopia_Payment_Request_Card
	 */
	public $service = null;

	/**
	 * orderId (Mandatory)	- payment transaction identifier generated by merchant; helps merchant to interpret a request to confirm or return url;
	 * it should be unique for the specified signature
	 *
	 * @var string(64)
	 */
	public $orderId 	= null;
	public $timestamp 	= null;
	public $type 		= self::PAYMENT_TYPE_SMS;

	/**
	 *
	 * IPN object
	 * @var Netopia_Payment_Request_Notify
	 */
	public $objPmNotify = null;

	/**
	 * returnUrl (Optional) 	- URL where the user is redirected from mobilpay.ro payment interface
	 * when the transaction is canceled or confirmed. If it is not supplied the application will use
	 * return URL configured in control panel
	 * use it only if you want to overwrite the configured one otherwise set it's value to NULL
	 *
	 * @var string
	 */
	public $returnUrl = null;

	/**
	 * confirmUrl (Optional)	- URL of the seller that will be requested when mobilpay.ro will make
	 * a decision about payment (e.g. confirmed, canceled). If it is not supplied the application will use
	 * confirm URL configured in control panel
	 * use it only if you want to overwrite the configured one otherwise set it's value to NULL
	 *
	 * @var string
	 */
	public $confirmUrl 	= null;
	/**
	 * @var string
	 */
	public $cancelUrl 	= null;

	/**
	 * @var string
	 */
	public $ipnCipher = null;

	/**
	 *
	 * parameters array
	 * @var array
	 */
	public $params = array();

	/**
	 * outEnvKey - output envelope key
	 * in this property is stored the envelope key after encrypting data to send to payment interface
	 */
	protected $outEnvKey = null;

	/**
	 * outEncData - output encrypted data
	 * in this property is stored the encrypted data to send to payment interface
	 */
	protected $outEncData = null;

	/**
	 * cipher algorithm used to encrypt data
	 * @var string
	 */
	protected $outCipher = null;

	/**
	 * intialization vector seed used to encrypt data
	 * @var string
	 */
	protected $outIv = null;

	/**
	 *
	 * the computed xml document
	 * @var DOMDocument
	 */
	protected $_xmlDoc = null;
	protected $_requestIdentifier = null;

	/**
	 *
	 * Standard class for holing request parameters
	 * @var stdClass
	 */
	protected $_objRequestParams = null;

	/**
	 *
	 * Standard class for holing request informations
	 * @var stdClass
	 */
	protected $_objRequestInfo 		= null;
	public $objReqNotify 			= null;
	public $selectedInstallments 	= null;
	public $reqInstallments 		= null;
	public $secretCode 				= null;
	public $paymentInstrument 		= null;

	/**
	 *  
	 * Constructor
	 */
	public function __construct()
	{
		$this->_requestIdentifier = md5(uniqid(wp_rand(0, 1000000)));
		$this->_objRequestParams = new stdClass();
	}

	/**
	 *
	 * Compute the _xmlDoc object
	 * @throws Exception On invalid properties
	 */
	abstract protected function _prepare();

	/**
	 *
	 * Populate the class from the request xml
	 * @param DOMNode $elem
	 * @return Netopia_Payment_Reuquest_Abstract
	 * @throws Exception On missing xml attributes
	 */
	abstract protected function _loadFromXml(DOMElement $elem);

	/**
	 *
	 * Factory the object from a xml string
	 * @param string $data
	 * @return Netopia_Payment_Request_Abstract
	 * @throws Exception On missing xml attributes
	 */
	static public function factory($data)
	{
		$objPmReq = null;
		$xmlDoc = new DOMDocument();
		if(@$xmlDoc->loadXML($data) === true)
		{
			// try to create payment request from xml
			$objPmReq = Netopia_Payment_Request_Abstract::_factoryFromXml($xmlDoc);
			$objPmReq->_setRequestInfo(self::VERSION_XML, $data);
		}
		else
		{
			// try to create payment request from query string
			$objPmReq = Netopia_Payment_Request_Abstract::_factoryFromQueryString($data);
			$objPmReq->_setRequestInfo(self::VERSION_QUERY_STRING, $data);
		}

		return $objPmReq;
	}

	/**
	 *
	 * Factory the object from a encrypted xml string
	 * @param string $envKey
	 * @param string $encData
	 * @param string $privateKeyContent
	 * @param string $privateKeyPassword
	 * 
	 * @return Netopia_Payment_Request_Abstract
	 * @throws Exception On missing xml attributes
	 */
	static public function factoryFromEncrypted($envKey, $encData, $privateKeyContent, $privateKeyPassword = null, $cipher_algo = 'rc4', $iv = null)
	{
		$privateKey = null;
		if ($privateKeyPassword == null)
		{
			$privateKey = openssl_pkey_get_private($privateKeyContent);
		}
		else
		{
			$privateKey = @openssl_pkey_get_private($privateKeyContent, $privateKeyPassword);
		}
		if ($privateKey === false)
		{
			throw new Exception('Error loading private key', esc_html(self::ERROR_CONFIRM_LOAD_PRIVATE_KEY));
		}
		$srcData = base64_decode($encData);
		if ($srcData === false)
		{
			throw new Exception('Failed decoding data', esc_html(self::ERROR_CONFIRM_FAILED_DECODING_DATA));
		}
		$srcEnvKey = base64_decode($envKey);
		if($srcEnvKey === false)
		{
			throw new Exception('Failed decoding envelope key', esc_html(self::ERROR_CONFIRM_FAILED_DECODING_ENVELOPE_KEY));
		}
		$srcIv = base64_decode($iv);
		if($srcIv === false)
		{
			throw new Exception('Failed decoding initialization vector', esc_html(self::ERROR_CONFIRM_FAILED_DECODING_IV));
		}

		$data = null;
		if(PHP_VERSION_ID >= 70000)
		{
			$result = @openssl_open($srcData, $data, $srcEnvKey, $privateKey, $cipher_algo, $srcIv);
		}
		else
		{
			$result = @openssl_open($srcData, $data, $srcEnvKey, $privateKey, $cipher_algo);
		}
		if($result === false)
		{
			throw new Exception('Failed decrypting data', esc_html(self::ERROR_CONFIRM_FAILED_DECRYPT_DATA));
		}

		return Netopia_Payment_Request_Abstract::factory($data);
	}

	/**
	 *
	 * Factory the object from a DOMDocument xml object
	 * @param DOMDocument $xmlDoc
	 * @return Netopia_Payment_Request_Abstract
	 * @throws Exception On missing xml attributes
	 * @throws Exception On unknown type
	 */
	static protected function _factoryFromXml(DOMDocument $xmlDoc)
	{
		$elems = $xmlDoc->getElementsByTagName('order');
		if($elems->length != 1)
		{
			throw new Exception('factoryFromXml order element not found', esc_html(Netopia_Payment_Request_Abstract::ERROR_FACTORY_BY_XML_ORDER_ELEM_NOT_FOUND));
		}
		$orderElem = $elems->item(0);
		$attr = $orderElem->attributes->getNamedItem('type');
		if($attr == null || strlen($attr->nodeValue) == 0)
		{
			throw new Exception('factoryFromXml invalid payment request type=' . esc_html($attr->nodeValue), esc_html(Netopia_Payment_Request_Abstract::ERROR_FACTORY_BY_XML_ORDER_TYPE_ATTR_NOT_FOUND));
		}
		switch ($attr->nodeValue)
		{
		case Netopia_Payment_Request_Abstract::PAYMENT_TYPE_COD:
			$objPmReq = new Netopia_Payment_Request_Cod();
			break;
		case Netopia_Payment_Request_Abstract::PAYMENT_TYPE_CARD:
			$objPmReq = new Netopia_Payment_Request_Card();
			break;
		case Netopia_Payment_Request_Abstract::PAYMENT_TYPE_SMS:
			$objPmReq = new Netopia_Payment_Request_Sms();
			break;
		case Netopia_Payment_Request_Abstract::PAYMENT_TYPE_ADMIN:
			$objPmReq = new Netopia_Payment_Request_Admin();
			break;
		case Netopia_Payment_Request_Abstract::PAYMENT_TYPE_INFO:
			$objPmReq = new Netopia_Payment_Request_Info();
			break;
		case Netopia_Payment_Request_Abstract::PAYMENT_TYPE_CASH:
			$objPmReq = new Netopia_Payment_Request_Cash();
			break;
		case Netopia_Payment_Request_Abstract::PAYMENT_TYPE_TRANSFER:
			$objPmReq = new Netopia_Payment_Request_Transfer();
			break;
		// case Netopia_Payment_Request_Abstract::PAYMENT_TYPE_ING_HOME_PAY:
		// 	$objPmReq = new Netopia_Payment_Request_IngHomePay();
		// 	break;
		case Netopia_Payment_Request_Abstract::PAYMENT_TYPE_HOME_PAY:
			$objPmReq = new Netopia_Payment_Request_Homepay();
			break;
		case Netopia_Payment_Request_Abstract::PAYMENT_TYPE_BITCOIN:
			$objPmReq = new Netopia_Payment_Request_Bitcoin();
			break;
		default:
			throw new Exception('factoryFromXml invalid payment request type=' . esc_html($attr->nodeValue), esc_html(Netopia_Payment_Request_Abstract::ERROR_FACTORY_BY_XML_INVALID_TYPE));
			break;
		}
		$objPmReq->_loadFromXml($orderElem);

		return $objPmReq;
	}

	/**
	 *
	 * Factory the object from a query string
	 * @param string $data
	 * @return Netopia_Payment_Request_Sms
	 * @throws Exception On missing parameters
	 */
	static protected function _factoryFromQueryString($data)
	{
		$objPmReq = new Netopia_Payment_Request_Sms();
		$objPmReq->_loadFromQueryString($data);

		return $objPmReq;
	}

	/**
	 *
	 * Setter for the request info object
	 * @param string $reqVersion
	 * @param string $reqData
	 */
	protected function _setRequestInfo($reqVersion, $reqData)
	{
		$this->_objRequestInfo = new stdClass();
		$this->_objRequestInfo->reqVersion 	= $reqVersion;
		$this->_objRequestInfo->reqData 	= $reqData;
	}

	/**
	 *
	 * Returns the request info object
	 * @return stdClass
	 */
	public function getRequestInfo()
	{
		return $this->_objRequestInfo;
	}

	/**
	 *
	 * Populate the class from the request xml
	 * @param DOMNode $elem
	 * @throws Exception On missing xml attributes
	 */
	protected function _parseFromXml(DOMNode $elem)
	{
		$xmlAttr = $elem->attributes->getNamedItem('id');
		if($xmlAttr == null || strlen((string)$xmlAttr->nodeValue) == 0)
		{
			throw new Exception('Netopia_Payment_Request_Sms::_parseFromXml failed: empty order id', esc_html(self::ERROR_LOAD_FROM_XML_ORDER_ID_ATTR_MISSING));
		}
		$this->orderId = $xmlAttr->nodeValue;
		$elems = $elem->getElementsByTagName('signature');
		if($elems->length != 1)
		{
			throw new Exception('Netopia_Payment_Request_Sms::loadFromXml failed: signature is missing', esc_html(self::ERROR_LOAD_FROM_XML_SIGNATURE_ELEM_MISSING));
		}
		$xmlAttr = $elem->attributes->getNamedItem('secretcode');
		if ($xmlAttr == null || strlen((string) $xmlAttr->nodeValue) == 0)
		{
			$this->secretCode = '';
		}
		else
		{
			$this->secretCode = $xmlAttr->nodeValue;
		}
		$xmlElem = $elems->item(0);
		$this->signature = $xmlElem->nodeValue;
		$elems = $elem->getElementsByTagName('url');
		if($elems->length == 1)
		{
			$xmlElem = $elems->item(0);
			//check for overwritten return url
			$elems = $xmlElem->getElementsByTagName('return');
			if($elems->length == 1)
			{
				$this->returnUrl = $elems->item(0)->nodeValue;
			}
			//check for overwritten confirm url
			$elems = $xmlElem->getElementsByTagName('confirm');
			if($elems->length == 1)
			{
				$this->confirmUrl = $elems->item(0)->nodeValue;
			}
			//check for overwritten cancel url
			$elems = $xmlElem->getElementsByTagName('cancel');
			if ($elems->length == 1)
			{
				$this->cancelUrl = $elems->item(0)->nodeValue;
			}
		}
		
		$elems = $elem->getElementsByTagName('ipn_cipher');
		if($elems->length == 1)
		{
			$xmlElem = $elems->item(0);
			$this->ipnCipher = $xmlElem->nodeValue;
		}

		$this->params = array();
		$paramElems = $elem->getElementsByTagName('params');
		if($paramElems->length == 1)
		{
			$paramElems = $paramElems->item(0)->getElementsByTagName('param');
			for ($i = 0; $i < $paramElems->length; $i++)
			{
				$xmlParam = $paramElems->item($i);
				$elems = $xmlParam->getElementsByTagName('name');
				if($elems->length != 1)
				{
					continue;
				}
				$paramName = $elems->item(0)->nodeValue;
				$what = '/(\[[0-9]*\])/i';
				$with = '';
				$paramName = preg_replace($what, $with, $paramName);
				$elems = $xmlParam->getElementsByTagName('value');
				if($elems->length != 1)
				{
					continue;
				}
				if (isset($this->params[$paramName]))
				{
					if (!is_array($this->params[$paramName]))
					{
						$this->params[$paramName] = array($this->params[$paramName]);
					}
					$this->params[$paramName][] = urldecode($elems->item(0)->nodeValue);
				}
				else
				{
					$this->params[$paramName] = urldecode($elems->item(0)->nodeValue);
				}
			}
		}
		$elems = $elem->getElementsByTagName('mobilpay');
		if($elems->length == 1)
		{
			$this->objPmNotify = new Netopia_Payment_Request_Notify();
			$this->objPmNotify->loadFromXml($elems->item(0));
		}
	}

	/**
	 *
	 * Computes the encryption data and encryption key
	 * @param string $x509FilePath
	 * @throws Exception
	 */
	public function encrypt($x509FilePath)
	{
		$publicKey = openssl_pkey_get_public($x509FilePath);
		if ($publicKey === false)
		{
			$publicKey = openssl_pkey_get_public("file://{$x509FilePath}");
		}
		if ($publicKey === false)
		{
			$this->outEncData 	= null;
			$this->outEnvKey 	= null;
			$this->outCipher 	= null;
			$this->outIv 		= null;
			$errorMessage = "Error while loading X509 public key certificate! Reason:";
			while(($errorString = openssl_error_string()))
			{
				$errorMessage .= $errorString . "\n";
			}
			throw new Exception(esc_html($errorMessage), esc_html(self::ERROR_LOAD_X509_CERTIFICATE));
		}
		$publicKeys = array(
			$publicKey
		);
		$encData 		= null;
		$envKeys 		= null;
		$cipher_algo 	= 'rc4';
		$iv 			= null;

		if(PHP_VERSION_ID >= 70000)
		{
			if(OPENSSL_VERSION_NUMBER > 0x10000000)
			{
				$cipher_algo = 'aes-256-cbc';
			}	
		}
		else
		{
			if(OPENSSL_VERSION_NUMBER >= 0x30000000)
			{
				$this->outEncData 	= null;
				$this->outEnvKey 	= null;
				$this->outCipher 	= null;
				$this->outIv 		= null;
				$errorMessage 		= 'incompatible configuration PHP ' . PHP_VERSION . ' & ' . OPENSSL_VERSION_TEXT;
				throw new Exception(esc_html($errorMessage), esc_html(self::ERROR_REQUIRED_CIPHER_NOT_AVAILABLE));
			}
		}
		$opensslCipherMethods = openssl_get_cipher_methods();
		if(in_array($cipher_algo, $opensslCipherMethods))
		{
		}
		else if(in_array(strtoupper($cipher_algo), $opensslCipherMethods))
		{
			$cipher_algo = strtoupper($cipher_algo);
		}
		else
		{
			$this->outEncData 	= null;
			$this->outEnvKey 	= null;
			$this->outCipher 	= null;
			$this->outIv 		= null;
			$errorMessage 		= '`' . $cipher_algo . '` required cipher is not available';
			throw new Exception(esc_html($errorMessage), esc_html(self::ERROR_REQUIRED_CIPHER_NOT_AVAILABLE));
		}
		if($this->ipnCipher === null)
		{
			$this->ipnCipher = $cipher_algo;
		}

		$this->_prepare();
		$srcData = $this->_xmlDoc->saveXML();
		if(PHP_VERSION_ID >= 70000)
		{
			$result = openssl_seal($srcData, $encData, $envKeys, $publicKeys, $cipher_algo, $iv);
		}
		else
		{
			$result = openssl_seal($srcData, $encData, $envKeys, $publicKeys, $cipher_algo);
		}
		if($result === false)
		{
			$this->outEncData 	= null;
			$this->outEnvKey 	= null;
			$this->outCipher 	= null;
			$this->outIv 		= null;
			$errorMessage = "Error while encrypting data! Reason:";
			while(($errorString = openssl_error_string()))
			{
				$errorMessage .= $errorString . "\n";
			}
			throw new Exception(esc_html($errorMessage), esc_html(self::ERROR_ENCRYPT_DATA));
		}
		$this->outEncData 	= base64_encode($encData);
		$this->outEnvKey 	= base64_encode($envKeys[0]);
		$this->outCipher 	= $cipher_algo;
		$this->outIv 		= (strlen($iv) > 0) ? base64_encode($iv) : '';
	}

	/**
	 *
	 * Returns the encryption envKey
	 * @return string
	 */
	public function getEnvKey()
	{
		return $this->outEnvKey;
	}

	/**
	 *
	 * Returns the encrypted data
	 * @return string
	 */
	public function getEncData()
	{
		return $this->outEncData;
	}

	/**
	 * Return cipher algorithm used to encrypt data
	 * @return string
	 */
	public function getCipher()
	{
		return $this->outCipher;
	}

	/**
	 * Return initialization vector seed used to encrypt data
	 */
	public function getIv()
	{
		return $this->outIv;
	}

	/**
	 *
	 * Set the request identifier
	 * @return
	 */
	public function setRequestIdentifierPrefix($prefix = null)
	{
		if (strpos($this->_requestIdentifier, $prefix) !== 0)
		{
			$this->_requestIdentifier = $prefix . $this->_requestIdentifier;
		}
	}

	/**
	 *
	 * Returns the request identifier
	 * @return string
	 */
	public function getRequestIdentifier()
	{
		return $this->_requestIdentifier;
	}

	/**
	 *
	 * Magic method for checking if request parameter is set
	 * @param string $name
	 * @return boolean
	 */
	public function __isset($name)
	{
		return (isset($this->_objRequestParams) && isset($this->_objRequestParams->$name));
	}

	/**
	 *
	 * Magic method for setting a request parameter
	 * @param string $name
	 * @param mixed $value
	 */
	public function __set($name, $value)
	{
		$this->_objRequestParams->$name = $value;
	}

	/**
	 *
	 * Magic method for getting the value of a request parameter
	 * @param string $name
	 * @return mixed
	 */
	public function __get($name)
	{
		if (!isset($this->_objRequestParams) || !isset($this->_objRequestParams->$name))
		{
			return null;
		}

		return $this->_objRequestParams->$name;
	}

	/**
	 *
	 * Deserialization logic
	 */
	public function __wakeup()
	{
		$this->_objRequestParams = new stdClass();
	}

	/**
	 *
	 * Serialization logic
	 * @return array
	 */
	public function __sleep()
	{
		return array(
			'_requestIdentifier',
			'_objRequestInfo',
			'invoice',
			'orderId',
			'signature',
			'returnUrl',
			'confirmUrl',
			'cancelUrl',
			'params',
			'reqInstallments',
			'selectedInstallments',
			'secretCode',
			'paymentInstrument', 
			'ipnCipher'
		);
	}
	
	/**
	 * Returns the computed XML document as string
	 * @return string
	 */
	public function toXml()
	{
		$this->_prepare();
		
		return $this->_xmlDoc->saveXML();
	}

	/**
	 *
	 * Returns the computed xml document
	 * @return DOMDocument
	 */
	public function getXml()
	{
		$this->_prepare();
		return $this->_xmlDoc;
	}
}