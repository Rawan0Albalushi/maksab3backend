<?php

namespace App\Http\Controllers\Api\V1\Vendor\Promotion;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Vendor\Promotion\BogoEnrollmentRequest;
use App\Http\Requests\Vendor\Promotion\BogoOfferItemListRequest;
use App\Http\Requests\Vendor\Promotion\BogoOfferListRequest;
use App\Http\Requests\Vendor\Promotion\PromotionRespondRequest;
use App\Http\Resources\Vendor\Promotion\BogoOfferResource;
use App\Support\Promotion\AddOnLabels;
use App\Services\Promotion\BogoOfferVendorService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BOGO for the store app -- the same surface the vendor panel has.
 *
 * A store never creates or edits an offer. It joins one the admin published with its own buy/get
 * selection, answers one the admin assigned it, reworks its selection, cancels its request, or
 * leaves.
 *
 * The store comes from the token on every call, never from the request, so a vendor cannot act on
 * another store's enrolment.
 */
class BogoOfferController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(private readonly BogoOfferVendorService $bogoOfferService)
    {
    }

    /**
     * The offer list, with the tab counts.
     *
     * Counts are taken before the tab filter, so they keep their totals while one tab is open.
     */
    public function index(BogoOfferListRequest $request): JsonResponse
    {
        $store = $this->vendorStore($request);

        if (! $store) {
            return $this->storeMissing();
        }

        // Records that the store has seen these rows. It does not silence the dashboard prompt:
        // an invitation is a decision the admin is waiting on.
        $this->bogoOfferService->markInvitationsSeen($store->id);

        $filters = $request->filters() + ['store_id' => $store->id, 'module_id' => $store->module_id];

        $offers = $this->bogoOfferService->getList(
            filters: $filters,
            paginate: ['per_page' => $request->perPage(), 'page' => $request->page()],
        );

        $this->bogoOfferService->primeOfferRows($offers->getCollection(), $store);

        return $this->responseFormatter(config('response.default_200'), [
            'data' => BogoOfferResource::collection($offers),
            'counts' => $this->bogoOfferService->stateCounts($filters),
            'pagination' => $this->paginateFormatter($offers),
        ]);
    }

    /** One offer with this store's frozen buy/get snapshot, so the app can show what it submitted. */
    public function show(Request $request, string $id): JsonResponse
    {
        $store = $this->vendorStore($request);

        if (! $store) {
            return $this->storeMissing();
        }

        $offer = $this->bogoOfferService->find($id, $store);

        if (! $offer) {
            return $this->notFound();
        }

        $this->bogoOfferService->primeOfferRows([$offer], $store);

        return $this->responseFormatter(
            config('response.default_200'),
            (new BogoOfferResource(
                $offer,
                withItems: true,
                addOnLines: AddOnLabels::forLines($offer->own_enrollment?->items ?? []),
            ))->render()
        );
    }

    /** Own menu for the combination builder, with variations and add-ons resolved. */
    public function items(BogoOfferItemListRequest $request): JsonResponse
    {
        $store = $this->vendorStore($request);

        if (! $store) {
            return $this->storeMissing();
        }

        return $this->responseFormatter(config('response.default_200'), [
            'items' => $this->bogoOfferService->getStoreItemList($store, $request->input('search')),
        ]);
    }

    /** Store-initiated join. Lands pending for the admin to approve. */
    public function join(BogoEnrollmentRequest $request, string $id): JsonResponse
    {
        $store = $this->vendorStore($request);

        if (! $store) {
            return $this->storeMissing();
        }

        $offer = $this->bogoOfferService->find($id, $store);

        if (! $offer) {
            return $this->notFound();
        }

        if ($blocked = $this->bogoOfferService->joinBlockedReason($offer, $store)) {
            return $this->errorResponse(config('response.forbidden_403'), $blocked['message'], $blocked['code']);
        }

        if ($this->bogoOfferService->findEnrollment($offer->id, $store->id)) {
            return $this->errorResponse(
                config('response.forbidden_403'),
                translate('You have already joined this offer'),
                'store'
            );
        }

        return $this->enrollmentResponse(
            $this->bogoOfferService->writeEnrollment($request->payload(), $offer, $store, null),
            translate('messages.your request has been sent for approval')
        );
    }

    /**
     * A reworked selection -- after a denial, or to change what is already running.
     *
     * Goes back to pending as a store request, so the admin stays the gate on whatever finally
     * goes live; an approved offer therefore stops applying until it is approved again.
     */
    public function resubmit(BogoEnrollmentRequest $request, string $id): JsonResponse
    {
        $store = $this->vendorStore($request);

        if (! $store) {
            return $this->storeMissing();
        }

        $offer = $this->bogoOfferService->find($id, $store);
        $enrollment = $offer ? $this->bogoOfferService->findEnrollment($offer->id, $store->id) : null;

        if (! $offer || ! $enrollment) {
            return $this->notFound();
        }

        if ($blocked = $this->bogoOfferService->resubmitBlockedReason($enrollment)) {
            return $this->errorResponse(config('response.forbidden_403'), $blocked, 'status');
        }

        if ($blocked = $this->bogoOfferService->joinBlockedReason($offer, $store)) {
            return $this->errorResponse(config('response.forbidden_403'), $blocked['message'], $blocked['code']);
        }

        return $this->enrollmentResponse(
            $this->bogoOfferService->writeEnrollment($request->payload(), $offer, $store, $enrollment),
            translate('messages.your request has been resubmitted')
        );
    }

    /** The store's answer to an offer the admin assigned it. */
    public function respond(PromotionRespondRequest $request, string $id): JsonResponse
    {
        $store = $this->vendorStore($request);

        if (! $store) {
            return $this->storeMissing();
        }

        $offer = $this->bogoOfferService->find($id, $store);
        $enrollment = $offer ? $this->bogoOfferService->findEnrollment($offer->id, $store->id) : null;

        if (! $enrollment) {
            return $this->notFound();
        }

        $result = $this->bogoOfferService->respondToEnrollment(
            $enrollment,
            $offer,
            $request->decision(),
            $request->rejectionReason()
        );

        if (($result['status_code'] ?? 200) >= 400) {
            return $this->errorResponse(config('response.forbidden_403'), $result['message'], $result['code']);
        }

        return $this->responseFormatter(
            ['message' => $result['message']] + config('response.default_200'),
            ['warnings' => $result['warnings'] ?? []]
        );
    }

    /**
     * Backs both cancel and leave: withdrawing a request that never went live and walking away
     * from a running one are the same delete, differing only in what the store is told.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $store = $this->vendorStore($request);

        if (! $store) {
            return $this->storeMissing();
        }

        $offer = $this->bogoOfferService->find($id, $store);
        $enrollment = $offer ? $this->bogoOfferService->findEnrollment($offer->id, $store->id) : null;

        if (! $enrollment) {
            return $this->notFound();
        }

        $result = $this->bogoOfferService->deleteEnrollment($enrollment, $offer);

        if (($result['status_code'] ?? 200) >= 400) {
            return $this->errorResponse(config('response.forbidden_403'), $result['message'], $result['code']);
        }

        return $this->responseFormatter(['message' => $result['message']] + config('response.default_delete_200'));
    }

    /**
     * Turn a write result into the envelope.
     *
     * validateEnrollmentItems() answers with a LIST of problems -- a bundle can be wrong in
     * several ways at once, and reporting only the first would have the store fix them one
     * round-trip at a time -- so that case carries the whole list rather than a single error.
     */
    private function enrollmentResponse(array $result, string $successMessage): JsonResponse
    {
        if (! empty($result['errors'])) {
            return $this->responseFormatter(config('response.forbidden_403'), errors: $result['errors']);
        }

        if (($result['status_code'] ?? 200) >= 400) {
            return $this->errorResponse(config('response.forbidden_403'), $result['message'], $result['code']);
        }

        return $this->responseFormatter(['message' => $successMessage] + config('response.default_200'), [
            'bundle_price' => $result['bundle_price'],
            'enrollment_state' => $result['enrollment_state'],
        ]);
    }

    private function notFound(): JsonResponse
    {
        return $this->errorResponse(config('response.default_404'), translate('No data found'), 'bogo_offer');
    }

    private function storeMissing(): JsonResponse
    {
        return $this->errorResponse(config('response.default_404'), translate('No data found'), 'store');
    }
}
