<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Splitstack\Nudge\Listeners\ResolveNotificationsOnAction;

it('matches when stored params are a subset of executed params', function () {
    $listener = new ResolveNotificationsOnAction();
    $match = (fn ($s, $e) => $this->paramsMatch($s, $e))->bindTo($listener, $listener);

    expect($match(['user_id' => 5], ['user_id' => 5, 'installation_id' => 88]))->toBeTrue();
});

it('rejects when a stored param value differs', function () {
    $listener = new ResolveNotificationsOnAction();
    $match = (fn ($s, $e) => $this->paramsMatch($s, $e))->bindTo($listener, $listener);

    expect($match(['user_id' => 5], ['user_id' => 9]))->toBeFalse();
});

it('rejects when a stored param key is missing from executed params', function () {
    $listener = new ResolveNotificationsOnAction();
    $match = (fn ($s, $e) => $this->paramsMatch($s, $e))->bindTo($listener, $listener);

    expect($match(['user_id' => 5], ['installation_id' => 88]))->toBeFalse();
});

it('matches when stored params are empty', function () {
    $listener = new ResolveNotificationsOnAction();
    $match = (fn ($s, $e) => $this->paramsMatch($s, $e))->bindTo($listener, $listener);

    expect($match([], ['user_id' => 5]))->toBeTrue();
});

it('rejects when stored params are non-empty but executed params are empty', function () {
    $listener = new ResolveNotificationsOnAction();
    $match = (fn ($s, $e) => $this->paramsMatch($s, $e))->bindTo($listener, $listener);

    expect($match(['user_id' => 5], []))->toBeFalse();
});

it('rejects when executed params are integer-keyed (positional variadic call)', function () {
    $listener = new ResolveNotificationsOnAction();
    $match = (fn ($s, $e) => $this->paramsMatch($s, $e))->bindTo($listener, $listener);

    expect($match(['user_id' => 5], [0 => 'some-action', 1 => ['id' => 5], 2 => []]))->toBeFalse();
});

it('rejects on strict type mismatch between stored and executed param values', function () {
    $listener = new ResolveNotificationsOnAction();
    $match = (fn ($s, $e) => $this->paramsMatch($s, $e))->bindTo($listener, $listener);

    expect($match(['user_id' => '5'], ['user_id' => 5]))->toBeFalse();
});

it('matches when executed params is an Arrayable', function () {
    $listener = new ResolveNotificationsOnAction();
    $match = (fn ($s, $e) => $this->paramsMatch($s, $e))->bindTo($listener, $listener);

    $executed = Collection::make(['user_id' => 5, 'installation_id' => 88]);

    expect($match(['user_id' => 5], $executed))->toBeTrue();
});

it('matches when stored params is an Arrayable', function () {
    $listener = new ResolveNotificationsOnAction();
    $match = (fn ($s, $e) => $this->paramsMatch($s, $e))->bindTo($listener, $listener);

    $stored = Collection::make(['user_id' => 5]);

    expect($match($stored, ['user_id' => 5, 'installation_id' => 88]))->toBeTrue();
});
