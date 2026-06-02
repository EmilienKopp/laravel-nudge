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

    $result = (new ConnectSlack)->handle(['user_id' => 1]);

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
        protected function doTheWork(array $params): string
        {
            return 'worked';
        }
    };

    $result = $action->handle(['user_id' => 1]);

    expect($result)->toBe('worked');
    Event::assertDispatched(ActionExecuted::class);
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
