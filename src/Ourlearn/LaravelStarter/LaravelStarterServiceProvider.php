<?php namespace Ourlearn\LaravelStarter;

use Illuminate\Support\ServiceProvider;

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

        $this->app['start.file'] = $this->app->share(function($app)
        {
            return new StartFromFileCommand($app);
        });

        $this->app['start.model'] = $this->app->share(function($app)
        {
            return new StartModelCommand($app);
        });

		$this->commands('start', 'start.file', 'start.model');
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