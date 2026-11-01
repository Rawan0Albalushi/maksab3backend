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

class SubscriptionCancel extends Mailable implements ShouldQueue
{
    use BuildsTemplatedMail, Queueable, QueueableMailable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $name;

    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $url = app(BusinessSettingService::class)->value('email_address', false) ?? '6am Mart';
        $store_name = $this->name;

        return $this->templatedMail(
            template: EmailTemplate::where('type', 'store')->where('email_type', 'subscription-cancel')->first(),
            fallbackTemplate: 5,
            subject: translate('Subscription canceled'),
            placeholders: [
                'store_name' => $store_name ?? '',
            ],
            viewData: ['url' => $url],
        );
    }
}
