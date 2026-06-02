<?php

declare(strict_types=1);

namespace Splitstack\Nudge\Tests\Fixtures;

use Splitstack\Nudge\Attributes\Handles;
use Splitstack\Nudge\Concerns\DispatchesActionExecuted;
use Splitstack\Nudge\Contracts\ResolvableAction;

class InstallGitHubApp implements ResolvableAction
{
    use DispatchesActionExecuted;

    public function actionKey(): string
    {
        return 'github.install';
    }

    #[Handles]
    protected function handle(array $params): mixed
    {
        return null;
    }
}
