<?php

class Inchoo_HTPayWay_StandardController extends Mage_Core_Controller_Front_Action
{
    /**
     * Redirect to PayWay
     */
    public function redirectAction()
    {
        $order = $this->_getLastOrder();

        if(!$order) {
            $this->_forward('noRoute');
            return;
        }

        $payWayModel = $this->_getPayWayModel();
        $payWayData = $payWayModel->prepareRequest($order);

        $payWayModel->debugData($payWayData);

        $this->getResponse()->setBody(
            $this->getLayout()
                ->createBlock('inchoo_htpayway/redirect')
                ->setTargetUrl($payWayModel->getRedirectUrl())
                ->setFormFields($payWayData)
                ->toHtml()
        );
    }

    /**
     * Success action
     */
    public function successAction()
    {
        $payWayParams = $this->getRequest()->getParams();
        $payWayModel = $this->_getPayWayModel();

        $payWayModel->debugData($payWayParams);

        if(!$payWayModel->validateResponse($payWayParams, 'success')) {
            $this->_forward('noRoute');
            return;
        }

        $order = Mage::getModel('sales/order')->loadByIncrementId($payWayParams['pgw_order_id']);
        if(!$order->getId()) {
            $this->_getCheckoutSession()->addError($this->__('This order no longer exists.'));
            $this->_redirect('checkout/cart');
            return;
        }

        //register transaction && create invoice

        /** @var Mage_Sales_Model_Order_Payment $payment */
        $payment = $order->getPayment();
        $payment
            ->setTransactionId($payWayParams['pgw_transaction_id'])
            ->setIsTransactionClosed(1)
            ->setTransactionAdditionalInfo(
                Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
                $payWayParams
            );
        $payment->registerCaptureNotification($payWayParams['pgw_amount']/100);

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
        $payWayModel = $this->_getPayWayModel();

        $payWayModel->debugData($payWayParams);

        if(!$payWayModel->validateResponse($payWayParams, 'failure')) {
            $this->_forward('noRoute');
            return;
        }

        /** @var $order Mage_Sales_Model_Order */
        $order = Mage::getModel('sales/order')->loadByIncrementId($payWayParams['pgw_order_id']);
        if(!$order->getId()) {
            $this->_getCheckoutSession()->addError($this->__('This order no longer exists.'));
            $this->_redirect('checkout/cart');
            return;
        }

        $payWayMessage = $this->__($payWayModel->getResultMessage($payWayParams['pgw_result_code']));
        $message = $this->__('HT PayWay message: %s.', $payWayMessage);

        //cancel order on cancelation code
        if($payWayParams['pgw_result_code'] == '3') {
            $order->cancel();
            //delete order form session on cancel ?
        } else {
            $history = $order->addStatusHistoryComment($message);
            $history->setIsCustomerNotified(false);
        }

        $order->save();

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
    protected function _getLastOrder()
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
        return Mage::getModel('inchoo_htpayway/standard');
    }

    /**
     * @param Mage_Sales_Model_Order $order
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
            return true;
        }
        return false;
    }

}
