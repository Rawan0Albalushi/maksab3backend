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

class StoreRegistration extends Mailable implements ShouldQueue
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
        $url = '';
        $store_name = $this->name;

        return $this->templatedMail(
            template: EmailTemplate::where('type', 'admin')->where('email_type', 'store_registration')->first(),
            fallbackTemplate: 1,
            subject: translate('Store registration'),
            placeholders: [
                'store_name' => $store_name ?? '',
            ],
            viewData: ['url' => $url],
        );
    }
}
