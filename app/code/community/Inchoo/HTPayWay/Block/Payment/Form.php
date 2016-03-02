<?php

class Inchoo_HTPayWay_Block_Payment_Form extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        $this->setTemplate('inchoo_htpayway/payment/form.phtml');
        return parent::_construct();
    }
}
