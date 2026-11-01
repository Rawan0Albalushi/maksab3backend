<?php

namespace App\Observers\Erp;

use App\Jobs\Erp\SendErpWebhookJob;
use App\Models\ErpApiToken;
use App\Models\Store;

/**
 * Fires an ERP webhook for every active token that has a webhook_url
 * configured. Runs on Store::created so any code path that inserts a
 * store row (admin form, API, seeder) propagates to the ERP. Re-fires
 * on Store::updated when status flips to 1 — that's the admin-approval
 * transition for a previously-pending self-registered vendor, and the
 * ERP uses it to promote the existing CRM Lead to a CRM Customer.
 *
 * Payload mirrors the GET /erp/vendors/{id} response shape so the ERP
 * side can ingest a webhook with the same code path it uses for polled
 * data — relations are eager-loaded to match the read controller.
 */
class StoreObserver
{
    public function created(Store $store): void
    {
        $this->dispatchVendorCreated($store);
    }

    /**
     * Self-registered vendors land with status=0. When admin approves them
     * (VendorController::updateVendorApplication), status flips 0→1 and we
     * re-fire vendor.created so the ERP can convert the Lead to a Customer.
     * Other Store updates (cache touches, image saves, slug fill) don't
     * pass the wasChanged('status') gate and are correctly ignored.
     */
    public function updated(Store $store): void
    {
        if (! $store->wasChanged('status')) {
            return;
        }
        if ((int) $store->status !== 1) {
            return;
        }
        $this->dispatchVendorCreated($store);
    }

    protected function dispatchVendorCreated(Store $store): void
    {
        $tokens = ErpApiToken::query()->withWebhook()->get();
        if ($tokens->isEmpty()) {
            return;
        }

        // Pull DB-default values back into the model. Admin\VendorController::store()
        // doesn't set status explicitly and relies on the stores.status DB default
        // (=1) — without refresh the in-memory model has status=null and the ERP
        // would route the payload to a CRM Lead instead of a CRM Customer.
        $store->refresh();
        $store->loadMissing([
            'module:id,module_name',
            'zone:id,name',
            'vendor:id,f_name,l_name,email,phone,status',
        ]);
        $payload = $store->toArray();

        foreach ($tokens as $token) {
            SendErpWebhookJob::dispatch($token->id, 'vendor.created', $payload);
        }
    }
}
