<?php

declare(strict_types=1);

namespace Splitstack\Nudge\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Splitstack\Nudge\Models\Concerns\HasResolvableNotifications;

class User extends Authenticatable
{
    use Notifiable;
    use HasResolvableNotifications;

    protected $guarded = [];
}
