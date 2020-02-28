<?php
namespace MPHB\Payments\Gateways;

use \MPHB\Admin\Groups;
use \MPHB\Admin\Fields;

class SimplepayGateway extends Gateway {

	/**
	 *
	 * @var Simplepay\IpnListener
	 */
	protected $ipnListener;

	protected $simplePaymentFrom;

	/**
	 *
	 * @var string
	 */

	public function __construct(){
		add_filter( 'mphb_gateway_has_instructions', array( $this, 'hideInstructions' ), 10, 2 );
		add_filter('mphb_sc_checkout_payment_mode_success_message', [$this, 'simplePaymentForm']);
		add_action('wp_loaded', [$this, 'responseListener']);
		add_action('mphb_sc_booking_confirmation_before_confirmation_messages', [$this, 'simpleResponse']);

		$this->setupSupportedCurrencies();

		parent::__construct();

//		$this->setupResponseListener();

		/*
		TODO
		if (!empty($_REQUEST['wc-api']) && $_REQUEST['wc-api'] == 'wc_gateway_simplepayhu') {
            $this->ipn_handler();
		}
		*/
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
		}

		// TODO handle IPN

		/* 		$ipnListnerArgs		 = array(
			'gatewayId'				 => $this->getId(),
			'sandbox'				 => $this->isSandbox,
			'verificationDisabled'	 => (bool) $this->getOption( 'disable_ipn_verification' ),
		);
		$this->ipnListener	 = new Simplepay\IpnListener( $ipnListnerArgs );
 */	}

	protected function setupProperties(){
		parent::setupProperties();
		$this->adminTitle	 = __( 'Simplepay', 'motopress-hotel-booking' );
	}

	protected function initDefaultOptions(){
		$defaults = array(
			'title'						 => __( 'Simplepay', 'motopress-hotel-booking' ),
			'description'				 => __( 'Pay via Simplepay', 'motopress-hotel-booking' ),
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
		$config[$currency . '_MERCHANT'] = $this->getOption( strtolower($currency) . '_merchant_id' );
		$config[$currency . '_SECRET_KEY'] = $this->getOption( strtolower($currency) . '_secret_key' );
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
		//http://localhost/~rrd/sk/booking-confirmation/?step=booking
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
				'label'		 => __( 'Simplepay HUF Merchant Id', 'motopress-hotel-booking' ),
				'default'	 => $this->getDefaultOption( 'huf_merchant_id' )
			) ),
			Fields\FieldFactory::create( "mphb_payment_gateway_{$this->id}_huf_secret_key", array(
				'type'		 => 'text',
				'label'		 => __( 'Simplepay HUF Secret Key', 'motopress-hotel-booking' ),
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

		// TODO replace wc-simplepayhu to the new string

		$message = '';
		$backStatus = $backref->backStatusArray;
		//some error before the user even redirected to SimplePay
		if (!empty($_REQUEST['err'])) {
//			$backStatus = $backref->backStatusArray;
			$backref->logFunc("BackRef", $_REQUEST, $backref->order_ref);
			$message .= '<div class="woocommerce-error">' . __('An error occured before the payment', 'wc-simplepayhu')
				. ' ' . '<strong>' . $_REQUEST['err'] . '</strong>'
				. '</div>';
			// TODO $order->update_status('wc-simplepay-error');

			// TODO add reorder link
		}

		//card authorization failed
		if (empty($_REQUEST['err']) && !$backref->checkResponse()) {
//			$backStatus = $backref->backStatusArray;
			$message .= '<div class="woocommerce-error">'
				. __('Unsuccessful transaction', 'wc-simplepayhu')
				.  '<br><strong>' . __('An error occured during the payment', 'wc-simplepayhu') . '</strong>'
				. '</div>';
			// TODO $order->update_status('wc-simplepay-error');
		}

		//success on card authorization
		if (empty($_REQUEST['err']) && $backref->checkResponse()) {
//			$backStatus = $backref->backStatusArray;
			if ($backStatus['PAYMETHOD'] == 'Visa/MasterCard/Eurocard') {
				$message .= '<div class="woocommerce-message">'
					. __('Card authorized at SimplePay', 'wc-simplepayhu')
					. ' ' . __('Current status is', 'wc-simplepayhu') . ' ';
				if ($backStatus['ORDER_STATUS'] == 'IN_PROGRESS') {
					$message .= '<strong>' . __('In progress', 'wc-simplepayhu') . '</strong>';
				}
				if ($backStatus['ORDER_STATUS' ] == 'PAYMENT_AUTHORIZED') {
					$message .= '<strong>' . __('Payment authorized', 'wc-simplepayhu') . '</strong>';
				}
				if ($backStatus['ORDER_STATUS'] == 'COMPLETE') {
					$message .= '<strong>' . __('Payment complete', 'wc-simplepayhu') . '</strong>';
				}
				$message .= '</div>';
				$message .= __('Successful transaction', 'wc-simplepayhu');
			}

			// TODO the payment not loading completely so we have no amount and currency but we need them

			$payment = new \MPHB\Entities\Payment([
				'id' => get_post_meta($_REQUEST['order_ref'], '_mphb_wait_payment')[0],
				'bookingId' => $_REQUEST['order_ref'],
				'amount' => json_decode(get_post_meta($_REQUEST['order_ref'])['_mphb_booking_price_breakdown'][0])->deposit,
				'currency' => $orderCurrency,
				'gatewayId' => 'simplepay',
				'gatewayMode' => $this->isSandbox ? 'sandbox' : 'live',	// TODO test this
				'transactionId' => $backStatus['PAYREFNO']
			]);
			MPHB()->paymentManager()->completePayment( $payment );
		}

		$backref->errorLogger();

		/*$message .= '<br>';
		$message .= __('SimplePay transaction ID is', 'wc-simplepayhu')
			. ' <strong>' . $backStatus['PAYREFNO'] . '</strong><br>';
		$message .= __('Your order ID is', 'wc-simplepayhu')
			. ' <strong>' . $backStatus['REFNOEXT'] . '</strong><br>';
		$message .= __('Transaction date is', 'wc-simplepayhu')
			. ' <strong>' . $backStatus['BACKREF_DATE'] . '</strong>';
		$message .= '</p>';*/

		// echo $message;	// TODO

		// TODO add $backStatus values to the url so we can use them in simpleResponse
		$url = MPHB()->settings()->pages()->getReservationReceivedPageUrl($payment) . '&mphb_confirmation_status=confirmed';
		wp_redirect($url);
		exit;
	}

	public function simpleResponse()
	{
		// TODO
//http://localhost/~rrd/sk/booking-confirmation/payment-success/?payment_id=1426&payment_key=payment_1426_5e5983243c9db5.87687388&mphb_payment_status=mphb-p-completed&mphb_confirmation_status=confirmed
		echo __('SimplePay transaction ID is', 'wc-simplepayhu')
			. ' <strong>' . $backStatus['PAYREFNO'] . '</strong><br>';
		echo __('Your order ID is', 'wc-simplepayhu')
			. ' <strong>' . $backStatus['REFNOEXT'] . '</strong><br>';
		echo __('Transaction date is', 'wc-simplepayhu')
			. ' <strong>' . $backStatus['BACKREF_DATE'] . '</strong>';
	}

}
