<?php

namespace Bramato\FilamentAiAssistent;

use Livewire\Livewire;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentAiAssistentServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('filament-stripe-manager')
            ->hasMigrations([
            ])
        ->hasViews()
        ->hasRoute('web');
    }

    public function bootingPackage()
    {
        parent::bootingPackage();
        $this->registerLivewireComponents();
        $this->app->register(EventServiceProvider::class);
        $this->loadTestingMigrations();
    }

    protected function registerLivewireComponents()
    {
        // Replace 'ComponentClass' with the actual full class name of your Livewire component
        //Livewire::component('credit-card-payment', \Bramato\FilamentAiAssistent\Components\CreditCardPayment::class);


        // Register other components similarly
    }

    protected function loadTestingMigrations(): void
    {
        if ($this->app->environment('testing')) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }
}
