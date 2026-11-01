<?php

namespace App\Traits\Customer;

use App\Models\User;
use App\Services\Order\CartService;

trait CustomerSessionTrait
{
    abstract protected function cartService(): CartService;

    protected function sessionPayload(User $user, string $loginType, mixed $guestId, bool $forceToken = false): array
    {
        $isPersonalInfo = $user->f_name ? 1 : 0;
        $token = null;

        if (($forceToken || $isPersonalInfo == 1) && auth()->loginUsingId($user->id)) {
            $token = auth()->user()->createToken('RestaurantCustomerAuth')->accessToken;
            $this->cartService()->mergeGuestCart($user->id, $guestId);
        }

        return $this->sessionShape($user, $loginType, $token, $isPersonalInfo);
    }

    protected function pendingSessionPayload(User $user, string $loginType, mixed $isExistUser = null, int $isPersonalInfo = 1): array
    {
        return $this->sessionShape($user, $loginType, null, $isPersonalInfo, $isExistUser);
    }

    private function sessionShape(User $user, string $loginType, mixed $token, int $isPersonalInfo, mixed $isExistUser = null): array
    {
        return [
            'token' => $token,
            'is_phone_verified' => 1,
            'is_email_verified' => 1,
            'is_personal_info' => $isPersonalInfo,
            'is_exist_user' => $isExistUser,
            'login_type' => $loginType,
            'email' => $user->email ?: null,
        ];
    }
}
