<?php

namespace App\Http\Resources\Common\Payment;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class WithdrawRequestResource extends BaseResource
{
    private const APPROVAL_LABELS = [0 => 'Pending', 1 => 'Approved', 2 => 'Denied'];

    public function toArray(Request $request): array
    {
        $withdraw = $this->resource;

        return array_merge(Arr::except($withdraw->attributesToArray(), ['created_at', 'approved']), [
            'status' => self::APPROVAL_LABELS[$withdraw->approved] ?? null,
            'requested_at' => $withdraw->created_at->format('Y-m-d H:i:s'),
            'bank_name' => $this->bankName($withdraw),
            'detail' => is_array($withdraw->withdrawal_method_fields)
                ? $withdraw->withdrawal_method_fields
                : json_decode((string) $withdraw->withdrawal_method_fields, true),
        ], $withdraw->relationsToArray());
    }

    private function bankName(mixed $withdraw): string
    {
        $method = $withdraw->type === 'disbursement' ? $withdraw->disbursementMethod : $withdraw->method;

        return $method?->method_name ?? translate('Account');
    }
}
