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

class UserStatus extends Mailable implements ShouldQueue
{
    use BuildsTemplatedMail, Queueable, QueueableMailable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $status;

    protected $name;

    public function __construct($status, $name)
    {
        $this->status = $status;
        $this->name = $name;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $status = $this->status;
        if ($status == 'suspended') {
            $data = EmailTemplate::where('type', 'user')->where('email_type', 'suspend')->first();
            $subject = translate('messages.Your account has been suspended');
        } else {
            $data = EmailTemplate::where('type', 'user')->where('email_type', 'unsuspend')->first();
            $subject = translate('Your account has been open again');
        }
        $url = '';
        $user_name = $this->name;

        return $this->templatedMail(
            template: $data,
            fallbackTemplate: 5,
            subject: $subject,
            placeholders: [
                'user_name' => $user_name ?? '',
            ],
            viewData: ['url' => $url],
        );
    }
}
