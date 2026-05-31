<?php

declare(strict_types=1);

namespace Splitstack\Nudge\Tests\Fixtures;

use Splitstack\Nudge\Notifications\ActionableNotification;

class GitHubSetupReminder extends ActionableNotification
{
    protected function withData(object $notifiable): array
    {
        return ['message' => 'Connect your GitHub account.'];
    }
}
