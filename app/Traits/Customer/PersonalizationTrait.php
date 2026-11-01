<?php

namespace App\Traits\Customer;

trait PersonalizationTrait
{
    public function recordItemAction(int $userId, int $itemId, string $signal): void
    {
        $this->forwardToPersonalization('recordItemAction', [$userId, $itemId, $signal], reportFailures: true);
    }
    public function recordStoreAction(int $userId, int $storeId, string $signal): void
    {
        $this->forwardToPersonalization('recordStoreAction', [$userId, $storeId, $signal], reportFailures: true);
    }
    public function recordSearchAction(int $userId, string $keyword, ?int $moduleId): void
    {
        $this->forwardToPersonalization('recordSearchAction', [$userId, $keyword, $moduleId], reportFailures: true);
    }
    public function recordServiceAction(int $userId, int $serviceId, string $signal): void
    {
        $this->forwardToPersonalization('recordServiceAction', [$userId, $serviceId, $signal], reportFailures: true);
    }
    public function recordVehicleAction(int $userId, int $vehicleId, string $signal, ?int $moduleId): void
    {
        $this->forwardToPersonalization('recordVehicleAction', [$userId, $vehicleId, $signal, $moduleId], reportFailures: true);
    }
    public function applyItemPersonalization($query, ?int $userId, $filter = null)
    {
        return $this->forwardToPersonalization('applyItemPersonalization', [$query, $userId, $filter], $query);
    }
    public function applyStorePersonalization($query, ?int $userId, $filter = null)
    {
        return $this->forwardToPersonalization('applyStorePersonalization', [$query, $userId, $filter], $query);
    }
    public function applyCategoryPersonalization($query, ?int $userId)
    {
        return $this->forwardToPersonalization('applyCategoryPersonalization', [$query, $userId], $query);
    }
    public function applyCampaignPersonalization($query, ?int $userId)
    {
        return $this->forwardToPersonalization('applyCampaignPersonalization', [$query, $userId], $query);
    }
    public function reorderByPreference($collection, ?int $userId, string $matchField, string $preferenceType)
    {
        return $this->forwardToPersonalization('reorderByPreference', [$collection, $userId, $matchField, $preferenceType], $collection);
    }
    public function rebuildSummary(int $userId, ?int $moduleId): void
    {
        $this->forwardToPersonalization('rebuildSummary', [$userId, $moduleId]);
    }
    private function personalizationTarget(): ?string
    {
        if (! class_exists(\Modules\AI\app\Services\Personalization\PersonalizationService::class)) {
            return null;
        }
        if (! \Modules\AI\app\Core\AiModule::isPersonalizationActive()) {
            return null;
        }

        return \Modules\AI\app\Services\Personalization\PersonalizationService::class;
    }
    private function forwardToPersonalization(string $method, array $arguments, mixed $fallback = null, bool $reportFailures = false): mixed
    {
        $target = $this->personalizationTarget();

        if (! $target) {
            return $fallback;
        }

        if (! $reportFailures) {
            return $target::$method(...$arguments);
        }

        try {
            return $target::$method(...$arguments);
        } catch (\Throwable $exception) {
            report($exception);

            return $fallback;
        }
    }
}
