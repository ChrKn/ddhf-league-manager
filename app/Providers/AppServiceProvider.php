<?php

namespace App\Providers;

use App\Support\SiteUrl;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Every view of the public site gets $url, which addresses a page in the language that
        // view is being rendered in. Views therefore never name a route, and a reader who
        // arrived in English stays in English by following any link on the page.
        ViewFacade::composer('site.*', function (View $view): void {
            $view->with('url', fn (string $name, mixed $parameters = []) => SiteUrl::to($name, $parameters));
        });
    }
}
