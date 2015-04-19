<?php

class Am_Paysystem_Payone extends Am_Paysystem_Abstract{
    const PLUGIN_STATUS = self::STATUS_BETA;
    const PLUGIN_REVISION = '4.2.18';

    protected $defaultTitle = 'Payone';
    protected $defaultDescription = 'Credit Card Payment';
   
    const API_URL = 'https://secure.pay1.de/frontend/';
    
    public function __construct(Am_Di $di, array $config)
    {
        parent::__construct($di, $config);
        $di->billingPlanTable->customFields()->add(
            new Am_CustomFieldText(
            'payone_product_id',
            'PayOne Product ID',
            'you have to create similar product in PayOne and enter its number here'
            )
        );
        
    }

    public function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addText("aid")->setLabel(array(
            'Sub Account ID', 
            ''));
        $form->addText("portalid")->setLabel(array(
            'Payment Portal ID', 
            ''));
        $form->addText('sercet_key', 'size=40')->setLabel(array('Secret Key',
            'You can assign the key to be used in the PMI (PAYONE Merchant Interface)'))->addRule('required');
        $form->addSelect("testing", array(), array('options' => array(
                ''=>'No',
                '1'=>'Yes' 
            )))->setLabel('Test Mode');

    }
    
    function getSupportedCurrencies()
    {
        return array('EUR', 'AUD', 'CHF', 'DKK', 'GBP', 'NOK', 'NZD', 'SEK', 'USD');
    }    
    
    public function _process(Invoice $invoice, Am_Request $request, Am_Paysystem_Result $result)
    {
        $u = $invoice->getUser();
        $a = new Am_Paysystem_Action_Redirect(self::API_URL);

        /*

         */
        $params = array(
            'aid'           => $this->getConfig('aid'),                         //Sub Account ID
            'portalid'      => $this->getConfig('portalid'),                    //Payment portal ID
            'mode'          => $this->getConfig('testing') ? 'test' : 'live',   //Test: Test mode, Live: Live mode
            
            'encoding'      => 'UTF-8',                                         //ISO 8859-1 (default), UTF-8
            
            'clearingtype'  => 'cc',                                            //elv: Debit payment
                                                                                //cc: Credit card
                                                                                //vor: Prepayment
                                                                                //rec: Invoice
                                                                                //sb: Online bank transfer
                                                                                //wlt: e-wallet
                                                                                //fnc: Financing

            'reference'     => $invoice->public_id,
            'customerid'    => $invoice->user_id,
            'invoiceid'     => $invoice->public_id,
            'param'      => $invoice->public_id,

            'successurl'    => $this->getReturnUrl(),                           //URL "payment successful" (only if responsetype=REDIRECT or required by corresponding request)
            'backurl'       => $this->getCancelUrl(),                            //URL "faulty payment" (only if responsetype=REDIRECT or required by corresponding request)

            //Parameter ( personal data )
            'firstname'     => $u->name_f,  //AN..50 First name
            'lastname'      => $u->name_l,  //AN..50 Surname
            //'company'       => '',        //AN..50 Company
            'street'        => $u->street,  //AN..50 Street
            'zip'           => $u->zip,     //AN..10 Postcode
            'city'          => $u->city,    //AN..50 City
            'country'       => $u->country, //Default Country (ISO 3166)
            'email'         => $u->email,   //AN..50 Email address
            'language'      => 'en'         //Language indicator (ISO 639)
                                            //If the language is not transferred, the browser
                                            //language will be used. For a non-supported
                                            //language, English will be used.
            /////
        );
            
        if($invoice->second_total>0){
            //Parameter („createaccess“)
            $first_period = new Am_Period($invoice->first_period);
            $second_period = new Am_Period($invoice->second_period);
            $params['request']                  = 'createaccess';
            $params['productid']                = $invoice->getItem(0)->getBillingPlanData('payone_product_id'); // + + N..7 ID for the offer
            $params['amount_trail']             = $invoice->first_total * 100; // - + N..6 Total price of all items during the initial term. Must equal the sum (quantity * price) of all items for the initial term (in the smallest currency unit, e.g. Cent).
            $params['amount_recurring']         = $invoice->second_total * 100; // - + N..6 Total price of all items during the subsequent term. Must equal the sum (quantity * price) of all items for the subsequent term (in the smallest currency unit, e.g. Cent).
            $params['period_unit_trail']        = strtoupper($first_period->getUnit()); // - + Default Time unit for initial term, possible values: Y: Value in years M: Value in months D: Value in days
            $params['period_length_trail']      = $first_period->getCount(); // - + N..4 Duration of the initial term. Can only be used in combination with period_unit_trail.
            $params['period_unit_recurring']    = strtoupper($second_period->getUnit()); // - + Default Time unit for subsequent term, possible values: Y: Value in years M: Value in months D: Value in days N: only if no subsequent term
            $params['period_length_recurring']  = $second_period->getCount(); // - + N..4 Duration of the subsequent term. Can only be used in combination with period_unit_recurring.
            $params['id_trail[1]']              = $invoice->getItem(0)->billing_plan_id; // + + AN..100 Item number (initial term)
            $params['no_trail[1]']              = 1; // + + N..5 Quantity (initial term)
            $params['pr_trail[1]']              = $invoice->first_total * 100; // + + N..7 Unit price of the item in smallest currency unit (initial term)
            $params['de_trail[1]']              = $invoice->getItem(0)->item_description; // + + AN..255 Description (initial term)
            $params['ti_trail[1]']              = $invoice->getItem(0)->item_title; // + + AN..100 Title (initial term)
            //$params['va_trail[1]']              = ''; // - + N..4 VAT rate (% or bp) (initial term) value < 100 = percent value > 99 = basis points
            $params['id_recurring[1]']          = $invoice->getItem(0)->billing_plan_id; // - + AN..100 Item number (subsequent term)
            $params['no_recurring[1]']          = 1; // - + N..5 Quantity (subsequent term)
            $params['pr_recurring[1]']          = $invoice->second_total * 100; // - + N..7 Unit price of the item in smallest currency unit (subsequent term)
            $params['de_recurring[1]']          = $invoice->getItem(0)->item_description; // - + AN..255 Description (subsequent term)
            $params['ti_recurring[1]']          = $invoice->getItem(0)->item_title; // - + AN..100 Title (subsequent term)
            //$params['va_recurring[1]']          = ''; // - + N..4 VAT rate (% or bp) (subsequent term) value < 100 = percent value > 99 = basis points
            /////
        } else {
            //Parameter ( „pre-/authorization“ )
            $params['request']  = 'authorization';
            $params['amount']   = $invoice->first_total * 100;
            $params['currency'] = $invoice->currency;
            $params['it[1]']    = 'goods';                     //For BSV: Item type
            $params['id[1]']    = '';                          //Your item no.
            $params['pr[1]']    = $invoice->first_total * 100; //Price in Cent
            $params['no[1]']    = 1;                           //Quantity
            $params['de[1]']    = '';                          //Item description
            //$params['va[1]']  = '';                        //VAT (optional)
            /////

        }

        foreach ($params as $k=>$v)
            $a->addParam ($k, $v);
        
        ksort($params);
        $a->hash = strtolower(md5(implode('', $params) . $this->getConfig('sercet_key'))); //Hash value (see chapter 3.1.4)
        
        $result->setAction($a);        
    }
    public function createTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        return new Am_Paysystem_Transaction_Payone($this, $request, $response, $invokeArgs);
    }
    public function createThanksTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        return new Am_Paysystem_Transaction_Payone_Thanks($this, $request, $response, $invokeArgs);
    }
    public function getRecurringType()
    {
        return self::REPORTS_REBILL;
    }
    function getReadme(){
        return <<<CUT
<b>Payone payment plugin configuration</b>

%root_surl%/payment/payone/ipn
CUT;
        
    }
}

class Am_Paysystem_Transaction_Payone_Thanks extends Am_Paysystem_Transaction_Incoming{

    public function findInvoiceId()
    {
        return $this->request->get('param[1]');
    }
    
    public function getUniqId()
    {
        return $this->request->get('txid');
    }
    
    public function validateSource()
    {
        if ($this->getPlugin()->getConfig('sercet_key') != $this->request->get('key'))
            return false;
        else
            return true;
    }
    
    public function validateStatus()
    {
        return ($this->request->get('txaction ') == 'paid');
    }
    
    public function validateTerms()
    {
        return true;
    }
    
    public function getInvoice()
    {
        return $this->invoice;
    }
}

class Am_Paysystem_Transaction_Payone extends Am_Paysystem_Transaction_Incoming{
    
    public function findInvoiceId()
    {
        return $this->request->get('param[1]');
    }
        
    public function getUniqId()
    {
        return $this->request->get('');
    }
    
    public function validateSource()
    {

        //SessionStatus. As a reply to the request, the string "SSOK" is expected.
        //TransactionStatus. As a reply to the request, the string "TSOK" is expected. 
        print "SSOK";

        //The SessionStatus/TransactionStatus is sent from the following IP addresses: 213.178.72.196, or 213.178.72.197 as well as 217.70.200.0/24.
        $this->_checkIp(<<<IPS
213.178.72.196-213.178.72.197
217.70.200.0-217.70.200.24
IPS
        );
        
        if ($this->getPlugin()->getConfig('sercet_key') != $this->request->get('key'))
            return false;
        else
            return true;
    }
    
    public function validateStatus()
    {
        return ($this->request->get('action[1]') == 'add');
    }
    
    public function validateTerms()
    {        
        return true;
    }

    
}
