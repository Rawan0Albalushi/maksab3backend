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

class CustomerRegistration extends Mailable implements ShouldQueue
{
    use BuildsTemplatedMail, Queueable, QueueableMailable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $name;

    protected $type;

    public function __construct($name, $type = false)
    {
        $this->name = $name;
        $this->type = $type;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $url = '';
        $user_name = $this->name;

        return $this->templatedMail(
            template: EmailTemplate::where('type', 'user')->where('email_type', 'registration')->first(),
            fallbackTemplate: 5,
            subject: translate('Customer registration'),
            placeholders: [
                'user_name' => $user_name ?? '',
            ],
            viewData: ['url' => $url],
        );
    }
}
