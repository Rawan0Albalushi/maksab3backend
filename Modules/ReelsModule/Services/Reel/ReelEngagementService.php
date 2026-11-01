<?php

namespace Modules\ReelsModule\Services\Reel;

use App\Services\BaseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\ReelsModule\Entities\Reel;
use Modules\ReelsModule\Entities\ReelEngagement;
use Modules\ReelsModule\Services\Reel\ReelService;

class ReelEngagementService extends BaseService
{
    public function recordView(Reel $reel, ?int $userId, ?string $guestId): void
    {
        $this->recordUnique($reel, $userId, $guestId, ReelEngagement::TYPE_VIEW, 'total_views');
    }
    public function recordVisit(Reel $reel, ?int $userId, ?string $guestId): void
    {
        $this->recordUnique($reel, $userId, $guestId, ReelEngagement::TYPE_VISIT, 'total_store_visits');
    }
    public function recordOrderSale(int $reelId, float $amount = 0, ?int $userId = null, ?string $guestId = null): bool
    {
        $reel = app(ReelService::class)->findLocked($reelId);

        if (! $reel) {
            return false;
        }

        $recorded = $this->engagementQuery($reel->id, ReelEngagement::TYPE_ORDER, $userId, $guestId)->exists();

        if (! $recorded) {
            ReelEngagement::create([
                'reel_id' => $reel->id,
                'user_id' => $userId,
                'guest_id' => $userId ? null : $guestId,
                'type' => ReelEngagement::TYPE_ORDER,
                'amount' => $amount,
            ]);
        }

        $reel->increment('order_count');
        $reel->increment('total_sale_amount', $amount);

        return true;
    }
    public function toggleLike(Reel $reel, int $userId): array
    {
        return DB::transaction(function () use ($reel, $userId) {
            $existing = ReelEngagement::where('reel_id', $reel->id)
                ->where('user_id', $userId)
                ->where('type', ReelEngagement::TYPE_LIKE)
                ->lockForUpdate()
                ->first();

            $locked = app(ReelService::class)->findLockedOrFail($reel->id);

            if ($existing) {
                $existing->delete();

                if ($locked->total_likes > 0) {
                    $locked->decrement('total_likes');
                }

                return $this->likeState($reel, false, max(0, (int) $locked->fresh()->total_likes), 'Unliked successfully');
            }

            ReelEngagement::create([
                'reel_id' => $locked->id,
                'user_id' => $userId,
                'type' => ReelEngagement::TYPE_LIKE,
            ]);

            $locked->increment('total_likes');

            return $this->likeState($reel, true, (int) $locked->fresh()->total_likes, 'Liked successfully');
        });
    }
    private function engagementQuery(mixed $reelId, string $type, ?int $userId, ?string $guestId): Builder
    {
        return ReelEngagement::where('reel_id', $reelId)
            ->where('type', $type)
            ->when($userId, fn (Builder $query) => $query->where('user_id', $userId))
            ->when(! $userId, fn (Builder $query) => $query->where('guest_id', $guestId));
    }
    private function likeState(Reel $reel, bool $liked, int $totalLikes, string $message): array
    {
        $reel->setAttribute('is_liked', $liked);
        $reel->setAttribute('total_likes', $totalLikes);

        return ['liked' => $liked, 'total_likes' => $totalLikes, 'message' => $message];
    }
    private function recordUnique(Reel $reel, ?int $userId, ?string $guestId, string $type, string $counterColumn): void
    {
        DB::transaction(function () use ($reel, $userId, $guestId, $type, $counterColumn) {
            $tracked = $this->engagementQuery($reel->id, $type, $userId, $guestId)->lockForUpdate()->exists();

            if ($tracked) {
                return;
            }

            ReelEngagement::create([
                'reel_id' => $reel->id,
                'user_id' => $userId,
                'guest_id' => $userId ? null : $guestId,
                'type' => $type,
            ]);

            app(ReelService::class)->incrementCounter($reel->id, $counterColumn);
            $reel->setAttribute($counterColumn, (int) $reel->getAttribute($counterColumn) + 1);
        });
    }
}
