<?php namespace johnhout\Buckaroo;

use johnhout\Buckaroo\SOAP\Services;
use Whoops\Example\Exception;
use Config;

/**
 * Class Buckaroo
 *
 * @package johnhout\Buckaroo
 *
 * Buckaroo BPE3 API client for Laravel 4
 * Made by: John in 't Hout - U-Lab.nl
 * Tips or suggestions can be mailed to john.hout@u-lab.nl or check github.
 * Thanks to Joost Faasen from johnhout for helping the SOAP examples / client.
 */

class Buckaroo
{
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
	 * Retrieving transaction data with a given Invoice number.
	 *
	 * @param $invoiceId
	 *
	 * @return array|string
	 */
	public function transactionInfo($invoiceId)
	{
		$this->request = new \johnhout\Buckaroo\Request();

		$InvoiceInfoRequest                  = new SOAP\Body();
		$InvoiceInfoRequest->Invoice         = array();
		$InvoiceInfoRequest->Invoice         = new \stdClass();
		$InvoiceInfoRequest->Invoice->Number = trim($invoiceId);
		$bpeResponse                         = $this->request->sendRequest($InvoiceInfoRequest, 'invoiceinfo');

		if( isset($bpeResponse->Transactions->Transaction) )
		{
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
		if( is_array($orderBPEdata) )
		{
			foreach($orderBPEdata as $value)
			{
				if( $value->Status->Success )
				{
					$transactionInfo = $value;
				}
			}
		}
		else
		{
			if( $orderBPEdata->Status->Success )
			{
				$transactionInfo = $orderBPEdata;
			}
		}

		if( !$transactionInfo )
		{
			self::addError('Order has not been payed yet.');
		}
		else
		{
			$this->request = new Request(Config::get('buckaroo::website_key'));

			$RefundInfoRequest                                = new SOAP\Body();
			$RefundInfoRequest->RefundInfo                    = array();
			$RefundInfoRequest->RefundInfo[0]                 = new \stdClass();
			$RefundInfoRequest->RefundInfo[0]->TransactionKey = $transactionInfo->ID;

			$BPEresponse = $this->request->sendRequest($RefundInfoRequest, 'refundinfo');

			if( $amount <= $BPEresponse->RefundInfo->MaximumRefundAmount and $BPEresponse->RefundInfo->IsRefundable )
			{
				$this->TransactionRequest = new \johnhout\Buckaroo\Request(Config::get('buckaroo::website_key'));

				$TransactionRequest                         = new SOAP\Body();
				$TransactionRequest->Currency               = $BPEresponse->RefundInfo->RefundCurrency;
				$TransactionRequest->AmountCredit           = $amount;
				$TransactionRequest->Invoice                = $invoice;
				$TransactionRequest->Description            = 'Retourbetaling ' . $invoice;
				$TransactionRequest->OriginalTransactionKey = $transactionInfo->ID;

				$TransactionRequest->Services          = new Services();
				$TransactionRequest->Services->Service = new SOAP\Service($BPEresponse->RefundInfo->ServiceCode, 'Refund', '');

				if( $BPEresponse->RefundInfo->ServiceCode == 'ideal' )
				{
					$TransactionRequest->Services->Service->RequestParameter = new SOAP\RequestParameter('issuer', $transactionInfo->ID);
				}

				self::$success = true;

				return $this->TransactionRequest->sendRequest($TransactionRequest, 'transaction');

			}
			else
			{
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
		if( !$invoiceId )
		{
			self::addError('The maximum refund amount is to low. Or the order is not refundable.');
		}
		else
		{
			$orderBPEdata = Buckaroo::getTransactionInfo($invoiceId);

			$transactionInfo = false;
			if( is_array($orderBPEdata) )
			{
				foreach($orderBPEdata as $value)
				{
					if( $value->Status->Success )
					{
						$transactionInfo = $value;
					}
				}
			}
			else
			{
				if( $orderBPEdata->Status->Success )
				{
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
		if( !$dataArray['brq_amount'] )
		{
			self::addError('Amount has not been set.');
		}
		elseif( !$dataArray['brq_invoicenumber'] )
		{
			self::addError('Amount has not been set.');
		}
		else
		{
			$dataArray['bpe_signature'] = self::createSignature($dataArray);
			$dataArray['bpe_url']       = ((Config::get('buckaroo::test_mode')) ? Config::get('buckaroo::bpe_post_test_url') : Config::get('buckaroo::bpe_post_url'));
			$dataArray['button']        = $button;

			self::$success = true;

			return \View::make('buckaroo::SubmitForm', $dataArray);
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
		$data['brq_websitekey'] = Config::get('buckaroo::website_key');
		$data['brq_currency']   = Config::get('buckaroo::currency');
		$data['brq_culture']    = Config::get('buckaroo::culture');
		$data['brq_return']     = Config::get('buckaroo::return_url');

		ksort($data);

		foreach($data as $arrKey => $arrValue)
		{
			$hashString .= strtolower($arrKey) . '=' . $arrValue;
		}

		$hashString .= Config::get('buckaroo::secret_key');

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

	public function getIssuerArray() {
		return array(
			"ABNANL2A" => "ABN AMRO",
			"ASNBNL21" => "ASN Bank",
			"FRBKNL2L" => "Friesland Bank",
			"INGBNL2A" => "ING",
			"RABONL2U" => "Rabobank",
			"SNSBNL2A" => "SNS Bank",
			"RBRBNL21" => "RegioBank",
			"TRIONL2U" => "Triodos Bank",
			"FVLBNL22" => "Van Lanschot",
			"KNABNL2H" => "KNAB bank"
		);
	}

}