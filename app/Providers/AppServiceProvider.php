<?php

namespace App\Providers;

use Config;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        try {
            Config::set('server-monitor.notifications.mail.to', implode(",", \App\User::select('email')->get()->pluck('email')->toArray()));
        } catch (\Exception $ex) {
            \Log::error('Ha ocurrido un error al inicialzar la aplicación', [$ex->getMessage()]);
        }
    }
}
