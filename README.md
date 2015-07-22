Buckaroo
========

A bridge between laravel 4 and the Buckaroo Payment engine.


Installation
============

Add `johnhout/buckaroo` as a requirement to composer.json:

```javascript
{
    "require": {
       "johnhout/buckaroo" : "dev-master"
    }
}
```

Update your packages with `composer update` or install with `composer install`.

Once Composer has installed or updated your packages you need to register Buckaroo with Laravel itself. Open up app/config/app.php and find the providers key towards the bottom and add:

```php
'johnhout\Buckaroo\BuckarooServiceProvider'
```

Configuration
=============

You should  publish a configuration file to enter your settings of your buckaroo account by running the following Artisan command.

```
$ php artisan config:publish johnhout/buckaroo
```

Add the serviceprovider
=============
We made it easy for you to access the class using the facade method. Add the following `in your config/app.php` in the 'aliases' array.

```php
'Buckaroo'    => 'johnhout\Buckaroo\Facades\Buckaroo',
```

Usage
=============

Before you will be transfered to a bank EU-Law requires the user to perform an action wich in our case will be a button.
The following code will generate a FORM with hidden inputs with everything set to be posted.
```php
Buckaroo::createForm(array(
	'brq_amount'  		=> '10.00',
	'brq_invoicenumber' => 'Invoice0001'
));
```

Adding a picked bank to the form for the iDeal portal.
```php
Buckaroo::createForm(array(
	'brq_amount'  				=> '10.00',
	'brq_invoicenumber' 		=> 'Invoice0001',
	'brq_payment_method' 		=> 'ideal',
	'brq_service_ideal_issuer' 	=> 'ABNANL2A'
));
```

Using PayPal.
```php
Buckaroo::createForm(array(
	'brq_amount'  		=> '10.00',
	'brq_invoicenumber' => 'Invoice0001',
	'brq_payment_method' => 'paypal',
	'brq_service_paypal_buyeremail' => 'user@example.com'
));
```
A complete list of possible banks to select from when using ideal
```html
<select>
	<option value="ABNANL2A">ABN AMRO</option>
	<option value="ASNBNL21">ASN Bank</option>
	<option value="INGBNL2A">ING</option>
	<option value="RABONL2U">Rabobank</option>
	<option value="SNSBNL2A">SNS Bank</option>
	<option value="RBRBNL21">RegioBank</option>
	<option value="TRIONL2U">Triodos Bank</option>
	<option value="FVLBNL22">Van Lanschot</option>
	<option value="KNABNL2H">KNAB bank</option>
</select>
```

Refunding
=============
Refunding is really simple just add an order number and a price (int rounded to 1).
```php
	Buckaroo::refund('Invoice00100', 0.5); // Refund 50 cents to the user of order Invoice00100
```

Transaction information
=============
Retrieving transaction Information of a previously made payment with  the order number.
```php
Buckaroo::transactionInfo('Invoice00100');
```
This will return the the whole object of a the transaction including multiple attempts dump the object to view more information.
```php
	$transactionData = Buckaroo::transactionInfo('Invoice00100');
	print_r($transactionData);
```

Helpers
=============
However if your simply want to resolve if a transaction was payed than you could use the following it returns a bool.
```php
Buckaroo::checkInvoiceForSuccess('Invoice00100');
// Returns true / false
```

Checking for success
```php
$transactionData = Buckaroo::transactionInfo('Invoice00100');
if(Buckaroo::success())
{
	var_dump($transactionData);
}
else
{
	var_dump(Buckaroo::errors());
}
```



