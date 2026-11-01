<?php

namespace App\Providers;

use App\Support\RuntimeEnvEditor;
use App\Support\RuntimeSettings;
use GeoSot\EnvEditor\EnvEditor;
use GeoSot\EnvEditor\ServiceProvider as EnvEditorServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(EnvEditor::class, function ($app) {
            return new RuntimeEnvEditor(
                new Repository(config(EnvEditorServiceProvider::PACKAGE, [])),
                $app->make(Filesystem::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        RuntimeSettings::boot();

        if (env('FORCE_ROUTE_HTTPS') == 'true' || env('FORCE_HTTPS') == 'true') {
            URL::forceScheme('https');
        }
        Paginator::useBootstrap();
        Validator::extend('isunique', function ($attribute, $value, $parameters, $validator) {
            $value = strtolower($value);
            $query = DB::table($parameters[0])->whereRaw("LOWER({$attribute}) = ?", [$value]);

            if (isset($parameters[1])) {
                $query->where($parameters[1], '!=', $parameters[2]);
            }

            return $query->count() === 0;
        });
        Validator::extend('exturl', function ($attribute, $value, $parameters, $validator) {
            $allowed_schemes = ['http', 'https', 'mailto', 'tel'];
            return in_array(parse_url($value, PHP_URL_SCHEME), $allowed_schemes, true);
        });
        View::addNamespace('blocks', base_path('blocks'));
    }
}
