<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            Route::middleware('api') // اجعلها تحت ميدل وير API
                ->prefix('api/admin') // أضف بادئة للـ admin
                ->group(base_path('routes/Admin.php'));


                Route::middleware('api') // اجعلها تحت ميدل وير API
                ->prefix('api/user')
                ->group(base_path('routes/User.php'));


                Route::middleware('api') // اجعلها تحت ميدل وير API
                ->prefix('api/product_provider')
                ->group(base_path('routes/product_Provider.php'));


                Route::middleware('api') // اجعلها تحت ميدل وير API
                ->prefix('api/service_provider')
                ->group(base_path('routes/Service_Provider.php'));



            Route::middleware('api') // اجعلها تحت ميدل وير API
                ->prefix('api/driver')
                ->group(base_path('routes/Driver.php'));
        });
    }
}
