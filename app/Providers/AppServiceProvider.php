<?php

namespace App\Providers;

use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\UserRepository;
use App\Services\PaymentAllocationService;
use App\Services\CreditManagementService;
use App\Services\ARAgingService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Repository bindings
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);

        // AR Service bindings
        $this->app->singleton(PaymentAllocationService::class);
        $this->app->singleton(CreditManagementService::class);
        $this->app->singleton(ARAgingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
