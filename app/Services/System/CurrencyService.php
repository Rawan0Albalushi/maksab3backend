<?php

namespace App\Services\System;

use App\Models\Currency;
use App\Services\BaseService;
use App\Support\Cache\ApiCache;
use Illuminate\Support\Facades\Config;

class CurrencyService extends BaseService
{
    public function code(): mixed
    {
        if (! config('currency')) {
            $currency = app(BusinessSettingService::class)->value('currency');
            Config::set('currency', $currency);
        } else {
            $currency = config('currency');
        }

        return $currency;
    }

    public function format(mixed $value): string
    {
        if (! config('currency_symbol_position')) {
            $position = app(BusinessSettingService::class)->value('currency_symbol_position');
            Config::set('currency_symbol_position', $position);
        } else {
            $position = config('currency_symbol_position');
        }

        $amount = number_format((float) $value, config('round_up_to_digit'));
        $symbol = $this->symbol();

        return $position == 'right' ? $amount.' '.$symbol : $symbol.' '.$amount;
    }

    public function symbol(): mixed
    {
        if (config('currency_symbol')) {
            return config('currency_symbol');
        }

        $symbol = Currency::where(['currency_code' => $this->code()])->first()?->currency_symbol;
        Config::set('currency_symbol', $symbol);

        return $symbol;
    }

    public function findConfiguredSymbol(): mixed
    {
        return ApiCache::remember('business_settings', 'currency_symbol', function () {
            return Currency::where(['currency_code' => $this->code()])->first()->currency_symbol;
        });
    }
}
