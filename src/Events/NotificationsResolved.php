<?php

declare(strict_types=1);

namespace Splitstack\Nudge\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class NotificationsResolved implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(
        public array $notificationIds,
    ) {}

    public function broadcastOn(): array
    {
        $channel = config('nudge.broadcast_channel', 'nudge-notifications');

        return [$channel];
    }
}
