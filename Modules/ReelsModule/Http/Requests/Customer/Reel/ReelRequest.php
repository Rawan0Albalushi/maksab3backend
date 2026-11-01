<?php

namespace Modules\ReelsModule\Http\Requests\Customer\Reel;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;
use Modules\ReelsModule\Support\ReelModuleConfig;

abstract class ReelRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function filters(): array
    {
        return [
            'module_id' => $this->moduleFilter(),
            'customer_id' => $this->customerId(),
        ];
    }

    public function identity(): array
    {
        $customerId = $this->customerId();
        $guestId = $this->input('guest_id');

        return [$customerId, $customerId || ! is_scalar($guestId) ? null : (string) $guestId];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->moduleId() <= 0) {
                $validator->errors()->add('moduleId', translate('Module ID required'));
            }
        });
    }

    protected function moduleId(): int
    {
        return (int) data_get(config('module.current_module_data'), 'id', $this->header('moduleId'));
    }

    protected function moduleFilter(): ?int
    {
        return ReelModuleConfig::isMultiModule() ? $this->moduleId() : null;
    }

    protected function customerId(): ?int
    {
        return $this->user('api')?->id;
    }

    protected function guestRule(): string
    {
        return $this->user('api') ? 'nullable|string|max:255' : 'required|string|max:255';
    }
}
