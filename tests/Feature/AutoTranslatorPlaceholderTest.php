<?php

namespace Tests\Feature;

use App\CentralLogics\Helpers;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AutoTranslatorPlaceholderTest extends TestCase
{
    private const ENDPOINT = 'translate.googleapis.com/*';

    private function gtx(string ...$sentences): array
    {
        return [array_map(fn ($s) => [$s, $s, null, null, 3], $sentences), null, 'en'];
    }

    public function test_the_placeholder_is_masked_on_the_way_out(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->gtx('#0# দেলিভারি'))]);

        Helpers::auto_translator(':count delivery man payouts', 'en', 'bn');

        Http::assertSent(function ($request) {
            $sent = $request->data()['q'] ?? urldecode((string) parse_url($request->url(), PHP_URL_QUERY));

            $this->assertStringNotContainsString(':count', $sent, 'the raw token reached the translator');
            $this->assertStringContainsString('#0#', $sent);

            return true;
        });
    }

    public function test_the_placeholder_is_restored_on_the_way_back(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->gtx('#0# ডেলিভারি ম্যান পেআউট'))]);

        $this->assertSame(
            ':count ডেলিভারি ম্যান পেআউট',
            Helpers::auto_translator(':count delivery man payouts', 'en', 'bn')
        );
    }

    public function test_several_placeholders_are_restored_in_the_order_the_translator_left_them(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->gtx(
            '#2# সেকেন্ডে #0# — #1# স্টেটমেন্ট থেকে ডেটাবেস পুনরুদ্ধার করা হয়েছে।'
        ))]);

        $this->assertSame(
            ':seconds সেকেন্ডে :file — :count স্টেটমেন্ট থেকে ডেটাবেস পুনরুদ্ধার করা হয়েছে।',
            Helpers::auto_translator('Database restored from :file — :count statements in :seconds seconds.', 'en', 'bn')
        );
    }

    public function test_a_spaced_out_sentinel_still_restores(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->gtx('# 0 # টেবিল নির্বাচন করা হয়েছে'))]);

        $this->assertSame(
            ':tables টেবিল নির্বাচন করা হয়েছে',
            Helpers::auto_translator(':tables tables selected', 'en', 'bn')
        );
    }

    public function test_a_token_that_is_a_prefix_of_another_is_masked_whole(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->gtx('#1# এর #0#'))]);

        $this->assertSame(
            ':store এর :store_name',
            Helpers::auto_translator(':store_name for :store', 'en', 'bn')
        );
    }

    public function test_a_hash_number_hash_already_in_the_source_is_left_alone(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->gtx('#0# আদেশ #1#'))]);

        $this->assertSame(
            '#0# আদেশ :count',
            Helpers::auto_translator('#0# order :count', 'en', 'bn')
        );
    }

    public function test_a_dropped_token_fails_the_translation_rather_than_shipping_it(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->gtx('ডেলিভারি ম্যান পেআউট গণনা'))]);

        $this->assertSame(
            ':count delivery man payouts',
            Helpers::auto_translator(':count delivery man payouts', 'en', 'bn')
        );
    }

    public function test_a_string_with_no_placeholder_is_untouched_by_any_of_this(): void
    {
        Http::fake([self::ENDPOINT => Http::response($this->gtx('দেলিভারি ম্যান'))]);

        $this->assertSame('দেলিভারি ম্যান', Helpers::auto_translator('Delivery man', 'en', 'bn'));
    }

    public function test_a_failed_request_still_hands_back_the_source(): void
    {
        Http::fake([self::ENDPOINT => Http::response('<html>Sorry…</html>', 429)]);

        $this->assertSame(
            ':count delivery man payouts',
            Helpers::auto_translator(':count delivery man payouts', 'en', 'bn')
        );
    }
}
