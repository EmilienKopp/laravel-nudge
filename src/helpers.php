<?php

declare(strict_types=1);

use Splitstack\Nudge\NudgeRunner;

if (! function_exists('nudge')) {
    function nudge(string|object $action, array $params = []): mixed
    {
        return app(NudgeRunner::class)->run($action, $params);
    }
}
