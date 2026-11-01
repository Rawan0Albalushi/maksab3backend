<?php

namespace App\Console\Commands;

use App\Traits\Store\StoreSubscriptionValidityTrait;
use Illuminate\Console\Command;

class StoreSubscriptionValiditySweep extends Command
{
    use StoreSubscriptionValidityTrait;

    protected $signature = 'store-subscription:validity-sweep';

    protected $description = 'Unsubscribe stores whose subscription has expired and close out the expired subscription rows';

    public function handle(): void
    {
        $unsubscribed = $this->expireDueStoreSubscriptions();

        $this->info($unsubscribed > 0
            ? "Store subscription validity sweep complete. {$unsubscribed} store(s) unsubscribed."
            : 'Store subscription validity sweep already ran for today. Nothing to do.');
    }
}
