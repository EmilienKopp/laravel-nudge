<?php

declare(strict_types=1);

namespace Splitstack\Nudge\Concerns;

use Splitstack\Nudge\Contracts\ResolvableAction;
use Splitstack\Nudge\Events\ActionExecuted;

trait DispatchesActionExecuted
{
    public function execute(array $params = []): mixed
    {
        $result = $this->handle($params);

        if ($this instanceof ResolvableAction) {
            ActionExecuted::dispatch($this->actionKey(), $params);
        }

        return $result;
    }

    abstract protected function handle(array $params): mixed;
}
