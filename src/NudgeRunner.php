<?php

declare(strict_types=1);

namespace Splitstack\Nudge;

class NudgeRunner
{
    public function run(string|object $action, array $params = []): mixed
    {
        $instance = is_string($action) ? app($action) : $action;

        return $instance->handle($params);
    }
}
