<?php

namespace App\Builder;

use Illuminate\Support\Facades\Auth;
use Modules\Builder\Contracts\VendorAuthProvider as VendorAuthProviderContract;

class VendorAuthProvider implements VendorAuthProviderContract
{
    public function current(): ?array
    {
        $user = Auth::guard('vendor')->user()
            ?: Auth::guard('vendor_employee')->user();

        if (!$user) {
            return null;
        }

        $name = \trim(($user->f_name ?? '') . ' ' . ($user->l_name ?? ''));

        return [
            'name'      => $name !== '' ? $name : ($user->email ?? 'Vendor'),
            'email'     => $user->email ?? null,
            'image_url' => $this->safeImageUrl($user),
        ];
    }

    public function logoutUrl(): string
    {
        try {
            return \route('logout');
        } catch (\Throwable) {
            return \url('/logout');
        }
    }

    private function safeImageUrl($user): ?string
    {
        try {
            return $user->image_full_url ?? null;
        } catch (\Throwable) {
            return null;
        }
    }
}
