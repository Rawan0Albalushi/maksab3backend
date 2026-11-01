<?php

namespace App\Console\Commands;

use App\Traits\Payment\ProCustomerSubscriptionTrait;
use Illuminate\Console\Command;

class CustomerSubscriptionReminder extends Command
{
    use ProCustomerSubscriptionTrait;

    protected $signature   = 'customer-subscription:reminder';
    protected $description = 'Send notification to Pro customers whose subscription is nearing expiration';

    public function handle(): void
    {
        $this->expireDueSubscriptions();
        $this->sendCustomerSubscriptionExpireNotification();
        $this->info('Subscription expire reminder notifications sent successfully.');
    }
}
