<?php

namespace App\Providers;

use App\Services\Ai\AiClient;
use App\Services\Ai\NullAiClient;
use App\Services\Ai\OpenAiClient;
use App\Services\Ai\StubAiClient;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AiClient::class, function ($app): AiClient {
            return match ($app['config']->get('ai.provider')) {
                'stub' => new StubAiClient,
                'openai' => new OpenAiClient,
                default => new NullAiClient,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiters();
    }

    protected function configureRateLimiters(): void
    {
        RateLimiter::for('tutor', function (Request $request) {
            return Limit::perMinutes(10, (int) config('ai.tutor_messages_per_10_minutes'))
                ->by('tutor:'.$request->user()?->id);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
