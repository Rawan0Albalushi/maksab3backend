<?php

namespace App\Builder\Support;

use App\CentralLogics\Helpers;
use Modules\Builder\Services\StorefrontContext;

class CardContext
{
    public static function default(): array
    {
        $context = app(StorefrontContext::class);

        return [
            'currency' => $context->getCurrencySymbol(),
        ];
    }

    private static function safeCurrency(): string
    {
        return app(StorefrontContext::class)->getCurrencySymbol();
    }
}
