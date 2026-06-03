<?php

declare(strict_types=1);

namespace Splitstack\Nudge\Listeners;

use Splitstack\Nudge\Events\ActionExecuted;
use Splitstack\Nudge\Events\NotificationsResolved;

readonly class ResolveNotificationsOnAction
{
    public function handle(ActionExecuted $event): void
    {
        $model = config('nudge.notification_model');
        $candidates = $model::query()
            ->where('data->_action_key', $event->actionKey)
            ->whereNull('resolved_at')
            ->get();

        $now = now();
        $resolvedNotificationIds = [];
        foreach ($candidates as $notification) {
            if ($this->paramsMatch($notification->data['_action_params'] ?? [], $event->params)) {
                $notification->update(['resolved_at' => $now]);
                $resolvedNotificationIds[] = $notification->id;
            }
        }

        // Dispatch a broadcastable event for any real-time UI updates if necessary
        if (config('nudge.broadcast_notifications') && $resolvedNotificationIds !== []) {
            event(new NotificationsResolved($resolvedNotificationIds));
        }
    }

    private function paramsMatch(iterable $stored, iterable $executed, bool $deep = false): bool
    {
        if (! $this->isValidCandidate($stored, $executed)) {
            return false;
        }

        $executed = $executed instanceof \Illuminate\Contracts\Support\Arrayable ? $executed->toArray() : (array) $executed;
        $stored   = $stored   instanceof \Illuminate\Contracts\Support\Arrayable ? $stored->toArray()   : (array) $stored;
        $deep     = $deep || config('nudge.match_params') === 'deep';

        foreach ($stored as $key => $value) {
            if (! array_key_exists($key, $executed)) {
                return false;
            }

            if ($deep && is_array($value) && is_array($executed[$key])) {
                if (! $this->paramsMatch($value, $executed[$key], deep: true)) {
                    return false;
                }
            } elseif ($executed[$key] !== $value) {
                return false;
            }
        }

        return true;
    }

    private function isValidCandidate(iterable $stored, iterable $executed): bool
    {
        $storedIsArrayOrArrayable = is_array($stored) || $stored instanceof \Illuminate\Contracts\Support\Arrayable;
        $executedIsArrayOrArrayable = is_array($executed) || $executed instanceof \Illuminate\Contracts\Support\Arrayable;
        if (! $storedIsArrayOrArrayable || ! $executedIsArrayOrArrayable) {
            return false;
        }

        return true;
    }
}
