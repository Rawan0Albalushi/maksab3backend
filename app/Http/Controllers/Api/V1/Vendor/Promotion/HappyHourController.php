<?php

namespace App\Http\Controllers\Api\V1\Vendor\Promotion;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Vendor\Promotion\HappyHourListRequest;
use App\Http\Requests\Vendor\Promotion\PromotionRespondRequest;
use App\Http\Resources\Vendor\Promotion\HappyHourResource;
use App\Services\Promotion\HappyHourVendorService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Happy Hour for the store app -- the same surface the vendor panel has.
 *
 * There is no item selection, so joining is one call: see the happy hours for my module, join one,
 * answer one the admin assigned me, cancel my own request, or leave. There is no resubmit either,
 * so a denied store cancels and joins again.
 */
class HappyHourController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(private readonly HappyHourVendorService $happyHourService)
    {
    }

    /** The list, with the tab counts and the one setting the join confirmation depends on. */
    public function index(HappyHourListRequest $request): JsonResponse
    {
        $store = $this->vendorStore($request);

        if (! $store) {
            return $this->storeMissing();
        }

        $this->happyHourService->markInvitationsSeen($store->id);

        $filters = $request->filters() + ['store_id' => $store->id, 'module_id' => $store->module_id];

        $happyHours = $this->happyHourService->getList(
            filters: $filters,
            paginate: ['per_page' => $request->perPage(), 'page' => $request->page()],
        );

        $this->happyHourService->primeRows($happyHours->getCollection(), $store);

        return $this->responseFormatter(config('response.default_200'), [
            'data' => HappyHourResource::collection($happyHours),
            'counts' => $this->happyHourService->stateCounts($filters),
            // The promise that a Pro Member keeps their own discount is only worth making where
            // Pro Member is switched on, so the app is told rather than left to assume.
            'pro_member_enabled' => $this->happyHourService->proMemberEnabled(),
            'pagination' => $this->paginateFormatter($happyHours),
        ]);
    }

    /** One happy hour with the full schedule, so the app can show when the window actually opens. */
    public function show(Request $request, string $id): JsonResponse
    {
        $store = $this->vendorStore($request);

        if (! $store) {
            return $this->storeMissing();
        }

        $happyHour = $this->happyHourService->find($id, $store);

        if (! $happyHour) {
            return $this->notFound();
        }

        $this->happyHourService->primeRows([$happyHour], $store);

        return $this->responseFormatter(
            config('response.default_200'),
            (new HappyHourResource(
                $happyHour,
                withSchedule: true,
                dates: $this->happyHourService->upcomingDates($happyHour)
            ))->render()
        );
    }

    /** Store-initiated join. Lands pending for the admin to approve. */
    public function join(Request $request, string $id): JsonResponse
    {
        $store = $this->vendorStore($request);

        if (! $store) {
            return $this->storeMissing();
        }

        // Out of module is not found rather than refused -- the happy hour does not reach this
        // store at all.
        $happyHour = $this->happyHourService->find($id, $store);

        if (! $happyHour) {
            return $this->notFound();
        }

        $result = $this->happyHourService->joinHappyHour($happyHour, $store);

        if ($result['status_code'] >= 400) {
            return $this->errorResponse(config('response.forbidden_403'), $result['message'], $result['code']);
        }

        return $this->responseFormatter(
            ['message' => $result['message']] + config('response.default_200'),
            ['enrollment_state' => $result['enrollment_state']]
        );
    }

    /** The store's answer to a happy hour the admin assigned it. */
    public function respond(PromotionRespondRequest $request, string $id): JsonResponse
    {
        $store = $this->vendorStore($request);

        if (! $store) {
            return $this->storeMissing();
        }

        $happyHour = $this->happyHourService->find($id, $store);
        $enrollment = $happyHour ? $this->happyHourService->findEnrollment($happyHour->id, $store->id) : null;

        if (! $happyHour || ! $enrollment) {
            return $this->notFound();
        }

        $result = $this->happyHourService->respondToEnrollment(
            $enrollment,
            $happyHour,
            $store,
            $request->decision(),
            $request->rejectionReason()
        );

        if ($result['status_code'] >= 400) {
            return $this->errorResponse(config('response.forbidden_403'), $result['message'], $result['code']);
        }

        return $this->responseFormatter(['message' => $result['message']] + config('response.default_200'));
    }

    /**
     * Backs both cancel and leave. Leaving is allowed even while the window is live -- the same
     * rule the admin side follows for removing a store.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $store = $this->vendorStore($request);

        if (! $store) {
            return $this->storeMissing();
        }

        $happyHour = $this->happyHourService->find($id, $store);
        $enrollment = $happyHour ? $this->happyHourService->findEnrollment($happyHour->id, $store->id) : null;

        if (! $enrollment) {
            return $this->notFound();
        }

        $result = $this->happyHourService->deleteEnrollment($enrollment);

        if ($result['status_code'] >= 400) {
            return $this->errorResponse(config('response.forbidden_403'), $result['message'], $result['code']);
        }

        return $this->responseFormatter(['message' => $result['message']] + config('response.default_delete_200'));
    }

    private function notFound(): JsonResponse
    {
        return $this->errorResponse(config('response.default_404'), translate('No data found'), 'happy_hour');
    }

    private function storeMissing(): JsonResponse
    {
        return $this->errorResponse(config('response.default_404'), translate('No data found'), 'store');
    }
}
