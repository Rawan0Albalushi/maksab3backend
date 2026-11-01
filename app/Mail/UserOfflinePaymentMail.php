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

class UserOfflinePaymentMail extends Mailable implements ShouldQueue
{
    use BuildsTemplatedMail, Queueable, QueueableMailable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $status;

    protected $name;

    public function __construct($name, $status)
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
        $sub = translate('Payment approved');
        $status = $this->status;
        if ($status == 'approved') {
            $data = EmailTemplate::where('type', 'user')->where('email_type', 'offline_payment_approve')->first();
        } elseif ($status == 'denied') {
            $sub = translate('Payment denied');
            $data = EmailTemplate::where('type', 'user')->where('email_type', 'offline_payment_deny')->first();
        }
        $url = '';
        $store_name = $this->name;

        return $this->templatedMail(
            template: $data,
            fallbackTemplate: 5,
            subject: $sub,
            placeholders: [
                'store_name' => $store_name ?? '',
            ],
            viewData: ['url' => $url],
        );
    }
}
