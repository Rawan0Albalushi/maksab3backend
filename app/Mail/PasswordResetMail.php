<?php

namespace App\Mail;

use App\Mail\Concerns\QueueableMailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable implements ShouldQueue
{
    use Queueable, QueueableMailable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $reset_url;

    public function __construct($reset_url)
    {
        $this->reset_url = $reset_url;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $reset_url = $this->reset_url;

        return $this->view('email-templates.admin-password-reset', ['url' => $reset_url]);
    }
}
