<?php

declare(strict_types=1);

namespace Splitstack\Nudge\Notifications;

use Illuminate\Notifications\Notification;

abstract class ActionableNotification extends Notification
{
    private ?string $pendingActionKey = null;

    private array $pendingActionParams = [];
    private ?string $pendingTargetUrl = null;

    public function forAction(string $actionKey, array $params = []): static
    {
        $this->pendingActionKey = $actionKey;
        $this->pendingActionParams = $params;

        return $this;
    }

    public function withParams(array $params): static
    {
        $this->pendingActionParams = $params;

        return $this;
    }

    public function targetUrl(string $url): static
    {
        $this->pendingTargetUrl = $url;

        return $this;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $actionKey = $this->useActionKey() ?? $this->pendingActionKey;

        return [
            ...$this->withData($notifiable),
            '_action_key' => $actionKey,
            '_action_params' => $this->pendingActionParams,
            '_target_url' => $this->pendingTargetUrl,
        ];
    }

    protected function withData(object $notifiable): array
    {
        return [];
    }

    protected function useActionKey(): ?string
    {
        return null;
    }
}
