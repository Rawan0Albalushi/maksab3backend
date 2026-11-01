<?php

namespace App\Services\System;

use App\Services\BaseService;
use App\Traits\System\ActivationTrait;

class AddonActivationService extends BaseService
{
    use ActivationTrait;

    public function getCurrentDomain(): string
    {
        return str_replace(['http://', 'https://', 'www.'], '', url('/'));
    }

    public function activate(array $input): array
    {
        $response = $this->getRequestConfig(
            username: ($input['username'] ?? null),
            purchaseKey: ($input['purchase_key'] ?? null),
            softwareId: ($input['software_id'] ?? null) ?? SOFTWARE_ID,
            softwareType: ($input['software_type'] ?? null) ?? base64_decode('cHJvZHVjdA==')
        );

        $status = $response['active'] ?? 0;
        $message = $response['message'] ?? translate('Activation failed');

        $response['active'] = ($response['active'] == 1 && ($input['status'] ?? null) == 1) ? 1 : 0;

        $this->updateActivationConfig(app: ($input['addon_name'] ?? null), response: $response);

        if ((int) $status) {
            return [
                'status' => (int) $status,
                'activation_status' => 1,
                'username' => ($input['username'] ?? null),
                'purchase_code' => ($input['purchase_code'] ?? null),
            ];
        }

        return [
            'status' => (int) $status,
            'message' => $message,
        ];
    }
}
