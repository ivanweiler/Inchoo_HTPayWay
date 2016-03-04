<?php

class Inchoo_HTPayWay_Block_Redirect extends Mage_Core_Block_Template
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('inchoo/htpayway/redirect.phtml');
    }

    public function getFormId()
    {
        return 'inchoo_htpayway_form';
    }

    public function getFormHtml()
    {
        $form = new Varien_Data_Form();

        $form->setAction($this->getTargetUrl())
            ->setId($this->getFormId())
            ->setMethod('POST')
            ->setUseContainer(true);

        foreach($this->getFormFields() as $name => $value) {
            $form->addField($name, 'hidden', array('name' => $name, 'value' => $value));
        }

        return $form->toHtml();
    }

    /*
    protected function _toHtml()
    {
        $form = new Varien_Data_Form();

        $form->setAction($this->getTargetUrl())
             ->setId($this->getFormId())
             ->setMethod('POST')
             ->setUseContainer(true);

        foreach($this->getFormFields() as $name => $value) {
            $form->addField($name, 'hidden', array('value' => $value));
        }

        $html = '<html><body>';
        $html .= '<p>' . $this->__('You will be redirected to the HT PayWay website in a few seconds.') . '</p>';
        $html .= $form->toHtml();
        $html .= '<script type="text/javascript">////document.getElementById("' . $this->getFormId() . '").submit();</script>';
        $html .= '</body></html>';

        return $html;
    }
    */

}
