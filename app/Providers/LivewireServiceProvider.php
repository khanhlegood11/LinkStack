<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class LivewireServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        $assetUrl = (env('FORCE_ROUTE_HTTPS') == 'true' || env('FORCE_HTTPS') == 'true')
            ? secure_url('')
            : url('');
        config(['livewire.asset_url' => $assetUrl]);
    }
}