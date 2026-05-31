<?php

namespace Splitstack\Nudge\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait IsResolvable
{
    public function actionKey(): ?string
    {
        return $this->data['_action_key'] ?? null;
    }
    public function actionParams(): array
    {
        return $this->data['_action_params'] ?? [];
    }
    public function targetUrl(): ?string
    {
        return $this->data['_target_url'] ?? null;
    }
    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }
    public function resolve(): void
    {
        $this->update(['resolved_at' => now()]);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereNotNull('resolved_at');
    }
}