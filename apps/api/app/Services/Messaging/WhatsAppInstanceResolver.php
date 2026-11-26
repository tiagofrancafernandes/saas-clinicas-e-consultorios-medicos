<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\Tenant\WhatsAppCategory;
use App\Models\Tenant\WhatsAppInstance;

final class WhatsAppInstanceResolver
{
    public function resolveForCategory(WhatsAppCategory $category): ?WhatsAppInstance
    {
        $categoryMatch = WhatsAppInstance::query()
            ->where('category', $category->value)
            ->where('active', true)
            ->first();

        if ($categoryMatch !== null) {
            return $categoryMatch;
        }

        return $this->resolveDefaultActive();
    }

    public function resolveDefaultActive(): ?WhatsAppInstance
    {
        return WhatsAppInstance::query()
            ->where('is_default', true)
            ->where('active', true)
            ->first();
    }
}
