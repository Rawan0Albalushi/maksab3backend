<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Mail\Concerns\BuildsTemplatedMail;
use App\Mail\Concerns\QueueableMailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Support\Notification\NotificationText;
use App\Services\System\BusinessSettingService;

class WithdrawRequestMail extends Mailable implements ShouldQueue
{
    use BuildsTemplatedMail, Queueable, QueueableMailable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $wallet;

    protected $status;

    protected $mail_type;

    public function __construct($status, $wallet, $mail_type = 'store')
    {
        $this->wallet = $wallet;
        $this->status = $status;
        $this->mail_type = $mail_type;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $status = $this->status;
        if ($status == 'approved') {
            $data = EmailTemplate::where('type', $this->mail_type)->where('email_type', 'withdraw_approve')->first();
        } elseif ($status == 'denied') {
            $data = EmailTemplate::where('type', $this->mail_type)->where('email_type', 'withdraw_deny')->first();
        } else {
            $data = EmailTemplate::where('type', 'admin')->where('email_type', 'withdraw_request')->first();
        }
        $wallet = $this->wallet;
        $store_name = $wallet->vendor->f_name;
        $delivery_man_name = $wallet?->deliveryman?->f_name.' '.$wallet?->deliveryman?->l_name;
        $deliveryMan = $wallet?->deliveryman?->is_ride ? $wallet?->deliveryman : null;

        return $this->templatedMail(
            template: $data,
            fallbackTemplate: 6,
            subject: NotificationText::forDeliveryman(translate('Withdraw request'), $deliveryMan),
            placeholders: [
                'store_name' => $store_name ?? '',
                'delivery_man_name' => $delivery_man_name ?? '',
                'transaction_id' => $wallet->id ?? '',
            ],
            viewData: ['wallet' => $wallet, 'transaction_id' => $wallet->id, 'time' => $wallet->created_at, 'amount' => $wallet->amount],
            textFilter: fn ($value) => NotificationText::forDeliveryman($value, $deliveryMan),
        );
    }
}
