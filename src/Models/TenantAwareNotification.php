<?php

declare(strict_types=1);

namespace Splitstack\Nudge\Models;

use Illuminate\Notifications\DatabaseNotification;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;
use Splitstack\Nudge\Models\Concerns\IsResolvable;


class TenantAwareNotification extends DatabaseNotification
{
    use UsesTenantConnection, IsResolvable;
    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];
}
