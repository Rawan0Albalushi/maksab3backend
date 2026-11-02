<?php

namespace App\Http\Controllers;

use App\Models\PaymentRequest;
use App\Traits\System\ProcessorTrait;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ThawaniPaymentController extends Controller
{
    use ProcessorTrait;

    private const MIN_UNIT_AMOUNT = 1;

    private const MAX_UNIT_AMOUNT = 5000000;

    private mixed $config_values;

    private string $mode = 'test';

    private PaymentRequest $payment;

    public function __construct(PaymentRequest $payment)
    {
        $config = $this->paymentConfig('thawani', 'payment_config');
        if (! is_null($config) && $config->mode == 'live') {
            $this->mode = 'live';
            $this->config_values = json_decode($config->live_values);
        } elseif (! is_null($config) && $config->mode == 'test') {
            $this->mode = 'test';
            $this->config_values = json_decode($config->test_values);
        }
        $this->payment = $payment;
    }

    public function index(Request $request): JsonResponse|Redirector|RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json($this->responseFormatter(GATEWAYS_DEFAULT_400, null, $this->errorProcessor($validator)), 400);
        }

        $payment = $this->payment::where('id', $request['payment_id'])->first();
        if (! isset($payment)) {
            return response()->json($this->responseFormatter(GATEWAYS_DEFAULT_204), 200);
        }

        if ((int) $payment->is_paid === 1) {
            return $this->paymentResponse($payment, 'success');
        }

        if ($payment->payment_method !== 'thawani' || strtoupper((string) $payment->currency_code) !== 'OMR') {
            return $this->paymentResponse($payment, 'fail');
        }

        $unitAmount = (int) round(((float) $payment->payment_amount) * 1000);
        if ($unitAmount < self::MIN_UNIT_AMOUNT || $unitAmount > self::MAX_UNIT_AMOUNT) {
            Log::warning('thawani.amount_rejected', ['payment_id' => $payment->id]);

            return $this->paymentResponse($payment, 'fail');
        }

        $secret = trim((string) ($this->config_values->private_key ?? ''));
        $publishable = trim((string) ($this->config_values->public_key ?? ''));
        if ($secret === '' || $publishable === '') {
            Log::warning('thawani.keys_missing', ['payment_id' => $payment->id, 'mode' => $this->mode]);

            return $this->paymentResponse($payment, 'fail');
        }

        $payload = [
            'client_reference_id' => (string) $payment->id,
            'mode' => 'payment',
            'products' => [[
                'name' => 'Maksab order',
                'quantity' => 1,
                'unit_amount' => $unitAmount,
            ]],
            'success_url' => route('thawani.callback', ['payment_id' => $payment->id]),
            'cancel_url' => route('thawani.cancel', ['payment_id' => $payment->id]),
            'metadata' => [
                'payment_id' => (string) $payment->id,
                'order_id' => (string) $payment->attribute_id,
            ],
        ];

        try {
            $response = Http::withHeaders($this->headers($secret))
                ->connectTimeout(5)
                ->timeout(20)
                ->acceptJson()
                ->post($this->sessionUrl(), $payload);
        } catch (ConnectionException) {
            Log::warning('thawani.session_unreachable', ['payment_id' => $payment->id]);

            return $this->paymentResponse($payment, 'fail');
        }

        $sessionId = $response->json('data.session_id');
        if (! $response->successful() || ! is_string($sessionId) || $sessionId === '') {
            Log::warning('thawani.session_failed', [
                'payment_id' => $payment->id,
                'status' => $response->status(),
            ]);

            return $this->paymentResponse($payment, 'fail');
        }

        $this->rememberSession($payment, $sessionId);

        return redirect()->away(
            'https://'.$this->host().'.thawani.om/pay/'.rawurlencode($sessionId).'?key='.rawurlencode($publishable)
        );
    }

    public function callback(Request $request): JsonResponse|Redirector|RedirectResponse
    {
        $payment = $this->findPayment($request);
        if (! isset($payment)) {
            return response()->json($this->responseFormatter(GATEWAYS_DEFAULT_204), 200);
        }

        if ((int) $payment->is_paid === 1) {
            return $this->paymentResponse($payment, 'success');
        }

        $sessionId = $this->storedSessionId($payment);
        $session = $sessionId ? $this->retrieveSession($sessionId) : null;
        if ($session === null) {
            return $this->paymentResponse($payment, 'fail');
        }

        $reference = (string) ($session['client_reference_id'] ?? '');
        $status = (string) ($session['payment_status'] ?? '');
        if ($reference !== (string) $payment->id) {
            Log::warning('thawani.reference_mismatch', ['payment_id' => $payment->id]);

            return $this->paymentResponse($payment, 'fail');
        }

        if (! in_array($status, ['paid', 'succeeded'], true)) {
            $this->runHook($payment->failure_hook, $payment);

            return $this->paymentResponse($payment, 'fail');
        }

        $transactionId = (string) ($session['payment_id'] ?? $sessionId);
        $updated = $this->payment::where('id', $payment->id)->where('is_paid', 0)->update([
            'payment_method' => 'thawani',
            'is_paid' => 1,
            'transaction_id' => $transactionId,
        ]);

        $fresh = $this->payment::find($payment->id);
        if ($updated) {
            $this->runHook($fresh->success_hook, $fresh);
        }

        return $this->paymentResponse($fresh, 'success');
    }

    public function cancel(Request $request): JsonResponse|Redirector|RedirectResponse
    {
        $payment = $this->findPayment($request);
        if (! isset($payment)) {
            return response()->json($this->responseFormatter(GATEWAYS_DEFAULT_204), 200);
        }

        if ((int) $payment->is_paid === 1) {
            return $this->paymentResponse($payment, 'success');
        }

        $this->runHook($payment->failure_hook, $payment);

        return $this->paymentResponse($payment, 'cancel');
    }

    private function findPayment(Request $request): ?PaymentRequest
    {
        $id = $request->input('payment_id');
        if (! is_string($id) || ! Str::isUuid($id)) {
            return null;
        }

        return $this->payment::find($id);
    }

    private function host(): string
    {
        return $this->mode === 'live' ? 'checkout' : 'uatcheckout';
    }

    private function sessionUrl(): string
    {
        return 'https://'.$this->host().'.thawani.om/api/v1/checkout/session';
    }

    private function headers(string $secret): array
    {
        return [
            'Accept' => 'application/json',
            'thawani-api-key' => $secret,
        ];
    }

    private function rememberSession(PaymentRequest $payment, string $sessionId): void
    {
        $extra = json_decode((string) $payment->additional_data, true);
        if (! is_array($extra)) {
            $extra = [];
        }
        $extra['thawani_session_id'] = $sessionId;
        $payment->additional_data = json_encode($extra);
        $payment->save();
    }

    private function storedSessionId(PaymentRequest $payment): ?string
    {
        $extra = json_decode((string) $payment->additional_data, true);
        $sessionId = is_array($extra) ? ($extra['thawani_session_id'] ?? null) : null;

        return is_string($sessionId) && $sessionId !== '' ? $sessionId : null;
    }

    private function retrieveSession(string $sessionId): ?array
    {
        $secret = trim((string) ($this->config_values->private_key ?? ''));
        if ($secret === '') {
            return null;
        }

        try {
            $response = Http::withHeaders($this->headers($secret))
                ->connectTimeout(5)
                ->timeout(20)
                ->acceptJson()
                ->get($this->sessionUrl().'/'.rawurlencode($sessionId));
        } catch (ConnectionException) {
            Log::warning('thawani.session_lookup_unreachable', ['session_id' => $sessionId]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('thawani.session_lookup_failed', ['status' => $response->status()]);

            return null;
        }

        $data = $response->json('data');

        return is_array($data) ? $data : null;
    }

    private function runHook(?string $hook, ?PaymentRequest $payment): void
    {
        if ($payment && $hook && function_exists($hook)) {
            call_user_func($hook, $payment);
        }
    }
}
