<?php

declare(strict_types=1);

use Splitstack\Nudge\Models\Concerns\IsResolvable;

function makeResolvable(array $data = [], mixed $resolvedAt = null): object
{
    return new class($data, $resolvedAt) {
        use IsResolvable;

        public array $data;
        public mixed $resolved_at;

        public function __construct(array $data, mixed $resolvedAt)
        {
            $this->data = $data;
            $this->resolved_at = $resolvedAt;
        }
    };
}

it('returns action key from data', function () {
    $model = makeResolvable(['_action_key' => 'github.install']);

    expect($model->actionKey())->toBe('github.install');
});

it('returns null action key when absent', function () {
    expect(makeResolvable()->actionKey())->toBeNull();
});

it('returns action params from data', function () {
    $model = makeResolvable(['_action_params' => ['user_id' => 5]]);

    expect($model->actionParams())->toBe(['user_id' => 5]);
});

it('returns empty array for action params when absent', function () {
    expect(makeResolvable()->actionParams())->toBe([]);
});

it('returns target url from data', function () {
    $model = makeResolvable(['_target_url' => 'https://example.com/connect']);

    expect($model->targetUrl())->toBe('https://example.com/connect');
});

it('returns null target url when absent', function () {
    expect(makeResolvable()->targetUrl())->toBeNull();
});

it('reports as resolved when resolved_at is set', function () {
    expect(makeResolvable(resolvedAt: now())->isResolved())->toBeTrue();
});

it('reports as not resolved when resolved_at is null', function () {
    expect(makeResolvable()->isResolved())->toBeFalse();
});
