<?php

namespace App\Mail;

use App\Mail\Concerns\QueueableMailable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CustomerMessage extends Mailable implements ShouldQueue
{
    use Queueable, QueueableMailable, SerializesModels;

    public function __construct(
        private mixed $body,
        private mixed $name,
        private mixed $subjectLine,
    ) {}

    public function build()
    {
        return $this->subject($this->subjectLine)
            ->view('email-templates.customer-message', [
                'body' => $this->body,
                'name' => $this->name,
            ]);
    }
}
