<?php

namespace App\Providers;

use App\Listeners\HandleClientMessage;
use App\Models\Category;
use App\Models\DeliveryMan;
use App\Models\Item;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Store;
use App\Observers\CategoryObserver;
use App\Observers\Erp\DeliveryManObserver;
use App\Observers\Erp\RefundObserver;
use App\Observers\Erp\StoreObserver as ErpStoreObserver;
use App\Observers\ItemObserver;
use App\Observers\OrderObserver;
use App\Observers\StoreObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Laravel\Reverb\Events\MessageReceived;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        MessageReceived::class => [
            HandleClientMessage::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        Order::observe(OrderObserver::class);
        Item::observe(ItemObserver::class);
        Category::observe(CategoryObserver::class);
        Store::observe(StoreObserver::class);
        Store::observe(ErpStoreObserver::class);
        DeliveryMan::observe(DeliveryManObserver::class);
        Refund::observe(RefundObserver::class);
    }
}
