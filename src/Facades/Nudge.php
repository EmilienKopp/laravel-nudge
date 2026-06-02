<?php

declare(strict_types=1);

namespace Splitstack\Nudge\Facades;

use Splitstack\Nudge\NudgeRunner;
use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed run(string|object $action, array $params = [])
 */
class Nudge extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return NudgeRunner::class;
    }
}
