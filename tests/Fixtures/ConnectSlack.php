<?php

declare(strict_types=1);

namespace Splitstack\Nudge\Tests\Fixtures;

use Splitstack\Nudge\NudgeAction;

class ConnectSlack extends NudgeAction
{
    public function actionKey(): string
    {
        return 'slack.connect';
    }

    protected function nudge(mixed ...$params): mixed
    {
        return 'connected';
    }
}
