<?php

declare(strict_types=1);

namespace Splitstack\Nudge\Listeners;

use Splitstack\Nudge\Events\ActionExecuted;

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
        foreach ($candidates as $notification) {
            if ($this->paramsMatch($notification->data['_action_params'] ?? [], $event->params)) {
                $notification->update(['resolved_at' => $now]);
            }
        }

        // Dispatch a broadcastable event for any real-time UI updates if necessary
        if (config('nudge.broadcast_notifications')) {
            event(new \Splitstack\Nudge\Events\NotificationsResolved($candidates->pluck('id')->toArray()));
        }
    }

    private function paramsMatch(iterable $stored, iterable $executed): bool
    {
        $validCandidate = $this->isValidCandidate($stored, $executed);

        if (! $validCandidate) {
            return false;
        }

        $executed = $executed instanceof \Illuminate\Contracts\Support\Arrayable ? $executed->toArray() : (array) $executed;
        $stored = $stored instanceof \Illuminate\Contracts\Support\Arrayable ? $stored->toArray() : (array) $stored;

        foreach ($stored as $key => $value) {
            if (! array_key_exists($key, $executed) || $executed[$key] !== $value) {
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
