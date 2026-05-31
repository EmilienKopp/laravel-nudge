<?php

declare(strict_types=1);

namespace Splitstack\Nudge\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasResolvableNotifications
{
    public function pendingNotifications(): MorphMany
    {
        return $this->notifications()->whereNull('resolved_at');
    }

    public function resolvedNotifications(): MorphMany
    {
        return $this->notifications()->whereNotNull('resolved_at');
    }
}
