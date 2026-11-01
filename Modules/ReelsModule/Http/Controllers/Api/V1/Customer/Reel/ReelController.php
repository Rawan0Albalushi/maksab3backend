<?php

namespace Modules\ReelsModule\Http\Controllers\Api\V1\Customer\Reel;

use App\Traits\Api\CachesApiPayloadTrait;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Modules\ReelsModule\Http\Requests\Customer\Reel\ReelLikeRequest;
use Modules\ReelsModule\Http\Requests\Customer\Reel\ReelListRequest;
use Modules\ReelsModule\Http\Requests\Customer\Reel\ReelShowRequest;
use Modules\ReelsModule\Http\Requests\Customer\Reel\ReelStatsRequest;
use Modules\ReelsModule\Http\Requests\Customer\Reel\ReelVisitRequest;
use Modules\ReelsModule\Http\Resources\Common\Reel\ReelResource;
use Modules\ReelsModule\Http\Resources\Customer\Reel\ReelListResource;
use Modules\ReelsModule\Http\Resources\Customer\Reel\ReelStatsResource;
use Modules\ReelsModule\Services\Reel\ReelEngagementService;
use Modules\ReelsModule\Services\Reel\ReelService;
use Modules\ReelsModule\Traits\ReelVideoTrait;

class ReelController extends BaseApiController
{
    use CachesApiPayloadTrait;

    use ReelVideoTrait;

    public function __construct(
        private readonly ReelService $reelService,
        private readonly ReelEngagementService $reelEngagementService
    ) {
    }

    public function index(ReelListRequest $request): JsonResponse
    {
        return $this->cachedJson('api.reels', $request, $request->filters(), fn () => $this->pagedResponse(
            $this->reelService->getActiveList($request->filters(), ['per_page' => $request->perPage(), 'page' => $request->page()]),
            ReelListResource::class
        ));
    }

    public function show(ReelShowRequest $request): mixed
    {
        $reel = $this->reelService->findActive($request->input('reel_id'), $request->filters());

        if (! $reel) {
            return $this->reelNotFound();
        }

        $this->reelEngagementService->recordView($reel, ...$request->identity());

        return $request->wantsStream()
            ? $this->streamReelVideo($reel, $request)
            : $this->responseFormatter(config('response.default_200'), new ReelResource($reel));
    }

    public function stats(ReelStatsRequest $request): JsonResponse
    {
        $reel = $this->reelService->findActive($request->input('reel_id'), $request->filters());

        return $reel
            ? $this->responseFormatter(config('response.default_200'), new ReelStatsResource($reel))
            : $this->reelNotFound();
    }

    public function like(ReelLikeRequest $request): JsonResponse
    {
        $reel = $this->reelService->findActive($request->input('reel_id'), $request->filters());

        if (! $reel) {
            return $this->reelNotFound();
        }

        $result = $this->reelEngagementService->toggleLike($reel, (int) $request->user('api')->id);

        return $this->responseFormatter(['message' => $result['message']] + config('response.default_200'), new ReelStatsResource($reel));
    }

    public function visit(ReelVisitRequest $request): JsonResponse
    {
        $reel = $this->reelService->findActive($request->input('reel_id'), $request->filters());

        if (! $reel) {
            return $this->reelNotFound();
        }

        $this->reelEngagementService->recordVisit($reel, ...$request->identity());

        return $this->responseFormatter(
            ['message' => translate('Updated successfully')] + config('response.default_200'),
            new ReelStatsResource($reel)
        );
    }

    private function reelNotFound(): JsonResponse
    {
        return $this->responseFormatter(
            ['message' => translate('No data found')] + config('response.default_404'),
            errors: [['code' => 'not_found', 'message' => translate('No data found')]]
        );
    }
}
