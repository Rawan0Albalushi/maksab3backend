<?php

namespace Modules\ReelsModule\Http\Requests\Customer\Reel;

use App\Models\Store;
use Modules\ReelsModule\Support\ReelModuleConfig;

class ReelListRequest extends ReelRequest
{
    public function rules(): array
    {
        return [
            'store_id' => 'nullable|integer|exists:stores,id',
            'limit' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'offset' => 'nullable|integer|min:1',
        ];
    }

    public function filters(): array
    {
        return array_merge(parent::filters(), [
            'store_id' => $this->filled('store_id') ? (int) $this->input('store_id') : null,
            'zone_header' => $this->header('zoneId'),
            'all_zone_service' => (bool) $this->currentModuleServesAllZones(),
        ]);
    }

    public function page(): int
    {
        return max(1, (int) ($this->input('offset') ?: $this->input('page') ?: 1));
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $isMultiModule = ReelModuleConfig::isMultiModule();

            if ($isMultiModule && $this->moduleId() <= 0) {
                $validator->errors()->add('moduleId', translate('Module ID required'));

                return;
            }

            if (! $this->filled('store_id')) {
                return;
            }

            $storeBelongsToModule = Store::query()
                ->where('id', (int) $this->input('store_id'))
                ->when($isMultiModule, fn ($query) => $query->where('module_id', $this->moduleId()))
                ->exists();

            if (! $storeBelongsToModule) {
                $validator->errors()->add('store_id', 'The selected store does not belong to the provided module.');
            }
        });
    }
}
