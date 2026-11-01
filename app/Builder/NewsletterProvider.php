<?php

namespace App\Builder;

use App\Models\Newsletter;
use Modules\Builder\Contracts\NewsletterProvider as NewsletterProviderContract;

class NewsletterProvider implements NewsletterProviderContract
{
    public function subscribe(string $email): array
    {
        $email = strtolower(trim($email));

        if (Newsletter::where('email', $email)->exists()) {
            return ['success' => false, 'errors' => [[
                'code'    => 'exists',
                'message' => translate('messages.Subscription exist') ?: 'You are already subscribed.',
            ]]];
        }

        try {
            Newsletter::create(['email' => $email]);
        } catch (\Throwable) {
            return ['success' => false, 'errors' => [[
                'code'    => 'persist',
                'message' => translate('messages.Subscription failed') ?: 'Could not subscribe right now.',
            ]]];
        }

        return ['success' => true];
    }
}
