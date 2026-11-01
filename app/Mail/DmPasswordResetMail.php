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

class DmPasswordResetMail extends Mailable implements ShouldQueue
{
    use BuildsTemplatedMail, Queueable, QueueableMailable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $otp;

    protected $name;

    protected $deliveryMan;

    public function __construct($otp, $deliveryMan)
    {
        $this->otp = $otp;
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
        $code = $this->otp;
        $user_name = $this->name;

        return $this->templatedMail(
            template: EmailTemplate::where('type', 'dm')->where('email_type', 'forget_password')->first(),
            fallbackTemplate: 4,
            subject: translate('Password reset'),
            placeholders: [
                'user_name' => $user_name ?? '',
            ],
            viewData: ['code' => $code],
            textFilter: fn ($value) => NotificationText::forDeliveryman($value, $this->deliveryMan),
        );
    }
}
