<?php

declare(strict_types=1);

namespace Splitstack\Nudge;

use Splitstack\Nudge\Attributes\Nudge;
use Splitstack\Nudge\Contracts\ResolvableAction;
use Splitstack\Nudge\Events\ActionExecuted;

abstract class NudgeAction implements ResolvableAction
{
    final public function handle(mixed ...$params): mixed
    {
        $method = $this->resolveImplementation();

        if (array_is_list($params) && ! empty($params)) {
            $params = $this->remapPositionalToNamed($params, $method);
        }

        $result = $this->$method(...$params);
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

    private function remapPositionalToNamed(array $params, string $method): array
    {
        $reflectionParams = (new \ReflectionMethod($this, $method))->getParameters();
        $named = array_values(array_filter($reflectionParams, fn ($p) => ! $p->isVariadic()));

        if (empty($named)) {
            return $params;
        }

        $remapped = [];
        foreach ($params as $i => $value) {
            $remapped[isset($named[$i]) ? $named[$i]->getName() : $i] = $value;
        }

        return $remapped;
    }
}
