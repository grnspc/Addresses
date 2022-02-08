<?php

namespace Grnspc\Addresses;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class AddressesServiceProvider extends PackageServiceProvider
{

     public function configurePackage(Package $package): void
    {
        $package
            ->name('addresses')
            ->hasConfigFile('address')
            ->hasMigration('create_addresses_table');
    }
}
