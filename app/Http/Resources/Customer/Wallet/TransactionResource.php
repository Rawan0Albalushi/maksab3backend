<?php

namespace App\Http\Resources\Customer\Wallet;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class TransactionResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'credit' => (float) $this->resource->credit,
            'debit' => (float) $this->resource->debit,
            'admin_bonus' => (float) $this->resource->admin_bonus,
            'transaction_type' => $this->resource->transaction_type,
            'reference' => $this->resource->reference,
            'created_at' => $this->resource->created_at,
        ]);
    }
}
