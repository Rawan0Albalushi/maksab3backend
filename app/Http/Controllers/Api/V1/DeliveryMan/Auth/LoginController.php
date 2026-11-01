<?php

namespace App\Http\Controllers\Api\V1\DeliveryMan\Auth;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\DeliveryMan\Auth\LoginRequest;
use App\Http\Requests\DeliveryMan\Auth\RegistrationRequest;
use App\Services\DeliveryMan\DeliveryManService;
use Illuminate\Http\JsonResponse;

class LoginController extends BaseApiController
{
    public function __construct(protected DeliveryManService $deliveryManService) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $authenticated = $this->deliveryManService->login($request->only(['phone', 'password']));

        if (! $authenticated) {
            return $this->credentialsError();
        }

        if ($authenticated->application_status != 'approved') {
            return $this->errorResponse(config('response.unauthorized_401'), translate('messages.Your account is not approved yet.'), 'auth-003');
        }

        if (! $authenticated->status) {
            return $this->errorResponse(config('response.unauthorized_401'), translate('messages.Your account has been suspended'), 'auth-003');
        }

        $type = $request->loginType();
        $deliveryMan = $this->deliveryManService->findByPhoneAndType($request->input('phone'), $type);

        if (! $deliveryMan) {
            return $this->credentialsError();
        }

        $token = $this->deliveryManService->issueAuthToken($deliveryMan);
        $topics = $this->deliveryManService->notificationTopics($deliveryMan, $type);

        return $this->responseFormatter(config('response.default_200'), [
            'token' => $token,
            'topic' => $topics['topic'] ?: 'No_topic_found',
            'zone_topic' => $topics['zone_topic'],
        ]);
    }

    public function store(RegistrationRequest $request): JsonResponse
    {
        $deliveryMan = $this->deliveryManService->createFromRegistration(
            $request->validated(),
            (array) $request->file('identity_image'),
            $request->file('image')
        );

        $this->deliveryManService->notifyRegistration($deliveryMan, $request->input('email'));

        return $this->responseFormatter(
            ['message' => translate('Added successfully')] + config('response.default_200')
        );
    }

    private function credentialsError(): JsonResponse
    {
        return $this->errorResponse(
            config('response.unauthorized_401'),
            translate('Incorrect credential, please try again'),
            'auth-001'
        );
    }
}
