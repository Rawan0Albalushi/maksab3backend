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

class DmSelfRegistration extends Mailable implements ShouldQueue
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
        if ($status == 'approved') {
            $data = EmailTemplate::where('type', 'dm')->where('email_type', 'approve')->first();
        } elseif ($status == 'denied') {
            $data = EmailTemplate::where('type', 'dm')->where('email_type', 'deny')->first();
        } else {
            $data = EmailTemplate::where('type', 'dm')->where('email_type', 'registration')->first();
        }
        $url = '';
        $dm_name = $this->name;
        $subject = NotificationText::forDeliveryman(translate('Deliveryman registration'), $this->deliveryMan);

        return $this->templatedMail(
            template: $data,
            fallbackTemplate: 5,
            subject: $subject,
            placeholders: [
                'delivery_man_name' => $dm_name ?? '',
            ],
            viewData: ['url' => $url],
            textFilter: fn ($value) => NotificationText::forDeliveryman($value, $this->deliveryMan),
        );
    }
}
