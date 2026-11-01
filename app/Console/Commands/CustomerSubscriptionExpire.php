<?php

namespace App\Console\Commands;

use App\Traits\Payment\ProCustomerSubscriptionTrait;
use Illuminate\Console\Command;

class CustomerSubscriptionExpire extends Command
{
    use ProCustomerSubscriptionTrait;

    protected $signature = 'customer-subscription:expire';

    protected $description = 'Expire Pro customer subscriptions whose end date has passed';

    public function handle(): void
    {
        $expired = $this->expireDueSubscriptions();

        $this->info("Pro customer subscription expiry sweep complete. {$expired} subscription(s) expired.");
    }
}
