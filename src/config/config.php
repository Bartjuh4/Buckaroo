<?php

	/**
	 * Class Buckaroo
	 *
	 * @package johnhout\Buckaroo
	 *
	 * Buckaroo BPE3 API client for Laravel 4
	 * Made by: John in 't Hout - U-Lab.nl
	 * Tips or suggestions can be mailed to john.hout@u-lab.nl or check github.
	 * Thanks to Joost Faasen from LinkORB for helping the SOAP examples / client.
	 */

	return array(

		/**
		 * Buckaroo has a test area and the live portal
		 * true = testmode
		 * false = live
		 */
		'test_mode'         => true,

		/**
		 *  Some settings wich need to be set!
		 */
		'website_key'       => 'YourWebsiteKey',
		'secret_key'        => 'YourSecretKey',

		/**
		 * Optional settings wich could be set!
		 *
		 * the pem_file should be placed in your public directory.
		 */
		'culture'           => 'nl-NL',
		'currency'          => 'EUR',
		'pem_file'          => 'BuckarooPrivateKey.pem',
		'return_url'        => 'http://yourwebsite.ext/returnpath/',

		/**
		 *  Default invoice settings
		 */
		'currency '         => 'EUR',
		'start_recurrent '  => false,

		/*
		 * Should not change however always be focused on the future.
		 */
		'wsdl_url'          => 'https://checkout.buckaroo.nl/soap/soap.svc?wsdl',
		'bpe_post_url'      => 'https://checkout.buckaroo.nl/html/',
		'bpe_post_test_url' => 'https://testcheckout.buckaroo.nl/html/',


	);
