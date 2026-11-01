<?php

namespace App\Mail\Concerns;

use App\Services\System\BusinessSettingService;
use App\Support\Notification\NotificationText;

trait BuildsTemplatedMail
{
    protected function templatedMail(
        mixed $template,
        int $fallbackTemplate,
        string $subject,
        array $placeholders = [],
        array $viewData = [],
        string $viewPrefix = 'email-templates.new-email-format-',
        ?callable $textFilter = null,
        array $rawText = [],
    ) {
        $format = $template ? $template->email_template : $fallbackTemplate;

        return $this->subject($subject)->view($viewPrefix.$format, array_merge([
            'company_name' => app(BusinessSettingService::class)->value('business_name', false),
            'data' => $template,
            'title' => $this->templatedText($template, 'title', $placeholders, $textFilter, $rawText),
            'body' => $this->templatedText($template, 'body', $placeholders, $textFilter, $rawText),
            'footer_text' => $this->templatedText($template, 'footer_text', $placeholders, $textFilter, $rawText),
            'copyright_text' => $this->templatedText($template, 'copyright_text', $placeholders, $textFilter, $rawText),
        ], $viewData));
    }

    private function templatedText(mixed $template, string $key, array $placeholders, ?callable $textFilter = null, array $rawText = []): mixed
    {
        $value = NotificationText::format(
            value: array_key_exists($key, $rawText) ? $rawText[$key] : ($template[$key] ?? ''),
            user_name: $placeholders['user_name'] ?? '',
            store_name: $placeholders['store_name'] ?? '',
            delivery_man_name: $placeholders['delivery_man_name'] ?? '',
            transaction_id: $placeholders['transaction_id'] ?? null,
            order_id: $placeholders['order_id'] ?? '',
            add_id: $placeholders['add_id'] ?? null,
        );

        return $textFilter ? $textFilter($value) : $value;
    }
}
