<?php

namespace Tests\Unit;

use App\CentralLogics\Helpers;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Google throttles the translate endpoint per CLIENT ID per source IP, and it began answering the
 * `gtx` id — the only one this used — with a 429 "Sorry..." page for server traffic.
 *
 * `auto_translator()` reports failure by handing the source string back, so the bulk translator
 * saw twenty rows return in English and stopped with "Translation service did not respond". The
 * other client ids answer normally and return byte-identical JSON, so the fix is to fall through.
 *
 * Faked, never live: the point is the fallback, and a test that depends on Google's current mood
 * would be the flakiest thing in the suite.
 */
class AutoTranslatorClientFallbackTest extends TestCase
{
    private function body(string $translated): array
    {
        return [[[$translated, 'source', null, null, 3]], null, 'en'];
    }

    private function clientOf(Request $request): string
    {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $query['client'] ?? '';
    }

    public function test_a_throttled_client_id_falls_through_to_one_that_answers(): void
    {
        $tried = [];

        Http::fake(function (Request $request) use (&$tried) {
            $client = $this->clientOf($request);
            $tried[] = $client;

            return $client === 'gtx'
                ? Http::response('<html><title>Sorry...</title></html>', 429)
                : Http::response($this->body('مرحبا'), 200);
        });

        $this->assertSame('مرحبا', Helpers::auto_translator('Hello', 'en', 'ar'));
        $this->assertContains('gtx', $tried, 'gtx is still tried first');
        $this->assertGreaterThan(1, count($tried), 'it must try another client id after the 429');
        $this->assertNull(Helpers::lastTranslationFailure());
    }

    public function test_the_working_client_is_remembered_so_a_batch_pays_the_429_once(): void
    {
        $tried = [];

        Http::fake(function (Request $request) use (&$tried) {
            $client = $this->clientOf($request);
            $tried[] = $client;

            return $client === 'gtx'
                ? Http::response('', 429)
                : Http::response($this->body('ok'), 200);
        });

        // The bulk translator makes twenty of these per request.
        for ($i = 0; $i < 5; $i++) {
            Helpers::auto_translator('Hello '.$i, 'en', 'ar');
        }

        $this->assertLessThanOrEqual(
            2,
            count(array_filter($tried, fn ($c) => $c === 'gtx')),
            'gtx must not be re-tried on every call once another id is known to work'
        );
    }

    public function test_every_client_throttled_reports_rate_limited_and_keeps_the_source(): void
    {
        Http::fake(fn () => Http::response('<html><title>Sorry...</title></html>', 429));

        $this->assertSame('Hello', Helpers::auto_translator('Hello', 'en', 'ar'),
            'the source is handed back rather than a half-translation');
        $this->assertSame('rate_limited', Helpers::lastTranslationFailure());
    }

    public function test_an_outage_is_reported_differently_from_a_throttle(): void
    {
        Http::fake(fn () => Http::response('', 500));

        $this->assertSame('Hello', Helpers::auto_translator('Hello', 'en', 'ar'));
        $this->assertSame('unreachable', Helpers::lastTranslationFailure());
    }

    public function test_a_mangled_placeholder_still_returns_the_source(): void
    {
        // Arabic really does turn "#1#" into "رقم 1#". Corrupting a :placeholder is worse than
        // leaving the row in English, and that guard must survive the fallback.
        Http::fake(fn () => Http::response($this->body('مرحبا #0#، طلبك رقم 1# جاهز.'), 200));

        $source = 'Hello :name, your order :id is ready.';

        $this->assertSame($source, Helpers::auto_translator($source, 'en', 'ar'));
    }
}
