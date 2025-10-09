<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Channels\DatabaseChannel;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 🔧 Registrar el canal "database" (compatible con Laravel 12)
        $this->app->afterResolving(ChannelManager::class, function ($manager) {
            if (!isset($manager->channels()['database'])) {
                $manager->extend('database', function () {
                    return new DatabaseChannel();
                });
            }
        });
    }


    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
