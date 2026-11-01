<?php

namespace App\Builder;

use App\CentralLogics\Helpers;
use Modules\Builder\Contracts\CapabilityProvider as CapabilityProviderContract;
use Modules\Builder\ValueObjects\HostCapabilities;
use Modules\Builder\ValueObjects\StorefrontScope;

class CapabilityProvider implements CapabilityProviderContract
{
    public function capabilities(?StorefrontScope $scope): HostCapabilities
    {
        $config = (array) config('builder.capabilities', []);

        $walletOn = (bool) config('builder.wallet_features_enabled', true);
        $config['features']['wallet'] = $walletOn;
        $config['payment']['wallet']  = $walletOn;
        $config['payment']['partial'] = $walletOn;

        $config['features']['guestCheckout'] = (bool) Helpers::get_business_settings('guest_checkout_status');


        return HostCapabilities::fromArray($config);
    }
}
