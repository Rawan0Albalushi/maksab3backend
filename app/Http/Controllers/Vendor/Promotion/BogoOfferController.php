<?php

namespace App\Http\Controllers\Vendor\Promotion;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\AddOn;
use App\Models\BogoOffer;
use App\Models\BogoOfferItem;
use App\Models\BogoOfferStore;
use App\Models\Store;
use App\Services\Promotion\PromotionNotifier;
use App\Traits\Promotion\HandlesBogoEnrollment;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The store's side of BOGO. A store never creates or edits an offer -- it joins one the admin
 * published, answers one the admin assigned it, reworks its own selection, cancels, or leaves.
 *
 * Every rule about what a valid selection is, and about who may act next, lives in
 * HandlesBogoEnrollment and HandlesPromotionEnrollment -- shared with the admin panel, so the two
 * sides cannot drift on the same question.
 *
 * The store is always taken from the session via Helpers::get_store_id(), never from the request:
 * a store_id in the payload would let one vendor enrol another's menu.
 */
class BogoOfferController extends Controller
{
    use HandlesBogoEnrollment;

    /**
     * Offers this store may see: switched on, and either still running or one it already joined.
     *
     * An expired offer nobody here joined drops off the list; one this store did join stays,
     * marked ended and read-only, because removing it would take away the only record of what ran.
     */
    public function index(Request $request): View
    {
        $store = $this->store();

        // Records that the store has now seen these rows. It no longer silences the dashboard
        // prompt: an invitation is a decision the admin is waiting on, so it is raised until the
        // store approves or denies rather than until it glances at the list.
        BogoOfferStore::where('store_id', $store->id)->where('checked', 0)->update(['checked' => 1]);

        $offers = BogoOffer::with([
            // storage backs image_full_url, which every row renders.
            'storage',
            'enrollments' => fn ($q) => $q->where('store_id', $store->id)->with('items'),
        ])
            ->where('module_id', $store->module_id)
            ->where('status', 1)
            ->where(function ($q) use ($store) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now())
                    ->orWhereHas('enrollments', fn ($e) => $e->where('store_id', $store->id));
            })
            ->when($request->input('search'), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    foreach ($this->searchWords($request->input('search')) as $word) {
                        $q->orWhere('title', 'like', "%{$word}%");
                    }
                });
            })
            ->latest()
            ->paginate(config('default_pagination'))
            ->withQueryString();

        $enrollments = $offers->getCollection()->flatMap(
            fn (BogoOffer $offer) => $offer->enrollments->each(
                fn (BogoOfferStore $e) => $e->setRelation('bogoOffer', $offer)->setRelation('store', $store)
            )
        );

        $this->primeEnrollmentItems($enrollments, $store->module);

        $enrollments->each(
            fn (BogoOfferStore $e) => $e
                ->setRelation('buyItems', $e->items->where('type', 'buy')->values())
                ->setRelation('getItems', $e->items->where('type', 'get')->values())
        );

        $hiddenReasons = $enrollments
            ->where('status', BogoOfferStore::STATUS_APPROVED)
            ->mapWithKeys(fn (BogoOfferStore $e) => [$e->id => $this->customerVisibilityProblems($e)])
            ->all();

        $offers->getCollection()->transform(function (BogoOffer $offer) use ($store, $hiddenReasons) {
            $enrollment = $offer->enrollments->first();

            $offer->is_eligible = $this->bogoStoreServesOfferTypes($store, $offer);
            $offer->has_ended = $this->bogoOfferHasEnded($offer);
            $offer->hidden_reasons = $hiddenReasons[$enrollment?->id] ?? [];
            // What a CUSTOMER would see right now, which is a different question from where the
            // request stands with the admin. "Approved" is not "running".
            $offer->visibility = $this->bogoCustomerVisibility($enrollment, $offer);
            $offer->locked_reason = $this->bogoEnrollmentLockedReason($enrollment, $offer);
            $offer->actions = $this->bogoEnrollmentActions($enrollment, $offer, $offer->is_eligible);
            // Composed from the same helpers the vendor app reads, so one enrolment is never
            // worded two ways.
            $offer->state_label = $this->enrollmentStateLabel($enrollment, $offer->has_ended);
            $offer->rejection_note = $this->enrollmentRejectionNote($enrollment);

            return $offer;
        });

        return view('vendor-views.promotions.bogo-offer.list', compact('offers', 'store'));
    }

    /** Drawer body for one offer, with this store's own enrolment if it has one. */
    public function getDetailView(string $id): View
    {
        $store = $this->store();
        $offer = $this->findInStoreModule($id, $store);
        $offer->loadMissing('storage');

        $enrollment = BogoOfferStore::with('items.item')
            ->where('bogo_offer_id', $offer->id)
            ->where('store_id', $store->id)
            ->first();

        $enrollment?->items->each(fn (BogoOfferItem $line) => $line->item?->setRelation('module', $store->module));

        $enrollment?->setRelation('buyItems', $enrollment->items->where('type', 'buy')->values())
            ->setRelation('getItems', $enrollment->items->where('type', 'get')->values());

        $offer->is_eligible = $this->bogoStoreServesOfferTypes($store, $offer);
        $offer->has_ended = $this->bogoOfferHasEnded($offer);
        $offer->has_started = ! $offer->start_date || ! $offer->start_date->isFuture();
        $offer->locked_reason = $this->bogoEnrollmentLockedReason($enrollment, $offer);
        $offer->actions = $this->bogoEnrollmentActions($enrollment, $offer, $offer->is_eligible);

        // "Approved" says the admin agreed; it does not say a customer can see the bundle today.
        $hiddenReasons = [];

        if ($enrollment && $enrollment->status === BogoOfferStore::STATUS_APPROVED) {
            $enrollment->setRelation('store', $store);
            $hiddenReasons = $this->customerVisibilityProblems($enrollment, $offer);
        }

        $offer->visibility = $this->bogoCustomerVisibility($enrollment, $offer);

        [$addOnNames, $addOnPrices] = $this->addOnLabels($enrollment);

        return view('vendor-views.promotions.bogo-offer.partials._detail_drawer', compact(
            'offer', 'enrollment', 'addOnNames', 'addOnPrices', 'hiddenReasons'
        ));
    }

    /** Own menu only -- the store comes from the session, so one vendor cannot list another's items. */
    public function getStoreItems(Request $request): JsonResponse
    {
        $store = $this->store();

        return response()->json(
            $this->storeItemPickerOptions($store->id, $request->input('search'), $store->module_id),
            200
        );
    }

    public function join(Request $request, string $id): JsonResponse
    {
        $store = $this->store();
        $offer = $this->findInStoreModule($id, $store);

        if ($blocked = $this->bogoJoinBlockedReason($offer, $store)) {
            return $this->errorResponse($blocked['code'], $blocked['message']);
        }

        if (BogoOfferStore::where('bogo_offer_id', $offer->id)->where('store_id', $store->id)->exists()) {
            return $this->errorResponse('store', translate('You have already joined this offer'));
        }

        return $this->writeEnrollment($request, $offer, $store, null);
    }

    /**
     * The store's answer to an offer the admin assigned it, with the items the admin chose.
     *
     * Only an admin-initiated pending row can be answered -- a store cannot approve a request it
     * raised itself.
     */
    public function respond(Request $request, string $id, string $status): RedirectResponse
    {
        if (! in_array($status, [BogoOfferStore::STATUS_APPROVED, BogoOfferStore::STATUS_REJECTED], true)) {
            abort(404);
        }

        $store = $this->store();
        $offer = BogoOffer::find($id);

        $enrollment = BogoOfferStore::with('items.item.module')
            ->where('bogo_offer_id', $id)
            ->where('store_id', $store->id)
            ->firstOrFail();

        if ($blocked = $this->bogoRespondBlockedReason($enrollment, $offer, translate('messages.there is no pending admin request for this offer'))) {
            Toastr::error($blocked);

            return back();
        }

        if ($status === BogoOfferStore::STATUS_REJECTED) {
            $request->validate(['rejection_reason' => 'nullable|string|max:255']);

            $this->markRejected($enrollment, 'store', $request->input('rejection_reason'));
            $enrollment->strandCarts();

            app(PromotionNotifier::class)->storeDeclinedInvitation(PromotionNotifier::BOGO, $offer, $store);

            Toastr::success(translate('messages.you have declined the BOGO offer'));

            return back();
        }

        // The admin picked these items when it sent the request; the store may have changed its
        // menu since. Accepting into a selection it can no longer serve is the same broken bundle
        // whichever side clicks approve.
        $problems = $this->enrollmentIntegrityProblems($enrollment);

        if ($problems['blocking']) {
            Toastr::error(translate('messages.this selection can no longer be built').' '.implode(' | ', $problems['blocking']));

            return back();
        }

        $enrollment->status = BogoOfferStore::STATUS_APPROVED;
        $enrollment->rejection_reason = null;
        $enrollment->rejected_by = null;
        $enrollment->save();

        if ($problems['warnings']) {
            Toastr::warning(translate('messages.this offer stays hidden until the item is available again')
                .' '.implode(' | ', $problems['warnings']));
        }

        app(PromotionNotifier::class)->enrollmentWentLive(PromotionNotifier::BOGO, $offer, $store);

        Toastr::success(translate('messages.you have joined the BOGO offer'));

        return back();
    }

    /**
     * A reworked selection -- after a denial, or to change what is already running.
     *
     * Goes back to pending as a store request, so the admin remains the gate on whatever finally
     * goes live; an approved offer therefore stops applying until it is approved again. An admin
     * request cannot be edited here: the store answers it as it stands, and reworks it only after
     * saying no.
     */
    public function resubmit(Request $request, string $id): JsonResponse
    {
        $store = $this->store();
        $offer = $this->findInStoreModule($id, $store);

        $enrollment = BogoOfferStore::where('bogo_offer_id', $offer->id)
            ->where('store_id', $store->id)
            ->firstOrFail();

        if ($blocked = $this->storeResubmitBlockedReason($enrollment)) {
            return $this->errorResponse('status', $blocked);
        }

        if ($blocked = $this->bogoJoinBlockedReason($offer, $store)) {
            return $this->errorResponse($blocked['code'], $blocked['message']);
        }

        return $this->writeEnrollment($request, $offer, $store, $enrollment);
    }

    /**
     * Backs both Cancel Request and Leave.
     *
     * Withdrawing a request that never went live and walking away from a running one are the same
     * delete, differing only in what the store is told. An unanswered admin request is neither --
     * it is responded to.
     */
    public function leave(string $id): RedirectResponse
    {
        $store = $this->store();

        $enrollment = BogoOfferStore::where('bogo_offer_id', $id)
            ->where('store_id', $store->id)
            ->firstOrFail();

        if ($blocked = $this->bogoDeleteBlockedReason($enrollment, BogoOffer::find($id))) {
            Toastr::error($blocked);

            return back();
        }

        $wasApproved = $this->enrollmentState($enrollment) === BogoOfferStore::STATUS_APPROVED;

        DB::transaction(function () use ($enrollment) {
            // The store has stopped serving the bundle, so the carts holding it go too.
            $enrollment->strandCarts();
            $enrollment->items()->delete();
            $enrollment->delete();
        });

        app(PromotionNotifier::class)->storeWithdrew(PromotionNotifier::BOGO, BogoOffer::find($id), $store);

        Toastr::success($wasApproved
            ? translate('messages.you have left the BOGO offer')
            : translate('Your request has been canceled'));

        return back();
    }

    /** Shared by join and resubmit: validate, freeze the snapshot, land on pending. */
    private function writeEnrollment(Request $request, BogoOffer $offer, Store $store, ?BogoOfferStore $existing): JsonResponse
    {
        $buyItems = Helpers::decodeJsonToArray($request->input('buy_items')) ?? [];
        $getItems = Helpers::decodeJsonToArray($request->input('get_items')) ?? [];

        if ($errors = $this->validateEnrollmentItems($offer, $buyItems, $getItems, $store->id)) {
            return response()->json(['errors' => $errors], 403);
        }

        $signature = $this->buildCombinationSignature($buyItems, $getItems);

        if ($this->combinationTaken($store->id, $signature, $existing?->id)) {
            return $this->errorResponse('combination', translate('messages.this item combination is already used in another running offer'));
        }

        try {
            DB::transaction(function () use ($offer, $store, $existing, $buyItems, $getItems, $signature) {
                // A rework replaces the frozen selection, so every cart already holding this bundle
                // is now priced against items the offer no longer contains. The other three paths
                // that end an arrangement -- the store's denial, its leave, and both admin removals
                // -- all clear carts; this one did not, which made resubmit the single way to leave
                // a customer holding a bundle that can no longer be honoured.
                $existing?->strandCarts();

                $enrollment = $existing ?: new BogoOfferStore([
                    'bogo_offer_id' => $offer->id,
                    'store_id' => $store->id,
                ]);

                // Always 'store': this selection is the store's own, so it waits on the admin. An
                // approved row drops back to pending here and stops applying until re-approved.
                $enrollment->requested_by = 'store';
                $enrollment->status = BogoOfferStore::STATUS_PENDING;
                $enrollment->rejection_reason = null;
                $enrollment->rejected_by = null;
                $enrollment->joined_at = $enrollment->joined_at ?: now();
                $enrollment->combination_signature = $signature;
                $enrollment->checked = 0;
                $enrollment->save();

                $this->syncEnrollmentItems($enrollment, $buyItems, $getItems);
            });
        } catch (UniqueConstraintViolationException) {
            // The exists() check above and this insert are not atomic, so two concurrent joins for
            // the same offer/store can both pass the check and race for the (bogo_offer_id,
            // store_id) unique index -- the constraint this table's migration names as the actual
            // guard against a double-click. The loser lands here instead of surfacing as a 500.
            return $this->errorResponse('store', translate('You have already joined this offer'));
        }

        app(PromotionNotifier::class)->storeRequestedJoin(PromotionNotifier::BOGO, $offer, $store);

        return response()->json([], 200);
    }

    /**
     * The offer, scoped to the store's own module.
     *
     * A store belongs to one module and so do its items, so an offer outside it could never be
     * assembled from this menu -- and an id typed into the address bar must not reach one.
     */
    private function findInStoreModule(string $id, Store $store): BogoOffer
    {
        return BogoOffer::where('module_id', $store->module_id)->findOrFail($id);
    }

    private function store(): Store
    {
        return Helpers::get_store_data() ?? abort(404);
    }

    /** @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection} */
    private function addOnLabels(?BogoOfferStore $enrollment): array
    {
        $ids = $enrollment
            ? $enrollment->items->flatMap(fn ($line) => $line->add_on_ids ?? [])->unique()->all()
            : [];

        if (! $ids) {
            return [collect(), collect()];
        }

        $addOns = AddOn::with('translations')->whereIn('id', $ids)->get(['id', 'name', 'price']);

        return [$addOns->pluck('name', 'id'), $addOns->pluck('price', 'id')];
    }

    /**
     * A padded search splits into empty pieces, each of which becomes LIKE '%%' and matches every
     * row, so "  term  " would answer with the whole list.
     *
     * @return string[]
     */
    private function searchWords(?string $search): array
    {
        return array_values(array_filter(explode(' ', trim((string) $search))));
    }

    private function errorResponse(string $code, string $message): JsonResponse
    {
        return response()->json(['errors' => [['code' => $code, 'message' => $message]]], 403);
    }
}
