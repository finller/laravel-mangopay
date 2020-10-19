<?php

namespace Finller\Mangopay;

use Illuminate\Support\ServiceProvider;
use Finller\Mangopay\Commands\MangopayCommand;
use MangoPay\MangoPayApi;

class MangopayServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/mangopay.php' => config_path('mangopay.php'),
            ], 'config');

            $this->publishes([
                __DIR__ . '/../resources/views' => base_path('resources/views/vendor/mangopay'),
            ], 'views');

            $migrationFileName = 'create_mangopay_table.php';
            if (!$this->migrationFileExists($migrationFileName)) {
                $this->publishes([
                    __DIR__ . "/../database/migrations/{$migrationFileName}.stub" => database_path('migrations/' . date('Y_m_d_His', time()) . '_' . $migrationFileName),
                ], 'migrations');
            }

            $this->commands([
                MangopayCommand::class,
            ]);
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'mangopay');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/mangopay.php', 'mangopay');

        $this->registerApi();
    }

    /**
     *
     * @return void
     */
    protected function registerApi()
    {
        $this->app->singleton(MangopayApi::class, function () {

            $mangoPayApi = new MangoPayApi();
            $mangoPayApi->Config->ClientId = config('mangopay.api.id');
            $mangoPayApi->Config->ClientPassword = config('mangopay.api.secret');
            $mangoPayApi->Config->TemporaryFolder = config('mangopay.folder');

            return $mangoPayApi;
        });
    }


    public static function migrationFileExists(string $migrationFileName): bool
    {
        $len = strlen($migrationFileName);
        foreach (glob(database_path("migrations/*.php")) as $filename) {
            if ((substr($filename, -$len) === $migrationFileName)) {
                return true;
            }
        }

        return false;
    }
}
