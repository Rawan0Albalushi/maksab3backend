<?php

namespace App\Observers\Erp;

use App\Jobs\Erp\SendErpWebhookJob;
use App\Models\ErpApiToken;
use App\Models\Refund;

class RefundObserver
{
    public function created(Refund $refund): void
    {
        $tokens = ErpApiToken::query()->withWebhook()->get();
        if ($tokens->isEmpty()) {
            return;
        }

        $refund->loadMissing([
            'order:id,user_id,store_id,order_amount,order_status',
            'order.customer:id,f_name,l_name,email,phone',
            'order.store:id,name',
        ]);
        $payload = $refund->toArray();

        foreach ($tokens as $token) {
            SendErpWebhookJob::dispatch($token->id, 'refund.created', $payload);
        }
    }
}
