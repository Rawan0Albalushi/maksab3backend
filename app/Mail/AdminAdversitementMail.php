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

class AdminAdversitementMail extends Mailable implements ShouldQueue
{
    use BuildsTemplatedMail, Queueable, QueueableMailable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $store_name;

    protected $email_type;

    protected $add_id;

    public function __construct($store_name, $email_type, $add_id = null)
    {
        $this->store_name = $store_name;
        $this->email_type = $email_type;
        $this->add_id = $add_id;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        if ($this->email_type == 'new_advertisement') {
            $subject = translate('New advertisement request');
            $data = EmailTemplate::where('type', 'admin')->where('email_type', 'new_advertisement')->first();
        } else {
            $subject = translate('Advertisement update request');
            $data = EmailTemplate::where('type', 'admin')->where('email_type', 'update_advertisement')->first();
        }
        $store_name = $this->store_name;
        $add_id = $this->add_id;

        return $this->templatedMail(
            template: $data,
            fallbackTemplate: 2,
            subject: $subject,
            placeholders: [
                'store_name' => $store_name ?? '',
                'add_id' => $add_id,
            ],
        );
    }
}
