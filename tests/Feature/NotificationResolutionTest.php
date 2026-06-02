<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Splitstack\Nudge\Events\ActionExecuted;
use Splitstack\Nudge\Listeners\ResolveNotificationsOnActionQueued;
use Splitstack\Nudge\Tests\Fixtures\GitHubSetupReminder;
use Splitstack\Nudge\Tests\Fixtures\InstallGitHubApp;
use Splitstack\Nudge\Tests\Fixtures\User;

beforeEach(function () {
    $this->user = User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'password' => 'secret']);
});

it('resolves a notification when its action is executed with matching params', function () {
    $this->user->notify(
        (new GitHubSetupReminder)->forAction('github.install', ['user_id' => $this->user->id])
    );

    expect($this->user->pendingNotifications()->count())->toBe(1);

    (new InstallGitHubApp)->handle(['user_id' => $this->user->id, 'installation_id' => 88]);

    expect($this->user->pendingNotifications()->count())->toBe(0)
        ->and($this->user->resolvedNotifications()->count())->toBe(1);
});

it('does not resolve a notification when params do not match', function () {
    $this->user->notify(
        (new GitHubSetupReminder)->forAction('github.install', ['user_id' => $this->user->id])
    );

    (new InstallGitHubApp)->handle(['user_id' => 999, 'installation_id' => 88]);

    expect($this->user->pendingNotifications()->count())->toBe(1);
});

it('does not resolve a notification when the action key does not match', function () {
    $this->user->notify(
        (new GitHubSetupReminder)->forAction('github.install', ['user_id' => $this->user->id])
    );

    ActionExecuted::dispatch('some.other.action', ['user_id' => $this->user->id]);

    expect($this->user->pendingNotifications()->count())->toBe(1);
});

it('resolves via manual ActionExecuted dispatch as an escape hatch', function () {
    $this->user->notify(
        (new GitHubSetupReminder)->forAction('github.install', ['user_id' => $this->user->id])
    );

    ActionExecuted::dispatch('github.install', ['user_id' => $this->user->id]);

    expect($this->user->resolvedNotifications()->count())->toBe(1);
});

it('only resolves notifications whose params are a subset of the executed params', function () {
    $otherUser = User::create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'secret']);

    $this->user->notify(
        (new GitHubSetupReminder)->forAction('github.install', ['user_id' => $this->user->id])
    );
    $otherUser->notify(
        (new GitHubSetupReminder)->forAction('github.install', ['user_id' => $otherUser->id])
    );

    (new InstallGitHubApp)->handle(['user_id' => $this->user->id, 'installation_id' => 88]);

    expect($this->user->resolvedNotifications()->count())->toBe(1)
        ->and($otherUser->pendingNotifications()->count())->toBe(1);
});

it('resolves all pending notifications for the same action and params', function () {
    $this->user->notify(
        (new GitHubSetupReminder)->forAction('github.install', ['user_id' => $this->user->id])
    );
    $this->user->notify(
        (new GitHubSetupReminder)->forAction('github.install', ['user_id' => $this->user->id])
    );

    (new InstallGitHubApp)->handle(['user_id' => $this->user->id]);

    expect($this->user->resolvedNotifications()->count())->toBe(2);
});

it('does not affect already resolved notifications', function () {
    $this->user->notify(
        (new GitHubSetupReminder)->forAction('github.install', ['user_id' => $this->user->id])
    );

    (new InstallGitHubApp)->handle(['user_id' => $this->user->id]);
    $resolvedAt = $this->user->resolvedNotifications()->first()->resolved_at;

    (new InstallGitHubApp)->handle(['user_id' => $this->user->id]);

    expect($this->user->resolvedNotifications()->first()->resolved_at)->toEqual($resolvedAt);
});

it('does not resolve notifications without an action key', function () {
    $this->user->notify(new GitHubSetupReminder);

    (new InstallGitHubApp)->handle(['user_id' => $this->user->id]);

    $notification = $this->user->notifications()->first();

    expect($notification->resolved_at)->toBeNull();
});

it('queues resolution when the queued listener is used', function () {
    Queue::fake();
    Event::forget(ActionExecuted::class);
    Event::listen(ActionExecuted::class, ResolveNotificationsOnActionQueued::class);

    $this->user->notify(
        (new GitHubSetupReminder)->forAction('github.install', ['user_id' => $this->user->id])
    );

    ActionExecuted::dispatch('github.install', ['user_id' => $this->user->id]);

    Queue::assertPushed(\Illuminate\Events\CallQueuedListener::class);
    expect($this->user->pendingNotifications()->count())->toBe(1);
});
