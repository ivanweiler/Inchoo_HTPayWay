<?php

class Inchoo_HTPayWay_Block_Redirect extends Mage_Page_Block_Redirect
{
    protected function _construct()
    {
        parent::_construct();

        $this->setTemplate('page/redirect.phtml');
    }

    public function getFormId()
    {
        return 'inchoo_htpayway_form';
    }

    public function getTargetURL()
    {
        return $this->getData('target_url');
    }

    public function getMethod()
    {
        return 'POST';
    }

    public function getFormFields()
    {
        return $this->getData('form_fields');
    }

    /*
    protected function _toHtml()
    {
        $form = new Varien_Data_Form();
        $formId = 'inchoo_tcompayway_redirect_form';

        $form->setAction($helper->getPostUrl())
             ->setId($formId)
             ->setName($formId)
             ->setMethod('POST')
             ->setUseContainer(true);

        $form->addField('ShopID', 'hidden', array('name'=>'ShopID', 'value'=>$shopID));

        $form->addField('Installments', 'hidden', array('name'=>'Installments', 'value'=>'N'));

        $html = '<html><body>';
        $html .= $this->__('You will be redirected to the T-Com PayWay website in a few seconds.');
        $html .= $form->toHtml();
        $html .= '<script type="text/javascript">document.getElementById("'.$formId.'").submit();</script>';
        $html .= '</body></html>';

        return $html;
    }
    */
}
