<?php

namespace Modules\ReelsModule\Http\Controllers\Api\V1\Vendor\Reel;

use App\CentralLogics\Helpers;
use App\Exceptions\InvalidUploadException;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\ReelsModule\Http\Requests\Vendor\Reel\ReelIdRequest;
use Modules\ReelsModule\Http\Requests\Vendor\Reel\ReelListRequest;
use Modules\ReelsModule\Http\Requests\Vendor\Reel\ReelStatusRequest;
use Modules\ReelsModule\Http\Requests\Vendor\Reel\ReelStoreRequest;
use Modules\ReelsModule\Http\Requests\Vendor\Reel\ReelUpdateRequest;
use Modules\ReelsModule\Http\Resources\Common\Reel\ReelResource;
use Modules\ReelsModule\Http\Resources\Vendor\Reel\ReelListResource;
use Modules\ReelsModule\Services\Reel\ReelService;
use Modules\ReelsModule\Support\ReelModuleConfig;

class ReelController extends BaseApiController
{
    private const MODULE_UNAVAILABLE = 'This feature is not available for the selected module';

    public function __construct(private readonly ReelService $reelService)
    {
    }

    public function index(ReelListRequest $request): JsonResponse
    {
        return $this->pagedResponse(
            $this->reelService->getList($request->filters(), ['per_page' => $request->perPage(), 'page' => $request->page()]),
            ReelListResource::class
        );
    }

    public function show(ReelIdRequest $request): JsonResponse
    {
        $reel = $this->reelService->findWithTranslations($request->input('reel_id'), $request->filters());

        return $reel
            ? $this->responseFormatter(config('response.default_200'), new ReelResource($reel))
            : $this->reelNotFound();
    }

    public function store(ReelStoreRequest $request): JsonResponse
    {
        if ($rejection = $this->uploadRejection($request)) {
            return $rejection;
        }

        try {
            $this->reelService->create($request->payload());
        } catch (InvalidUploadException $exception) {
            return $this->uploadFailed($exception);
        }

        return $this->responseFormatter(
            ['message' => translate('Added successfully')] + config('response.default_store_201')
        );
    }

    public function update(ReelUpdateRequest $request): JsonResponse
    {
        if ($rejection = $this->demoRejection()) {
            return $rejection;
        }

        $reel = $this->reelService->find($request->input('reel_id'), $request->filters());

        if (! $reel) {
            return $this->reelNotFound();
        }

        try {
            $this->reelService->update($reel, $request->payload());
        } catch (InvalidUploadException $exception) {
            return $this->uploadFailed($exception);
        }

        return $this->responseFormatter(
            ['message' => translate('Updated successfully')] + config('response.default_update_200')
        );
    }

    public function destroy(ReelIdRequest $request): JsonResponse
    {
        $reel = $this->reelService->find($request->input('reel_id'), $request->filters());

        if (! $reel) {
            return $this->reelNotFound();
        }

        $this->reelService->delete($reel);

        return $this->responseFormatter(
            ['message' => translate('Deleted successfully')] + config('response.default_delete_200')
        );
    }

    public function updateStatus(ReelStatusRequest $request): JsonResponse
    {
        $reel = $this->reelService->find($request->input('reel_id'), $request->filters());

        if (! $reel) {
            return $this->reelNotFound();
        }

        $this->reelService->updateStatus($reel, $request->boolean('status'));

        return $this->responseFormatter(
            ['message' => translate('Updated successfully')] + config('response.default_update_200')
        );
    }

    private function uploadRejection(Request $request): ?JsonResponse
    {
        if (! addon_published_status('ReelsModule')) {
            return $this->rejection('addon_not_published', translate(self::MODULE_UNAVAILABLE));
        }

        if ($rejection = $this->demoRejection()) {
            return $rejection;
        }

        if (! Helpers::get_business_settings('vendor_can_upload_reels')) {
            return $this->rejection('feature_disabled', translate(self::MODULE_UNAVAILABLE));
        }

        return ReelModuleConfig::isMultiModule()
            && ! ReelModuleConfig::isAllowedType($this->vendorModuleType($request))
            ? $this->rejection('module_not_allowed', translate(self::MODULE_UNAVAILABLE))
            : null;
    }

    private function vendorModuleType(Request $request): ?string
    {
        return $request->input('vendor')?->stores[0]?->module?->module_type;
    }

    private function demoRejection(): ?JsonResponse
    {
        return getEnvMode() === 'demo'
            ? $this->rejection('demo_mode', translate('Uploads are disabled in demo mode'))
            : null;
    }

    private function uploadFailed(InvalidUploadException $exception): JsonResponse
    {
        return $this->responseFormatter(
            ['message' => $exception->getMessage()] + config('response.unprocessable_entity_422'),
            errors: [['code' => 'invalid_upload', 'message' => $exception->getMessage()]]
        );
    }

    private function rejection(string $code, string $message): JsonResponse
    {
        return $this->responseFormatter(
            ['message' => $message] + config('response.forbidden_403'),
            errors: [['code' => $code, 'message' => $message]]
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
