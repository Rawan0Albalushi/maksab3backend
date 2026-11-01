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

class CustomerRegistrationPOS extends Mailable implements ShouldQueue
{
    use BuildsTemplatedMail, Queueable, QueueableMailable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $name;

    protected $email;

    protected $password;

    public function __construct($name, $email, $password)
    {
        $this->name = $name;
        $this->password = $password;
        $this->email = $email;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $user_name = $this->name;
        $email = $this->email;
        $password = $this->password;
        $body_2 = NotificationText::format(value: $data['body_2'] ?? '', user_name: $user_name ?? '');

        return $this->templatedMail(
            template: EmailTemplate::where('type', 'user')->where('email_type', 'pos_registration')->first(),
            fallbackTemplate: 10,
            subject: translate('User registration mail'),
            placeholders: [
                'user_name' => $user_name ?? '',
            ],
            viewData: ['body_2' => $body_2, 'email' => $email, 'password' => $password],
        );
    }
}
