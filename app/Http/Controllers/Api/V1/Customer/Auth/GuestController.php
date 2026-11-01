<?php

namespace App\Http\Controllers\Api\V1\Customer\Auth;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\Customer\GuestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestController extends BaseApiController
{
    public function __construct(
        private readonly GuestService $guestService
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $guest = $this->guestService->create([
            'ip_address' => $request->ip(),
            'fcm_token' => $request->input('fcm_token'),
        ]);

        return $guest
            ? $this->responseFormatter(config('response.default_store_201'), ['guest_id' => $guest->id])
            : $this->responseFormatter(config('response.default_500'));
    }
}
