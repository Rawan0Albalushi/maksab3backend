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

class EmailVerification extends Mailable implements ShouldQueue
{
    use BuildsTemplatedMail, Queueable, QueueableMailable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $reset_url;

    protected $name;

    public function __construct($reset_url, $name)
    {
        $this->reset_url = $reset_url;
        $this->name = $name;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $code = $this->reset_url;
        $user_name = $this->name;

        return $this->templatedMail(
            template: EmailTemplate::where('type', 'user')->where('email_type', 'registration_otp')->first(),
            fallbackTemplate: 4,
            subject: translate('Email verification'),
            placeholders: [
                'user_name' => $user_name ?? '',
            ],
            viewData: ['code' => $code],
        );
    }
}
