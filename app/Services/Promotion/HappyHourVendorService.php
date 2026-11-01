<?php

namespace App\Services\Promotion;

use App\Models\HappyHour;
use App\Models\HappyHourStore;
use App\Models\Store;
use App\Services\BaseService;
use App\Services\System\BusinessSettingService;
use App\Traits\Promotion\HandlesPromotionEnrollment;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Happy Hour for the store app -- the same surface the vendor panel has.
 *
 * There is no item selection, so joining is one call and there is no resubmit: a denied store
 * cancels its enrolment and joins again. The state machine and the joinable rule both come from
 * HandlesPromotionEnrollment, shared with the panel.
 */
class HappyHourVendorService extends BaseService
{
    use HandlesPromotionEnrollment;

    /**
     * Switched-on happy hours in this store's module that have not ended, plus any this store is
     * already on.
     *
     * `notEnded()` rather than "end_date is null or in the future": a custom schedule has no
     * end_date at all, so the naive test kept every expired one on the list.
     */
    public function getList(array $filters, array $paginate = []): LengthAwarePaginator
    {
        return $this->visibleQuery($filters)
            ->when(
                ($filters['type'] ?? 'all') !== 'all',
                fn ($q) => $this->filterByState($q, (string) $filters['type'], (int) $filters['store_id'])
            )
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    /** Counted before the tab filter, so the tabs keep their totals while one is open. */
    public function stateCounts(array $filters): array
    {
        $storeId = (int) $filters['store_id'];
        $counts = ['all' => 0, 'not_joined' => 0, 'pending' => 0,
            'admin_requested' => 0, 'approved' => 0, 'rejected' => 0];

        $happyHours = $this->visibleQuery($filters)->get();
        $counts['all'] = $happyHours->count();

        foreach ($happyHours as $happyHour) {
            $enrollment = $happyHour->enrollments->firstWhere('store_id', $storeId);
            $counts[$this->enrollmentState($enrollment)]++;
        }

        return $counts;
    }

    /**
     * Attach what the row renders and a resource may not work out for itself.
     *
     * The visibility check reads the store's schedule and its order allowance, so it queries.
     */
    public function primeRows($happyHours, Store $store): void
    {
        foreach ($happyHours as $happyHour) {
            $enrollment = $happyHour->enrollments->firstWhere('store_id', $store->id);
            $ended = $happyHour->hasEnded();

            $happyHour->has_ended = $ended;
            $happyHour->is_running = $happyHour->isRunningNow();
            $happyHour->is_eligible = ! $ended && (bool) $happyHour->status;
            $happyHour->ineligible_reason = $happyHour->is_eligible
                ? null
                : translate('This happy hour has already ended');
            // What a CUSTOMER sees, as against what the admin answered. An approved happy hour
            // between its windows is `scheduled` rather than a problem: an hour a day is what it
            // is for.
            $happyHour->visibility = $this->happyHourCustomerVisibility($enrollment, $happyHour, $store);
            $happyHour->actions = $this->happyHourActions($enrollment, $ended);
            $happyHour->state = $this->enrollmentState($enrollment);
            $happyHour->state_label = $this->enrollmentStateLabel($enrollment, $ended);
            $happyHour->rejection_note = $this->enrollmentRejectionNote($enrollment, 'happy_hour');
            $happyHour->own_enrollment = $enrollment;
        }
    }

    public function find(mixed $id, Store $store): ?HappyHour
    {
        return HappyHour::with(['storage', 'enrollments' => fn ($q) => $q->where('store_id', $store->id)])
            ->where('module_id', $store->module_id)
            ->where(fn ($q) => $q->where('id', $id)->orWhere('slug', $id))
            ->first();
    }

    public function findEnrollment(mixed $happyHourId, int $storeId): ?HappyHourStore
    {
        return HappyHourStore::where('happy_hour_id', $happyHourId)->where('store_id', $storeId)->first();
    }

    /** The dated windows still to come, for the detail screen. */
    public function upcomingDates(HappyHour $happyHour): array
    {
        return $happyHour->dates()
            ->whereDate('applicable_date', '>=', now()->toDateString())
            ->orderBy('applicable_date')
            ->get()
            ->map(fn ($date) => [
                'applicable_date' => $date->applicable_date?->format('Y-m-d'),
                'start_time' => $date->start_time,
                'end_time' => $date->end_time,
                'status' => (bool) $date->status,
            ])->values()->all();
    }

    /**
     * Store-initiated join. Lands pending for the admin to approve.
     *
     * Joining is only ever a FIRST join. A denied store cancels its enrolment first, which deletes
     * the row -- reviving a rejected row in place would hide the refusal from the admin, who would
     * then see a fresh request that had already been turned down.
     */
    public function joinHappyHour(HappyHour $happyHour, Store $store): array
    {
        if ($happyHour->hasEnded() || ! $happyHour->status) {
            return ['status_code' => 403, 'code' => 'happy_hour', 'message' => translate('This happy hour has already ended')];
        }

        if ($existing = $this->findEnrollment($happyHour->id, $store->id)) {
            return [
                'status_code' => 403,
                'code' => 'happy_hour',
                'message' => match ($this->enrollmentState($existing)) {
                    'pending' => translate('messages.you have already requested to join this happy hour'),
                    'approved' => translate('messages.you have already joined this happy hour'),
                    'admin_requested' => translate('messages.respond to the admin request for this happy hour'),
                    default => translate('messages.cancel the denied request before joining again'),
                },
            ];
        }

        if ($clash = $this->overlapWithCommitted($happyHour, $store)) {
            return ['status_code' => 403, 'code' => 'overlap', 'message' => $this->overlapMessage($clash)];
        }

        HappyHourStore::create([
            'happy_hour_id' => $happyHour->id,
            'store_id' => $store->id,
            'status' => HappyHourStore::STATUS_PENDING,
            'requested_by' => 'store',
            'joined_at' => now(),
            'checked' => 0,
        ]);

        app(PromotionNotifier::class)->storeRequestedJoin(PromotionNotifier::HAPPY_HOUR, $happyHour, $store);

        return [
            'status_code' => 200,
            'message' => translate('messages.your request to join the happy hour has been sent'),
            'enrollment_state' => HappyHourStore::STATUS_PENDING,
        ];
    }

    /** The store's answer to a happy hour the admin assigned it. */
    public function respondToEnrollment(HappyHourStore $enrollment, HappyHour $happyHour, Store $store, string $status, ?string $reason): array
    {
        if ($blocked = $this->respondBlockedReason($enrollment, translate('messages.there is no pending admin request for this happy hour'))) {
            return ['status_code' => 403, 'code' => 'status', 'message' => $blocked];
        }

        if ($happyHour->hasEnded()) {
            return ['status_code' => 403, 'code' => 'happy_hour', 'message' => translate('This happy hour has already ended')];
        }

        if ($status === HappyHourStore::STATUS_REJECTED) {
            $this->markRejected($enrollment, 'store', $reason);

            app(PromotionNotifier::class)->storeDeclinedInvitation(PromotionNotifier::HAPPY_HOUR, $happyHour, $store);

            return ['status_code' => 200, 'message' => translate('messages.you have declined the happy hour')];
        }

        // Accepting IS joining, so the same overlap rule applies as it does to a join.
        if ($clash = $this->overlapWithCommitted($happyHour, $store)) {
            return ['status_code' => 403, 'code' => 'overlap', 'message' => $this->overlapMessage($clash)];
        }

        $enrollment->status = HappyHourStore::STATUS_APPROVED;
        $enrollment->rejection_reason = null;
        $enrollment->rejected_by = null;
        $enrollment->joined_at ??= now();
        $enrollment->save();

        app(PromotionNotifier::class)->enrollmentWentLive(PromotionNotifier::HAPPY_HOUR, $happyHour, $store);

        return ['status_code' => 200, 'message' => translate('messages.you have joined the happy hour')];
    }

    /**
     * Withdraw a request or leave a running one -- the same delete, worded differently.
     *
     * Leaving is allowed even while the window is live, which is the rule the admin side follows
     * for removing a store.
     */
    public function deleteEnrollment(HappyHourStore $enrollment): array
    {
        if ($blocked = $this->storeDeleteBlockedReason($enrollment)) {
            return ['status_code' => 403, 'code' => 'status', 'message' => $blocked];
        }

        $wasApproved = $this->enrollmentState($enrollment) === HappyHourStore::STATUS_APPROVED;
        // Read before the delete; afterwards the model no longer carries them.
        $storeId = $enrollment->store_id;
        $happyHourId = $enrollment->happy_hour_id;

        DB::transaction(fn () => $enrollment->delete());

        app(PromotionNotifier::class)->storeWithdrew(
            PromotionNotifier::HAPPY_HOUR,
            HappyHour::find($happyHourId),
            $storeId
        );

        return [
            'status_code' => 200,
            'message' => $wasApproved
                ? translate('messages.you have left the happy hour')
                : translate('Your request has been canceled'),
        ];
    }

    public function markInvitationsSeen(int $storeId): void
    {
        HappyHourStore::where('store_id', $storeId)->where('checked', 0)->update(['checked' => 1]);
    }

    /**
     * Whether the "Pro Members keep their own discount" line is worth showing.
     *
     * The promise is only true where Pro Member is switched on, so the app is told rather than
     * left to assume.
     */
    public function proMemberEnabled(): bool
    {
        return (bool) app(BusinessSettingService::class)->value('pro_member_status', false);
    }

    /**
     * The happy hours this store is already committed to, compared against the one in hand.
     *
     * A store may hold as many happy hours as it likes as long as their windows do not touch: the
     * discount is store-wide, so two running at once would leave the basket's price decided by
     * whichever enrolment was read first.
     */
    private function overlapWithCommitted(HappyHour $happyHour, Store $store): ?array
    {
        $committed = HappyHourStore::where('store_id', $store->id)
            ->whereIn('status', [HappyHourStore::STATUS_PENDING, HappyHourStore::STATUS_APPROVED])
            ->where('happy_hour_id', '!=', $happyHour->id)
            ->pluck('happy_hour_id')
            ->all();

        return app(HappyHourScheduleService::class)->overlapWith($happyHour, $committed);
    }

    private function overlapMessage(array $clash): string
    {
        return translate('messages.This happy hour overlaps one you are already in')
            .' : '.$clash['title'].' ('.$clash['window'].')';
    }

    /** Resubmit is off: a happy hour has no selection to rework. */
    private function happyHourActions(?HappyHourStore $enrollment, bool $ended): array
    {
        $actions = $this->enrollmentActions($enrollment, ! $ended, supportsResubmit: false);

        return array_merge($actions, [
            'can_join' => $actions['can_join'] && ! $ended,
            'can_respond' => $actions['can_respond'] && ! $ended,
        ]);
    }

    private function visibleQuery(array $filters)
    {
        $storeId = (int) $filters['store_id'];

        return HappyHour::with(['storage', 'enrollments' => fn ($q) => $q->where('store_id', $storeId)])
            ->where('module_id', $filters['module_id'])
            ->where('status', 1)
            ->where(function ($q) use ($storeId) {
                $q->notEnded()->orWhereHas('enrollments', fn ($e) => $e->where('store_id', $storeId));
            })
            ->when($filters['search'] ?? null, function ($query) use ($filters) {
                $query->where(function ($q) use ($filters) {
                    foreach (array_filter(explode(' ', (string) $filters['search'])) as $word) {
                        $q->orWhere('title', 'like', "%{$word}%");
                    }
                });
            });
    }

    private function filterByState($query, string $state, int $storeId)
    {
        $mine = fn ($q) => $q->where('store_id', $storeId);

        return match ($state) {
            'not_joined' => $query->whereDoesntHave('enrollments', $mine),
            'pending' => $query->whereHas('enrollments', fn ($q) => $mine($q)->where('status', HappyHourStore::STATUS_PENDING)->where('requested_by', 'store')),
            'admin_requested' => $query->whereHas('enrollments', fn ($q) => $mine($q)->where('status', HappyHourStore::STATUS_PENDING)->where('requested_by', 'admin')),
            'approved' => $query->whereHas('enrollments', fn ($q) => $mine($q)->where('status', HappyHourStore::STATUS_APPROVED)),
            'rejected' => $query->whereHas('enrollments', fn ($q) => $mine($q)->where('status', HappyHourStore::STATUS_REJECTED)),
            default => $query,
        };
    }
}
