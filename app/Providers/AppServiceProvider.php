<?php

namespace App\Providers;

use App\Channels\ChannelManager;
use App\Models\PersonalAccessToken;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChannelManager::class, fn () => new ChannelManager(config('channels.drivers')));
    }

    public function boot(): void
    {
        
    RateLimiter::for('api', function ($request) {
        return Limit::perMinute(60)->by(
            $request->user()?->id ?: $request->ip()
        );
    });
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
