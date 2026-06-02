<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Splitstack\Nudge\Attributes\Nudge;
use Splitstack\Nudge\Events\ActionExecuted;
use Splitstack\Nudge\Facades\Nudge as NudgeFacade;
use Splitstack\Nudge\NudgeAction;
use Splitstack\Nudge\Tests\Fixtures\ConnectSlack;
use Splitstack\Nudge\Tests\TestCase;

uses(TestCase::class);

it('calls nudge() convention and dispatches ActionExecuted via handle()', function () {
    Event::fake();

    $result = (new ConnectSlack)->handle(user_id: 1);

    expect($result)->toBe('connected');
    Event::assertDispatched(ActionExecuted::class, fn ($e) =>
        $e->actionKey === 'slack.connect' && $e->params === ['user_id' => 1]
    );
});

it('resolves implementation from #[Nudge] attribute', function () {
    Event::fake();

    $action = new class extends NudgeAction {
        public function actionKey(): string { return 'test.action'; }

        #[Nudge]
        protected function doTheWork(mixed ...$params): string
        {
            return 'worked';
        }
    };

    $result = $action->handle(user_id: 1);

    expect($result)->toBe('worked');
    Event::assertDispatched(ActionExecuted::class);
});

it('remaps positional args to named params when handle() is called positionally', function () {
    Event::fake();

    $action = new class extends NudgeAction {
        public function actionKey(): string { return 'test.action'; }

        protected function nudge(string $action, int $installation_id): string
        {
            return "$action:$installation_id";
        }
    };

    $result = $action->handle('install', 88);

    expect($result)->toBe('install:88');
    Event::assertDispatched(ActionExecuted::class, fn ($e) =>
        $e->params === ['action' => 'install', 'installation_id' => 88]
    );
});

it('does not remap when handle() is called with named args', function () {
    Event::fake();

    $action = new class extends NudgeAction {
        public function actionKey(): string { return 'test.action'; }

        protected function nudge(string $action, int $installation_id): string
        {
            return "$action:$installation_id";
        }
    };

    $action->handle(action: 'install', installation_id: 88);

    Event::assertDispatched(ActionExecuted::class, fn ($e) =>
        $e->params === ['action' => 'install', 'installation_id' => 88]
    );
});

it('skips remap when implementation is fully variadic', function () {
    Event::fake();

    $action = new class extends NudgeAction {
        public function actionKey(): string { return 'test.action'; }

        protected function nudge(mixed ...$params): mixed
        {
            return $params;
        }
    };

    $result = $action->handle('a', 'b');

    // integer-keyed — no names to reflect on
    expect($result)->toBe([0 => 'a', 1 => 'b']);
    Event::assertDispatched(ActionExecuted::class, fn ($e) =>
        $e->params === [0 => 'a', 1 => 'b']
    );
});

it('throws when neither nudge() nor #[Nudge] method exists', function () {
    $action = new class extends NudgeAction {
        public function actionKey(): string { return 'test.action'; }
    };

    expect(fn () => $action->handle())->toThrow(\RuntimeException::class, 'must implement nudge()');
});

it('Nudge facade runs an action instance', function () {
    Event::fake();

    $result = NudgeFacade::run(new ConnectSlack, ['user_id' => 1]);

    expect($result)->toBe('connected');
    Event::assertDispatched(ActionExecuted::class);
});

it('Nudge facade resolves a class string from the container', function () {
    Event::fake();

    $result = NudgeFacade::run(ConnectSlack::class, ['user_id' => 1]);

    expect($result)->toBe('connected');
    Event::assertDispatched(ActionExecuted::class);
});
