<?php

namespace Modules\AI\app\Traits;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait ResolvesChatContext
{
    protected function chatIdentityError(array $identity): ?JsonResponse
    {
        if ($identity['user_id'] || $identity['guest_id']) {
            return null;
        }

        return $this->errorResponse(
            config('response.forbidden_403'),
            'Provide either authentication token or guest_id.',
            'identity'
        );
    }

    protected function chatUser(): ?User
    {
        return auth('api')->user();
    }

    protected function chatIdentity(Request $request): array
    {
        $user = $this->chatUser();
        $guestId = $request->input('guest_id');

        return [
            'user_id' => $user?->getKey(),
            'guest_id' => $user || ! is_scalar($guestId) || ! $guestId ? null : (string) $guestId,
        ];
    }

    protected function chatModuleId(Request $request): ?int
    {
        $moduleId = $request->header('moduleId') ?? $request->input('module_id');

        return $moduleId ? (int) $moduleId : null;
    }

    protected function chatZoneIds(Request $request): array
    {
        $raw = $request->header('zoneId') ?? $request->input('zone_id');

        if (! $raw) {
            return [];
        }

        $decoded = is_array($raw) ? $raw : json_decode($raw, true);

        if (is_array($decoded)) {
            return array_values(array_filter(array_map(static fn ($zone) => (int) $zone, $decoded), static fn ($zone) => $zone > 0));
        }

        return (int) $raw > 0 ? [(int) $raw] : [];
    }

    protected function chatCoordinate(Request $request, string $name): ?float
    {
        $value = $request->header($name) ?? $request->input($name);

        return is_numeric($value) ? (float) $value : null;
    }
}
