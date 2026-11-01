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

class VendorCampaignRequestMail extends Mailable implements ShouldQueue
{
    use BuildsTemplatedMail, Queueable, QueueableMailable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $name;

    protected $status;

    public function __construct($name, $status)
    {
        $this->name = $name;
        $this->status = $status;
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
        } elseif ($status == 'denied') {
            $data = EmailTemplate::where('type', 'store')->where('email_type', 'campaign_deny')->first();
            $template = $data ? $data->email_template : 7;
        } elseif ($status == 'pending') {
            $data = EmailTemplate::where('type', 'store')->where('email_type', 'campaign_request')->first();
            $template = $data ? $data->email_template : 7;
        }
        $store_name = $this->name;

        return $this->templatedMail(
            template: EmailTemplate::where('type', 'store')->where('email_type', 'campaign_approve')->first(),
            fallbackTemplate: 1,
            subject: translate('Store campaign request'),
            placeholders: [
                'store_name' => $store_name ?? '',
            ],
        );
    }
}
