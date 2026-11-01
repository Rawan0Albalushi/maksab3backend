<?php

namespace App\Builder;

use App\CentralLogics\Helpers;
use App\Models\Contact;
use Illuminate\Support\Facades\Http;
use Modules\Builder\Contracts\ContactProvider as ContactProviderContract;

class ContactProvider implements ContactProviderContract
{
    public function submit(array $payload): array
    {
        $recaptchaError = $this->verifyRecaptcha(
            $payload['recaptchaToken'] ?? null,
            $payload['ip'] ?? null,
        );
        if ($recaptchaError) {
            return ['success' => false, 'errors' => [$recaptchaError]];
        }

        try {
            $contact = new Contact();
            $contact->setAttribute('name',    (string) ($payload['name']    ?? ''));
            $contact->setAttribute('email',   (string) ($payload['email']   ?? ''));
            $contact->setAttribute('subject', (string) ($payload['subject'] ?? ''));
            $contact->setAttribute('message', (string) ($payload['message'] ?? ''));
            $contact->save();
        } catch (\Throwable) {
            return ['success' => false, 'errors' => [[
                'code'    => 'persist',
                'message' => translate('messages.Failed to send message') ?: 'Could not send your message.',
            ]]];
        }

        return ['success' => true];
    }

    public function recaptchaSiteKey(): ?string
    {
        $settings = Helpers::get_business_settings('recaptcha');
        if (!is_array($settings) || (int) ($settings['status'] ?? 0) !== 1) {
            return null;
        }
        $key = (string) ($settings['site_key'] ?? '');
        return $key !== '' ? $key : null;
    }

    private function verifyRecaptcha(?string $token, ?string $ip): ?array
    {
        $settings = Helpers::get_business_settings('recaptcha');
        if (!is_array($settings) || (int) ($settings['status'] ?? 0) !== 1) {
            return null;
        }

        $base = translate('reCAPTCHA failed') ?: 'ReCAPTCHA failed.';

        if (!$token) {
            return ['code' => 'recaptcha', 'message' => $base];
        }

        $secret = (string) ($settings['secret_key'] ?? '');
        if ($secret === '') {
            return ['code' => 'recaptcha', 'message' => $base];
        }

        try {
            $response = Http::asForm()->timeout(8)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret'   => $secret,
                'response' => $token,
                'remoteip' => $ip ?? '',
            ]);

            if (!$response->successful()) {
                return ['code' => 'recaptcha', 'message' => $base];
            }

            $body = $response->json();
            if (($body['success'] ?? false) !== true) {
                $codes = is_array($body['error-codes'] ?? null) ? implode(', ', $body['error-codes']) : 'unknown';
                report(new \RuntimeException('reCAPTCHA soft-fail (matching host behavior): ' . $codes));
            }
        } catch (\Throwable $e) {
            report($e);
            return ['code' => 'recaptcha', 'message' => $base];
        }

        return null;
    }
}
