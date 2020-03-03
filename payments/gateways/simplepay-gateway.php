<?php
namespace MPHB\Payments\Gateways;

use \MPHB\Admin\Groups;
use \MPHB\Admin\Fields;

class SimplepayGateway extends Gateway {

	protected $simplePaymentFrom;

	/**
	 *
	 * @var string
	 */

	public function __construct() {
		if (get_user_locale() == 'hu_HU') {
			load_textdomain('moto-gateway-simplepay', dirname(__FILE__) . '/simplepay/simplepay-hu.mo');
		}

		add_filter( 'mphb_gateway_has_instructions', array( $this, 'hideInstructions' ), 10, 2 );
		add_filter('mphb_sc_checkout_payment_mode_success_message', [$this, 'simplePaymentForm']);
		add_action('wp_loaded', [$this, 'responseListener']);
		add_action('mphb_sc_booking_confirmation_before_confirmation_messages', [$this, 'simpleResponse']);

		$this->setupSupportedCurrencies();

		add_shortcode('simplepay_error', [$this, 'simplepay_error_shortcode']);

		if (!empty($_REQUEST['wc-api']) && $_REQUEST['wc-api'] == 'moto_gateway_simplepay') {
			$this->ipn_handler();
		}

		parent::__construct();
	}

    /**
     * @param bool $show
     * @param string $gatewayId
     * @return bool
     *
     * @since 3.6.1
     */
    public function hideInstructions( $show, $gatewayId ){
        if ( $gatewayId == $this->id ) {
            $show = false;
        }
        return $show;
    }

	public function responseListener(){

		if(isset($_REQUEST['back_ref'])) {
			$this->backref_handler();
		}

		if(isset($_REQUEST['timeout'])) {
			$this->timeout_handler();
		}
	}

	protected function setupProperties(){
		parent::setupProperties();
		$this->adminTitle	 = __( 'Simplepay', 'moto-gateway-simplepay' );
	}

	protected function initDefaultOptions(){
		$defaults = array(
			'title'						 => __( 'Simplepay', 'moto-gateway-simplepay' ),
			'description'				 => __( 'Pay via Simplepay', 'moto-gateway-simplepay' ),
			'enabled'					 => false,
			'is_sandbox'				 => false,
			'disable_ipn_verification'	 => false
		);
		return array_merge( parent::initDefaultOptions(), $defaults );
	}

	protected function initId(){
		return 'simplepay';
	}

	/**
	 *
	 * @return bool
	 */
	public function isActive(){
		return parent::isActive() && $this->isSupportCurrency();
	}

    private function setSimplePayConfig($currency)
	{
		$config = [];
		require_once plugin_dir_path(__FILE__) . '../../libraries/simplepay-sdk/config.php';
		require_once plugin_dir_path(__FILE__) . '../../libraries/simplepay-sdk/SimplePayment.class.php';

		$currency = 'HUF';
		$config[$currency . '_MERCHANT'] = get_option( 'mphb_payment_gateway_simplepay_' . strtolower($currency) . '_merchant_id' );
		$config[$currency . '_SECRET_KEY'] = get_option( 'mphb_payment_gateway_simplepay_' . strtolower($currency) . '_secret_key' );
		$config['LOG_PATH'] = plugin_dir_path(__FILE__) . '/simplepay/logs';

		$config['SANDBOX'] = $this->isSandbox;

		return $config;
	}

	public function simplePaymentForm () {
		return $this->simplePaymentForm;
	}


	/**
	 *
	 * @param \MPHB\Entities\Booking $booking
	 * @param \MPHB\Entities\Payment $payment
	 */
	public function processPayment( \MPHB\Entities\Booking $booking, \MPHB\Entities\Payment $payment ){

		$currency = 'HUF';
        $config = $this->setSimplePayConfig($currency);

		// Redirect to Simplepay checkout
		$simple = new \SimpleLiveUpdate($config, $currency);
		$simple->logFunc(
			'SimpleObjectCreation',
			['bookingId' => $payment->getBookingId()],
			$payment->getBookingId()
		);
		$simple->addProduct([
			'name' => 'booking',
			'code' => $payment->getBookingId(),
			'info' => $booking->getCheckInDate()->format( 'Y-m-d' ) . ' - ' . $booking->getCheckOutDate()->format( 'Y-m-d' ),
			'price' => $payment->getAmount(),
			'qty' => 1,
			'vat' => 0, // no VAT $order_data['prices_include_tax']
		]);

		//var_dump($booking);
		//var_dump($payment);

		$simple->setField('BILL_FNAME', $booking->getCustomer()->getFirstName());
		$simple->setField('BILL_LNAME', $booking->getCustomer()->getLastName());
		$simple->setField('BILL_EMAIL', $booking->getCustomer()->getEmail());
		$simple->setField('BILL_PHONE', $booking->getCustomer()->getPhone());
		$simple->setField('BILL_ADDRESS',$booking->getCustomer()->getAddress1());
		$simple->setField('BILL_CITY', $booking->getCustomer()->getCity());
		$simple->setField('BILL_STATE', $booking->getCustomer()->getState());
		$simple->setField('BILL_ZIPCODE', $booking->getCustomer()->getZip());
		$simple->setField('BILL_COUNTRYCODE', $booking->getCustomer()->getCountry());

		$simple->setField('DELIVERY_COUNTRYCODE', $booking->getCustomer()->getCountry());
		$simple->setField('DELIVERY_FNAME', $booking->getCustomer()->getFirstName());
		$simple->setField('DELIVERY_LNAME', $booking->getCustomer()->getLastName());
		$simple->setField('DELIVERY_ADDRESS',$booking->getCustomer()->getAddress1());
		$simple->setField('DELIVERY_PHONE', $booking->getCustomer()->getPhone());
		$simple->setField('DELIVERY_ZIPCODE', $booking->getCustomer()->getZip());
		$simple->setField('DELIVERY_CITY', $booking->getCustomer()->getCity());
		$simple->setField('DELIVERY_STATE', $booking->getCustomer()->getState());

		$simple->setField('ORDER_REF', $payment->getBookingId());

		$simple->setField('ORDER_SHIPPING', 0);
		$simple->setField('DISCOUNT', 0);

		//$url = preg_replace('#^https?://#', '', plugin_dir_url(__FILE__));
		$url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		$simple->setField('BACK_REF', $url . '&back_ref=1');
		$simple->setField('TIMEOUT_URL', $url . '&timeout=1');
		$simple->setField('LANGUAGE', $booking->getLanguage());
		$simple->setField('CURRENCY', $currency);

		$this->simplePaymentForm = '<h2>' . __('Redirecting to SimplePay', 'moto-gateway-simplepay') . '</h2>';
		$this->simplePaymentForm .= '<p>';
		$this->simplePaymentForm .= __('Pay by credit card', 'moto-gateway-simplepay') . '<br>';
		$this->simplePaymentForm .= __('Booking amount', 'moto-gateway-simplepay') . ': ' . $payment->getAmount() . ' ' . $payment->getCurrency();
		$this->simplePaymentForm .= '</p>';
		$this->simplePaymentForm .= $simple->createHtmlForm(
			'redirectForm',
			'button',
			__('Go to SimplePay', 'moto-gateway-simplepay')
		);
	}


	/**
	 *
	 * @param \MPHB\Admin\Tabs\SettingsSubTab $subTab
	 */
	public function registerOptionsFields( &$subTab ){
		parent::registerOptionsFields( $subTab );
		$group = new Groups\SettingsGroup( "mphb_payments_{$this->id}_group2", '', $subTab->getOptionGroupName() );

		$groupFields = array(
			Fields\FieldFactory::create( "mphb_payment_gateway_{$this->id}_huf_merchant_id", array(
				'type'		 => 'text',
				'label'		 => __( 'Simplepay HUF Merchant Id', 'moto-gateway-simplepay' ),
				'default'	 => $this->getDefaultOption( 'huf_merchant_id' )
			) ),
			Fields\FieldFactory::create( "mphb_payment_gateway_{$this->id}_huf_secret_key", array(
				'type'		 => 'text',
				'label'		 => __( 'Simplepay HUF Secret Key', 'moto-gateway-simplepay' ),
				'default'	 => $this->getDefaultOption( 'huf_secret_key' )
			) ),
		);

		$group->addFields( $groupFields );

		$subTab->addGroup( $group );
	}

	private function setupSupportedCurrencies(){
		$supportedCurrencies		 = include('simplepay/supported-currencies.php');
		$supportedCurrencies		 = apply_filters( 'mphb_simplepay_supported_currencies', $supportedCurrencies );
		$this->supportedCurrencies	 = $supportedCurrencies;
	}

	public function isSupportCurrency(){
		return in_array( MPHB()->settings()->currency()->getCurrencyCode(), $this->supportedCurrencies );
	}

	/**
	 * Called from SimplePay site after payment / cancel
	 */
	protected function backref_handler()
	{
		$orderCurrency = $_REQUEST['order_currency'];
		$config = $this->setSimplePayConfig($orderCurrency);

		$backref = new \SimpleBackRef($config, $orderCurrency);

		$backref->order_ref = $_REQUEST['order_ref'];

		$payment = new \MPHB\Entities\Payment([
			'id' => get_post_meta($_REQUEST['order_ref'], '_mphb_wait_payment')[0],
			'bookingId' => $_REQUEST['order_ref'],
			'amount' => json_decode(get_post_meta($_REQUEST['order_ref'])['_mphb_booking_price_breakdown'][0])->deposit,
			'currency' => $orderCurrency,
			'gatewayId' => 'simplepay',
			'gatewayMode' => $this->isSandbox ? 'sandbox' : 'live',	// TODO test this
		]);

		$backStatus = $backref->backStatusArray;

		//some error before the user even redirected to SimplePay
		if (!empty($_REQUEST['err'])) {
			$backref->logFunc("BackRef", $_REQUEST, $backref->order_ref);
			MPHB()->paymentManager()->failPayment( $payment );
			$url = MPHB()->settings()->pages()->getPaymentFailedPageUrl( $payment ) . '&mphb_confirmation_status=cancelled';
			// TODO add reorder link
		}

		//card authorization failed
		if (empty($_REQUEST['err']) && !$backref->checkResponse()) {
			$backref->logFunc("BackRef", $_REQUEST, $backref->order_ref);
			MPHB()->paymentManager()->failPayment( $payment );
			$url = MPHB()->settings()->pages()->getPaymentFailedPageUrl( $payment ) . '&mphb_confirmation_status=cancelled&type=failed&payrefno=' . $backStatus['PAYREFNO'] . '&refnoext=' . $backStatus['REFNOEXT'] . '&backrefdate=' . $backStatus['BACKREF_DATE'];
		}

		//success on card authorization
		if (empty($_REQUEST['err']) && $backref->checkResponse()) {
			$payment->setTransactionId($backStatus['PAYREFNO']);
			MPHB()->paymentManager()->completePayment( $payment );
			$url = MPHB()->settings()->pages()->getReservationReceivedPageUrl($payment) . '&mphb_confirmation_status=confirmed&payrefno=' . $backStatus['PAYREFNO'] . '&refnoext=' . $backStatus['REFNOEXT'] . '&backrefdate=' . $backStatus['BACKREF_DATE'];
		}

		$backref->errorLogger();
		wp_redirect($url);
		exit;
	}

	protected function timeout_handler()
	{
		$orderCurrency = $_REQUEST['order_currency'];
		$config = $this->setSimplePayConfig($orderCurrency);
		$timeout = new \SimpleLiveUpdate($config, $orderCurrency);
		$timeout->order_ref = $_REQUEST['order_ref'];

		if ($_REQUEST['redirect'] == 1) {
			$log['TRANSACTION'] = 'ABORT';
			$tpye = 'abort';
		} else {
			$log['TRANSACTION'] = 'TIMEOUT';
			$tpye = 'timeout';
		}

		$payment = new \MPHB\Entities\Payment([
			'id' => get_post_meta($_REQUEST['order_ref'], '_mphb_wait_payment')[0],
			'bookingId' => $_REQUEST['order_ref'],
			'amount' => json_decode(get_post_meta($_REQUEST['order_ref'])['_mphb_booking_price_breakdown'][0])->deposit,
			'currency' => $orderCurrency,
			'gatewayId' => 'simplepay',
			'gatewayMode' => $this->isSandbox ? 'sandbox' : 'live',	// TODO test this
		]);

		$url = MPHB()->settings()->pages()->getPaymentFailedPageUrl( $payment ) . '&mphb_confirmation_status=cancelled&type=' . $tpye;
		$log['ORDER_ID'] = (isset($_REQUEST['order_ref'])) ? $_REQUEST['order_ref'] : 'N/A';
		$log['CURRENCY'] = (isset($_REQUEST['order_currency'])) ? $_REQUEST['order_currency'] : 'N/A';
		$log['REDIRECT'] = (isset($_REQUEST['redirect'])) ? $_REQUEST['redirect'] : '0';
		$timeout->logFunc("Timeout", $log, $log['ORDER_ID']);

		wp_redirect($url);
		exit;
	}

	public function simpleResponse($returnMode = true)
	{
		$output = __('SimplePay transaction ID is', 'moto-gateway-simplepay')
			. ' <strong>' . $_GET['payrefno'] . '</strong><br>';
		$output .= __('Your booking ID is', 'moto-gateway-simplepay')
			. ' <strong>' . $_GET['refnoext'] . '</strong><br>';
		$output .= __('Transaction date is', 'moto-gateway-simplepay')
			. ' <strong>' . $_GET['backrefdate'] . '</strong>';
		if (!$returnMode) echo $output;
		if ($returnMode) return $output;
	}

	public function simplepay_error_shortcode()
	{
		if (isset($_REQUEST['type'])) {
			$output = '<p>';
			if ($_REQUEST['type'] == 'abort') {
				$output .= __('You have cancelled the payment', 'moto-gateway-simplepay');
			}
			if ($_REQUEST['type'] == 'timeout') {
				$output .= __('You exceeded the maximum time available to start the transaction.', 'moto-gateway-simplepay');
			}
			if ($_REQUEST['type'] == 'failed') {
				$output .= __('Please check if the details provided during the transaction are correct.', 'moto-gateway-simplepay');
				$output .= __('If all of the details were provided correctly, please contact the bank that issued your card in order to investigate the cause of the rejection.', 'moto-gateway-simplepay');
			}
			$output .= '</p>';
		}
		$output .= '<p>';
		$output .= $this->simpleResponse(true);
		$output .= '</p>';
		return $output;
	}

	public function ipn_handler()
	{
		$orderCurrency = $_REQUEST['CURRENCY'];
		$config = $this->setSimplePayConfig($orderCurrency);

		$ipn = new \SimpleIpn($config, $orderCurrency);
		if ($ipn->validateReceived()) {
			$ipn->confirmReceived();
		}
		$ipn->errorLogger();

		// flush output and exit as otherwise we run into a die statement at class-wc-api.php
		// and epayment will not in response output
		wp_ob_end_flush_all();
		exit;
	}
}
