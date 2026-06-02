# Laravel Nudge

![Tests](https://img.shields.io/github/actions/workflow/status/emilienkopp/laravel-nudge/tests.yml?label=tests)
![PHP Version](https://img.shields.io/badge/php-^8.2-blue.svg?style=flat-square)
![Laravel Version](https://img.shields.io/badge/laravel-^11.0-orange.svg?style=flat-square)
[![Total Downloads](https://img.shields.io/packagist/dt/splitstack/laravel-nudge.svg?style=flat-square)](https://packagist.org/packages/splitstack/laravel-nudge)

<p align="center">
  <img src="./art/Nudge-LOGO-round-sm.png" alt="laravel-nudge" width="200">
</p>

Give notifications a lifecycle. A notification declares the action it is waiting on; when that action runs anywhere in your application, the notification resolves itself — no manual wiring required.

→ [See it in action](./DEMO.md)

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
  └─ nudge(InstallGitHubApp::class, ['user_id' => 5, 'installation_id' => 88])
       └─ fires ActionExecuted("github.install", [...])
            └─ listener finds the notification, stamps resolved_at
```

The controller, the webhook handler, and the notification have no knowledge of each other.

## Actions

There are two ways to make an action class resolvable, depending on whether it already exists or is being written from scratch.

---

### New action classes — extend `NudgeAction`

If you are writing an action class from scratch, extend `NudgeAction`. Implement your logic in a `protected nudge()` method (convention) or in any method marked `#[Nudge]` (for a custom name). The `handle()` entry point, event dispatch, and resolution are all handled for you.

```php
use Splitstack\Nudge\NudgeAction;

class InstallGitHubApp extends NudgeAction
{
    public function actionKey(): string
    {
        return 'github.install';
    }

    protected function nudge(array $params): mixed
    {
        // your logic here — no event dispatch needed
    }
}
```

If you prefer a different method name, mark it with `#[Nudge]`:

```php
use Splitstack\Nudge\Attributes\Nudge;
use Splitstack\Nudge\NudgeAction;

class InstallGitHubApp extends NudgeAction
{
    public function actionKey(): string
    {
        return 'github.install';
    }

    #[Nudge]
    protected function install(array $params): mixed
    {
        // your logic here
    }
}
```

**Call sites** — pick whichever style fits your codebase:

```php
// Global helper
nudge(InstallGitHubApp::class, ['user_id' => $user->id]);

// Facade
use Splitstack\Nudge\Facades\Nudge;
Nudge::run(InstallGitHubApp::class, ['user_id' => $user->id]);
// or 
Nudge::run($myActionInstance, ['user_id' => $user->id]);

// Direct call of handle on the instance
$action->handle(['user_id' => $user->id]);
```

---

### Existing action classes — use the trait

If you have an action class that already exists and already has its own call sites, you do not need to rewrite call sites.
(You're free to do so if you want, of course. In that case refer to the "New action classes" section above)

Implement `ResolvableAction`, add the `DispatchesActionExecuted` trait, and drop `$this->nudge($params)` at the point in your method where the action completes. Your call site stays exactly as it was.

```php
use Splitstack\Nudge\Concerns\DispatchesActionExecuted;
use Splitstack\Nudge\Contracts\ResolvableAction;

class InstallGitHubApp implements ResolvableAction
{
    use DispatchesActionExecuted;

    public function actionKey(): string // Add this method to declare the action key that resolves notifications
    {
        return 'github.install';
    }

    public function doOrDoNotThereIsNoTry(array $params): mixed  // keep your existing method name
    {
        // your existing logic, untouched

        $this->nudge($params); // ← only addition; fires ActionExecuted when done

        return $result;
    }
}
```

```php
// call site — completely unchanged
(new InstallGitHubApp)->doOrDoNotThereIsNoTry(['user_id' => $user->id]);
```

---

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

// sending (no params):
$user->notify(new AppNotification('Connect your GitHub account.', 'github.install'));

// sending (with params):
$user->notify(
    (new AppNotification('Connect your GitHub account.', 'github.install'))
        ->withParams(['user_id' => $user->id])
);
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

## Upgrading from < 0.6.0

The old `execute()` entry point and the requirement to name your handler `handle()` have been removed in favour of the two-path API above. `execute()` still works but emits `E_USER_DEPRECATED` — follow the deprecation message to migrate at your own pace.

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
