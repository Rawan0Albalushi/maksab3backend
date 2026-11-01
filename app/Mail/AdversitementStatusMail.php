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

class AdversitementStatusMail extends Mailable implements ShouldQueue
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
        $store_name = $this->store_name;
        $add_id = $this->add_id;
        if ($this->email_type == 'advertisement_pause') {
            $subject = translate('Your advertisement has been paused');
        } elseif ($this->email_type == 'advertisement_approved') {
            $subject = translate('Your advertisement is approved');
        } elseif ($this->email_type == 'advertisement_create') {
            $subject = translate('Your advertisement is created by admin');
        } elseif ($this->email_type == 'advertisement_deny') {
            $subject = translate('Your advertisement is denied');
        } elseif ($this->email_type == 'advertisement_resume') {
            $subject = translate('Your advertisement is resumed');
        } else {
            $subject = translate('Your adversement');
        }

        return $this->templatedMail(
            template: EmailTemplate::where('type', 'store')->where('email_type', $this->email_type)->first(),
            fallbackTemplate: 11,
            subject: $subject,
            placeholders: [
                'store_name' => $store_name ?? '',
                'add_id' => $add_id,
            ],
        );
    }
}
