<?php

namespace Tests\Feature;

use App\CentralLogics\Helpers;
use App\Models\PaymentRequest;
use App\Services\Payment\SettingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

function thawani_test_success_hook($data): void
{
    $GLOBALS['thawani_hook_calls'] = ($GLOBALS['thawani_hook_calls'] ?? 0) + 1;
}

function thawani_test_failure_hook($data): void
{
    $GLOBALS['thawani_fail_calls'] = ($GLOBALS['thawani_fail_calls'] ?? 0) + 1;
}

class ThawaniPaymentControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['thawani_hook_calls'] = 0;
        $GLOBALS['thawani_fail_calls'] = 0;

        DB::table('addon_settings')->updateOrInsert(
            ['key_name' => 'thawani', 'settings_type' => 'payment_config'],
            [
                'mode' => 'test',
                'is_active' => 1,
                'test_values' => json_encode([
                    'gateway' => 'thawani',
                    'mode' => 'test',
                    'status' => '1',
                    'public_key' => 'pk_test_public',
                    'private_key' => 'sk_test_secret',
                ]),
                'live_values' => json_encode([
                    'gateway' => 'thawani',
                    'mode' => 'live',
                    'status' => '0',
                    'public_key' => '',
                    'private_key' => '',
                ]),
                'additional_data' => json_encode([
                    'gateway_title' => 'Thawani',
                    'gateway_image' => '',
                    'storage' => 'public',
                ]),
            ]
        );
    }

    public function test_pay_redirects_to_staging_and_callback_marks_the_request_paid_once(): void
    {
        $payment = $this->payment(2.5);

        Http::fake(function ($request) use ($payment) {
            if ($request->method() === 'POST') {
                return Http::response(['data' => ['session_id' => 'sess_test_1']], 200);
            }

            return Http::response([
                'data' => [
                    'session_id' => 'sess_test_1',
                    'client_reference_id' => (string) $payment->id,
                    'payment_status' => 'paid',
                    'payment_id' => 'pay_test_1',
                ],
            ], 200);
        });

        $pay = $this->get('/payment/thawani/pay?payment_id='.$payment->id);

        $pay->assertRedirect();
        $location = $pay->headers->get('Location');
        $this->assertStringStartsWith('https://uatcheckout.thawani.om/pay/sess_test_1?key=', $location);
        $this->assertStringNotContainsString('sk_test_secret', $location);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://uatcheckout.thawani.om/api/v1/checkout/session'
                && $request->header('thawani-api-key')[0] === 'sk_test_secret'
                && $request['products'][0]['unit_amount'] === 2500;
        });

        $callback = $this->get('/payment/thawani/callback?payment_id='.$payment->id);
        $callback->assertRedirect('http://127.0.0.1:8081/payment-return?flag=success&&token='.base64_encode(
            'payment_method=thawani&&attribute_id=9&&transaction_reference=pay_test_1'
        ));

        $this->assertSame(1, (int) $payment->fresh()->is_paid);
        $this->assertSame('pay_test_1', $payment->fresh()->transaction_id);
        $this->assertSame(1, $GLOBALS['thawani_hook_calls']);

        $this->get('/payment/thawani/callback?payment_id='.$payment->id);
        $this->assertSame(1, $GLOBALS['thawani_hook_calls']);
    }

    public function test_unpaid_session_does_not_mark_the_request_paid(): void
    {
        $payment = $this->payment(1);

        Http::fake(fn () => Http::response([
            'data' => [
                'client_reference_id' => (string) $payment->id,
                'payment_status' => 'unpaid',
            ],
        ], 200));

        $payment->additional_data = json_encode(['thawani_session_id' => 'sess_open']);
        $payment->save();

        $this->get('/payment/thawani/callback?payment_id='.$payment->id)
            ->assertRedirect('http://127.0.0.1:8081/payment-return?flag=fail&&token='.base64_encode(
                'payment_method=thawani&&attribute_id=9&&transaction_reference='
            ));

        $this->assertSame(0, (int) $payment->fresh()->is_paid);
        $this->assertSame(1, $GLOBALS['thawani_fail_calls']);
    }

    public function test_lookup_failure_leaves_the_order_hook_uncalled(): void
    {
        $payment = $this->payment(1);
        $payment->additional_data = json_encode(['thawani_session_id' => 'sess_open']);
        $payment->save();

        Http::fake(fn () => Http::response(['message' => 'unavailable'], 500));

        $this->get('/payment/thawani/callback?payment_id='.$payment->id);

        $this->assertSame(0, (int) $payment->fresh()->is_paid);
        $this->assertSame(0, $GLOBALS['thawani_fail_calls']);
    }

    public function test_callback_rejects_a_session_for_another_payment(): void
    {
        $payment = $this->payment(1);
        $payment->additional_data = json_encode(['thawani_session_id' => 'sess_other']);
        $payment->save();

        Http::fake(fn () => Http::response([
            'data' => [
                'client_reference_id' => 'someone-else',
                'payment_status' => 'paid',
                'payment_id' => 'pay_other',
            ],
        ], 200));

        $this->get('/payment/thawani/callback?payment_id='.$payment->id);

        $this->assertSame(0, (int) $payment->fresh()->is_paid);
        $this->assertSame(0, $GLOBALS['thawani_hook_calls']);
    }

    public function test_amount_below_one_baisa_does_not_call_thawani(): void
    {
        $payment = $this->payment(0.0004);
        Http::fake();

        $this->get('/payment/thawani/pay?payment_id='.$payment->id);

        Http::assertNothingSent();
        $this->assertSame(0, (int) $payment->fresh()->is_paid);
    }

    public function test_cancel_leaves_the_request_unpaid(): void
    {
        $payment = $this->payment(1);

        $this->get('/payment/thawani/cancel?payment_id='.$payment->id)
            ->assertRedirect('http://127.0.0.1:8081/payment-return?flag=cancel&&token='.base64_encode(
                'payment_method=thawani&&attribute_id=9&&transaction_reference='
            ));

        $this->assertSame(0, (int) $payment->fresh()->is_paid);
        $this->assertSame(1, $GLOBALS['thawani_fail_calls']);
    }

    public function test_return_url_keeps_the_order_page_query(): void
    {
        $payment = $this->payment(1);
        $payment->external_redirect_link = 'http://127.0.0.1:8000/order-successful?id=100000&status=';
        $payment->save();

        $this->get('/payment/thawani/cancel?payment_id='.$payment->id)
            ->assertRedirect('http://127.0.0.1:8000/order-successful?id=100000&status=&flag=cancel&&token='.base64_encode(
                'payment_method=thawani&&attribute_id=9&&transaction_reference='
            ));
    }

    public function test_active_gateway_list_includes_thawani_when_digital_payment_is_on(): void
    {
        DB::table('business_settings')->updateOrInsert(
            ['key' => 'digital_payment'],
            ['value' => json_encode(['status' => '1'])]
        );
        Helpers::clearBusinessSettingsCache();

        $names = array_column(app(SettingService::class)->getActiveGateways(), 'gateway');

        $this->assertContains('thawani', $names);
    }

    private function payment(float $amount): PaymentRequest
    {
        $payment = new PaymentRequest();
        $payment->payment_amount = $amount;
        $payment->success_hook = 'Tests\\Feature\\thawani_test_success_hook';
        $payment->failure_hook = 'Tests\\Feature\\thawani_test_failure_hook';
        $payment->payer_id = '1';
        $payment->receiver_id = '100';
        $payment->currency_code = 'OMR';
        $payment->payment_method = 'thawani';
        $payment->additional_data = json_encode(['business_name' => 'Maksab']);
        $payment->payer_information = json_encode(['name' => 'Test']);
        $payment->receiver_information = json_encode(['name' => 'Maksab']);
        $payment->external_redirect_link = 'http://127.0.0.1:8081/payment-return';
        $payment->attribute = 'order';
        $payment->attribute_id = '9';
        $payment->payment_platform = 'web';
        $payment->is_paid = 0;
        $payment->save();

        return $payment;
    }
}
