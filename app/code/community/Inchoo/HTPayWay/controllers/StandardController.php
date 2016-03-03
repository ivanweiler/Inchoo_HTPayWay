<?php

class Inchoo_HTPayWay_StandardController extends Mage_Core_Controller_Front_Action
{
    /**
     * Redirect to PayWay
     */
    public function redirectAction()
    {
        $order = $this->_getCheckoutSessionOrder();

        if(!$order) {
            $this->_forward('noRoute');
            return;
        }

        $payWayModel = $this->_getPayWayModel();
        $payWayData = $payWayModel->prepareOrderData($order);

        $this->getResponse()->setBody(
            $this->getLayout()
                ->createBlock('inchoo_htpayway/redirect')
                ->setTargetUrl($payWayModel->getRedirectUrl())
                ->setFormFields($payWayData)
                ->toHtml()
        );

        $payWayModel->debugData($payWayData);
    }

    /**
     * Success action
     */
    public function successAction()
    {
        $payWayParams = $this->getRequest()->getParams();
        $payWayModel = $this->_getPayWayModel();

        $payWayModel->debugData($payWayParams);

        if(!$payWayModel->validateResponse($payWayParams)) {
            $this->_forward('noRoute');
            return;
        }

        $payWayParams['pgw_order_id'] = substr($payWayParams['pgw_order_id'], 7); //dev

        $order = Mage::getModel('sales/order')->loadByIncrementId($payWayParams['pgw_order_id']);
        if(!$order->getId()) {
            $this->_getCheckoutSession()->addError($this->__('This order no longer exists.'));
            $this->_redirect('checkout/cart');
            return;
        }

        //register transaction && create invoice

        $payment = $order->getPayment();
        $payment
            ->setTransactionId($payWayParams['pgw_transaction_id'])
            ->setIsTransactionClosed(0)
            ->registerCaptureNotification($payWayParams['pgw_amount']/100);

        //avoiding duplicate history and order save
        $order->getStatusHistoryCollection()->getLastItem()->setIsCustomerNotified(true);
        $order->save();

        if($payment->getCreatedInvoice()) {
            $order->sendNewOrderEmail();
        }

        $this->_redirect('checkout/onepage/success', array('_secure'=>true));
    }

    /**
     * Cancel or Failure action
     */
    public function failureAction()
    {
        $payWayParams = $this->getRequest()->getParams();
        $this->_getPayWayModel()->debugData($payWayParams);

        if(!$this->_validateFailureParams($payWayParams)) {
            $this->_forward('noRoute');
            return;
        }

        $payWayParams['pgw_order_id'] = substr($payWayParams['pgw_order_id'], 7); //dev

        /** @var $order Mage_Sales_Model_Order */
        $order = Mage::getModel('sales/order')->loadByIncrementId($payWayParams['pgw_order_id']);
        if(!$order->getId()) {
            $this->_getCheckoutSession()->addError($this->__('This order no longer exists.'));
            $this->_redirect('checkout/cart');
            return;
        }

        $message = $this->__(
            'HT PayWay message: %s.',
            $this->__($this->_getPayWayModel()->getResultMessage($payWayParams['pgw_result_code']))
        );

        //cancel order on cancelation code
        if($payWayParams['pgw_result_code'] == '3') {
            $order->cancel();
        } else {
            $history = $order->addStatusHistoryComment($message);
            $history->setIsCustomerNotified(false);
        }

        $order->save();

        //clear order from session here?

        if(Mage::getSingleton('checkout/cart')->isEmpty()) {
            $this->_restoreQuote($order);
        }

        $this->_getCheckoutSession()->addError($message);
        $this->_redirect('checkout/cart');
    }

    /**
     * @return Mage_Checkout_Model_Session
     */
    protected function _getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * @return bool|Mage_Sales_Model_Order
     */
    protected function _getCheckoutSessionOrder()
    {
        $session = Mage::getSingleton('checkout/session');

        if(!$session->getLastOrderId()) {
            return false;
        }

        $order = Mage::getModel('sales/order')->load($session->getLastOrderId());

        if(!$order->getId()) {
            return false;
        }

        return $order;
    }

    /**
     * @return Inchoo_HTPayWay_Model_Standard
     */
    protected function _getPayWayModel()
    {
        //return Mage::helper('payment')->getMethodInstance(Inchoo_HTPayWay_Model_Standard::CODE);
        return Mage::getSingleton('inchoo_htpayway/standard');
    }

    /**
     * @param $order
     * @return bool
     */
    protected function _restoreQuote($order)
    {
        $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());
        if ($quote->getId()) {

            $quote->setIsActive(1)
                ->setReservedOrderId(null)
                ->save();

            Mage::getSingleton('checkout/session')->replaceQuote($quote);
            //->unsLastRealOrderId();
            return true;
        }
        return false;
    }

    /**
     * @return bool
     */
    protected function _validateSuccessParams($data)
    {
        if(!$this->_getPayWayModel()->validateResponse($data)) {
            return false;
        }

        foreach(array('pgw_order_id', 'pgw_transaction_id') as $key) {
            if(!isset($data[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return bool
     */
    protected function _validateFailureParams($data)
    {
        if(!$this->_getPayWayModel()->validateResponse($data)) {
            return false;
        }

        foreach(array('pgw_order_id', 'pgw_result_code') as $key) {
            if(!isset($data[$key])) {
                return false;
            }
        }

        return true;
    }

}
