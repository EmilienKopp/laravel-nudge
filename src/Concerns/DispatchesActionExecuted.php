<?php

declare(strict_types=1);

namespace Splitstack\Nudge\Concerns;

use Splitstack\Nudge\Attributes\Handles;
use Splitstack\Nudge\Contracts\ResolvableAction;
use Splitstack\Nudge\Events\ActionExecuted;

trait DispatchesActionExecuted
{
    public function nudge(array|\Illuminate\Contracts\Support\Arrayable $params = []): mixed
    {
        $method = $this->resolveHandlerMethod();
        $result = $this->$method($params);

        if ($this instanceof ResolvableAction) {
            ActionExecuted::dispatch($this->actionKey(), $params);
        }

        return $result;
    }

    private function resolveHandlerMethod(): string
    {
        foreach ((new \ReflectionClass($this))->getMethods() as $method) {
            if ($method->getAttributes(Handles::class)) {
                return $method->getName();
            }
        }

        throw new \RuntimeException(static::class . ' has no method marked with #[Handles]');
    }
}
