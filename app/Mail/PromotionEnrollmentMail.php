<?php

namespace App\Mail;

use App\Mail\Concerns\BuildsTemplatedMail;
use App\Mail\Concerns\QueueableMailable;
use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The six seeded promotion templates, addressed the way every other templated mail here is:
 * by (type, email_type). P1 seeded `bogo_request` / `bogo_approve` / `bogo_deny` and the three
 * happy hour equivalents, all under type `store`.
 *
 * One mailable rather than six, because the templates differ only in their copy -- which lives in
 * `email_templates` and is edited in the panel, not here.
 */
class PromotionEnrollmentMail extends Mailable implements ShouldQueue
{
    use BuildsTemplatedMail, Queueable, QueueableMailable, SerializesModels;

    public function __construct(
        protected ?string $storeName,
        protected string $emailType,
        protected ?string $offerTitle = null,
        // Set for the admin copy, where the subject is the operator-editable notification_messages
        // row rather than one of the store-facing subjects below.
        protected ?string $subjectOverride = null,
    ) {}

    public function build()
    {
        return $this->templatedMail(
            template: EmailTemplate::where('type', 'store')->where('email_type', $this->emailType)->first(),
            // 11 is what the neighbouring store-facing mails fall back to when a template row is
            // missing, so an unseeded install still sends something laid out correctly.
            fallbackTemplate: 11,
            subject: $this->subjectFor(),
            placeholders: [
                'store_name' => $this->storeName ?? '',
            ],
        );
    }

    private function subjectFor(): string
    {
        if (filled($this->subjectOverride)) {
            return $this->subjectOverride;
        }

        $title = $this->offerTitle ? ' - '.$this->offerTitle : '';

        return match ($this->emailType) {
            'bogo_request' => translate('We have received your BOGO offer request').$title,
            'bogo_approve' => translate('Your BOGO offer request is approved').$title,
            'bogo_deny' => translate('Update on your BOGO offer request').$title,
            'happy_hour_request' => translate('We have received your Happy Hour request').$title,
            'happy_hour_approve' => translate('Your Happy Hour request is approved').$title,
            'happy_hour_deny' => translate('Update on your Happy Hour request').$title,
            default => translate('Promotion enrolment update').$title,
        };
    }
}
