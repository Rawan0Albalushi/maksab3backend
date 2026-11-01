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

class SubscriptionSuccessful extends Mailable implements ShouldQueue
{
    use BuildsTemplatedMail, Queueable, QueueableMailable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $name;

    protected $url;

    public function __construct($name, $url)
    {
        $this->name = $name;
        $this->url = $url;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $url = $this->url;
        $store_name = $this->name;

        return $this->templatedMail(
            template: EmailTemplate::where('type', 'store')->where('email_type', 'subscription-successful')->first(),
            fallbackTemplate: 5,
            subject: translate('Subscription successful'),
            placeholders: [
                'store_name' => $store_name ?? '',
            ],
            viewData: ['url' => $url, 'type' => 'invoice'],
        );
    }
}
