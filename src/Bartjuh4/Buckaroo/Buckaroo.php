<?php

namespace Bartjuh4\Buckaroo;

use Bartjuh4\Buckaroo\SOAP\Services;
use Whoops\Example\Exception;
use Config;

/**
 * Class Buckaroo
 *
 * @package Bartjuh4\Buckaroo
 *
 * Buckaroo BPE3 API client for Laravel 4
 * Made by: John in 't Hout - U-Lab.nl
 * Tips or suggestions can be mailed to john.hout@u-lab.nl or check github.
 * Thanks to Joost Faasen from Bartjuh4 for helping the SOAP examples / client.
 */
class Buckaroo {

	/**
	 * Returns wether the the
	 *
	 * @var bool
	 */
	public static $success = false;

	/**
	 * Holds the given errors
	 *
	 * @var array
	 */
	public static $errors = array();

	/**
	 * The url to redirect if this is neccessary by Buckaroo
	 * @var type string
	 */
	public static $redirectUrl = false;

	/**
	 * Custom params to add to the payment request
	 * @var type 
	 */
	public $customParams = null;

	/**
	 * Retrieving transaction data with a given Invoice number.
	 *
	 * @param $invoiceId
	 *
	 * @return array|string
	 */
	public function transactionInfo($invoiceId)
	{
		$this->request = new \Bartjuh4\Buckaroo\Request();

		$InvoiceInfoRequest = new SOAP\Body();
		$InvoiceInfoRequest->Invoice = array();
		$InvoiceInfoRequest->Invoice = new \stdClass();
		$InvoiceInfoRequest->Invoice->Number = trim($invoiceId);
		$bpeResponse = $this->request->sendRequest($InvoiceInfoRequest, 'invoiceinfo');

		if (isset($bpeResponse->Transactions->Transaction)) {
			return $bpeResponse->Transactions->Transaction;
		}

		self::addError('Order ' . $invoiceId . ' not found.');
	}

	/**
	 * Add a refund based on an given Invoice number
	 *
	 * @param $dataArray
	 *
	 * @return mixed
	 * @throws \Whoops\Example\Exception
	 */
	public function refund($invoice, $amount)
	{
		$orderBPEdata = Buckaroo::transactionInfo($invoice);

		$transactionInfo = false;
		if (is_array($orderBPEdata)) {
			foreach ($orderBPEdata as $value) {
				if ($value->Status->Success) {
					$transactionInfo = $value;
				}
			}
		} else {
			if ($orderBPEdata->Status->Success) {
				$transactionInfo = $orderBPEdata;
			}
		}

		if (!$transactionInfo) {
			self::addError('Order has not been payed yet.');
		} else {
			$this->request = new Request(Config::get('buckaroo.website_key'));

			$RefundInfoRequest = new SOAP\Body();
			$RefundInfoRequest->RefundInfo = array();
			$RefundInfoRequest->RefundInfo[0] = new \stdClass();
			$RefundInfoRequest->RefundInfo[0]->TransactionKey = $transactionInfo->ID;

			$BPEresponse = $this->request->sendRequest($RefundInfoRequest, 'refundinfo');

			if ($amount <= $BPEresponse->RefundInfo->MaximumRefundAmount and $BPEresponse->RefundInfo->IsRefundable) {
				$this->TransactionRequest = new \Bartjuh4\Buckaroo\Request(Config::get('buckaroo.website_key'));

				$TransactionRequest = new SOAP\Body();
				$TransactionRequest->Currency = $BPEresponse->RefundInfo->RefundCurrency;
				$TransactionRequest->AmountCredit = $amount;
				$TransactionRequest->Invoice = $invoice;
				$TransactionRequest->Description = 'Retourbetaling ' . $invoice;
				$TransactionRequest->OriginalTransactionKey = $transactionInfo->ID;

				$TransactionRequest->Services = new Services();
				$TransactionRequest->Services->Service = new SOAP\Service($BPEresponse->RefundInfo->ServiceCode, 'Refund', '');

				if ($BPEresponse->RefundInfo->ServiceCode == 'ideal') {
					$TransactionRequest->Services->Service->RequestParameter = new SOAP\RequestParameter('issuer', $transactionInfo->ID);
				}

				self::$success = true;

				return $this->TransactionRequest->sendRequest($TransactionRequest, 'transaction');
			} else {
				self::addError('The maximum refund amount is to low. Or the order is not refundable.');
			}
		}
	}

	/**
	 * Check an order if it has been payed with a given Invoice number.
	 *
	 * @param $invoiceId
	 *
	 * @return bool
	 */
	public function checkInvoiceForSuccess($invoiceId)
	{
		if (!$invoiceId) {
			self::addError('The maximum refund amount is to low. Or the order is not refundable.');
		} else {
			$orderBPEdata = Buckaroo::transactionInfo($invoiceId);

			$transactionInfo = false;
			if (is_array($orderBPEdata)) {
				foreach ($orderBPEdata as $value) {
					if ($value->Status->Success) {
						$transactionInfo = $value;
					}
				}
			} else {
				if ($orderBPEdata->Status->Success) {
					$transactionInfo = $orderBPEdata;
				}
			}

			self::$success = true;

			return (!$transactionInfo) ? false : true;
		}
	}

	/**
	 * Returns a form for submission to Buckaroo.
	 *
	 * @param      $dataArray
	 * @param null $button
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function createForm($dataArray, $button = null)
	{
		if (!$dataArray['brq_amount']) {
			self::addError('Amount has not been set.');
		} elseif (!$dataArray['brq_invoicenumber']) {
			self::addError('Amount has not been set.');
		} else {
			$dataArray['bpe_signature'] = self::createSignature($dataArray);
			$dataArray['bpe_url'] = ((Config::get('buckaroo.test_mode')) ? Config::get('buckaroo.bpe_post_test_url') : Config::get('buckaroo.bpe_post_url'));
			$dataArray['button'] = $button;

			self::$success = true;

			return \View::make('buckaroo.SubmitForm', $dataArray);
		}
	}

	/**
	 * @param $data
	 *
	 * @return string
	 */
	public function createSignature($data)
	{

		$hashString = '';
		// Add additional data to array
		$data['brq_websitekey'] = Config::get('buckaroo.website_key');
		$data['brq_currency'] = Config::get('buckaroo.currency');
		$data['brq_culture'] = Config::get('buckaroo.culture');
		$data['brq_return'] = Config::get('buckaroo.return_url');

		ksort($data);

		foreach ($data as $arrKey => $arrValue) {
			$hashString .= strtolower($arrKey) . '=' . $arrValue;
		}

		$hashString .= Config::get('buckaroo.secret_key');

		return sha1($hashString);
	}

	/**
	 * @param $message
	 */
	public function addError($message)
	{
		array_push(self::$errors, array('message' => $message));
	}

	/**
	 * @return bool
	 */
	public function success()
	{
		return self::$success;
	}

	/**
	 * @return array
	 */
	public function errors()
	{
		return self::$errors;
	}

	public function redirectUrl()
	{
		return self::$redirectUrl;
	}

	public function getIssuerArray($method = 'ideal', $option = false)
	{
		$values = array();
		switch ($method) {
			case 'creditcard':
				$values = array(
					'mastercard' => 'MasterCard',
					'visa' => 'Visa'
				);
				break;
			case 'ideal':
			default:
				$values = array(
					"0031" => "ABN AMRO",
					"0761" => "ASN Bank",
					"0721" => "ING",
					"0021" => "Rabobank",
					"0751" => "SNS Bank",
					"0771" => "RegioBank",
					"0511" => "Triodos Bank",
					"0161" => "Van Lanschot",
					"0801" => "KNAB bank"
				);
				break;
		}
		if ($option) {
			return isset($values[$option]);
		}
		return $values;
	}

	public function payment($order_id, $amount, $description = '', $method, $method_extra = false)
	{
		$this->TransactionRequest = new \Bartjuh4\Buckaroo\Request(Config::get('buckaroo.website_key'));

		$invoice_id = str_pad($order_id, 8, '0', STR_PAD_LEFT);

		$TransactionRequest = new SOAP\Body();
		$TransactionRequest->Currency = Config::get('buckaroo.currency');
		$TransactionRequest->AmountDebit = $amount;
		$TransactionRequest->Invoice = 'Invoice' . $invoice_id . '_' . rand(999, 99999);
		$TransactionRequest->Description = $description;
		$TransactionRequest->ReturnURL = Config::get('buckaroo.return_url');
		$TransactionRequest->StartRecurrent = Config::get('buckaroo.start_recurrent');

		$this->customParams = array(
			'sessionId' => \Session::getId(),
			'orderId' => $order_id
		);

		$TransactionRequest->Services = new SOAP\Services();

		switch ($method) {
			case 'ideal':
				$TransactionRequest->Services->Service = new SOAP\Service($method, 'Pay', 1);
				// Add parameters for this service
				$TransactionRequest->Services->Service->RequestParameter = new SOAP\RequestParameter('issuer', $method_extra);
				break;
			case 'mastercard':
			case 'visa':
			case 'amex':
				$TransactionRequest->Services->Service = new SOAP\Service($method, 'Pay', 1);
				break;
		}

		if ($this->customParams) {
			$TransactionRequest = $this->_addCustomParameters($TransactionRequest);
		}

		// Optionally pass the client ip-address for logging
		$TransactionRequest->ClientIP = new SOAP\IPAddress(\Request::getClientIp());

		$response = $this->TransactionRequest->sendRequest($TransactionRequest, 'transaction');

		if ($response->Status->Code) {
			switch ($response->Status->Code->Code) {
				case 190:
					return true;
					break;
				case 790:
					break;
				case 791:
					break;
				case 490:
				case 491:
				case 492:
				case 690:
				case 792:
				case 793:
				case 890:
				case 891:
					$this->addError('Payment failed: ' . $response->Status->Code->_);
					return false;
					break;
			}
		}

		if ($response->RequiredAction) {
			switch ($response->RequiredAction->Type) {
				case 'Redirect':
					self::$redirectUrl = $response->RequiredAction->RedirectURL;
					return true;
					break;
			}
		}

		$this->addError('Payment failed');
		return false;
	}

	protected function _addCustomParameters(&$TransactionRequest)
	{
		$requestParameters = array();
		foreach ($this->customParams as $fieldName => $value) {
			if (
					(is_null($value) || $value === '') || (
					is_array($value) && (is_null($value['value']) || $value['value'] === '')
					)
			) {
				continue;
			}

			if (is_array($value)) {
				$requestParameter = new SOAP\RequestParameter($fieldName, $value['value'], $value['group']);
			} else {
				$requestParameter = new SOAP\RequestParameter($fieldName, $value);
			}

			$requestParameters[] = $requestParameter;
		}

		if (empty($requestParameters)) {
			unset($TransactionRequest->AdditionalParameters);
			return;
		} else {
			$TransactionRequest->AdditionalParameters = $requestParameters;
		}

		return $TransactionRequest;
	}

}
