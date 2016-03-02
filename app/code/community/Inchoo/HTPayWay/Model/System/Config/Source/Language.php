<?php

class Inchoo_HTPayWay_Model_System_Config_Source_Language
{
    public function toOptionArray()
    {
        return array(
            array('value'=>'hr', 'label'=>Mage::helper('inchoo_htpayway')->__('Croatian')),
            array('value'=>'en', 'label'=>Mage::helper('inchoo_htpayway')->__('English')),
            array('value'=>'de', 'label'=>Mage::helper('inchoo_htpayway')->__('German')),
            array('value'=>'it', 'label'=>Mage::helper('inchoo_htpayway')->__('Italian')),
            array('value'=>'fr', 'label'=>Mage::helper('inchoo_htpayway')->__('French')),
            array('value'=>'ru', 'label'=>Mage::helper('inchoo_htpayway')->__('Russian')),
        );
    }

}