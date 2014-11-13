<?php namespace Bartjuh4\Buckaroo;

/**
 * Class Buckaroo
 *
 * @package Bartjuh4\Buckaroo
 *
 * Buckaroo BPE3 API client for Laravel 4
 * Made by: John in 't Hout - U-Lab.nl
 * Tips or suggestions can be mailed to john.hout@u-lab.nl or check github.
 * Thanks to Joost Faasen from LinkORB for helping the SOAP examples / client.
 */


use Illuminate\Support\ServiceProvider;

class BuckarooServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package('Bartjuh4/buckaroo');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app['buckaroo'] = $this->app->share(function($app)
		{
			return new Buckaroo()   ;
		});
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array('buckaroo');
	}

}