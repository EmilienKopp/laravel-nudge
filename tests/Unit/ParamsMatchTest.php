<?php

declare(strict_types=1);

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
