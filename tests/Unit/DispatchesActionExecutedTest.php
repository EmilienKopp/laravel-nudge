<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Splitstack\Nudge\Tests\TestCase;

uses(TestCase::class);
use Splitstack\Nudge\Concerns\DispatchesActionExecuted;
use Splitstack\Nudge\Contracts\ResolvableAction;
use Splitstack\Nudge\Events\ActionExecuted;

it('dispatches ActionExecuted when nudge() is called on a ResolvableAction', function () {
    Event::fake();

    $action = new class implements ResolvableAction {
        use DispatchesActionExecuted;

        public function actionKey(): string { return 'test.action'; }

        public function vodkatonic(mixed ...$params): void
        {
            $this->nudge(...$params);
        }
    };

    $action->vodkatonic(user_id: 1);

    Event::assertDispatched(ActionExecuted::class, fn ($e) =>
        $e->actionKey === 'test.action' && $e->params === ['user_id' => 1]
    );
});

it('does not dispatch ActionExecuted when the class is not a ResolvableAction', function () {
    Event::fake();

    $action = new class {
        use DispatchesActionExecuted;

        public function run(array $params): void
        {
            $this->nudge($params);
        }
    };

    $action->run(['user_id' => 1]);

    Event::assertNotDispatched(ActionExecuted::class);
});

it('deprecated execute() calls handle() and dispatches the event', function () {
    Event::fake();

    $action = new class implements ResolvableAction {
        use DispatchesActionExecuted;

        public function actionKey(): string { return 'test.action'; }

        public function handle(array $params): string
        {
            return 'handled';
        }
    };

    $deprecations = [];
    set_error_handler(function (int $errno, string $msg) use (&$deprecations): bool {
        if ($errno === E_USER_DEPRECATED) $deprecations[] = $msg;
        return true;
    });
    $result = $action->execute(['user_id' => 1]); // @phpstan-ignore-line
    restore_error_handler();

    expect($result)->toBe('handled')
        ->and($deprecations)->toHaveCount(1)
        ->and($deprecations[0])->toContain('execute()');

    Event::assertDispatched(ActionExecuted::class);
});

it('nudge() defaults to empty params', function () {
    Event::fake();

    $action = new class implements ResolvableAction {
        use DispatchesActionExecuted;

        public function actionKey(): string { return 'test.action'; }

        public function run(): void
        {
            $this->nudge();
        }
    };

    $action->run();

    Event::assertDispatched(ActionExecuted::class, fn ($e) => $e->params === []);
});
