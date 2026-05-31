# Demo

A notification is sent when a user clocks out, prompting them to log their activities. Once they log activities, the notification resolves itself — no polling, no manual wiring.

![Demo](./art/laravel-nudge-demo.gif)

---

## How it wires together

**1. Extend `ActionableNotification` and declare which action resolves it**

```php
use Splitstack\Nudge\Notifications\ActionableNotification;

final class ActivityReminderNotification extends ActionableNotification
{
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function withData(object $notifiable): array
    {
        return ['message' => 'You just clocked out. Log what you worked on!'];
    }

    public function useActionKey(): ?string
    {
        return $this->actionKey; // set via ->forAction(...)
    }
}
```

**2. Send the notification and bind it to an action key + payload**

```php
$user->notify(
    (new ActivityReminderNotification())
        ->forAction('activity_log.synced', ['daily_log_id' => $log->id])
);
```

**3. Implement `ResolvableAction` in the action that should dismiss the notification**

```php
use Splitstack\Nudge\Concerns\DispatchesActionExecuted;
use Splitstack\Nudge\Contracts\ResolvableAction;

final readonly class SyncActivities implements ResolvableAction
{
    use DispatchesActionExecuted;

    public function actionKey(): string
    {
        return 'activity_log.synced';
    }

    public function handle(SyncActivitiesDTO $dto): void
    {
        // ... sync logic
        // DispatchesActionExecuted fires ActionExecuted automatically after handle()
    }
}
```

**4. (Optional) Custom notification model — e.g. for multitenancy**

```php
use Illuminate\Notifications\DatabaseNotification;
use Splitstack\Nudge\Models\Concerns\IsResolvable;

final class TenantDatabaseNotification extends DatabaseNotification
{
    use IsResolvable, UsesTenantConnection;
}
```

```php
// config/nudge.php
'notification_model' => App\Models\TenantDatabaseNotification::class,
```

When `SyncActivities::handle()` runs for a given `daily_log_id`, Nudge automatically finds the matching pending notification and stamps `resolved_at` on it.

---

## Frontend: reacting to resolution in real time

Enable broadcasting in the config:

```php
// config/nudge.php
'broadcast_notifications' => true,
'broadcast_channel' => 'nudge-notifications',
```

Nudge broadcasts a `.nudge.resolved` event on that channel when a notification is resolved. On the client, subscribe once and reload only the notification data:

```ts
// Svelte example — any Echo-compatible client works the same way
$effect(() => {
    const channel = window.Echo?.channel('nudge-notifications');
    channel?.listen('.nudge.resolved', () => {
        router.reload({ only: ['notifications', 'unreadNotificationsCount'] });
    });

    return () => window.Echo?.leave('nudge-notifications');
});
```

No polling. The bell updates the moment the user completes the action.
