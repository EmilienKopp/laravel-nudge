# Observing Notifications — Design Sketch

A Laravel package that gives notifications a lifecycle. A notification can declare the action
it is waiting on; when that action is executed anywhere in the application, the notification
resolves itself automatically.

---

## Core Concept

A notification is not just a message — it is a **pending state** tied to an expected future
action. The notification stores a reference to *what needs to happen*. When it happens, the
notification moves from `pending` to `resolved` without any manual wiring.

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│  Notification created                                                           │
│    data: { title, message, ... }                                                │
│    action_key: "github.install"        ← stable string, not FQCN               │
│    action_params: { user_id: 5 }       ← the subset that must match            │
│    resolved_at: null                   ← separate from read_at                 │
└────────────────────────────────┬────────────────────────────────────────────────┘
                                 │
                    (user installs the GitHub App)
                                 │
┌────────────────────────────────▼────────────────────────────────────────────────┐
│  InstallGitHubApp::execute(['user_id' => 5, 'installation_id' => 88])          │
│    → dispatches ActionExecuted('github.install', ['user_id' => 5, ...])        │
└────────────────────────────────┬────────────────────────────────────────────────┘
                                 │
┌────────────────────────────────▼────────────────────────────────────────────────┐
│  ResolveNotificationsListener                                                   │
│    finds notifications where action_key = 'github.install'                     │
│      AND action_params ⊆ executed params  (subset match)                       │
│    sets resolved_at = now()                                                     │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Database

Two new nullable columns on the `notifications` table. They live alongside `data` — no
separate table needed.

```php
$table->string('action_key')->nullable()->index();
$table->jsonb('action_params')->nullable();
$table->timestamp('resolved_at')->nullable()->index();
```

`resolved_at` is intentionally separate from `read_at`:

| State           | read_at | resolved_at |
| --------------- | ------- | ----------- |
| Unread, pending | null    | null        |
| Read, pending   | set     | null        |
| Auto-resolved   | null    | set         |
| Read & resolved | set     | set         |

A notification can be resolved without the user ever opening it (e.g. they did the thing
another way), or read and dismissed without ever being resolved.

---

## The Action Side

### Interface

```php
interface ResolvableAction
{
    public function actionKey(): string;
}
```

### Trait (the mechanism)

```php
trait DispatchesActionExecuted
{
    public function execute(array $params = []): mixed
    {
        $result = $this->handle($params);

        if ($this instanceof ResolvableAction) {
            ActionExecuted::dispatch($this->actionKey(), $params);
        }

        return $result;
    }

    abstract protected function handle(array $params): mixed;
}
```

Actions call `execute()` publicly. `handle()` is the implementation. The event is dispatched
after `handle()` returns — never on failure (exception aborts before the dispatch).

Alternatively, if the codebase already has a use-case / action pattern with its own base
class, the trait can be applied there instead.

### Example action

```php
class InstallGitHubApp implements ResolvableAction
{
    use DispatchesActionExecuted;

    public function actionKey(): string
    {
        return 'github.install';
    }

    protected function handle(array $params): void
    {
        // ... do the installation work
    }
}
```

### ActionExecuted event

```php
final readonly class ActionExecuted
{
    use Dispatchable;

    public function __construct(
        public string $actionKey,
        public array $params,
    ) {}
}
```

---

## The Notification Side

### ActionableNotification

A notification class (or trait) that adds the action spec to the stored data.

```php
abstract class ActionableNotification extends Notification
{
    private ?string $pendingActionKey = null;
    private array $pendingActionParams = [];

    public function forAction(string $actionKey, array $params = []): static
    {
        $this->pendingActionKey = $actionKey;
        $this->pendingActionParams = $params;
        return $this;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            ...($this->toDatabasePayload($notifiable)),
            '_action_key'    => $this->pendingActionKey,
            '_action_params' => $this->pendingActionParams,
        ];
    }

    abstract protected function toDatabasePayload(object $notifiable): array;
}
```

Wait — storing action metadata inside `data` couples it to payload shape. Better to use a
custom channel that writes to the dedicated columns directly:

### ObservableChannel (preferred)

```php
class ObservableChannel
{
    public function send(mixed $notifiable, ActionableNotification $notification): void
    {
        $notifiable->notifications()->create([
            'id'            => Str::uuid(),
            'type'          => $notification::class,
            'data'          => $notification->toDatabasePayload($notifiable),
            'action_key'    => $notification->getActionKey(),
            'action_params' => $notification->getActionParams(),
        ]);
    }
}
```

Actions are registered in `via()` like any other channel:

```php
public function via(object $notifiable): array
{
    return [ObservableChannel::class];
}
```

---

## The Listener (the engine)

```php
final readonly class ResolveNotificationsOnAction
{
    public function handle(ActionExecuted $event): void
    {
        // Candidate notifications: same action key, unresolved
        $candidates = DatabaseNotification::query()
            ->where('action_key', $event->actionKey)
            ->whereNull('resolved_at')
            ->get();

        $now = now();

        foreach ($candidates as $notification) {
            if ($this->paramsMatch($notification->action_params, $event->params)) {
                $notification->update(['resolved_at' => $now]);
            }
        }
    }

    /**
     * All stored params must be present (and equal) in the executed params.
     * Executed params may carry extra keys — that's fine.
     */
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
```

Register:
```php
Event::listen(ActionExecuted::class, ResolveNotificationsOnAction::class);
```

---

## Usage — full example

```php
// Creating the notification (e.g. in CheckGitHubInstallationOnLogin listener)
$user->notify(
    (new GitHubSetupReminder())
        ->forAction('github.install', ['user_id' => $user->id])
);

// The action (called whenever GitHub installation completes — webhook, OAuth, CLI, anywhere)
app(InstallGitHubApp::class)->execute([
    'user_id'         => $user->id,
    'installation_id' => $installationId,
]);
// → fires ActionExecuted('github.install', ['user_id' => 1, 'installation_id' => 88])
// → listener finds the notification, sets resolved_at
```

The notification resolves without the controller, the webhook handler, or anyone else knowing
a notification existed.

---

## Scopes on the model

```php
// In an extended DatabaseNotification model:
public function scopePending(Builder $query): Builder
{
    return $query->whereNull('resolved_at');
}

public function scopeResolved(Builder $query): Builder
{
    return $query->whereNotNull('resolved_at');
}

public function isResolved(): bool
{
    return $this->resolved_at !== null;
}

public function resolve(): void
{
    $this->update(['resolved_at' => now()]);
}
```

---

## Open design questions

**1. Action key registry**
Should the package enforce a central registry of action keys (to catch typos at boot time),
or keep it convention-only? A registry would let the package validate that `'github.install'`
is actually bound to a class, but adds boilerplate.

**2. Queued resolution**
If there are many pending notifications per action key, resolving synchronously in the
listener could be slow. The listener could dispatch a queued job instead. Tradeoff: slight
delay before the notification shows as resolved in the UI.

**3. Param matching strictness**
Current design: subset match (stored ⊆ executed). Alternative: exact match, or a custom
matcher closure registered per action key. Subset is the most flexible default.

**4. Re-notification on resolution**
When a notification is auto-resolved, should the system fire another event (e.g.
`NotificationResolved`) that could send a "nice work!" push or broadcast update? Useful for
real-time UIs but optional.

**5. Action executed without going through `execute()`**
If the action logic is triggered by a webhook and someone calls `handle()` directly (bypassing
`execute()`), the event never fires. Mitigation: document that `ActionExecuted::dispatch()`
can always be called manually as an escape hatch, or make `handle()` protected and unfindable
from outside.

**6. Multi-notifiable**
Current design assumes one `action_params` set per notification row. If the same action
should resolve notifications for *multiple users*, each user needs their own notification row
— which is the normal pattern anyway.

---

## Package surface area

```
src/
  Contracts/
    ResolvableAction.php       interface
  Concerns/
    DispatchesActionExecuted.php  trait for action classes
  Events/
    ActionExecuted.php
  Listeners/
    ResolveNotificationsOnAction.php
  Channels/
    ObservableChannel.php
  Notifications/
    ActionableNotification.php   abstract base
  Models/
    Concerns/
      HasResolvableNotifications.php  trait for notifiable models (scopes, helpers)
  NudgeServiceProvider.php
```

Small. The listener + event + channel is the entire runtime surface. Everything else is
convenience.
