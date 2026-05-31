<?php

declare(strict_types=1);

namespace Splitstack\Nudge\Models;

use Illuminate\Notifications\DatabaseNotification;
use Splitstack\Nudge\Models\Concerns\IsResolvable;


class Notification extends DatabaseNotification
{
    use IsResolvable;
    
    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];
}
