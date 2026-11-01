<?php

namespace App\Services\Promotion;

use App\Models\BogoOffer;
use App\Models\BogoOfferStore;
use App\Models\Store;
use App\Services\BaseService;
use App\Traits\Promotion\HandlesBogoEnrollment;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use App\CentralLogics\Helpers;

class BogoOfferVendorService extends BaseService
{
    use HandlesBogoEnrollment;

    public function getList(array $filters, array $paginate = []): LengthAwarePaginator
    {
        $paginator = $this->visibleQuery($filters)
            ->when(
                ($filters['type'] ?? 'all') !== 'all',
                fn ($q) => $this->filterByState($q, (string) $filters['type'], (int) $filters['store_id'])
            )
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        return $paginator;
    }

    public function primeOfferRows($offers, Store $store): void
    {
        $approved = collect($offers)->flatMap(
            fn (BogoOffer $offer) => $offer->enrollments
                ->where('status', BogoOfferStore::STATUS_APPROVED)
                ->each(fn (BogoOfferStore $e) => $e->setRelation('bogoOffer', $offer)->setRelation('store', $store))
        );

        $hiddenReasons = $this->customerVisibilityProblemsFor($approved);

        foreach ($offers as $offer) {
            $enrollment = $offer->enrollments->firstWhere('store_id', $store->id);
            $blocked = $this->bogoJoinBlockedReason($offer, $store);

            $offer->is_eligible = $blocked === null;

            $offer->ineligible_reason = $blocked['message'] ?? null;
            $offer->has_ended = $this->bogoOfferHasEnded($offer);
            $offer->hidden_reasons = $hiddenReasons[$enrollment?->id] ?? [];

            $offer->visibility = $this->bogoCustomerVisibility($enrollment, $offer);
            $offer->locked_reason = $this->bogoEnrollmentLockedReason($enrollment, $offer);
            $offer->actions = $this->bogoEnrollmentActions($enrollment, $offer, $offer->is_eligible);

            $offer->state = $this->enrollmentState($enrollment);
            $offer->state_label = $this->enrollmentStateLabel($enrollment, $offer->has_ended);
            $offer->rejection_note = $this->enrollmentRejectionNote($enrollment);
            $offer->own_enrollment = $enrollment;
        }
    }

    public function stateCounts(array $filters): array
    {
        $storeId = (int) $filters['store_id'];
        $counts = ['all' => 0, 'not_joined' => 0, 'pending' => 0,
            'admin_requested' => 0, 'approved' => 0, 'rejected' => 0];

        $offers = $this->visibleQuery($filters)->get();
        $counts['all'] = $offers->count();

        foreach ($offers as $offer) {
            $enrollment = $offer->enrollments->firstWhere('store_id', $storeId);
            $counts[$this->enrollmentState($enrollment)]++;
        }

        return $counts;
    }

    public function find(mixed $id, Store $store): ?BogoOffer
    {
        return BogoOffer::with([
            'storage',
            'enrollments' => fn ($q) => $q->where('store_id', $store->id)->with('items.item.module'),
        ])
            ->where('module_id', $store->module_id)
            ->where(fn ($q) => $q->where('id', $id)->orWhere('slug', $id))
            ->first();
    }

    public function findEnrollment(mixed $offerId, int $storeId): ?BogoOfferStore
    {
        return BogoOfferStore::with('items.item.module')
            ->where('bogo_offer_id', $offerId)
            ->where('store_id', $storeId)
            ->first();
    }

    public function getStoreItemList(Store $store, ?string $search = null): array
    {
        return $this->storeItemPickerOptions($store->id, $search, $store->module_id);
    }

    public function joinBlockedReason(BogoOffer $offer, Store $store): ?array
    {
        return $this->bogoJoinBlockedReason($offer, $store);
    }

    public function resubmitBlockedReason(BogoOfferStore $enrollment): ?string
    {
        return $this->storeResubmitBlockedReason($enrollment);
    }

    public function writeEnrollment(array $payload, BogoOffer $offer, Store $store, ?BogoOfferStore $existing): array
    {
        $buyItems = Helpers::decodeJsonToArray($payload['buy_items'] ?? null) ?? [];
        $getItems = Helpers::decodeJsonToArray($payload['get_items'] ?? null) ?? [];

        if ($errors = $this->validateEnrollmentItems($offer, $buyItems, $getItems, $store->id)) {
            return ['status_code' => 403, 'errors' => $errors];
        }

        $signature = $this->buildCombinationSignature($buyItems, $getItems);

        if ($this->combinationTaken($store->id, $signature, $existing?->id)) {
            return [
                'status_code' => 403,
                'code' => 'combination',
                'message' => translate('messages.this item combination is already used in another running offer'),
            ];
        }

        $bundlePrice = DB::transaction(function () use ($offer, $store, $existing, $buyItems, $getItems, $signature) {
            $existing?->strandCarts();

            $enrollment = $existing ?: new BogoOfferStore([
                'bogo_offer_id' => $offer->id,
                'store_id' => $store->id,
            ]);

            $enrollment->requested_by = 'store';
            $enrollment->status = BogoOfferStore::STATUS_PENDING;
            $enrollment->rejection_reason = null;
            $enrollment->rejected_by = null;
            $enrollment->joined_at = $enrollment->joined_at ?: now();
            $enrollment->combination_signature = $signature;
            $enrollment->checked = 0;
            $enrollment->save();

            return $this->syncEnrollmentItems($enrollment, $buyItems, $getItems);
        });

        app(PromotionNotifier::class)->storeRequestedJoin(PromotionNotifier::BOGO, $offer, $store);

        return [
            'status_code' => 200,
            'bundle_price' => (float) $bundlePrice,
            'enrollment_state' => BogoOfferStore::STATUS_PENDING,
        ];
    }

    public function respondToEnrollment(BogoOfferStore $enrollment, ?BogoOffer $offer, string $status, ?string $reason): array
    {
        if ($blocked = $this->bogoRespondBlockedReason($enrollment, $offer, translate('messages.there is no pending admin request for this offer'))) {
            return ['status_code' => 403, 'code' => 'status', 'message' => $blocked];
        }

        if ($status === BogoOfferStore::STATUS_REJECTED) {
            $this->markRejected($enrollment, 'store', $reason);
            $enrollment->strandCarts();

            app(PromotionNotifier::class)->storeDeclinedInvitation(PromotionNotifier::BOGO, $offer, $enrollment->store_id);

            return ['status_code' => 200, 'message' => translate('messages.you have declined the BOGO offer')];
        }

        $problems = $this->enrollmentIntegrityProblems($enrollment);

        if ($problems['blocking']) {
            return [
                'status_code' => 403,
                'code' => 'items',
                'message' => translate('messages.this selection can no longer be built').' '.implode(' | ', $problems['blocking']),
            ];
        }

        $enrollment->status = BogoOfferStore::STATUS_APPROVED;
        $enrollment->rejection_reason = null;
        $enrollment->rejected_by = null;
        $enrollment->save();

        app(PromotionNotifier::class)->enrollmentWentLive(PromotionNotifier::BOGO, $offer, $enrollment->store_id);

        return [
            'status_code' => 200,
            'message' => translate('messages.you have joined the BOGO offer'),

            'warnings' => $problems['warnings'],
        ];
    }

    public function deleteEnrollment(BogoOfferStore $enrollment, ?BogoOffer $offer): array
    {
        if ($blocked = $this->bogoDeleteBlockedReason($enrollment, $offer)) {
            return ['status_code' => 403, 'code' => 'status', 'message' => $blocked];
        }

        $wasApproved = $this->enrollmentState($enrollment) === BogoOfferStore::STATUS_APPROVED;

        $storeId = $enrollment->store_id;

        DB::transaction(function () use ($enrollment) {
            $enrollment->strandCarts();
            $enrollment->items()->delete();
            $enrollment->delete();
        });

        app(PromotionNotifier::class)->storeWithdrew(PromotionNotifier::BOGO, $offer, $storeId);

        return [
            'status_code' => 200,
            'message' => $wasApproved
                ? translate('messages.you have left the BOGO offer')
                : translate('Your request has been canceled'),
        ];
    }

    public function markInvitationsSeen(int $storeId): void
    {
        BogoOfferStore::where('store_id', $storeId)->where('checked', 0)->update(['checked' => 1]);
    }

    private function visibleQuery(array $filters)
    {
        $storeId = (int) $filters['store_id'];

        return BogoOffer::with([
            'storage',
            'enrollments' => fn ($q) => $q->where('store_id', $storeId)->with('items.item.module'),
        ])
            ->where('module_id', $filters['module_id'])
            ->where('status', 1)
            ->where(function ($q) use ($storeId) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now())
                    ->orWhereHas('enrollments', fn ($e) => $e->where('store_id', $storeId));
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
            'pending' => $query->whereHas('enrollments', fn ($q) => $mine($q)->where('status', BogoOfferStore::STATUS_PENDING)->where('requested_by', 'store')),
            'admin_requested' => $query->whereHas('enrollments', fn ($q) => $mine($q)->where('status', BogoOfferStore::STATUS_PENDING)->where('requested_by', 'admin')),
            'approved' => $query->whereHas('enrollments', fn ($q) => $mine($q)->where('status', BogoOfferStore::STATUS_APPROVED)),
            'rejected' => $query->whereHas('enrollments', fn ($q) => $mine($q)->where('status', BogoOfferStore::STATUS_REJECTED)),
            default => $query,
        };
    }
}
