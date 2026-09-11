<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\SettingService;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View as ViewInstance;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureDates();
        $this->configureModels();
        $this->configurePasswords();
        $this->configureUrls();
        $this->configureRateLimiters();
        $this->configureViews();
    }

    /**
     * Data the public shell needs on every page.
     *
     * The mosque name and address sit in the navbar, the footer and the page
     * title of all four public pages, so binding them here saves each controller
     * from remembering to pass them — and from going out of step when one of
     * them forgets.
     *
     * Both the layout and the pages are listed, because `@extends` renders the
     * child template first to collect its sections: data bound only to the
     * layout would not exist yet when `@section('description', $mosqueName)` is
     * evaluated.
     */
    private function configureViews(): void
    {
        View::composer(['layouts.public', 'pages.portal.*'], static function (ViewInstance $view): void {
            $settings = app(SettingService::class);

            $view->with([
                'mosqueName' => $settings->mosqueName(),
                'mosqueAddress' => (string) $settings->get('mosque.address', ''),
            ]);
        });
    }

    /**
     * Login is throttled per email+IP so a password spray is slow; the device
     * API is throttled per token so one misbehaving player cannot drown out the
     * others.
     */
    private function configureRateLimiters(): void
    {
        RateLimiter::for('login', static fn (Request $request): Limit => Limit::perMinute(5)
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('device', static function (Request $request): Limit {
            $token = $request->bearerToken() ?? $request->header('X-Device-Token') ?? $request->ip();

            return Limit::perMinute((int) config('dkm.device.rate_limit'))->by((string) $token);
        });
    }

    /**
     * Immutable dates everywhere, so passing a Carbon into a service can never
     * mutate the caller's copy.
     */
    private function configureDates(): void
    {
        Date::use(CarbonImmutable::class);
    }

    /**
     * Outside production, throw when `fill()` is handed an attribute the model
     * does not declare as fillable — that is almost always a typo or an attempt
     * to mass-assign something that should not be mass-assignable.
     *
     * Lazy-load prevention is deliberately left off: several Blade partials are
     * rendered from collections built by different callers, and an N+1 warning
     * there would break a page rather than surface a performance note.
     */
    private function configureModels(): void
    {
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());
    }

    private function configurePasswords(): void
    {
        Password::defaults(static function (): Password {
            $rule = Password::min(10)->letters()->numbers();

            return app()->isProduction() ? $rule->mixedCase()->uncompromised() : $rule;
        });
    }

    private function configureUrls(): void
    {
        if (app()->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
