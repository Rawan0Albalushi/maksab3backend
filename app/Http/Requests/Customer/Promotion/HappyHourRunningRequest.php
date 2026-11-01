<?php

namespace App\Http\Requests\Customer\Promotion;

use App\Http\Requests\BaseRequest;

/**
 * The home-screen banner poll. Takes no input -- zone and module both arrive as headers -- but
 * carries a class of its own because every endpoint has one.
 */
class HappyHourRunningRequest extends BaseRequest
{
    public function rules(): array
    {
        return [];
    }
}
