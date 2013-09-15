<?php namespace Ourlearn\LaravelStarter;

use Illuminate\Support\ServiceProvider;
use Ourlearn\LaravelStarter\Command;

class LaravelStarterServiceProvider extends ServiceProvider {

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
		$this->package('ourlearn/laravel-starter');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app['start'] = $this->app->share(function($app)
		{
			return new StartCommand($app);
		});

		$this->commands('start');
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}

}