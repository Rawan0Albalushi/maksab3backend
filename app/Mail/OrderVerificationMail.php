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

class OrderVerificationMail extends Mailable implements ShouldQueue
{
    use BuildsTemplatedMail, Queueable, QueueableMailable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $otp;

    protected $name;

    public function __construct($otp, $name)
    {
        $this->otp = $otp;
        $this->name = $name;
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
            template: EmailTemplate::where('type', 'user')->where('email_type', 'order_verification')->first(),
            fallbackTemplate: 4,
            subject: translate('Order verification'),
            placeholders: [
                'user_name' => $user_name ?? '',
            ],
            viewData: ['code' => $code],
        );
    }
}
