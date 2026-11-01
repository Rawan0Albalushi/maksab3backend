<?php

namespace App\Http\Controllers\Api\V1\Customer\Promotion;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Customer\Promotion\BogoOfferListRequest;
use App\Http\Requests\Customer\Promotion\BogoStoreOfferListRequest;
use App\Http\Resources\Customer\Promotion\BogoBundleResource;
use App\Support\Promotion\AddOnLabels;
use App\Support\Promotion\ItemRatings;
use App\Http\Resources\Customer\Promotion\BogoOfferResource;
use App\Services\Promotion\BogoOfferCustomerService;
use App\Services\Store\StoreService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;

/**
 * Customer-facing BOGO: the home card, the offer list, an offer's participating stores, and the
 * offers one store runs.
 *
 * Read-only. Adding a bundle to a cart is the cart's own endpoint, addressed by `bundle_id` --
 * the enrolment id these responses carry.
 *
 * Offer identifiers accept an id or a slug throughout.
 */
class BogoOfferController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(private readonly BogoOfferCustomerService $bogoOfferService)
    {
    }

    /** The home "BOGO Offer Is Live" card, plus a short preview of the offers behind it. */
    public function home(BogoOfferListRequest $request): JsonResponse
    {
        $this->applyZoneIds($request);

        $summary = $this->bogoOfferService->homeSummary(
            $request->filters() + $this->offerContext($request),
            $request->perPage()
        );

        $this->bogoOfferService->primeRemainingUses($summary['offers'], $this->offerOwner($request));

        return $this->responseFormatter(config('response.default_200'), [
            'is_live' => $summary['is_live'],
            'title' => translate('Hurry up! BOGO offer is live.'),
            'description' => translate('messages.Get the best value from your order with our exclusive BOGO deals'),
            'total_offers' => $summary['total_offers'],
            'offers' => BogoOfferResource::collection($summary['offers']),
        ]);
    }

    /** The BOGO offer list screen. */
    public function index(BogoOfferListRequest $request): JsonResponse
    {
        $this->applyZoneIds($request);

        $offers = $this->bogoOfferService->getList(
            filters: $request->filters() + $this->offerContext($request),
            paginate: ['per_page' => $request->perPage(), 'page' => $request->page()],
        );

        $this->bogoOfferService->primeRemainingUses($offers->getCollection(), $this->offerOwner($request));

        return $this->responseFormatter(config('response.default_200'), [
            'data' => BogoOfferResource::collection($offers),
            'pagination' => $this->paginateFormatter($offers),
        ]);
    }

    /**
     * Offer details: the offer, plus every participating store's bundle.
     *
     * One store contributes exactly one bundle per offer, so the bundle list pages over stores.
     */
    public function show(BogoOfferListRequest $request, string $id): JsonResponse
    {
        $this->applyZoneIds($request);

        $context = $request->filters() + $this->offerContext($request);
        $offer = $this->bogoOfferService->findAvailable($context, $id);

        if (! $offer) {
            return $this->errorResponse(
                config('response.default_404'),
                translate('messages.Offer not found'),
                'bogo_offer'
            );
        }

        $this->bogoOfferService->primeRemainingUses([$offer], $this->offerOwner($request));

        $bundles = $this->bogoOfferService->getBundleList(
            offer: $offer,
            filters: $context,
            paginate: ['per_page' => $request->perPage(), 'page' => $request->page()],
        );

        // Once for the page — every enrolment renders its lines (rule 11).
        $addOnLines = AddOnLabels::forParents($bundles->getCollection());
        $ratings = ItemRatings::forParents($bundles->getCollection());

        return $this->responseFormatter(config('response.default_200'), [
            'offer' => (new BogoOfferResource($offer))->render(),
            'data' => array_map(
                fn ($enrollment) => (new BogoBundleResource(
                    $enrollment, addOnLines: $addOnLines, ratings: $ratings
                ))->render(),
                $bundles->getCollection()->all()
            ),
            'pagination' => $this->paginateFormatter($bundles),
        ]);
    }

    /**
     * The BOGO offers one store runs, for the store detail flow.
     *
     * Each row carries the offer AND its bundle, so opening a store needs no second call. The
     * store object is left off the bundle -- this screen already has it.
     */
    public function storeOffers(BogoStoreOfferListRequest $request): JsonResponse
    {
        $this->applyZoneIds($request);

        $filters = $request->filters() + $this->offerContext($request);
        $store = app(StoreService::class)->findActiveInZones(
            $filters['store_id'],
            $filters['zone_ids'],
            $filters['module_id']
        );

        if (! $store) {
            return $this->errorResponse(
                config('response.default_404'),
                translate('No data found'),
                'store'
            );
        }

        $offers = $this->bogoOfferService->getStoreOfferList(
            store: $store,
            filters: $filters,
            paginate: ['per_page' => $request->perPage(), 'page' => $request->page()],
        );

        $this->bogoOfferService->primeRemainingUses($offers->getCollection(), $this->offerOwner($request));

        // One enrolment per offer here, but still a page of them — batched for the same reason.
        $enrollments = $offers->getCollection()->map(fn ($offer) => $offer->enrollments->first())->filter();
        $addOnLines = AddOnLabels::forParents($enrollments);
        $ratings = ItemRatings::forParents($enrollments);

        return $this->responseFormatter(config('response.default_200'), [
            'data' => $offers->getCollection()->map(function ($offer) use ($addOnLines, $ratings) {
                $enrollment = $offer->enrollments->first();

                return (new BogoOfferResource($offer))->render() + ($enrollment
                    ? (new BogoBundleResource(
                        $enrollment, withStore: false, addOnLines: $addOnLines, ratings: $ratings
                    ))->render()
                    : ['bundle_id' => null, 'buy_count' => 0, 'get_count' => 0]);
            })->values()->all(),
            'pagination' => $this->paginateFormatter($offers),
        ]);
    }

    /** Zone, module and the coordinates the store distance is measured from. */
    private function offerContext(BogoOfferListRequest|BogoStoreOfferListRequest $request): array
    {
        return [
            'zone_ids' => $this->zoneIds($request),
            'module_id' => $this->currentModuleId(),
            'longitude' => $request->header('longitude'),
            'latitude' => $request->header('latitude'),
        ];
    }

    /**
     * Who is asking, for the per-customer redemption count.
     *
     * Resolved from the TOKEN rather than from `$request->user`. These endpoints are public --
     * they sit behind `module-check` alone so a browsing visitor still sees the offers -- and
     * `$request->user` is only ever populated by `apiGuestCheck`, which does not run here. Reading
     * it meant a logged-in customer was counted as a guest, and `remaining_uses` reported the full
     * allowance however many times they had already redeemed the offer.
     *
     * `auth('api')` resolves the bearer token whether or not any middleware asked it to, which is
     * the same way every other public-but-personalised endpoint in this folder does it.
     */
    private function offerOwner(BogoOfferListRequest|BogoStoreOfferListRequest $request): array
    {
        if (auth('api')->check()) {
            return ['user_id' => auth('api')->id(), 'is_guest' => 0, 'phone' => null];
        }

        return [
            'user_id' => $request->input('guest_id'),
            'is_guest' => 1,
            // A guest's history is keyed by phone, since there is no account to hang it on.
            'phone' => $request->input('contact_person_number'),
        ];
    }
}
