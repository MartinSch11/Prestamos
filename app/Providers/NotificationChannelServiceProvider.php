<?php

namespace App\Providers;

use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Support\ServiceProvider;

class NotificationChannelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->afterResolving(ChannelManager::class, function (ChannelManager $manager) {
            // Registrar canal 'database' si no existe
            $manager->extend('database', function ($app) {
                return new DatabaseChannel($app['db']);
            });
        });
    }

    public function boot(): void
    {
        //
    }
}
