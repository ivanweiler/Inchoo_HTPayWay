<?php
/**
 * PayWay Standard implementation
 */
class Inchoo_HTPayWay_Model_Standard extends Mage_Payment_Model_Method_Abstract
{
    /**
     * Api endpoints
     */
    const API_URL = 'https://pgw.ht.hr/services/payment/api/%s';
    const API_TEST_URL = 'https://pgwtest.ht.hr/services/payment/api/%s';

    /**
     * PayWay method name
     */
    const API_METHOD = 'authorize-form';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = 'inchoo_htpayway';

    /**
     * @var string
     */
    protected $_formBlockType = 'inchoo_htpayway/payment_form';

    /**
     * Payment Method features
     * @var bool
     */
    protected $_canUseInternal              = false;
    protected $_canUseForMultishipping      = false;
    protected $_isInitializeNeeded          = true;
    protected $_canManageRecurringProfiles  = false;

    /**
     * @var array
     */
    protected $_supportedCurrencyCodes = array('HRK');

    /**
     * @var array
     */
    protected $_resultCode = array(
        '0'     => 'Action successful',
        '1'     => 'Action failed',
        '2'     => 'Processing error',
        '3'     => 'Action canceled',
        '4'     => 'Action failed (3D Secure MPI)',
        '5'     => 'Authorization not found',
        '1000'  => 'Invalid signature',
        '1001'  => 'Invalid shop id',
        '1002'  => 'Invalid transaction id',
        '1003'  => 'Invalid amount',
        '1004'  => 'Invalid authorization type',
        '1005'  => 'Invalid announcement duration',
        '1006'  => 'Invalid installments number',
        '1007'  => 'Invalid language',
        '1008'  => 'Invalid authorization token',
        '1100'  => 'Invalid card number',
        '1101'  => 'Invalid card expiration date',
        '1102'  => 'Invalid card verification number',
        '1200'  => 'Invalid order id',
        '1201'  => 'Invalid order info',
        '1202'  => 'Invalid order items',
        '1300'  => 'Invalid return method',
        '1301'  => 'Invalid success url',
        '1302'  => 'Invalid failure url',
        '1304'  => 'Invalid merchant data',
        '1400'  => 'Invalid first name',
        '1401'  => 'Invalid last name',
        '1402'  => 'Invalid street',
        '1403'  => 'Invalid city',
        '1404'  => 'Invalid postcode',
        '1405'  => 'Invalid country',
        '1406'  => 'Invalid telephone',
        '1407'  => 'Invalid e-mail address'
    );

    /**
     * @var array
     */
    protected $_signatureKeyOrder = array(
        'method',
        'pgw_shop_id',
        'pgw_order_id',
        'pgw_amount',
        'pgw_authorization_type',
        'pgw_authorization_token',
        'pgw_language',
        'pgw_return_method',
        'pgw_success_url',
        'pgw_failure_url',
        'pgw_first_name',
        'pgw_last_name',
        'pgw_street',
        'pgw_city',
        'pgw_post_code',
        'pgw_country',
        'pgw_telephone',
        'pgw_email',
        'pgw_merchant_data',
        'pgw_order_info',
        'pgw_order_items',
        'pgw_disable_installments'
    );

    /**
     * @var array
     */
    protected $_successParams = array(
        'pgw_trace_ref'         => true,
        'pgw_transaction_id'    => true,
        'pgw_order_id'          => true,
        'pgw_amount'            => true,
        'pgw_installments'      => true,
        'pgw_card_type_id'      => true,
        'pgw_merchant_data'     => false
    );

    /**
     * @var array
     */
    protected $_failureParams = array(
        'pgw_result_code'   => true,
        'pgw_trace_ref'     => true,
        'pgw_order_id'      => true,
        'pgw_merchant_data' => false
    );

    /**
     * {@inheritdoc}
     */
    public function initialize($paymentAction, $stateObject)
    {
        $stateObject->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
        $stateObject->setStatus('pending_payment');
        $stateObject->setIsNotified(false);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function canUseForCurrency($currencyCode)
    {
        if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
            return false;
        }
        return true;
    }

    /**
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('inchoo_htpayway/standard/redirect', array('_secure' => true));
    }

    /**
     * @return string
     */
    public function getRedirectUrl()
    {
        return sprintf(
            $this->getConfigData('test_mode') ? self::API_TEST_URL : self::API_URL,
            self::API_METHOD
        );
    }

    /**
     * @return string
     */
    public function getSuccessUrl()
    {
        return Mage::getUrl('inchoo_htpayway/standard/success', array('_secure' => true));
    }

    /**
     * @return string
     */
    public function getFailureUrl()
    {
        return Mage::getUrl('inchoo_htpayway/standard/failure', array('_secure' => true));
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return array
     */
    public function prepareRequest(Mage_Sales_Model_Order $order)
    {
        $pgwData = array();
        $billing = $order->getBillingAddress();

        $pgwData['method'] = self::API_METHOD;

        $pgwData['pgw_shop_id']     = $this->getConfigData('shop_id');
        $pgwData['pgw_order_id']    = $order->getIncrementId();
        $pgwData['pgw_amount']      = number_format($order->getBaseGrandTotal(), 2, '', '');

        // 0 = authorize; 1 = capture
        $pgwData['pgw_authorization_type'] = '1';
        $pgwData['pgw_language']           = $this->getConfigData('language');

        $pgwData['pgw_success_url']    = $this->getSuccessUrl();
        $pgwData['pgw_failure_url']    = $this->getFailureUrl();

        $pgwData['pgw_first_name']     = $this->_prepareString($billing->getFirstname(), 20);
        $pgwData['pgw_last_name']      = $this->_prepareString($billing->getLastname(), 20);
        $pgwData['pgw_street']         = $this->_prepareString($billing->getStreet(1), 40);
        $pgwData['pgw_city']           = $this->_prepareString($billing->getCity(), 20);
        $pgwData['pgw_post_code']      = $this->_prepareString($billing->getPostcode(), 9);
        $pgwData['pgw_country']        = $this->_prepareString($billing->getCountry(), 50);
        $pgwData['pgw_telephone']      = $this->_prepareString($billing->getTelephone(), 50);
        $pgwData['pgw_email']          = $order->getCustomerEmail();

        $pgwData['pgw_signature'] = $this->_getSignature($pgwData, $this->_signatureKeyOrder);

        return $pgwData;
    }

    /**
     * Validates success/failure response for required params and valid signature
     *
     * @param array $data
     * @param string $action
     * @return bool
     */
    public function validateResponse($data, $action)
    {
        if(!isset($data['pgw_signature'])) {
            return false;
        }

        $signature = $data['pgw_signature'];
        unset($data['pgw_signature']);

        switch($action) {
            case 'success':
                $params = $this->_successParams;
                break;
            case 'failure':
                $params = $this->_failureParams;
                break;
            default:
                return false;
        }

        foreach($params as $param => $required) {
            if($required && !isset($data[$param])) {
                return false;
            }
        }

        return ($signature == $this->_getSignature($data, array_keys($params)));
    }

    /**
     * @param int|string $code
     * @return string
     */
    public function getResultMessage($code)
    {
        return isset($this->_resultCode[$code]) ? $this->_resultCode[$code] : 'Unknown result';
    }

    /**
     * Returns data signature, key order respected if needed
     *
     * @param array $data
     * @param array $keyOrder
     * @return string
     */
    protected function _getSignature($data, $keyOrder = array())
    {
        $toHash = '';
        $secret = $this->getConfigData('shop_secret_key');

        if($keyOrder) {
            foreach($keyOrder as $key) {
                if(isset($data[$key])) {
                    $toHash .= $data[$key] . $secret;
                }
            }
        } else {
            foreach($data as $value) {
                $toHash .= $value . $secret;
            }
        }

        return hash('sha512', $toHash);
    }

    /**
     * Transliterate string and respect maximum length
     *
     * @param string $string
     * @param int|bool $length
     * @return string
     */
    protected function _prepareString($string, $length = false)
    {
        if(function_exists('transliterator_transliterate')) {
            /**
             * older ICU versions don't have Latin-ASCII, so we're trying from best to worse
             */
            $trans = array(
                'Any-Latin; Latin-ASCII;',
                'Any-Latin; NFD; [:Nonspacing Mark:] Remove; NFC;',
                'Any-Latin;'
            );

            foreach($trans as $t) {
                $translit = @transliterator_transliterate($t, $string);
                if($translit) {
                    $string = $translit;
                    break;
                }
            }
        }

        /**
         * Form on PayWay side currently has problem with html chars, it is reported
         * and will be fixed on their side, but this is possible workaround
         */
        //$string = str_replace(array('\'', '"', '&', '/', '<', '>'), ' ', $string);
        //$string = preg_replace('#\s+#', ' ', $string);

        $string = trim($string);

        if($length > 0) {
            $string = substr($string, 0, $length);
        }

        return $string;
    }

}