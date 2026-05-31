# Laravel Nudge


![Tests](https://img.shields.io/github/actions/workflow/status/emilienkopp/laravel-nudge/tests.yml?label=tests)
![PHP Version](https://img.shields.io/badge/php-^8.2-blue.svg?style=flat-square)
![Laravel Version](https://img.shields.io/badge/laravel-^11.0-orange.svg?style=flat-square)
[![Total Downloads](https://img.shields.io/packagist/dt/splitstack/laravel-nudge.svg?style=flat-square)](https://packagist.org/packages/splitstack/laravel-nudge)

<p align="center">
  <img src="./art/Nudge-LOGO-round-sm.png" alt="laravel-nudge" width="200">
</p>

Give notifications a lifecycle. A notification declares the action it is waiting on; when that action runs anywhere in your application, the notification resolves itself — no manual wiring required.

## Installation

```bash
composer require splitstack/laravel-nudge
```

Publish and run the migration:

```bash
php artisan vendor:publish --tag=nudge-migrations
php artisan migrate
```

The migration adds a `resolved_at` column to your `notifications` table. If the table does not exist yet, it creates it.

## Concept

A notification is not just a message — it is a pending state tied to an expected future action.

```
User is notified to install the GitHub App
  └─ notification stored with action_key = "github.install", user_id = 5

User installs the GitHub App (via webhook, OAuth callback, CLI — anywhere)
  └─ InstallGitHubApp::execute(['user_id' => 5, 'installation_id' => 88])
       └─ fires ActionExecuted("github.install", [...])
            └─ listener finds the notification, stamps resolved_at
```

The controller, the webhook handler, and the notification have no knowledge of each other.

## Actions

Implement `ResolvableAction` and use the `DispatchesActionExecuted` trait. Call `execute()` publicly; put your logic in `handle()`.

```php
use Splitstack\Nudge\Contracts\ResolvableAction;
use Splitstack\Nudge\Concerns\DispatchesActionExecuted;

class InstallGitHubApp implements ResolvableAction
{
    use DispatchesActionExecuted;

    public function actionKey(): string
    {
        return 'github.install';
    }

    protected function handle(array $params): void
    {
        // installation logic
    }
}
```

The `ActionExecuted` event is dispatched automatically after `handle()` returns. If `handle()` throws, the event is never fired.

You can also dispatch the event manually as an escape hatch — useful for actions you don't own:

```php
use Splitstack\Nudge\Events\ActionExecuted;

ActionExecuted::dispatch('github.install', ['user_id' => $user->id]);
```

## Notifications

Extend `ActionableNotification` and supply the action key either via `forAction()` at call site or by overriding `useActionKey()` on the class.

**Option A — `forAction()` at call site** (action key decided by the caller):

```php
$user->notify(
    (new GitHubSetupReminder)->forAction('github.install', ['user_id' => $user->id])
);
```

**Option B — `useActionKey()` on the class** (action key baked into the notification):

```php
class AppNotification extends ActionableNotification
{
    public function __construct(
        public readonly string $message,
        public readonly ?string $actionKey = null,
    ) {}

    protected function withData(object $notifiable): array
    {
        return ['message' => $this->message];
    }

    public function useActionKey(): ?string
    {
        return $this->actionKey;
    }
}

// sending:
$user->notify(new AppNotification('Connect your GitHub account.', 'github.install'));
```

`withData()` is optional — omit it if your notification needs no payload beyond the action metadata. If both `useActionKey()` and `forAction()` are used, `useActionKey()` takes precedence.

### Param matching

The stored params are matched as a **subset** of the executed params. This means:

```php
// stored:   ['user_id' => 5]
// executed: ['user_id' => 5, 'installation_id' => 88]
// → resolves ✓

// stored:   ['user_id' => 5]
// executed: ['user_id' => 9]
// → does not resolve ✗
```

Extra keys in the executed params are ignored. Store only the params that must match.

## Querying

Add `HasResolvableNotifications` to your notifiable model for convenience scopes:

```php
use Splitstack\Nudge\Models\Concerns\HasResolvableNotifications;

class User extends Authenticatable
{
    use HasResolvableNotifications;
}
```

```php
$user->pendingNotifications()->get();
$user->resolvedNotifications()->get();
```

Or query directly on `DatabaseNotification`:

```php
use Illuminate\Notifications\DatabaseNotification;

DatabaseNotification::whereNull('resolved_at')->get();
DatabaseNotification::whereNotNull('resolved_at')->get();
```

## Migrating an existing notification class

If you already have a notification with a `toDatabase()` method and you swap `extends Notification` for `extends ActionableNotification`, **do not keep the `toDatabase()` override**. `ActionableNotification::toDatabase()` is what injects `_action_key` and `_action_params` into the stored payload — overriding it silently strips that metadata and the notification will never resolve.

Replace `toDatabase()` with `withData()` instead:

```php
// before — toDatabase() override will break resolution
class MyNotification extends ActionableNotification
{
    public function toDatabase(object $notifiable): array
    {
        return ['message' => 'Do the thing.'];
    }
}

// after
class MyNotification extends ActionableNotification
{
    protected function withData(object $notifiable): array
    {
        return ['message' => 'Do the thing.'];
    }
}
```

`withData()` is merged into the final payload by the parent; your data and the action metadata both end up in the database.

## Caveats

### Avoid sending notifications inside controller methods that can be hit multiple times

Some middleware or frontend patterns cause controller methods to be invoked more than once per user interaction. A well-known case is [Inertia.js deferred props](https://inertiajs.com/deferred-props): a `HandleInertiaRequests` middleware that uses `Inertia::defer()` triggers a second request to the same route, calling the controller twice. If `$user->notify(...)` is in that controller, the same notification gets stored twice.

The safer placement is inside a dedicated action, a queued job, an observer, or a service — anywhere outside the HTTP layer that can be hit multiple times.

```php
// risky: controller may be called more than once
public function dashboard(Request $request): Response
{
    $request->user()->notify(new SomeReminder); // could fire twice
    return Inertia::render('Dashboard');
}

// safer: notification lives outside the repeatedly-hit method
public function dashboard(Request $request): Response
{
    SendOnboardingReminder::dispatchIf(
        ! $request->user()->hasCompletedOnboarding()
    );
    return Inertia::render('Dashboard');
}
```

## Requirements

- PHP 8.2+
- Laravel 11+
