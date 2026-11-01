<?php

namespace App\Mail;

use App\Models\DeliveryMan;
use App\Models\EmailTemplate;
use App\Mail\Concerns\BuildsTemplatedMail;
use App\Mail\Concerns\QueueableMailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Support\Notification\NotificationText;
use App\Services\System\BusinessSettingService;

class DmSuspendMail extends Mailable implements ShouldQueue
{
    use BuildsTemplatedMail, Queueable, QueueableMailable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $status;

    protected $name;

    protected $deliveryMan;

    public function __construct($status, $deliveryMan)
    {
        $this->status = $status;
        $this->deliveryMan = $deliveryMan instanceof DeliveryMan ? $deliveryMan : null;
        $this->name = $deliveryMan instanceof DeliveryMan ? $deliveryMan->full_name : $deliveryMan;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $status = $this->status;
        if ($status == 'suspend') {
            $data = EmailTemplate::where('type', 'dm')->where('email_type', 'suspend')->first();
            $subject = translate('messages.Your account has been suspended');
        } else {
            $data = EmailTemplate::where('type', 'dm')->where('email_type', 'unsuspend')->first();
            $subject = translate('Your account has been open again');
        }
        $delivery_man_name = $this->name;

        return $this->templatedMail(
            template: $data,
            fallbackTemplate: 7,
            subject: NotificationText::forDeliveryman($subject, $this->deliveryMan),
            placeholders: [
                'delivery_man_name' => $delivery_man_name ?? '',
            ],
            textFilter: fn ($value) => NotificationText::forDeliveryman($value, $this->deliveryMan),
        );
    }
}
