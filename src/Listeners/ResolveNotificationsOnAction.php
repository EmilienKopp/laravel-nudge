<?php

declare(strict_types=1);

namespace Splitstack\Nudge\Listeners;

use Illuminate\Notifications\DatabaseNotification;
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
    }

    private function paramsMatch(array $stored, array $executed): bool
    {
        foreach ($stored as $key => $value) {
            if (! array_key_exists($key, $executed) || $executed[$key] != $value) {
                return false;
            }
        }

        return true;
    }
}
