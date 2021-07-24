<?php

namespace Finller\Mangopay;

use MangoPay\MangoPayApi;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MangopayServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-mangopay')
            ->hasConfigFile()
            // ->hasViews()
            ->hasMigration('create_mangopay_table');
        // ->hasCommand(SkeletonCommand::class);
    }

    public function packageRegistered()
    {
        $this->app->singleton(MangopayApi::class, function () {
            $mangoPayApi = new MangoPayApi();
            $mangoPayApi->Config->ClientId = config('mangopay.api.id');
            $mangoPayApi->Config->ClientPassword = config('mangopay.api.secret');
            $mangoPayApi->Config->TemporaryFolder = config('mangopay.folder');

            return $mangoPayApi;
        });
    }
}
