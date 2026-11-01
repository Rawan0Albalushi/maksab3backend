<?php

namespace App\Mail;

use App\Models\DeliveryMan;
use App\Models\EmailTemplate;
use App\Mail\Concerns\BuildsTemplatedMail;
use App\Mail\Concerns\QueueableMailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Support\Notification\NotificationText;
use App\Services\System\BusinessSettingService;

class CollectCashMail extends Mailable implements ShouldQueue
{
    use BuildsTemplatedMail, Queueable, QueueableMailable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $wallet;

    protected $name;

    protected $deliveryMan;

    public function __construct($wallet, $deliveryMan)
    {
        $this->wallet = $wallet;
        $this->deliveryMan = $deliveryMan instanceof DeliveryMan ? $deliveryMan : null;
        $this->name = $deliveryMan instanceof DeliveryMan ? $deliveryMan->full_name : $deliveryMan;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $wallet = $this->wallet;
        $delivery_man_name = $this->name;

        return $this->templatedMail(
            template: EmailTemplate::where('type', 'dm')->where('email_type', 'cash_collect')->first(),
            fallbackTemplate: 6,
            subject: translate('Collect cash'),
            placeholders: [
                'delivery_man_name' => $delivery_man_name ?? '',
                'transaction_id' => $wallet->transaction_id ?? '',
            ],
            viewData: ['wallet' => $wallet, 'transaction_id' => $wallet->transaction_id, 'time' => $wallet->created_at, 'amount' => $wallet->amount],
            textFilter: fn ($value) => NotificationText::forDeliveryman($value, $this->deliveryMan),
        );
    }
}
