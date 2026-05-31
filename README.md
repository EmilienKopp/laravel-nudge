# Laravel Nudge


![Tests](https://img.shields.io/github/actions/workflow/status/emilienkopp/laravel-nudge/tests.yml?label=tests)
![PHP Version](https://img.shields.io/badge/php-^8.2-blue.svg?style=flat-square)
![Laravel Version](https://img.shields.io/badge/laravel-^11.0-orange.svg?style=flat-square)
[![Total Downloads](https://img.shields.io/packagist/dt/splitstack/laravel-nudge.svg?style=flat-square)](https://packagist.org/packages/splitstack/laravel-nudge)

<p align="center">
![laravel-nudge](./art/Nudge-LOGO-round-sm.png)
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

Extend `ActionableNotification` and call `forAction()` when sending:

```php
use Splitstack\Nudge\Notifications\ActionableNotification;

class GitHubSetupReminder extends ActionableNotification
{
    protected function withData(object $notifiable): array
    {
        return [
            'message' => 'Connect your GitHub account to continue.',
        ];
    }
}
```

```php
$user->notify(
    (new GitHubSetupReminder)->forAction('github.install', ['user_id' => $user->id])
);
```

`withData()` is optional — omit it if your notification needs no payload beyond the action metadata.

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

## Requirements

- PHP 8.2+
- Laravel 11+
