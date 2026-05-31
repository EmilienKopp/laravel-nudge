<?php

declare(strict_types=1);

namespace Splitstack\Nudge;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Splitstack\Nudge\Events\ActionExecuted;
use Splitstack\Nudge\Listeners\ResolveNotificationsOnActionQueued;
use Splitstack\Nudge\Listeners\ResolveNotificationsOnActionSync;

class NudgeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'nudge-migrations');

            $this->publishes([
                __DIR__.'/../config/nudge.php' => config_path('nudge.php'),
            ], 'nudge-config');
        }

        $listener = config('nudge.queued_listeners', false) 
            ? ResolveNotificationsOnActionQueued::class 
            : ResolveNotificationsOnActionSync::class;

        Event::listen(ActionExecuted::class, $listener);

    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nudge.php', 'nudge');
    }
}
