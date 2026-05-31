<?php

declare(strict_types=1);

namespace Splitstack\Nudge\Concerns;

use LogicException;
use Splitstack\Nudge\Contracts\ResolvableAction;
use Splitstack\Nudge\Events\ActionExecuted;

trait DispatchesActionExecuted
{
    public function execute(array|\Illuminate\Contracts\Support\Arrayable $params): mixed
    {
        // Guard against handle being undefined function
        if (! method_exists($this, 'handle')) {
            throw new LogicException('Classes using DispatchesActionExecuted must implement a handle method.');
        }

        $result = $this->handle($params);

        if ($this instanceof ResolvableAction) {
            ActionExecuted::dispatch($this->actionKey(), $params);
        }

        return $result;
    }
}
