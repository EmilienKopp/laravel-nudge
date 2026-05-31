<?php

declare(strict_types=1);

namespace Splitstack\Nudge\Events;

use Illuminate\Foundation\Events\Dispatchable;

final readonly class ActionExecuted
{
    use Dispatchable;

    public function __construct(
        public string $actionKey,
        public array|\Illuminate\Contracts\Support\Arrayable $params,
    ) {}
}
