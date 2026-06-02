<?php

declare(strict_types=1);

namespace Splitstack\Nudge\Concerns;

use Splitstack\Nudge\Contracts\ResolvableAction;
use Splitstack\Nudge\Events\ActionExecuted;

trait DispatchesActionExecuted
{
    protected function nudge(mixed ...$params): void
    {
        if ($this instanceof ResolvableAction) {
            ActionExecuted::dispatch($this->actionKey(), $params);
        }
    }

    /** @deprecated Add $this->nudge(...$params) inside your handler and call it directly. */
    public function execute(array|\Illuminate\Contracts\Support\Arrayable $params = []): mixed
    {
        trigger_error(
            static::class . '::execute() is deprecated. Call $this->nudge(...$params) from within your handler method and invoke it directly.',
            E_USER_DEPRECATED
        );

        if (! method_exists($this, 'handle')) {
            throw new \RuntimeException(
                static::class . ' has no handle() method. Add $this->nudge(...$params) to your handler and call it directly.'
            );
        }

        $result = $this->handle($params);

        if ($this instanceof ResolvableAction) {
            ActionExecuted::dispatch($this->actionKey(), (array) $params);
        }

        return $result;
    }
}
