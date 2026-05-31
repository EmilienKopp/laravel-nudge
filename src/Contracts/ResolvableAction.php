<?php

declare(strict_types=1);

namespace Splitstack\Nudge\Contracts;

interface ResolvableAction
{
    public function actionKey(): string;
}
