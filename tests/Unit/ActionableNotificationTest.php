<?php

declare(strict_types=1);

use Splitstack\Nudge\Notifications\ActionableNotification;

beforeEach(function () {
    $this->notification = new class extends ActionableNotification {};
    $this->notifiable = new stdClass();
});

it('returns database channel via via()', function () {
    expect($this->notification->via($this->notifiable))->toBe(['database']);
});

it('stores action key and params via forAction()', function () {
    $this->notification->forAction('github.install', ['user_id' => 5]);

    $data = $this->notification->toDatabase($this->notifiable);

    expect($data['_action_key'])->toBe('github.install')
        ->and($data['_action_params'])->toBe(['user_id' => 5]);
});

it('is chainable from forAction()', function () {
    $result = $this->notification->forAction('github.install');

    expect($result)->toBe($this->notification);
});

it('stores target url via targetUrl()', function () {
    $this->notification->targetUrl('https://example.com/connect');

    $data = $this->notification->toDatabase($this->notifiable);

    expect($data['_target_url'])->toBe('https://example.com/connect');
});

it('is chainable from targetUrl()', function () {
    $result = $this->notification->targetUrl('https://example.com');

    expect($result)->toBe($this->notification);
});

it('chains forAction and targetUrl together', function () {
    $this->notification
        ->forAction('github.install', ['user_id' => 5])
        ->targetUrl('https://example.com/connect');

    $data = $this->notification->toDatabase($this->notifiable);

    expect($data['_action_key'])->toBe('github.install')
        ->and($data['_action_params'])->toBe(['user_id' => 5])
        ->and($data['_target_url'])->toBe('https://example.com/connect');
});

it('defaults action key to null when forAction is not called', function () {
    $data = $this->notification->toDatabase($this->notifiable);

    expect($data['_action_key'])->toBeNull();
});

it('defaults action params to empty array when forAction is not called', function () {
    $data = $this->notification->toDatabase($this->notifiable);

    expect($data['_action_params'])->toBe([]);
});

it('defaults target url to null when targetUrl is not called', function () {
    $data = $this->notification->toDatabase($this->notifiable);

    expect($data['_target_url'])->toBeNull();
});

it('merges withData payload into toDatabase output', function () {
    $notification = new class extends ActionableNotification {
        protected function withData(object $notifiable): array
        {
            return ['message' => 'Connect your GitHub account.'];
        }
    };

    $data = $notification->toDatabase($this->notifiable);

    expect($data['message'])->toBe('Connect your GitHub account.');
});

it('action metadata overrides withData if keys collide', function () {
    $notification = new class extends ActionableNotification {
        protected function withData(object $notifiable): array
        {
            return ['_action_key' => 'should.be.overridden'];
        }
    };

    $notification->forAction('real.key');
    $data = $notification->toDatabase($this->notifiable);

    expect($data['_action_key'])->toBe('real.key');
});

it('defaults withData to empty array', function () {
    $data = $this->notification->toDatabase($this->notifiable);

    expect($data)->toHaveKeys(['_action_key', '_action_params', '_target_url']);
});
