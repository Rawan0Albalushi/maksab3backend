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

class VendorSelfRegistration extends Mailable implements ShouldQueue
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
        if ($status == 'approved') {
            $data = EmailTemplate::where('type', 'store')->where('email_type', 'approve')->first();
        } elseif ($status == 'denied') {
            $data = EmailTemplate::where('type', 'store')->where('email_type', 'deny')->first();
        } else {
            $data = EmailTemplate::where('type', 'store')->where('email_type', 'registration')->first();
        }
        $url = '';
        $store_name = $this->name;

        return $this->templatedMail(
            template: $data,
            fallbackTemplate: 5,
            subject: translate('New store registration'),
            placeholders: [
                'store_name' => $store_name ?? '',
            ],
            viewData: ['url' => $url],
        );
    }
}
