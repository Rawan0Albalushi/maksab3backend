<?php

namespace Modules\AI\app\Http\Requests\Vendor\Product;

use App\Http\Requests\BaseRequest;

abstract class AutoFillRequest extends BaseRequest
{
    /**
     * Whose quota a generation counts against, and which one. Both used to come from the request:
     * a store_id named another store's quota (and leaving it out skipped the limit and the count
     * altogether), and requestType "image" made a text generation check the image limit while
     * counting nothing. The store is the token's; every request here is a text (section) one.
     */
    public function usageContext(): array
    {
        return [
            'store_id' => $this->tokenStoreId(),
            'request_type' => null,
        ];
    }

    protected function tokenStoreId(): mixed
    {
        return $this->input('vendor')?->stores[0]?->id;
    }
}
