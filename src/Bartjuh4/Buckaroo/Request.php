<?php

namespace Bartjuh4\Buckaroo;

use Bartjuh4\Buckaroo\SOAP;

class Request
{

    private $soapClient = null;
    private $testMode = true;

    function __construct()
    {
        $this->soapClient = new SoapClientWSSEC(\Config::get('buckaroo.wsdl_url'), array('trace' => 1));
        $this->loadPem();
    }

    public function loadPem()
    {
        $this->soapClient->loadPem(public_path() . '/'. \Config::get('buckaroo.pem_file'));
    }

    public function sendRequest($TransactionRequest, $type)
    {

        if (!\Config::get('buckaroo.website_key')) {
            throw new \Exception('website_key not defined');
        }

        // Envelope and wrapper stuff
        $Header = new SOAP\Header();
        $Header->MessageControlBlock = new SOAP\MessageControlBlock();
        $Header->MessageControlBlock->Id = '_control';
        $Header->MessageControlBlock->WebsiteKey = \Config::get('buckaroo.website_key');
        $Header->MessageControlBlock->Culture = \Config::get('buckaroo.culture');


        $Header->MessageControlBlock->TimeStamp = time();
        $Header->MessageControlBlock->Channel = 'Web';
        $Header->Security = new SOAP\SecurityType();
        $Header->Security->Signature = new SOAP\SignatureType();
        $Header->Security->Signature->SignedInfo = new SOAP\SignedInfoType();

        $Reference = new SOAP\ReferenceType();
        $Reference->URI = '#_body';
        $Transform = new SOAP\TransformType();
        $Transform->Algorithm = 'http://www.w3.org/2001/10/xml-exc-c14n#';
        $Reference->Transforms = array($Transform);

        $Reference->DigestMethod = new SOAP\DigestMethodType();
        $Reference->DigestMethod->Algorithm = 'http://www.w3.org/2000/09/xmldsig#sha1';
        $Reference->DigestValue = '';

        $Transform2 = new SOAP\TransformType();
        $Transform2->Algorithm = 'http://www.w3.org/2001/10/xml-exc-c14n#';
        $ReferenceControl = new SOAP\ReferenceType();
        $ReferenceControl->URI = '#_control';
        $ReferenceControl->DigestMethod = new SOAP\DigestMethodType();
        $ReferenceControl->DigestMethod->Algorithm = 'http://www.w3.org/2000/09/xmldsig#sha1';
        $ReferenceControl->DigestValue = '';
        $ReferenceControl->Transforms = array($Transform2);

        $Header->Security->Signature->SignedInfo->Reference = array($Reference, $ReferenceControl);
        $Header->Security->Signature->SignatureValue = '';

        $soapHeaders[] = new \SOAPHeader('https://checkout.buckaroo.nl/PaymentEngine/', 'MessageControlBlock', $Header->MessageControlBlock);
        $soapHeaders[] = new \SOAPHeader('http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd', 'Security', $Header->Security);
        $this->soapClient->__setSoapHeaders($soapHeaders);

        if (\Config::get('buckaroo.test_mode')) {
            $this->soapClient->__SetLocation('https://testcheckout.buckaroo.nl/soap/');
        } else {
            $this->soapClient->__SetLocation('https://checkout.buckaroo.nl/soap/');
        }

        switch ($type) {
            case 'invoiceinfo':
                $response = $this->soapClient->InvoiceInfo($TransactionRequest);
                if (isset($response->Invoice)) {
                    $response = $response->Invoice;
                }
                break;
            case 'transaction':
                $response = $this->soapClient->TransactionRequest($TransactionRequest);
                break;
            case 'refundinfo':
                $response = $this->soapClient->RefundInfo($TransactionRequest);
                break;
        }

        return $response;
    }
}

