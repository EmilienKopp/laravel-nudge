<?php

declare(strict_types=1);

namespace Splitstack\Nudge\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;

final readonly class ResolveNotificationsOnActionQueued extends ResolveNotificationsOnAction implements ShouldQueue
{
}
