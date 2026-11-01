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

class PlaceOrder extends Mailable implements ShouldQueue
{
    use BuildsTemplatedMail, Queueable, QueueableMailable, SerializesModels;

    protected $order_id;

    public function __construct($order_id)
    {
        $this->order_id = $order_id;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $order_id = $this->order_id;
        $order = Order::where('id', $order_id)->first();
        $url = route('order_invoice', ['id' => base64_encode($order_id)]);
        $user_name = $order?->customer?->f_name.' '.$order?->customer?->l_name;
        $store_name = $order?->store?->name;
        $delivery_man_name = $order->delivery_man?->f_name.' '.$order->delivery_man?->l_name;

        return $this->templatedMail(
            template: EmailTemplate::where('type', 'user')->where('email_type', 'new_order')->first(),
            fallbackTemplate: 3,
            subject: translate('Order placed'),
            placeholders: [
                'user_name' => $user_name ?? '',
                'store_name' => $store_name ?? '',
                'delivery_man_name' => $delivery_man_name ?? '',
                'order_id' => $order_id ?? '',
            ],
            viewData: ['order' => $order, 'url' => $url],
        );
    }
}
