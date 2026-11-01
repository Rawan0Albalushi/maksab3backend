<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\Order;
use App\Mail\Concerns\BuildsTemplatedMail;
use App\Mail\Concerns\QueueableMailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Support\Notification\NotificationText;
use App\Services\System\BusinessSettingService;

class RefundedOrderMail extends Mailable implements ShouldQueue
{
    use BuildsTemplatedMail, Queueable, QueueableMailable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $o_id;

    public function __construct($o_id)
    {
        $this->o_id = $o_id;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $order = Order::where('id', $this->o_id)->first();

        return $this->templatedMail(
            template: EmailTemplate::where('type', 'user')->where('email_type', 'refund_order')->first(),
            fallbackTemplate: 9,
            subject: translate('Order refunded'),
            placeholders: [
                'user_name' => $order->customer->f_name.' '.$order->customer->l_name,
                'store_name' => $order->store->name,
                'delivery_man_name' => $order->delivery_man?->f_name.' '.$order->delivery_man?->l_name,
                'order_id' => $this->o_id,
            ],
            viewData: ['order' => $order],
        );
    }
}
