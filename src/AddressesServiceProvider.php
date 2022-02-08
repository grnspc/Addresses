<?php

namespace Grnspc\Addresses;

use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Grnspc\Addresses\Contracts\Address as AddressContract;

class AddressesServiceProvider extends ServiceProvider
{
	public function boot(AddressRegistrar $addressLoader)
	{
		$this->offerPublishing();
		$this->registerModelBindings();

        $this->app->singleton(AddressRegistrar::class, function ($app) use ($addressLoader) {
            return $addressLoader;
        });
	}

	public function register()
	{
		$this->mergeConfigFrom(__DIR__ . '/../config/address.php', 'address');
		$this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
	}

	protected function offerPublishing()
	{
		if (!function_exists('config_path')) {
			// function not available and 'publish' not relevant in Lumen
			return;
		}

		$this->publishes(
			[
				__DIR__ . '/../config/address.php' => config_path('address.php'),
			],
			'config'
		);

		$this->publishes(
			[
				__DIR__ . '/../database/migrations/' => database_path('migrations'),
			],
			'migrations'
		);
	}

	protected function registerModelBindings()
	{
		$config = $this->app->config['address.models'];

		if (!$config) {
			return;
		}

		$this->app->bind(AddressContract::class, $config['address']);
	}

	/**
	 * Returns existing migration file if found, else uses the current timestamp.
	 *
	 * @return string
	 */
	protected function getMigrationFileName($migrationFileName): string
	{
		$timestamp = date('Y_m_d_His');

		$filesystem = $this->app->make(Filesystem::class);

		return Collection::make($this->app->databasePath() . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR)
			->flatMap(function ($path) use ($filesystem, $migrationFileName) {
				return $filesystem->glob($path . '*_' . $migrationFileName);
			})
			->push($this->app->databasePath() . "/migrations/{$timestamp}_{$migrationFileName}")
			->first();
	}
}
