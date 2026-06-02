<?php

declare(strict_types=1);

namespace Splitstack\Nudge;

use Splitstack\Nudge\Attributes\Nudge;
use Splitstack\Nudge\Contracts\ResolvableAction;
use Splitstack\Nudge\Events\ActionExecuted;

abstract class NudgeAction implements ResolvableAction
{
    final public function handle(array $params = []): mixed
    {
        $method = $this->resolveImplementation();
        $result = $this->$method($params);
        ActionExecuted::dispatch($this->actionKey(), $params);
        return $result;
    }

    private function resolveImplementation(): string
    {
        foreach ((new \ReflectionClass($this))->getMethods() as $method) {
            if ($method->getAttributes(Nudge::class)) {
                return $method->getName();
            }
        }

        if (method_exists($this, 'nudge')) {
            return 'nudge';
        }

        throw new \RuntimeException(
            static::class . ' must implement nudge() or mark a method with #[Nudge].'
        );
    }
}
