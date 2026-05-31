<?php

declare(strict_types=1);

namespace Splitstack\Nudge\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class NotificationsResolved implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(
        public array $notificationIds,
    ) {}

    public function broadcastAs(): string
    {
        return 'notifications.resolved';
    }

    public function broadcastOn(): array
    {
        return [new Channel(config('nudge.broadcast_channel', 'nudge-notifications'))];
    }
}
