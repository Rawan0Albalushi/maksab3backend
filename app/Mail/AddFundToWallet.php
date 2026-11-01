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

class AddFundToWallet extends Mailable implements ShouldQueue
{
    use BuildsTemplatedMail, Queueable, QueueableMailable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $wallet;

    public function __construct($wallet)
    {
        $this->wallet = $wallet;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $wallet = $this->wallet;
        $user_name = $wallet->user->f_name;

        return $this->templatedMail(
            template: EmailTemplate::where('type', 'user')->where('email_type', 'add_fund')->first(),
            fallbackTemplate: 6,
            subject: translate('Add fund to wallet'),
            placeholders: [
                'user_name' => $user_name ?? '',
                'transaction_id' => $wallet->transaction_id ?? '',
            ],
            viewData: ['wallet' => $wallet, 'transaction_id' => $wallet->transaction_id, 'time' => $wallet->created_at, 'amount' => $wallet->credit],
        );
    }
}
